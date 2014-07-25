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
  Gustavus\Utility\String,
  Gustavus\Utility\PageUtil,
  Gustavus\Concert\PermissionsManager,
  Campus\Utility\Autocomplete,
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
  private function edit($filePath)
  {
    $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());

    if (!$fm->userCanEditFile()) {
      $this->addSessionMessage('Oops! It appears that you don\'t have access to edit this file');
      return false;
    }

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

    if ($this->getMethod() === 'POST' && $fm->editFile($_POST)) {
      // trying to save an edit
      $userCanPublish = PermissionsManager::userCanPublishFile($this->getLoggedInUsername(), Config::removeDocRootFromPath($filePath));
      if ($userCanPublish && $fm->stageFile()) {
        return true;
      } else if (!$userCanPublish) {
        return $this->savePendingDraft($fm);
      }
    }

    $this->insertEditingResources($filePath);

    $draftFilename = $fm->makeEditableDraft($editDraft);

    if ($draftFilename === false) {
      return $this->renderErrorPage(Config::GENERIC_ERROR_MESSAGE);
    }

    return (new File($draftFilename))->loadAndEvaluate();
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
      // @todo we need to send an email alerting the site admin of a pending draft
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
  public function createNewPage($filePath, $fromFilePath = null)
  {
    if ($fromFilePath === null) {
      $fromFilePath = Config::TEMPLATE_PAGE;
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, $fromFilePath, $this->getDB());

    if (!PermissionsManager::userCanCreatePage($this->getLoggedInUsername(), Config::removeDocRootFromPath($filePath))) {
      $this->addSessionMessage('Oops! It appears that you don\'t have access to create this page.');
      return false;
    }

    if (!$fm->acquireLock()) {
      $this->addSessionMessage('Oops! We were unable to create a lock for this file. Someone else must currently be editing it. Please try back later.', false);
      return false;
    }

    if ($fm->draftExists() && $fm->userHasOpenDraft()) {
      $editDraft = true;
    } else {
      $editDraft = false;
    }

    if ($this->getMethod() === 'POST' && $fm->editFile($_POST) && $fm->stageFile()) {
      // trying to save a new page
      return true;
    }

    $this->insertEditingResources($filePath);

    $draftFilename = $fm->makeEditableDraft($editDraft);

    if ($draftFilename === false) {
      return $this->renderErrorPage(Config::GENERIC_ERROR_MESSAGE);
    }

    return (new File($draftFilename))->loadAndEvaluate();
  }

  /**
   * Handles deleting a file
   *
   * @param  string $filePath Path to the file to delete
   * @return string|boolean String for confirmation, boolean otherwise
   */
  private function deleteFile($filePath)
  {
    $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());

    if (!file_exists($filePath)) {
      return $this->redirect(dirname(Config::removeDocRootFromPath($filePath)));
    }

    if (!$fm->userCanEditFile()) {
      $this->addSessionMessage('Oops! It appears that you don\'t have access to edit this file');
      return false;
    }

    if (!$fm->acquireLock()) {
      $this->addSessionMessage('Oops! We were unable to create a lock for this file. Someone else must currently be editing it. Please try back later.', false);
      return false;
    }

    if ($fm->draftExists()) {
      // someone has a draft open for this page.
      $this->addSessionMessage('someone has a draft open for this page.', false);
    }

    if ($this->getMethod() === 'POST' && isset($_POST['filePath'], $_POST['concertAction'], $_POST['deleteAction'])) {

      if ($_POST['deleteAction'] === 'confirmDelete' && urldecode($_POST['filePath']) === $filePath && $fm->stageForDeletion()) {
        if (isset($_GET['barebones'])) {
          $url = (new String(Config::removeDocRootFromPath(dirname($filePath))))->getValue();
          // @todo what should we do here?
          return ['action' => 'return', 'value' => true];
        } else {
          return PageUtil::renderPageNotFound(true);
        }
      } else if ($_POST['deleteAction'] === 'cancelDelete') {
        $fm->stopEditing();
        $url = (new String(Config::removeDocRootFromPath(dirname($filePath))))->getValue();
        if (isset($_GET['barebones'])) {
          return true;
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

                if (response) {
                  alert(\'This page has been deleted.\');
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
      'value'  => $this->renderTemplate('confirmDelete.html.twig', ['actionUrl' => $_SERVER['REQUEST_URI'], 'filePath' => urlencode($filePath), 'scripts' => $scripts]),
    ];
    return $return;
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

      if ($this->userIsDeleting()) {
        return $this->deleteFile($filePath);
      }

      $isEditingPublicDraft = false;
      // check if it is a draft request
      if ($this->isDraftRequest() || ($isEditingPublicDraft = $this->userIsEditingPublicDraft($filePath))) {
        if (!$isEditingPublicDraft) {
          $this->addMoshMenu();
        }
        // pass onto draft controller to process this request
        return $this->forward('handleDraftActions', ['filePath' => $filePath]);
      }

      $filePathFromDocRoot = Config::removeDocRootFromPath($filePath);
      // check to see if the user has access to edit this page
      if (PermissionsManager::userCanEditFile($this->getLoggedInUsername(), $filePathFromDocRoot)) {

        $this->addMoshMenu();
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
          $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());
          if ($fm->userHasLock()) {
            // user has a lock for this page.
            $this->addSessionMessage('It looks like you were in the process of editing this page but left before finishing. Would you like to <a href="?concert=edit">continue</a>?', false);
          }
        }
      }
    } else if (!$this->alreadyMoshed() && $this->userIsViewingPublicDraft($filePath)) {
      // let ourselves know that we have already moshed this request.
      $this->markMoshed();
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
    $query = $this->getQueryFromRequest();

    switch ($query) {
      case 'hasSharedDraft':
        $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());
        $draft = $fm->getDraftForUser($this->getLoggedInUsername());
          return ($draft['type'] === Config::PUBLIC_DRAFT && !empty($draft['additionalUsers']));
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
    if ($this->userWantsToStopEditing()) {
      $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());
      $fm->stopEditing();

      if ($fm->userHasOpenDraft() && ($draft = $this->forward('showDraft', ['filePath' => $filePath, 'showSingle' => true]))) {
        return [
          'action' => 'return',
          'value'  => $draft,
        ];
      }
    } else if ($this->userWantsToEdit() || $this->userIsSaving()) {
      // @todo this isn't finished
      // @todo copy functionality should also live here?
      $creationResult = $this->createNewPage($filePath);
      if ($creationResult) {
        return [
          'action' => 'return',
          'value'  => $creationResult,
        ];
      } else {
        // something happened in the request. Probably failed to acquire a lock.
        $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());
        if ($fm->userHasOpenDraft() && ($draft = $this->forward('showDraft', ['filePath' => $filePath, 'showSingle' => true]))) {
          return [
            'action' => 'return',
            'value'  => $draft,
          ];
        } else {
          // @todo should anything happen here?
        }
      }
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