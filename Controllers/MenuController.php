<?php
/**
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 */
namespace Gustavus\Concert\Controllers;

use Gustavus\Concert\Config,
  Gustavus\Concert\FileManager,
  Gustavus\Concert\PermissionsManager,
  Gustavus\Utility\PageUtil,
  Gustavus\Utility\String;

/**
 * Handles menu actions
 *
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 *
 * @todo  write tests
 */
class MenuController extends SharedController
{
  /**
   * Associative array of the query parameters for the page we are building a menu for
   *
   * @var array
   */
  private $queryParams = [];

  /**
   * Path to the page we are building a menu for
   *
   * @var string
   */
  private $filePath;

  /**
   * Current menu
   *
   * @var array
   */
  private $menu;

  /**
   * FileManager
   *
   * @var FileManager
   */
  private $fileManager;

  /**
   * Builds a FileManager to use if it doesn't exist yet.
   *
   * @return FileManager
   */
  private function getFileManager()
  {
    if (empty($this->fileManager)) {
      $this->analyzeReferer();
      $this->fileManager = new FileManager($this->getLoggedInUsername(), $this->filePath, null, $this->getDB());
    }
    return $this->fileManager;
  }

  /**
   * Renders the menu for navigating concert actions
   *
   * @param  array $params Array of parameters that might get passed depending on how it is called
   * @return string
   */
  public function renderMenu(array $params = array())
  {
    if (!$this->isLoggedIn()) {
      return '';
    }
    // if another controller forwards here, but doesn't want to be checking the referer, they will pass forReferer = false
    if (isset($params['forReferer']) && $params['forReferer'] === false) {
      $forReferer = false;
    } else {
      $forReferer = true;
    }

    $this->analyzeReferer($forReferer);
    $this->addRefererParamsToGet();

    $this->addDraftButtons();
    $this->addPublicDraftButtons();
    $this->addEditButtons();

    if (!empty($this->menu)) {
      return $this->renderView('menu.html.twig', ['menu' => $this->menu]);
    }
    return '';
  }

  /**
   * Adds draft editing buttons to the current menu
   *
   * @param
   */
  private function addPublicDraftButtons()
  {
    if ($this->userIsViewingPublicDraft(Config::removeDocRootFromPath($this->filePath))) {
      $draftName = $this->guessDraftName($this->filePath);
      $draft = $this->getFileManager()->getDraft($draftName);
      if (PermissionsManager::userCanEditDraft($this->getLoggedInUsername(), $draft)) {

        if ($this->isRequestFromConcertRoot($this->filePath)) {
          $url = $this->buildUrl('editDraft', ['draftName' => $draft['draftFilename']]);
        } else {
          $query = $this->queryParams;
          $query['concert'] = 'editDraft';
          $url = (new String(Config::removeDocRootFromPath($this->filePath)))->addQueryString($query)->buildUrl()->getValue();
        }

        $this->menu[] = [
          'id'       => 'concertEditDraft',
          'text'     => 'Edit Draft',
          'url'      => $url,
          'classes'  => 'blue',
          'thickbox' => false,
        ];
      }
    } else if ($this->userIsEditingPublicDraft(Config::removeDocRootFromPath($this->filePath))) {
      $draftName = $this->guessDraftName($this->filePath);
      $draft = $this->getFileManager()->getDraft($draftName);

      if ($this->isRequestFromConcertRoot($this->filePath)) {
        $url = $this->buildUrl('drafts', ['draftName' => $draft['draftFilename']]);
      } else {
        $query = $this->queryParams;
        $query['concert'] = 'viewDraft';
        $url = (new String(Config::removeDocRootFromPath($this->filePath)))->addQueryString($query)->buildUrl()->getValue();
      }

      $this->menu[] = [
        'id'       => 'concertStopEditingDraft',
        'text'     => 'Stop Editing Draft',
        'url'      => $url,
        'classes'  => 'red',
        'thickbox' => false,
      ];
    }
  }

  /**
   * Adds draft editing buttons to the current menu
   *
   * @param
   */
  private function addDraftButtons()
  {
    // add button to add users to draft if possible.
    $draft = $this->getFileManager()->getDraftForUser($this->getLoggedInUsername());

    if (empty($draft)) {
      // we might be viewing a public draft.
      $currentDraft = $this->getFileManager()->getDraft($this->guessDraftName());
      if (!empty($currentDraft) && PermissionsManager::userOwnsDraft($this->getLoggedInUsername(), $currentDraft)) {
        $draft = $currentDraft;
      }
    }

    if (!empty($draft) && $draft['type'] === Config::PUBLIC_DRAFT) {
      if ($this->userIsAddingUsersToDraft(Config::removeDocRootFromPath($this->filePath))) {
        if ($this->isRequestFromConcertRoot()) {
          $url = $this->buildUrl('drafts', ['draftName' => $draft['draftFilename']]);
        } else {
          $query = $this->queryParams;
          $query['concert'] = 'viewDraft';
          $query['concertDraft'] = $draft['draftFilename'];
          $url = (new String(Config::removeDocRootFromPath($this->filePath)))->addQueryString($query)->buildUrl()->getValue();
        }
        $this->menu[] = [
          'id'       => 'viewDraft',
          'text'     => 'View Draft',
          'url'      => $url,
          'thickbox' => false,
        ];
      } else {
        if ($this->isRequestFromConcertRoot()) {
          $url = $this->buildUrl('addUsersToDraft', ['draftName' => $draft['draftFilename']]);
        } else {
          $query = $this->queryParams;
          $query['concert'] = 'addUsers';
          $query['concertDraft'] = $draft['draftFilename'];
          $url = (new String(Config::removeDocRootFromPath($this->filePath)))->addQueryString($query)->buildUrl()->getValue();
        }
        $this->menu[] = [
          'id'      => 'addUsersToDraft',
          'text'    => 'Add users to your draft',
          'url'     => $url,
          'classes' => 'green',
        ];
      }
    }

    $drafts = $this->getFileManager()->findDraftsForCurrentUser();

    if (!empty($drafts)) {
      $query = $this->queryParams;
      $query['concert'] = 'viewDraft';

      unset($query['concertDraft']);
      $pathFromDocRoot = Config::removeDocRootFromPath($this->filePath);

      $this->menu[] = [
        'id'   => 'viewDrafts',
        'text' => 'View all drafts',
        'url'  => (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
      ];
    }
  }

  /**
   * Adds buttons for editing actions
   *
   * @return  void
   */
  private function addEditButtons()
  {
    $pathFromDocRoot = Config::removeDocRootFromPath($this->filePath);
    $query = $this->queryParams;
    if (!PermissionsManager::userCanEditFile($this->getLoggedInUsername(), $pathFromDocRoot)){
      return;
    }
    if ($this->userWantsToEdit() || $this->userIsSaving()) {
      $query['concert'] = 'stopEditing';
      $this->menu[] = [
        'id'       => 'stopEditing',
        'text'     => 'Stop Editing',
        'url'      => (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
        'thickbox' => false,
        'classes'  => 'red',
      ];
    } else {

      $draft = $this->getFileManager()->getDraftForUser($this->getLoggedInUsername());

      if (!empty($draft) && PermissionsManager::userOwnsDraft($this->getLoggedInUsername(), $draft)) {
        if ($this->isRequestFromConcertRoot()) {
          $url = $this->buildUrl('editDraft', ['draftName' => $draft['draftFilename']]);
        } else {
          $query = $this->queryParams;
          $query['concert'] = 'editDraft';
          $query['concertDraft'] = $draft['draftFilename'];
          $url = (new String(Config::removeDocRootFromPath($this->filePath)))->addQueryString($query)->buildUrl()->getValue();
        }
        $this->menu[] = [
          'id'       => 'editDraft',
          'text'     => 'Continue draft',
          'url'      => $url,
          'thickbox' => false,
        ];
      } else {
        $query['concert'] = 'edit';
        $this->menu[] = [
          'id'       => 'startEditing',
          'text'     => 'Edit Page',
          'url'      => (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
          'thickbox' => false,
          'classes'  => 'blue',
        ];
      }
    }
  }

  /**
   * Gets the page requesting a menu and splits it up into pieces we can later use
   *
   * @param  boolean $forReferer Whether to analyze the referer or not
   * @return void
   */
  private function analyzeReferer($forReferer = true)
  {
    if (!empty($this->filePath)) {
      // looks like we've already analyzed the referer.
      return;
    }

    if ($forReferer) {
      $referer = PageUtil::getReferer();
    } else {
      if (isset($_SERVER['REQUEST_URI'])) {
        $referer = $_SERVER['REQUEST_URI'];
      } else {
        $referer = null;
      }
    }

    $parts = parse_url($referer);

    if (isset($parts['query'])) {
      $this->queryParams = (new String($parts['query']))->splitQueryString()->getValue();
    } else {
      $this->queryParams = [];
    }

    // $parts['path'] will have a leading slash. We want to remove the trailing slash from the doc root
    $this->filePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $parts['path'];
  }

  /**
   * Adds query parameters from the referer to $_GET so we can successfully see what the user should be able to do
   *
   * @return  void
   */
  private function addRefererParamsToGet()
  {
    $this->analyzeReferer();
    if (!empty($this->queryParams)) {
      $_GET = array_merge($this->queryParams, $_GET);
    }
  }
}