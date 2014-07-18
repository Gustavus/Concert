<?php
/**
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 */
namespace Gustavus\Concert\Controllers;

use Gustavus\Concert\Config,
  Gustavus\Concert\FileManager,
  Gustavus\Utility\File,
  Gustavus\Utility\PageUtil,
  Gustavus\Concert\PermissionsManager;

/**
 * Handles draft actions
 *
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 *
 * @todo  write tests
 */
class DraftController extends SharedController
{
  /**
   * Shows a draft
   *
   * @param  array $param       Array of parameters
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

    $filePathFromDocRoot = Config::removeDocRootFromPath($filePath);

    if (!PermissionsManager::userCanEditFile($this->getLoggedInUsername(), $filePathFromDocRoot)) {
      return $this->redirect($filePathFromDocRoot);
      exit;
    }

    $fm = new FileManager($this->getLoggedInUsername(), $params['filePath']);

    $drafts = $fm->findDraftsForCurrentUser($draft);

    if (empty($drafts)) {
      return $this->renderErrorPage('Oops! It looks like there aren\'t any drafts to show');
    }

    if (!$showSingle && $draft === null && count($drafts) > 0) {
      // user has access to a few drafts. We want to ask them which draft they want to view
      return $this->renderMultipleDraftOptions($drafts);
    }

    $draft = reset($drafts);

    $this->addSessionMessage(sprintf('%s<br/>This draft will live at "%s" when published.', Config::DRAFT_NOTE, Config::removeDocRootFromPath($draft['destFilepath'])), false);


    $draftFileName = $fm->getDraftFileName($draft['username'], true);

    if (!$draftFileName || !file_exists($draftFileName)) {
      return $this->renderErrorPage('Oops! It appears as if the draft could not be found.');
    }

    return (new File($draftFileName))->loadAndEvaluate();
  }

  /**
   * Renders draft options for people who have multiple drafts to choose from
   *
   * @param  array $drafts
   * @return string
   */
  private function renderMultipleDraftOptions($drafts)
  {
    return $this->renderTemplate('draftOptions.html.twig', ['drafts' => $drafts]);
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


    $fm = new FileManager($this->getLoggedInUsername(), $this->buildUrl('drafts', ['draftName' => '']));

    $draft = $fm->getDraft($draftName);

    $filePathFromDocRoot = Config::removeDocRootFromPath($draft['destFilepath']);

    if ($draft['type'] !== Config::PUBLIC_DRAFT) {
      return PageUtil::renderPageNotFound(true);
    }

    $messageAdditions = '';
    if ((!empty($draft['additionalUsers']) && in_array($this->getLoggedInUsername(), $draft['additionalUsers'])) || $this->getLoggedInUsername() === $draft['username']) {
      $messageAdditions = sprintf('<br/><a href="%s" class="button">Edit Draft</a>', $this->buildUrl('editDraft', ['draftName' => $draft['draftFileName']]));
    }

    $this->addSessionMessage(sprintf('%s<br/>This draft will live at "%s" when published.%s', Config::DRAFT_NOTE, Config::removeDocRootFromPath($draft['destFilepath']), $messageAdditions), false);

    return (new File(Config::$draftDir . $draftName))->loadAndEvaluate();
  }

  /**
   * Handles editing public drafts
   *
   * @param  array  $params Params from Router
   * @return string
   */
  public function editPublicDraft(array $params)
  {
    $draftName = $params['draftName'];

    $fm = new FileManager($this->getLoggedInUsername(), $this->buildUrl('editDraft', ['draftName' => '']));

    // we've got our draft.
    $draft = $fm->getDraft($draftName);

    $draftFilePath = Config::$draftDir . $draftName;

    // now we need to make a fileManager to edit the current draft
    $draftFM = new FileManager($this->getLoggedInUsername(), $draftFilePath);
    $draftFM->setUserIsEditingPublicDraft();

    $fm = new FileManager($this->getLoggedInUsername(), $draft['destFilepath']);
    $fm->setUserIsEditingPublicDraft();

    // we need to create a lock on the draft file as well as the file the draft represents
    if (!$draftFM->acquireLock() || !$fm->acquireLock()) {
      return $this->renderErrorPage('Oops! We were unable to create a lock for this file. Someone else must currently be editing it. Please try back later.');
    }

    if ($this->getMethod() === 'POST') {
      // trying to save an edit
      if ($draftFM->editFile($_POST) && $draftFM->saveDraft($draft['type'])) {
        $draftFM->stopEditing();
        $fm->stopEditing();
        return [
          'action' => 'return',
          'value'  => true,
          'redirectUrl' => $this->buildUrl('drafts', ['draftName' => $draftName]),
        ];
      }
    }

    $additionalButtons = [
      [
        'url'  => $this->buildUrl('drafts', ['draftName' => $draftName]),
        'id'   => 'concertStopEditing',
        'text' => 'Stop editing draft',
      ]
    ];

    $this->insertEditingResources($this->buildUrl('editDraft', ['draftName' => $draftName]), ['saveDraft'], $additionalButtons);

    $draftFileName = $draftFM->makeEditableDraft();

    if ($draftFileName === false) {
      return $this->renderErrorPage('Something happened');
    }

    return (new File($draftFileName))->loadAndEvaluate();
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

    if (!PermissionsManager::userCanEditFile($this->getLoggedInUsername(), Config::removeDocRootFromPath($filePath))) {
      return json_encode(['error' => true, 'reason' => Config::NOT_ALLOWED_TO_EDIT_MESSAGE]);
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath);

    if ($fm->editFile($_POST)) {
      $draftType = ($this->userIsSavingPrivateDraft()) ? Config::PRIVATE_DRAFT : Config::PUBLIC_DRAFT;
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
    if (!PermissionsManager::userCanEditFile($this->getLoggedInUsername(), Config::removeDocRootFromPath($filePath))) {
      return json_encode(['error' => true, 'reason' => Config::NOT_ALLOWED_TO_EDIT_MESSAGE]);
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, $fromFilePath);

    if (!$fm->acquireLock()) {
      // lock couldn't be acquired
      return json_encode(['error' => true, 'reason' => Config::LOCK_NOT_AQUIRED_MESSAGE]);
    }

    if ($fm->editFile($_POST)) {
      $draftType = ($this->userIsSavingPrivateDraft()) ? Config::PRIVATE_DRAFT : Config::PUBLIC_DRAFT;
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
    if (!PermissionsManager::userCanEditFile($this->getLoggedInUsername(), Config::removeDocRootFromPath($filePath))) {
      return json_encode(['error' => true, 'reason' => Config::NOT_ALLOWED_TO_EDIT_MESSAGE]);
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath);
    if (!$fm->userHasOpenDraft()) {
      // user doesn't have a draft they can delete. Nothing needs to happen.
      return true;
    } else {
      $fm->destroyDraft();
      return true;
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
    if ($this->userWantsToViewDraft()) {
      $params['draft'] = $this->getDraftFromRequest();
      return ['action' => 'return', 'value' => $this->showDraft($params)];
    } else if (Config::userIsEditingPublicDraft($params['filePath'])) {
      $params['draftName'] = basename($params['filePath']);
      return ['action' => 'return', 'value' => $this->editPublicDraft($params)];
    } else if ($this->userIsSavingDraft()) {
      return ['action' => 'return', 'value' => $this->saveDraft($params['filePath'])];
    } else if ($this->userIsDeletingDraft()) {
      return ['action' => 'return', 'value' => $this->deleteDraft($params['filePath'])];
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

    $draftFM = new FileManager($this->getLoggedInUsername(), $filePath);

    $draftFM->setUserIsEditingPublicDraft();
    $draft = $draftFM->getDraft($draftName);

    $draftFM->stopEditing();

    // release the lock on the file the draft represents
    $fm = new FileManager($this->getLoggedInUsername(), $draft['destFilepath']);
    $fm->setUserIsEditingPublicDraft();
    $fm->stopEditing();

    return [
      'action' => 'return',
      'value'  => true,
    ];
  }

  /**
   * Gets the requested draft from the url
   *
   * @return string|null
   */
  private function getDraftFromRequest()
  {
    if (isset($_GET['concertDraft'])) {
      return $_GET['concertDraft'];
    }
    return null;
  }

  /**
   * Gets the file path of the page we are trying to copy
   *
   * @return string
   */
  private function getFilePathToCopy()
  {
    // @todo this needs to get the file to copy from the request
    return Config::TEMPLATE_PAGE;
  }
}