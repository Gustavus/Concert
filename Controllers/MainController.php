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
  Gustavus\Utility\String,
  Gustavus\Utility\PageUtil,
  Gustavus\Concert\PermissionsManager,
  Campus\Utility\Autocomplete,
  Gustavus\Extensibility\Actions,
  Gustavus\Extensibility\Filters,
  Gustavus\Revisions\API as RevisionsAPI,
  Config as GACConfig,
  InvalidArgumentException;

/**
 * Handles main Concert actions
 *
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 */
class MainController extends SharedController
{
  /**
   * Handles file manager requests.
   *   All of fileManager's php requests get routed through here so we can figure out what site they are editing based off of the access key used
   *
   * @param  array $params Array of parameters containing the requested file
   * @return string
   */
  public function handleFileManagerRequest($params)
  {
    assert('isset($params[\'request\'], $_GET[\'accessKey\'])');

    // we don't want warnings to get triggered since filemanager does a lot of crazy stuff. Mostly with wanting relative paths when we are actually using absolute paths
    error_reporting(~E_WARNING);

    PageUtil::startSessionIfNeeded();
    if (isset($_SESSION['concertCMS']['siteAccessKeys'][$_GET['accessKey']])) {
      $siteBase = $_SESSION['concertCMS']['siteAccessKeys'][$_GET['accessKey']];

      $parentSiteBase = PermissionsManager::findParentSiteForFile(Utility::removeDocRootFromPath(str_replace('//', '/', $siteBase . DIRECTORY_SEPARATOR . 'index.php')));
      if (empty($parentSiteBase)) {
        return null;
      }
      // set our current parent's site base in the session
      $_SESSION['concertCMS']['currentParentSiteBase'] = Utility::addDocRootToPath($parentSiteBase);
      $_SESSION['concertCMS']['userCanUploadToCurrentSite'] = PermissionsManager::userCanUpload($this->getLoggedInUsername(), $siteBase . DIRECTORY_SEPARATOR . 'index.php');
    } else {
      // no access key. We won't know what to do.
      return null;
    }

    // now we need to fix things for filemanager
    if ($this->getMethod() === 'POST' && strpos($params['request'], 'upload.php') !== false) {
      if (isset($_POST['path'])) {
        $_POST['path'] = Utility::addDocRootToPath($_POST['path']);
      }

      if (isset($_POST['path_thumb'])) {
        $_POST['path_thumb'] = Utility::addDocRootToPath($_POST['path_thumb']);
      }
    }

    if ($this->getMethod() === 'POST' && strpos($params['request'], 'execute.php') !== false) {
      // we need to make sure the thumb path is full.
      if (isset($_POST['path_thumb'])) {
        $_POST['path_thumb'] = Utility::addDocRootToPath($_POST['path_thumb']);
      }
    }

    if (strpos($params['request'], 'ajax_calls.php') !== false && isset($_GET['sub_action'], $_GET['file']) && $_GET['sub_action'] === 'preview') {
      $_GET['file'] = Utility::addDocRootToPath($_GET['file']);
    }

    $file = (new File(Utility::addDocRootToPath($this->buildUrl('fileManager', ['request' => '']) . $params['request'])));
    if (strpos($params['request'], '.php') !== false) {
      $output = $file->loadAndEvaluate();
      return str_replace(rtrim($_SERVER['DOCUMENT_ROOT'], '/'), '', $output);
    } else {
      return $file->serve();
    }
  }

  /**
   * Handles editing a page
   *
   * @param  string $filePath Path to the file we are trying to edit
   * @return string|boolean String of the rendered edit page or boolean on save
   */
  private function edit($filePath)
  {
    $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());

    if (!$fm->userCanEditFile()) {
      $this->addConcertMessage(Config::NOT_ALLOWED_TO_EDIT_MESSAGE, 'error');
      return false;
    }

    if ($fm->isNonEditableFile()) {
      $this->addConcertMessage(Config::SPECIAL_FILE_MESSAGE, 'error');
      return false;
    }

    if (!$fm->acquireLock()) {
      $this->addConcertMessage($this->renderLockNotAcquiredMessage($fm), 'error');
      return false;
    }

    if ($fm->draftExists()) {
      $this->addConcertMessage($this->renderOpenDraftMessage($fm));
    }

    if ($fm->draftExists() && $fm->userHasOpenDraft()) {
      $draft = $fm->getDraft();
      // we want to re-create our FileManager to be based off of the current draft.
      $fm = new FileManager($this->getLoggedInUsername(), $filePath, Config::$draftDir . $draft['draftFilename'], $this->getDB());
      $fm->setUserIsEditingDraft();
      $editDraft = true;
      // add a message saying that the draft is older than the published date of the page and it might be out of sync.
      $this->addOutdatedDraftMessageIfNeeded($draft);
      if (Utility::sharedDraftHasBeenEditedByCollaborator($draft)) {
        // this draft has been modified since the owner last saved it.
        $this->addConcertMessage(Config::DRAFT_EDITED_BY_COLLABORATOR_MESSAGE);
      }
    } else {
      $editDraft = false;
    }

    if ($this->getMethod() === 'POST' && $fm->editFile($_POST)) {
      // trying to save an edit
      $userCanPublish = PermissionsManager::userCanPublishFile($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath));
      if ($userCanPublish && $fm->stageFile()) {
        return true;
      } else if (!$userCanPublish) {
        return $this->savePendingDraft($fm);
      }
    }

    $this->insertEditingResources($filePath, self::findRedirectPath());

    $draftFilename = $fm->makeEditableDraft($editDraft);

    if ($draftFilename === false) {
      return $this->renderErrorPage(Config::GENERIC_ERROR_MESSAGE);
    }

    $page = $this->displayPage($draftFilename, true);

    // remove our editable draft since it doesn't need to sit around anywhere anymore.
    unlink($draftFilename);
    return $page;
  }

  /**
   * Saves a draft waiting to be published
   *
   * @param  FileManager $fileManager FileManager instance to save the draft for
   * @return boolean
   */
  private function savePendingDraft($fileManager)
  {
    if ($fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT)) {
      $pendingDraft = $fileManager->getDraft();

      $publishers = PermissionsManager::findPublishersForFile(Utility::removeDocRootFromPath($pendingDraft['destFilepath']));

      $this->forward('emailPendingDraft', ['draft' => $pendingDraft, 'publishers' => $publishers]);

      return true;
    }
    return false;
  }

  /**
   * Creates a new page for the user
   *
   * @param  string $filePath       Absolute path to the file to create
   * @param  string $fromFilePath   Absolute path to a file to create the new file from
   * @return boolean
   */
  private function createNewPage($filePath, $fromFilePath = null)
  {
    if ($fromFilePath === null) {
      if (isset($_POST['fromFilePath'])) {
        // our from file path is specified here.
        $fromFilePath = $_POST['fromFilePath'];
      } else {
        // default to our default template.
        $fromFilePath = Config::DEFAULT_TEMPLATE_PAGE;
      }
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, $fromFilePath, $this->getDB());

    if (!PermissionsManager::userCanCreatePage($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath))) {
      $this->addConcertMessage(Config::NOT_ALLOWED_TO_CREATE_MESSAGE, 'error');
      return false;
    }

    if (!$fm->acquireLock()) {
      $this->addConcertMessage($this->renderLockNotAcquiredMessage($fm), 'error');
      return false;
    }

    if ($fm->draftExists() && $fm->userHasOpenDraft()) {
      $editDraft = true;
    } else {
      $editDraft = false;
    }

    if ($this->getMethod() === 'POST' && $fm->editFile($_POST)) {
      $userCanPublish = PermissionsManager::userCanPublishFile($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath));
      if ($userCanPublish && $fm->stageFile()) {
        return true;
      } else if (!$userCanPublish) {
        return $this->savePendingDraft($fm);
      }
      // trying to save a new page
      return true;
    }

    $this->insertEditingResources($filePath, self::findRedirectPath(), null, null, ['isCreation' => 'true', 'fromFilePath' => $fromFilePath]);

    $draftFilename = $fm->makeEditableDraft($editDraft);

    if ($draftFilename === false) {
      return $this->renderErrorPage(Config::GENERIC_ERROR_MESSAGE);
    }

    $page = $this->displayPage($draftFilename, true);

    // remove our editable draft since it doesn't need to sit around anywhere anymore.
    unlink($draftFilename);
    return $page;
  }

  /**
   * Handles deleting a file
   *
   * @param  string $filePath Path to the file to delete
   * @return string|boolean String for confirmation, boolean otherwise
   */
  private function deletePage($filePath)
  {
    $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());

    if (!file_exists($filePath)) {
      return $this->redirect(dirname(Utility::removeDocRootFromPath($filePath)));
    }

    if ($fm->isNonEditableFile()) {
      if (isset($_GET['barebones'])) {
        return ['action' => 'return', 'value' => self::buildConcertMessageDiv(Config::SPECIAL_FILE_MESSAGE, 'error')];
      }
      $this->addConcertMessage(Config::SPECIAL_FILE_MESSAGE, 'error');
      return false;
    }

    if (!PermissionsManager::userCanDeletePage($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath))) {
      if (isset($_GET['barebones'])) {
        return ['action' => 'return', 'value' => self::buildConcertMessageDiv(Config::NOT_ALLOWED_TO_DELETE_MESSAGE, 'error')];
      }
      $this->addConcertMessage(Config::NOT_ALLOWED_TO_DELETE_MESSAGE, 'error');
      return false;
    }

    if (!$fm->acquireLock()) {
      if (isset($_GET['barebones'])) {
        return ['action' => 'return', 'value' => self::buildConcertMessageDiv($this->renderLockNotAcquiredMessage($fm), 'error')];
      }
      $this->addConcertMessage($this->renderLockNotAcquiredMessage($fm), 'error');
      return false;
    }

    if ($fm->draftExists()) {
      // someone has a draft open for this page.
      $message = $this->renderOpenDraftMessage($fm);
    } else {
      $message = '';
    }

    if ($this->getMethod() === 'POST' && isset($_POST['filePath'], $_POST['concertAction'], $_POST['deleteAction'])) {

      if ($_POST['deleteAction'] === 'confirmDelete' && urldecode($_POST['filePath']) === $filePath && $fm->stageForDeletion()) {
        if (isset($_GET['barebones'])) {
          $url = (new String(Utility::removeDocRootFromPath(dirname($filePath))))->getValue();
          return ['action' => 'return', 'value' => json_encode(['redirectUrl' => $url])];
        } else {
          return PageUtil::renderPageNotFound(true);
        }
      } else if ($_POST['deleteAction'] === 'cancelDelete') {
        $fm->stopEditing();
        $redirectPath = self::findRedirectPath();

        $url = (!empty($redirectPath)) ? $redirectPath : Utility::removeDocRootFromPath($filePath);
        if (isset($_GET['barebones'])) {
          return ['action' => 'return', 'value' => true];
        } else {
          return $this->redirect($url);
        }
      }
    }

    if (isset($_GET['barebones'])) {
      $scripts = '<script type="text/javascript">
            $(\'#concertDelete .deleteAction\').click(function(e) {
              e.preventDefault();
              var form = $(this).parents(\'form\');
              var action = $(this).attr(\'value\');

              var url  = form.attr(\'action\');
              var data = form.serialize();
              data += \'&deleteAction=\' + action;
              $.post(url, data, function(response) {
                $(\'#concertDelete .deleteAction\').colorbox.close();

                if (response && response.redirectUrl) {
                  setTimeout(function() {
                    window.location = response.redirectUrl;
                  }, 2000);
                }
              }, \'json\')
            })
          </script>';
    } else {
      $scripts = '';
    }

    // confirmation form
    $return = [
      'action' => 'return',
      'value'  => $this->renderTemplate('confirmDelete.html.twig', ['actionUrl' => $_SERVER['REQUEST_URI'], 'filePath' => $filePath, 'scripts' => $scripts, 'message' => $message]),
    ];
    return $return;
  }

  /**
   * Handles revisions
   *
   * @param  string $filePath Path to the file to handle revisions for
   * @param  string $redirectPath URL to redirect to on revision actions if different than $filePath
   * @return array
   */
  private function handleRevisions($filePath, $redirectPath = null)
  {
    // we don't want this set anymore
    unset($_GET['forwardedFrom']);

    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->getDB(), PermissionsManager::userCanManageRevisions($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath)));

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());

    if (!PermissionsManager::userCanViewRevisions($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath))) {
      $this->addConcertMessage(Config::NOT_ALLOWED_TO_VIEW_REVISIONS, 'error');
      return false;
    }

    Filters::add(RevisionsAPI::RENDER_REVISION_FILTER, function($content) {
      $contentArr = FileManager::separateContentByType($content);
      return implode("\n\r", $contentArr['content']);
    });

    if (PermissionsManager::userCanManageRevisions($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath))) {
      // user can manage revisions. Now add the restore hook action.
      Actions::add(RevisionsAPI::RESTORE_HOOK, function($revisionContent, $oldMessage, $restoreAction) use ($filePath, $fm, $redirectPath) {

        if (!$fm->acquireLock()) {
          return $this->redirectWithMessage($_SERVER['REQUEST_URI'], $this->renderLockNotAcquiredMessage($fm));
        }
        switch ($restoreAction) {
          case RevisionsAPI::UNDO_ACTION:
            $action = Config::UNDO_RESTORE_STAGE;
              break;
          case RevisionsAPI::RESTORE_ACTION:
          default:
            $action = Config::RESTORE_STAGE;
              break;
        }

        $fm->stageFile($action, $revisionContent);
        $redirectPath = ($redirectPath === null) ? $filePath : $redirectPath;

        $redirectPath = Utility::removeDocRootFromPath($redirectPath);

        $query = [
          'concert' => 'revisions',
        ];
        if (isset($_GET['concertAction'])) {
          $query['concertAction'] = $_GET['concertAction'];
        }

        if ($restoreAction === RevisionsAPI::UNDO_ACTION) {
          $_POST = [];

          $url = (new String($redirectPath))->addQueryString($query)->buildUrl()->getValue();
          return $this->redirectWithMessage($url, Config::UNDO_RESTORE_MESSAGE);

        } else {
          $_POST = [];

          $query['revisionsAction'] = 'thankYou';
          $url = (new String($redirectPath))->addQueryString($query)->buildUrl()->getValue();
          return $this->redirectWithMessage($url, Config::RESTORED_MESSAGE);
        }
      });
    }

    $moshed = self::alreadyMoshed();
    // revisions doesn't like concertMoshed being set in GET.
    unset($_GET['concertMoshed']);
    // render(true) so we get the content returned to us.
    $this->setContent($revisionsAPI->render(true));
    if ($moshed) {
      self::markMoshed();
    }

    return ['action' => 'return', 'value' => $this->renderPage()];
  }

  /**
   * Renders a person's recent activity
   *
   * @return string
   */
  public function viewRecentActivity()
  {
    $dbal = $this->getDB();
    $username = $this->getLoggedInUsername();

    // drafts
    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('destFilepath')
      ->addSelect('draftFilename')
      ->addSelect('type')
      ->addSelect('additionalUsers')
      ->addSelect('date')
      ->from('drafts', 'd')
      ->where('username = :username')
      ->orderBy('date', 'DESC')
      ->setMaxResults('10');

    $drafts =  $dbal->fetchAll($qb->getSQL(), [':username' => $username]);

    // staged files
    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('destFilepath')
      ->addSelect('action')
      ->addSelect('date')
      ->addSelect('publishedDate')
      ->from('stagedFiles', 'sf')
      ->where('username = :username')
      ->andWhere('action NOT IN (:httpdDir, :httpdHtAccess)')
      ->orderBy('publishedDate', 'DESC')
      ->setMaxResults('10');

    $published = $dbal->fetchAll($qb->getSQL(), [
        ':username' => $username,
        ':httpdDir' => Config::CREATE_HTTPD_DIRECTORY_STAGE,
        ':httpdHtAccess' => Config::CREATE_HTTPD_DIR_HTACCESS_STAGE
    ]);

    if (isset($_GET['barebones'])) {
      return $this->renderView('recentActivity.html.twig', ['drafts' => $drafts, 'published' => $published, 'isBarebones' => true]);
    } else {
      $this->setTitle('Recent Concert Activity');
      return $this->renderTemplate('recentActivity.html.twig', ['drafts' => $drafts, 'published' => $published, 'isBarebones' => false]);
    }
    // @todo view all activity for a user. Get this from staged files. Do we want to include drafts?
  }

  /**
   * Checks for any requests for users already working in Concert and also checks to see if it can add anything into the Template such as edit options or other actions.
   * Returns an array of actions to take.
   * Return Values:
   * <ul>
   *   <li>action: {boolean} Whether any action is required or not. PossibleValues:
   *     <ul>
   *       <li>return: This tells us that we need to return the value</li>
   *       <li>none: Nothing needs to happen</li>
   *     </ul>
   *   </li>
   *   <li>value: {string} Value for caller to return or add into the Template.</li>
   *   <li>redirectUrl: {string} URL to redirect to.</li>
   * </ul>
   *
   * @param  string|array $params String of the filepath to mosh on, or an associative array of parameters for moshing
   *   Possible keys:
   *   <ul>
   *     <li>filePath: Required. FilePath to mosh for</li>
   *     <li>dbal: Internal. Doctrine connection to use</li>
   *     <li>redirectPath: Internal. redirectPath to send to a few actions if needed</li>
   *
   * @throws InvalidArgumentException If $filePath is null and doesn't exist in $_POST
   * @return array Array containing actions the template render needs to take.
   */
  public function mosh($params)
  {
    if (GACConfig::isProductionBackup()) {
      // we don't want people to edit or do anything if we are working on our backup server
      return [
        'action' => 'none',
      ];
    }
    if (is_array($params)) {
      if (isset($params['dbal'])) {
        $this->setDBAL($params['dbal']);
      }

      assert('isset($params["filePath"])');
      $filePath = $params['filePath'];
    } else {
      $filePath = $params;
    }

    // @todo does commenting this out break anything? I hope not. It breaks editing drafts if it runs.
    // if (strpos($filePath, '.php') === false) {
    //   // make sure our filePath is a file
    //   $filePath = str_replace('//', '/', $filePath . DIRECTORY_SEPARATOR . 'index.php');
    // }

    if ($this->isLoggedIn() && !self::alreadyMoshed()) {
      // let ourselves know that we have already moshed this request.
      self::markMoshed();

      $filePath = Utility::addDocRootToPath($filePath);

      if (self::isSiteNavRequest() && !self::isForwardedFromSiteNav()) {
        // this has to happen before anything, because it will forward back to here and then everything else will get ran.
        $this->addMoshMenu();
        return $this->forward('handleSiteNavActions', ['filePath' => $filePath]);
      }

      if (self::isRequestingQuery()) {
        return ['action' => 'return', 'value' => $this->handleQueryRequest($filePath)];
      }

      // Check if the user wants to stop editing
      if (self::isRequestingLockRelease()) {
        return $this->stopEditing($filePath);
      }

      if (self::userIsDeleting()) {
        return $this->deletePage($filePath);
      }

      if (self::isRevisionRequest()) {
        $this->addMoshMenu();
        $redirectUrl = isset($params['redirectPath']) ? $params['redirectPath'] : null;
        return $this->handleRevisions($filePath, $redirectUrl);
      }

      $isEditingPublicDraft = false;
      // check if it is a draft request
      if (self::isDraftRequest() || ($isEditingPublicDraft = $this->userIsEditingPublicDraft($filePath))) {
        $this->addMoshMenu();
        // pass onto draft controller to process this request
        return $this->forward('handleDraftActions', ['filePath' => $filePath]);
      }

      $filePathFromDocRoot = Utility::removeDocRootFromPath($filePath);
      // check to see if the user has access to edit this page
      if (PermissionsManager::userCanEditFile($this->getLoggedInUsername(), $filePathFromDocRoot)) {

        $this->addMoshMenu();
        if (!self::isInternalForward()) {
          // we don't want to clear any messages if we have forwarded back to here
          $this->setConcertMessage(null, false);
        }

        if (!file_exists($filePath)) {
          // we need to check to see if the user is trying to create a new page
          $result = $this->handleNewPageRequest($filePath);

          if ($result) {
            return $result;
          }
        } else if (self::userIsEditing() || self::userIsSaving()) {
          // user is editing or saving
          $editResult = $this->edit($filePath);
          if ($editResult) {
            return [
              'action' => 'return',
              'value'  => $editResult,
            ];
          }
        } else {
          if (self::userIsDoneEditing()) {
            return $this->stopEditing($filePath);
          }
          $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());
          if ($fm->userHasLock()) {
            // user has a lock for this page.
            $this->addConcertMessage(Config::CONTINUE_EDITING_MESSAGE);
          }
        }
      }
    } else if (!self::alreadyMoshed() && $this->userIsViewingPublicDraft($filePath)) {
      // let ourselves know that we have already moshed this request.
      self::markMoshed();
      return $this->forward('handleDraftActions', ['filePath' => $filePath]);
    }
    return [
      'action' => 'none',
    ];
  }

  /**
   * Handles requests for querying for information
   *
   * @param  string $filePath Path to the file we are wanting information for
   * @return boolean
   */
  private function handleQueryRequest($filePath)
  {
    $query = self::getQueryFromRequest();

    switch ($query) {
      case 'hasSharedDraft':
        $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());
        $draft = $fm->getDraftForUser($this->getLoggedInUsername());
          return ($draft['type'] === Config::PUBLIC_DRAFT);
      default:
          return false;
    }
  }

  /**
   * Stops editing a page and releases the locks
   *
   * @param  string $filePath Path of the page to stop editing
   * @return array
   */
  private function stopEditing($filePath)
  {
    if ($this->userIsEditingPublicDraft($filePath)) {
      return $this->forward('stopEditingPublicDraft', ['filePath' => $filePath]);
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());

    $this->addMoshMenu();
    $fm->stopEditing();

    return [
      'action' => 'none',
      'value'  => true,
    ];
  }

  /**
   * Handles requests for creating new pages
   *
   * @param  string $filePath Path of the file to create
   * @return array
   */
  private function handleNewPageRequest($filePath)
  {
    // Note: Setting this to true will show a draft of the page if one exists instead of the 404 page. We want the 404 page so the user knows that this page doesn't exist yet.
    $showDraftInsteadOfErrorPage = false;
    if (self::userIsDoneEditing()) {
      $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());
      $fm->stopEditing();

      if ($showDraftInsteadOfErrorPage && $fm->userHasOpenDraft() && ($draft = $this->forward('showDraft', ['filePath' => $filePath, 'showSingle' => true]))) {
        return [
          'action' => 'return',
          'value'  => $draft,
        ];
      }
    } else if (self::userIsEditing() || self::userIsSaving()) {
      if (isset($_GET['srcFilePath'])) {
        if (isset(Config::$templates[$_GET['srcFilePath']])) {
          $fromFilePath = Config::$templates[$_GET['srcFilePath']]['location'];
        } else {
          $fromFilePath = self::isInternalForward() ? $_GET['srcFilePath'] : Utility::addDocRootToPath(urldecode($_GET['srcFilePath']));
          // we will have an absolute path if we were internally forwarded
        }
      } else {
        $fromFilePath = null;
      }

      $creationResult = $this->createNewPage($filePath, $fromFilePath);
      if ($creationResult) {
        return [
          'action' => 'return',
          'value'  => $creationResult,
        ];
      } else {
        // something happened in the request. Probably failed to acquire a lock.
        $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());
        if ($showDraftInsteadOfErrorPage && $fm->userHasOpenDraft() && ($draft = $this->forward('showDraft', ['filePath' => $filePath, 'showSingle' => true]))) {
          return [
            'action' => 'return',
            'value'  => $draft,
          ];
        }
      }
    }
    // nothing for us to do.
    return [
      'action' => 'none',
    ];
  }

  /**
   * Handles web requests to mosh
   *
   * @throws InvalidArgumentException If a filePath doesn't exist in $_POST
   * @return string
   */
  public function handleMoshRequest()
  {
    if (!isset($_POST['filePath'])) {
      throw new InvalidArgumentException('A file path is not specified.');
    }
    $filePath = $_POST['filePath'];

    $moshActions = $this->mosh($filePath);
    if (isset($moshActions['action'], $moshActions['value']) && $moshActions['action'] === 'return') {
      return $moshActions['value'];
    }
  }

  /**
   * Handles autocomplete results for user names
   *
   * @param  array $args Array from routing
   * @return string
   */
  public function autocompleteUser($args)
  {
    $value = $args['value'];

    $results = Autocomplete::executeQuery($value, 'FirstName,LastName,Username',
        ['Employee', 'Faculty', 'Administrator', 'Support Staff', 'Student'], 'Username,FirstName,LastName', null, false);

    $return = [];
    foreach ($results as $result) {
      if (isset($result->FirstName, $result->LastName, $result->Username)) {
        $return[] = [
          'label' => sprintf('%s %s (%s)', $result->FirstName, $result->LastName, $result->Username),
          'value' => $result->Username,
        ];
      }
    }

    return json_encode($return);
  }
}