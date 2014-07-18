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
  Gustavus\Resources\Resource,
  Gustavus\Extensibility\Filters,
  Gustavus\Concert\PermissionsManager,
  InvalidArgumentException;

/**
 * Handles main Concert actions
 *
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 *
 * @todo  write tests
 */
class MainController extends SharedController
{
  /**
   * Handles editing a page
   *
   * @param  string $page Page we are trying to edit
   * @return string|boolean String of the rendered edit page or boolean on save
   */
  public function edit($page = null)
  {
    if ($page === null && !isset($_GET['page'])) {
      return $this->renderErrorPage('Oops! It looks like there was no page specified to edit.');
    }
    if ($page === null) {
      $page = $_SERVER['DOCUMENT_ROOT'] . '/' . $_GET['page'];
    }

    $fm = new FileManager($this->getLoggedInUsername(), $page);

    if (!$fm->acquireLock()) {
      $this->addSessionMessage('Oops! We were unable to create a lock for this file. Someone else must currently be editing it. Please try back later.', false);
      return false;
    }

    if ($fm->draftExists()) {
      // someone has a draft open for this page.
      $this->addSessionMessage('someone has a draft open for this page.', false);
      // @todo if a user has a draft open, should we treat this as a "lock"?
    }

    if ($fm->draftExists() && $fm->userHasOpenDraft()) {
      $editDraft = true;
    } else {
      $editDraft = false;
    }

    if ($this->getMethod() === 'POST') {
      // trying to save an edit
      if ($fm->editFile($_POST) && PermissionsManager::userCanPublishFile($this->getLoggedInUsername(), Config::removeDocRootFromPath($page)) && $fm->stageFile()) {
        return true;
      }
    }

    $this->insertEditingResources($page);

    $draftFileName = $fm->makeEditableDraft($editDraft);

    if ($draftFileName === false) {
      return $this->renderErrorPage('Something happened');
    }

    return (new File($draftFileName))->loadAndEvaluate();
  }

  /**
   * Creates a new page for the user
   *
   * @param  string $filePath       Absolute path to the file to create
   * @param  string $fromFilePath   Absolute path to a file to create the new file from
   * @return boolean
   */
  public function createNewPage($filePath, $fromFilePath = null)
  {
    if ($fromFilePath === null) {
      $fromFilePath = Config::TEMPLATE_PAGE;
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, $fromFilePath);

    if (!$fm->acquireLock()) {
      $this->addSessionMessage('Oops! We were unable to create a lock for this file. Someone else must currently be editing it. Please try back later.', false);
      return false;
    }

    if ($fm->draftExists() && $fm->userHasOpenDraft()) {
      $editDraft = true;
    } else {
      $editDraft = false;
    }

    if ($this->getMethod() === 'POST') {
      // trying to save a new page

      if ($fm->editFile($_POST) && PermissionsManager::userCanCreatePage($this->getLoggedInUsername(), Config::removeDocRootFromPath($filePath)) && $fm->stageFile()) {
        return true;
      }
    }

    $this->insertEditingResources($filePath);

    $draftFileName = $fm->makeEditableDraft($editDraft);

    if ($draftFileName === false) {
      return $this->renderErrorPage('Something happened');
    }

    return (new File($draftFileName))->loadAndEvaluate();
  }

  /**
   * Checks for any requests for users already working in Concert and also checks to see if it can add anything into the Template such as edit options or other actions.
   * Returns an array of actions to take.
   * Return Values:
   * <ul>
   *   <li>action: {boolean} Whether any action is require or not. PossibleValues:
   *     <ul>
   *       <li>action: {string} Action needed to take. Possible keys: 'return' and 'none'.</li>
   *       <li>value: {string} Value to return if the action is "return".</li>
   *     </ul>
   *   </li>
   *   <li>value: {string} Value for caller to return or add into the Template.</li>
   * </ul>
   *
   * @param  string $filePath Filepath to mosh on
   *
   * @throws InvalidArgumentException If $filePath is null and doesn't exist in $_POST
   * @return array Array containing actions the template render needs to take.
   */
  public function mosh($filePath)
  {
    if ($this->isLoggedIn() && !$this->alreadyMoshed()) {
      // let ourselves know that we have already moshed this request.
      $this->markMoshed();
      if (strpos($filePath, $_SERVER['DOCUMENT_ROOT']) === false) {
        // we want to force our doc root.
        $filePath = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $filePath);
      }

      if ($this->isRequestingQuery()) {
        return ['action' => 'return', 'value' => $this->handleQueryRequest($filePath)];
      }

      // Check if the user wants to stop editing
      if ($this->isRequestingLockRelease()) {
        return $this->stopEditing($filePath);
      }

      $isEditingPublicDraft = false;
      // check if it is a draft request
      if ($this->isDraftRequest() || ($isEditingPublicDraft = Config::userIsEditingPublicDraft($filePath))) {
        if (!$isEditingPublicDraft) {
          $this->addMoshingActions($filePath);
        }
        // use is trying to view a draft.
        return $this->forward('handleDraftActions', ['filePath' => $filePath]);
      }

      $filePathFromDocRoot = Config::removeDocRootFromPath($filePath);
      // check to see if the user has access to edit this page
      if (PermissionsManager::userCanEditFile($this->getLoggedInUsername(), $filePathFromDocRoot)) {

        $this->addMoshingActions($filePath);
        $this->setSessionMessage(null, false);

        if (!file_exists($filePath) && PermissionsManager::userCanCreatePage($this->getLoggedInUsername(), $filePathFromDocRoot)) {
          // we need to check to see if the user is trying to create a new page
          $result = $this->handleNewPageRequest($filePath);
          if ($result) {
            return $result;
          }
        } else if ($this->userWantsToEdit() || $this->userIsSaving()) {
          // user is editing or saving
          $editResult = $this->edit($filePath);
          if ($editResult) {
            return [
              'action' => 'return',
              'value'  => $editResult,
            ];
          }
        } else {
          if ($this->userWantsToStopEditing()) {
            return $this->stopEditing($filePath);
          }
          $fm = new FileManager($this->getLoggedInUsername(), $filePath);
          if ($fm->userHasLock()) {
            // user has a lock for this page.
            $this->addSessionMessage('It looks like you were in the process of editing this page but left before finishing. Would you like to <a href="?concert=edit">continue</a>?', false);
          }
        }
      }
    }
    return [
      'action' => 'none',
    ];
  }

  private function handleQueryRequest($filePath)
  {
    $query = $this->getQueryFromRequest();

    switch ($query) {
      case 'hasSharedDraft':
        $fm = new FileManager($this->getLoggedInUsername(), $filePath);
        $draft = $fm->getDraftForUser($this->getLoggedInUsername());
          return ($draft['type'] === Config::PUBLIC_DRAFT && !empty($draft['additionalUsers']));
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
    if (Config::userIsEditingPublicDraft($filePath)) {
      return $this->forward('stopEditingPublicDraft', ['filePath' => $filePath]);
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath);

    $this->addMoshingActions($filePath);
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
    if ($this->userWantsToStopEditing()) {
      $fm = new FileManager($this->getLoggedInUsername(), $filePath);
      $fm->stopEditing();

      if ($fm->userHasOpenDraft() && ($draft = $this->forward('showDraft', ['filePath' => $filePath, 'showSingle' => true]))) {
        return [
          'action' => 'return',
          'value'  => $draft,
        ];
      }
    } else if ($this->userWantsToEdit()) {
      // @todo this isn't finished
      $creationResult = $this->createNewPage($filePath);
      if ($creationResult) {
        return [
          'action' => 'return',
          'value'  => $creationResult,
        ];
      } else {
        // something happened in the request. Probably failed to acquire a lock.
        $fm = new FileManager($this->getLoggedInUsername(), $filePath);
        if ($fm->userHasOpenDraft() && ($draft = $this->forward('showDraft', ['filePath' => $filePath, 'showSingle' => true]))) {
          return [
            'action' => 'return',
            'value'  => $draft,
          ];
        }
      }
    }
  }

  /**
   * Adds action buttons for moshing
   *
   * @param  string $filePath Path to the file we are moshing for
   * @return  void
   */
  private function addMoshingActions($filePath)
  {
    if ($this->userWantsToEdit() || $this->userIsSaving()) {
      Filters::add('userBox', function($content) {
        // @todo make this remove concert stuff from the url
        return $content . '<a href="?concert=stopEditing" class="button red concertEditPage">Stop Editing</a>';
      });
    } else {
      Filters::add('userBox', function($content) {
        return $content . '<a href="?concert=edit" class="button red concertEditPage">Edit Page</a>';
      });
    }
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
}