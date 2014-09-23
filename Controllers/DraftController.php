<?php
/**
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 */
namespace Gustavus\Concert\Controllers;

use Gustavus\Concert\Config,
  Gustavus\Concert\Utility,
  Gustavus\Concert\FileManager,
  Gustavus\Utility\File,
  Gustavus\Utility\PageUtil,
  Gustavus\Utility\String,
  Gustavus\Concert\PermissionsManager,
  Gustavus\FormBuilderMk2\ElementRenderers\TwigElementRenderer,
  Gustavus\FormBuilderMk2\FormElement,
  Gustavus\Resources\Resource,
  Gustavus\Extensibility\Filters;

/**
 * Handles draft actions
 *
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 */
class DraftController extends SharedController
{
  /**
   * Shows a draft
   *
   * @param  array $params Array of parameters
   * @return boolean
   */
  public function showDraft($params)
  {
    $filePath = $params['filePath'];
    if (isset($params['draft'])) {
      $draft = $params['draft'];
    } else {
      $draft = null;
    }
    $showSingle = (isset($params['showSingle'])) ? $params['showSingle'] : false;

    $filePathFromDocRoot = Utility::removeDocRootFromPath($filePath);

    if (!PermissionsManager::userCanEditFile($this->getLoggedInUsername(), $filePathFromDocRoot)) {
      return $this->redirect($filePathFromDocRoot);
      exit;
    }
    $this->addMoshMenu();

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());

    $drafts = $fm->findDraftsForCurrentUser($draft);

    if (empty($drafts)) {
      return $this->renderErrorPage('Oops! It looks like there aren\'t any drafts to show');
    }

    if (!$showSingle && $draft === null && count($drafts) > 0) {
      // user has access to a few drafts. We want to ask them which draft they want to view
      return $this->renderMultipleDraftOptions($drafts);
    }

    $draft = reset($drafts);

    if (!self::isForwardedFromSiteNav()) {
      // we don't want to add any messages if working on a site nav
      $messageAdditions = '';

      if ($draft['type'] === Config::PUBLIC_DRAFT) {
        $messageAdditions .= sprintf('This draft is a shared draft. Other users can see if by going to: <a href="%1$s">%1$s</a>.', $this->buildUrl('drafts', ['draftName' => $draft['draftFilename']], '', true));
      }

      if (self::isRequestFromConcertRoot()) {
        $messageAdditions .= sprintf('<br/>This draft will live at "%s" when published.', Utility::removeDocRootFromPath($draft['destFilepath']));
      }
      $this->addConcertMessage(Config::DRAFT_NOTE . $messageAdditions, false);
    }


    $draftFilename = $fm->getDraftFileName($draft['username'], true);

    if (!$draftFilename || !file_exists($draftFilename)) {
      return $this->renderErrorPage('Oops! It appears as if the draft could not be found.');
    }

    if ($draft['type'] === Config::PENDING_PUBLISH_DRAFT && PermissionsManager::userCanPublishPendingDrafts($this->getLoggedInUsername(), $filePath)) {
      // this draft is pending publish, and the user has access to publish drafts.
      return $this->handlePendingDraft($draft, $fm);
    }

    // add a message saying that the draft is older than the published date of the page and it might be out of sync.
    $this->addOutdatedDraftMessageIfNeeded($draft);

    return $this->displayPage($draftFilename, true);
  }

  /**
   * Renders draft options for people who have multiple drafts to choose from
   *
   * @param  array $drafts
   * @return string
   */
  private function renderMultipleDraftOptions($drafts)
  {
    return $this->renderTemplate('draftOptions.html.twig', ['drafts' => $drafts, 'siteNav' => self::isForwardedFromSiteNav()]);
  }

  /**
   * Handles pending draft actions
   *
   * @param  array $draft Array of the current draft
   * @param  FileManager $fm FileManager representing the current file
   * @return string
   */
  private function handlePendingDraft($draft, $fm)
  {
    $draftFilename = $fm->getDraftFileName($draft['username'], true);
    if (isset($_POST['action']) && in_array($_POST['action'], ['publish', 'reject'])) {

      $message = (!empty($_POST['message'])) ? $_POST['message']: null;

      if ($_POST['action'] === 'publish') {
        // we want to publish the file.
        if (!$fm->acquireLock()) {
          $this->addConcertMessage($this->renderLockNotAcquiredMessage($fm), false);
          return $this->displayPage($draftFilename, true);
        }

        if ($fm->stagePublishPendingDraft($draft['username'])) {
          if (!$this->forward('emailPendingDraftPublished', ['draft' => $draft, 'message' => $message])) {
            return $this->redirectWithMessage(Utility::removeDocRootFromPath($draft['destFilepath']), Config::DRAFT_PUBLISHED_NOT_SENT_MESSAGE);
          }
          return $this->redirect(Utility::removeDocRootFromPath($draft['destFilepath']));
        }
      } else if ($_POST['action'] === 'reject') {
        if (!$this->forward('emailPendingDraftRejected', ['draft' => $draft, 'message' => $message])) {
          return $this->redirectWithMessage(Utility::removeDocRootFromPath($draft['destFilepath']), Config::DRAFT_REJECTION_NOT_SENT_MESSAGE);
        }
        return $this->redirect(Utility::removeDocRootFromPath($draft['destFilepath']));
      }
    } else {
      $filePathFromDocRoot = Utility::removeDocRootFromPath($draft['destFilepath']);
      // Let's give them the option to publish the draft.
      $url = (new String($filePathFromDocRoot))->addQueryString(['concert' => 'viewDraft', 'concertDraft' => $draft['draftFilename']]);

      if (isset($_GET['confirmReject']) && $_GET['confirmReject'] === 'true') {
        return $this->renderView('confirmPendingDraftAction.html.twig', ['url' => $url, 'forPublish' => false]);
      } else if (isset($_GET['confirmPublish']) && $_GET['confirmPublish'] === 'true') {
        return $this->renderView('confirmPendingDraftAction.html.twig', ['url' => $url, 'forPublish' => true]);
      }

      // add a message saying that the draft is older than the published date of the page and it might be out of sync.
      $this->addOutdatedDraftMessageIfNeeded($draft);

      $this->addConcertMessage($this->renderView('publishPendingDraftActions.html.twig',
          [
            'url'     => $url,
            'draftOwner' => $draft['username'],
          ]
      ), false, true);
    }
    return $this->displayPage($draftFilename, true);
  }

  /**
   * Renders a publicly accessible draft
   *
   * @param  array $params Params from Router
   * @return string
   */
  public function renderPublicDraft(array $params)
  {
    assert('isset($params["draftName"])');
    $draftName = $params['draftName'];
    $this->addMoshMenu();

    $fm = new FileManager($this->getLoggedInUsername(), $this->buildUrl('drafts', ['draftName' => '']), null, $this->getDB());

    $draft = $fm->getDraft($draftName);

    $filePathFromDocRoot = Utility::removeDocRootFromPath($draft['destFilepath']);

    if ($draft['type'] !== Config::PUBLIC_DRAFT) {
      return PageUtil::renderPageNotFound(true);
    }

    $shouldRedirectToFullPath = true;
    if (self::isRequestFromConcertRoot() && $shouldRedirectToFullPath) {
      // we are using this location as a url shortener
      return $this->redirect((new String($filePathFromDocRoot))->addQueryString(['concert' => 'viewDraft', 'concertDraft' => $draft['draftFilename']])->buildUrl()->getValue());
    }

    $messageAdditions = '';
    if (PermissionsManager::userCanEditDraft($this->getLoggedInUsername(), $draft)) {
      if (self::isRequestFromConcertRoot()) {
        $url = $this->buildUrl('editDraft', ['draftName' => $draft['draftFilename']]);
      } else {
        $url = (new String($_SERVER['REQUEST_URI']))->addQueryString(['concert' => 'editDraft', 'concertDraft' => $draft['draftFilename']])->buildUrl()->getValue();
      }
      $messageAdditions = sprintf('<br/><a href="%s" class="button">Edit Draft</a>', $url);
    }


    if (self::isRequestFromConcertRoot()) {
      $messageAdditions = sprintf('<br/>This draft will live at "%s" when published.%s', Utility::removeDocRootFromPath($draft['destFilepath']), $messageAdditions);
    }
    $this->addConcertMessage(Config::DRAFT_NOTE . $messageAdditions, false);

    // add a message saying that the draft is older than the published date of the page and it might be out of sync.
    $this->addOutdatedDraftMessageIfNeeded($draft);

    return $this->displayPage(Config::$draftDir . $draftName, true);
  }

  /**
   * Handles editing public drafts
   *
   * @param  array  $params Params from Router
   * @return string
   */
  public function editPublicDraft(array $params)
  {
    $this->addMoshMenu();
    $draftName = $params['draftName'];

    $fm = new FileManager($this->getLoggedInUsername(), $this->buildUrl('editDraft', ['draftName' => '']), null, $this->getDB());

    // we've got our draft.
    $draft = $fm->getDraft($draftName);

    if (empty($draft)) {
      return $this->renderErrorPage('Oops! It looks like the specified draft doesn\'t exist.');
    }

    $draftFilePath = Config::$draftDir . $draftName;

    // now we need to make a fileManager to edit the current draft
    $draftFM = new FileManager($this->getLoggedInUsername(), $draftFilePath, null, $this->getDB());
    $draftFM->setUserIsEditingDraft();

    if (!PermissionsManager::userCanEditDraft($this->getLoggedInUsername(), $draft)) {
      return $this->renderErrorPage('Oops! It looks like you don\'t have access to edit this draft.');
    }

    // we need to create a lock on the draft file as well as the file the draft represents
    if (!$draftFM->acquireLock()) {
      return $this->renderErrorPage($this->renderLockNotAcquiredMessage($draftFM));
    }

    if (self::isRequestFromConcertRoot()) {
      $buttonUrl = $this->buildUrl('drafts', ['draftName' => $draftName]);
    } else {
      $buttonUrl = (new String($_SERVER['REQUEST_URI']))->addQueryString(['concert' => 'viewDraft', 'concertDraft' => $draft['draftFilename']])->buildUrl()->getValue();
    }

    if ($this->getMethod() === 'POST' && $draftFM->editFile($_POST) && $draftFM->saveDraft($draft['type'])) {
      $draftFM->stopEditing();
      return [
        'action' => 'return',
        'value'  => true,
        'redirectUrl' => $buttonUrl,
      ];
    }

    $additionalButtons = [
      [
        'url'  => $buttonUrl,
        'id'   => 'concertStopEditing',
        'text' => 'Stop editing draft',
      ]
    ];

    $this->insertEditingResources($this->buildUrl('editDraft', ['draftName' => $draftName]), null, ['saveDraft'], $additionalButtons);

    $draftFilename = $draftFM->makeEditableDraft();

    if ($draftFilename === false) {
      return $this->renderErrorPage(Config::GENERIC_ERROR_MESSAGE);
    }

    // add a message saying that the draft is older than the published date of the page and it might be out of sync.
    $this->addOutdatedDraftMessageIfNeeded($draft);

    return $this->displayPage($draftFilename, true);
  }

  /**
   * Saves a draft for the specified file
   *
   * @param  string $filePath Path of the file the draft is being made for
   * @return string|boolean   JSON on failure, boolean on success
   */
  private function saveDraft($filePath)
  {
    if (!file_exists($filePath)) {
      // user is trying to create a new page
      $fromFilePath = $this->getFilePathToCopy();
      return $this->saveDraftForNewFile($filePath, $fromFilePath);
    }

    if (!PermissionsManager::userCanEditFile($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath))) {
      return json_encode(['error' => true, 'reason' => Config::NOT_ALLOWED_TO_EDIT_MESSAGE]);
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());

    if (!$fm->acquireLock()) {
      return $this->renderErrorPage($this->renderLockNotAcquiredMessage($fm));
    }

    if ($fm->editFile($_POST)) {
      $draftType = (self::userIsSavingPrivateDraft()) ? Config::PRIVATE_DRAFT : Config::PUBLIC_DRAFT;
      if ($fm->saveDraft($draftType)) {
        return true;
      }
    }
  }

  /**
   * Saves a draft of a new file
   *
   * @param  string $filePath     Path of the file the draft is being made for
   * @param  string $fromFilePath Path of the file the draft is being made from
   * @return string|boolean       JSON on failure, boolean on success
   */
  private function saveDraftForNewFile($filePath, $fromFilePath)
  {
    if (!PermissionsManager::userCanEditFile($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath))) {
      return json_encode(['error' => true, 'reason' => Config::NOT_ALLOWED_TO_EDIT_MESSAGE]);
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, $fromFilePath, $this->getDB());

    $draft = $fm->getDraftForUser($this->getLoggedInUsername());

    if (!empty($draft)) {
      $draftFilePath = Config::$draftDir . $draft['draftFilename'];
      // user has an open draft. We need to create a new file from the current draft
      $fm = new FileManager($this->getLoggedInUsername(), $filePath, $draftFilePath, $this->getDB());
    }

    if (!$fm->acquireLock()) {
      // lock couldn't be acquired
      return json_encode(['error' => true, 'reason' => $this->renderLockNotAcquiredMessage($fm)]);
    }

    if ($fm->editFile($_POST)) {
      $draftType = (self::userIsSavingPrivateDraft()) ? Config::PRIVATE_DRAFT : Config::PUBLIC_DRAFT;
      if ($fm->saveDraft($draftType)) {
        return true;
      }
    }
  }

  /**
   * Deletes the user's draft for the specified file path
   *
   * @param  string $filePath File path to delete draft for
   * @return string|boolean  JSON on failure, boolean on success
   */
  private function deleteDraft($filePath)
  {
    if (!PermissionsManager::userCanEditFile($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath))) {
      return json_encode(['error' => true, 'reason' => Config::NOT_ALLOWED_TO_EDIT_MESSAGE]);
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());
    if (!$fm->userHasOpenDraft()) {
      // user doesn't have a draft they can delete. Nothing needs to happen.
      return true;
    } else {
      $fm->destroyDraft();
      return true;
    }
  }

  /**
   * Adds users to a draft
   *
   * @param array $params Parameters from router
   * @return  string|array
   */
  public function addUsersToDraft($params)
  {
    $draftName = $params['draftName'];

    $fm = new FileManager($this->getLoggedInUsername(), $this->buildUrl('addUsersToDraft', ['draftName' => $draftName]), null, $this->getDB());

    $draft = $fm->getDraft($draftName);

    if (!$draft) {
      return $this->renderErrorPage(Config::DRAFT_NON_EXISTENT);
    }

    if (!PermissionsManager::userOwnsDraft($this->getLoggedInUsername(), $draft)) {
      // @todo should we have the owner of this draft be displayed?
      return $this->renderErrorPage('Oops! It looks like you don\'t own this draft. Please have the owner add users.');
    }

    // we don't want this form to be persisted
    $this->flushForm('concertAddUsersToDraft');

    $form = $this->buildForm('concertAddUsersToDraft', ['\Gustavus\Concert\Forms\ShareDraft', 'getConfig'], [$draft, $this->buildUrl('addUsersToDraft', ['draftName' => $draftName])], 1);

    if ($this->getMethod() === 'POST' && $form->validate()) {
      $additionalUsers = [];
      foreach ($form->getChildElement('adduserssection')->setIteratorSource(FormElement::ISOURCE_CHILDREN) as $child) {
        if ($child->getName() === 'person') {
          $additionalUsers[] = $child->getChildElement('username')->getValue();
        }
      }
      $additionalUsers = array_filter(array_unique($additionalUsers));
      if ($fm->addUsersToDraft($draftName, $additionalUsers)) {
        if (!empty($draft['additionalUsers']) && is_array($draft['additionalUsers'])) {
          $usersToEmail = [];

          foreach ($additionalUsers as $additionalUser) {
            if (!in_array($additionalUser, $draft['additionalUsers'])) {
              $usersToEmail[] = $additionalUser;
            }
          }
        } else {
          $usersToEmail = $additionalUsers;
        }
        if (!empty($usersToEmail)) {
          $this->forward('emailSharedDraft', ['draft' => $draft, 'usernames' => $usersToEmail]);
        }

        if (!isset($_GET['barebones'])) {
          return $this->redirect($this->buildUrl('addUsersToDraft', ['draftName' => $draftName]));
        }
        return true;
      }
    }

    $additionalScripts = [['path' => Config::WEB_DIR . 'js/autocompleteUser.js', 'version' => Config::AUTOCOMPLETE_JS_VERSION]];
    $renderer = new TwigElementRenderer();

    if (isset($_GET['barebones'])) {
      // we aren't rendering this in the template
      $resources = $renderer->getExternalResources();
      $r = '';
      if (!empty($resources['css'])) {
        $r .= sprintf('<link rel="stylesheet" type="text/css" href="%s"/>',
            Resource::renderCSS($resources['css'])
        );
      }
      if (!empty($resources['js'])) {
        $additionalScripts = array_merge($resources['js'], $additionalScripts);
      }

      // now we need to add formBuilder, autocomplete, and colorbox submission javascripts
      $r .= sprintf(
          '<script type="text/javascript">
            Modernizr.load({
              load: "%s",
              complete: function() {
                $(function() {
                  Extend.apply(\'autocompleteUser\');
                });
              }
            });
            $(\'.concertSubmitAddUsers\').click(function(e) {
              e.preventDefault();
              var form = $(this).parents(\'form\');

              var url = form.attr(\'action\');
              var data = form.serialize();
              $.post(url, data, function() {
                $(\'.concertSubmitAddUsers\').colorbox.close();
              })
            })
          </script>',
          Resource::renderResource($additionalScripts)
      );
      $r .= $renderer->render($form);
      return $r;
    } else {

      $this->addMoshMenu();

      $this->addFormResources($renderer, null, $additionalScripts);

      $this->setContent($renderer->render($form));
      return $this->displayPage();
    }
  }

  /**
   * Handles draft requests
   *
   * @param  array  $params Params to pass onto the correct handler
   * @return mixed
   */
  public function handleDraftActions(array $params)
  {
    if (self::userIsDoneEditingDraft() && isset($_GET['concertDraft'])) {
      // user is done editing this draft. We might need to show more information about the draft, so we just release the lock and keep going with the request.
      $this->stopEditingPublicDraft(['filePath' => $_GET['concertDraft']]);
    }

    switch (true) {
      case $this->userIsViewingPublicDraft($params['filePath']):
        $params['draftName'] = self::guessDraftName();
          return ['action' => 'return', 'value' => $this->renderPublicDraft($params)];

      case self::userIsViewingDraft():
        $params['draft'] = self::getDraftFromRequest();
          return ['action' => 'return', 'value' => $this->showDraft($params)];

      case $this->userIsEditingPublicDraft($_SERVER['REQUEST_URI']):
        $params['draftName'] = self::guessDraftName($params['filePath']);
        $result = $this->editPublicDraft($params);
        if (is_array($result)) {
          return $result;
        }
          return ['action' => 'return', 'value' => $result];

      case self::userIsSavingDraft():
          return ['action' => 'return', 'value' => $this->saveDraft($params['filePath'])];

      case self::userIsDeletingDraft():
          return ['action' => 'return', 'value' => $this->deleteDraft($params['filePath'])];

      case self::userIsAddingUsersToDraft():
        $params['draftName'] = self::guessDraftName($params['filePath']);
          return ['action' => 'return', 'value' => $this->addUsersToDraft($params)];
    }
  }

  /**
   * Stops editing a public draft
   *   This will release the lock on both the draft and the file the draft represents
   *
   * @param  array $params Params from router
   * @return array
   */
  public function stopEditingPublicDraft($params)
  {
    $filePath = $params['filePath'];

    $draftName = basename($filePath);
    $filePath  = Config::$draftDir . $draftName;

    $draftFM = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());

    $draftFM->setUserIsEditingDraft();
    $draftFM->stopEditing();

    return [
      'action' => 'return',
      'value'  => true,
    ];
  }

  /**
   * Gets the file path of the page we are trying to copy
   *
   * @return string
   */
  private function getFilePathToCopy()
  {
    if (isset($_GET['srcFilePath']) && self::isInternalForward()) {
      // we want a specific path
      return $_GET['srcFilePath'];
    }
    $referer = PageUtil::getReferer();

    $parts = parse_url($referer);
    if (isset($parts['query'])) {
      // we need to see if the filePath to copy is set in the referer
      $query = (new String($parts['query']))->splitQueryString()->getValue();
      if (isset($query['srcFilePath'])) {
        return Utility::addDocRootToPath(urldecode($query['srcFilePath']));
      }
    }
    return Config::DEFAULT_TEMPLATE_PAGE;
  }
}