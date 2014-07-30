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
  Gustavus\Utility\String,
  Gustavus\Utility\File;

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
   * @return void
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
   * @return void
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
        if ($this->isRequestFromConcertRoot($this->filePath)) {
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
        if ($this->isRequestFromConcertRoot($this->filePath)) {
          $url = $this->buildUrl('addUsersToDraft', ['draftName' => $draft['draftFilename']]);
        } else {
          $query = $this->queryParams;
          $query['concert'] = 'addUsers';
          $query['concertDraft'] = $draft['draftFilename'];
          $url = (new String(Config::removeDocRootFromPath($this->filePath)))->addQueryString($query)->buildUrl()->getValue();
        }
        $this->menu[] = [
          'id'           => 'addUsersToDraft',
          'text'         => 'Add users to your draft',
          'url'          => $url,
          'classes'      => 'green',
          'thickboxData' => ['height' => '400px'],
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
    if (!PermissionsManager::userCanEditFile($this->getLoggedInUsername(), $pathFromDocRoot)) {
      return;
    }

    if (PermissionsManager::userCanCreatePage($this->getLoggedInUsername(), $pathFromDocRoot)) {
      $this->menu[] = [
        'id'            => 'createPage',
        'text'          => 'Create New Page',
        'url'           => $this->buildUrl('newPageMenu'),
        'thickbox'      => true,
        'thickboxData' => ['height' => '400px'],
      ];
    }

    if (PermissionsManager::userCanDeletePage($this->getLoggedInUsername(), $pathFromDocRoot)) {
      $query['concert'] = 'delete';
      $this->menu[] = [
        'id'       => 'deletePage',
        'text'     => 'Delete Page',
        'url'      => (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
        'thickbox' => true,
        'classes'  => 'red',
      ];
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
        if ($this->isRequestFromConcertRoot($this->filePath)) {
          $url = $this->buildUrl('editDraft', ['draftName' => $draft['draftFilename']]);
        } else {
          $query = $this->queryParams;
          $query['concert'] = 'edit';
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
   * Renders the html for our file tree plugin
   *
   * @param  boolean $forSrcFile Whether this is a tree for the src file or not.
   * @return string
   */
  private function renderFileTree($forSrcFile = false)
  {
    $this->analyzeReferer();

    if (isset($_GET['dir'])) {
      $dir = urldecode($_GET['dir']);
    } else {
      $dir = dirname(Config::removeDocRootFromPath($this->filePath));
    }

    $root = $_SERVER['DOCUMENT_ROOT'];
    $absDir = $root . $dir;
    $return = '';

    $newIndexFileHTML = sprintf('<li class="file ext_php"><a href="#" rel="%s">index.php</a></li>', htmlentities($dir . 'index.php'));

    $newFileHTML = sprintf('<li class="file ext_php"><a href="#" rel="%s">+newFile</a></li>', htmlentities($dir . 'concertNewFile'));

    $newFolderHTML = sprintf('<li class="directory collapsed"><a href="#" rel="%s">+newFolder</a></li>', htmlentities($dir . 'concertNewFolder/'));

    if (file_exists($absDir)) {
      $foundFiles = scandir($absDir);
      $files = [];
      foreach ($foundFiles as $file) {
        if (substr($file, strlen($file) - 4) === '.php' || is_dir($absDir . $file)) {
          $files[] = $file;
        }
      }
      // @todo do we need this? scandir sorts them by default
      //natcasesort($files);
      // The 2 accounts for . and ..
      if (count($files) > 2) {
        $return .= '<ul class="jqueryFileTree" style="display: none;">';
        if ($forSrcFile && (rtrim($this->filePath, '/') === rtrim($absDir, '/') || rtrim(dirname($this->filePath), '/') === rtrim($absDir, '/'))) {
          // we want our default templates on top
          foreach (Config::$templates as $templateName => $template) {
            $return .= sprintf('<li class="file ext_html"><a href="#" rel="%s">%s Template</a></li>', htmlentities($template), (new String($templateName))->titleCase()->getValue());
          }
        }
        // All dirs
        foreach ($files as $file) {
          if (file_exists($absDir . $file) && $file != '.' && $file != '..' && is_dir($absDir . $file)) {
            $return .= sprintf('<li class="directory collapsed"><a href="#" rel="%s" />%s</a></li>', htmlentities($dir . $file), htmlentities($file));
          }
        }
        // All files
        foreach ($files as $file) {
          if (file_exists($absDir . $file) && $file != '.' && $file != '..' && !is_dir($absDir . $file)) {
            $ext = preg_replace('/^.*\./', '', $file);
            if ($forSrcFile) {
              $return .= sprintf('<li class="file ext_%s"><a href="#" rel="%s">%s</a></li>', $ext, htmlentities($dir . $file), htmlentities($file));
            } else {
              $return .= sprintf('<li class="file ext_%s disabled">%s</li>', $ext, htmlentities($file));
            }
          }
        }
        if (!$forSrcFile) {
          $return .= $newFileHTML;
          $return .= $newFolderHTML;
        }
        $return .= '</ul>';
      }
    } else {
      if (!$forSrcFile) {
        $return = sprintf('<ul class="jqueryFileTree" style="display: none;">%s%s%s</ul>', $newIndexFileHTML, $newFileHTML, $newFolderHTML);
      }
    }
    return $return;
  }

  /**
   * Renders the form for creating a new page.
   *
   * @param  array $params array of params from router
   * @return string
   */
  public function renderNewPageForm($params = null)
  {
    // @todo should we abstract this out?
    if (!empty($params) && isset($params['fileTree']) && ($params['fileTree'] === 'toFile' || $params['fileTree'] === 'fromFile')) {
      return $this->renderFileTree($params['fileTree'] === 'fromFile');
    }

    if ($this->isBareboneRequest()) {
      $this->analyzeReferer();
    } else {
      $this->analyzeReferer(false);
    }

    // $fm = new FileManager($this->getLoggedInUsername(), $this->filePath, null, $this->getDB());

    // if (!PermissionsManager::userCanCreatePage($this->getLoggedInUsername(), Config::removeDocRootFromPath($this->filePath))) {
    //   $this->addSessionMessage('Oops! It appears that you don\'t have access to create this page.');
    //   return false;
    // }
    //@todo remove this
    //$this->filePath = '/cis/www/billy/concert/arst.php';
    $siteBase = PermissionsManager::findUsersSiteForFile($this->getLoggedInUsername(), Config::removeDocRootFromPath($this->filePath));
    if (empty($siteBase)) {
      // user doesn't have access to this site.
      if ($this->isBareboneRequest()) {
        return false;
      } else {
        return $this->renderErrorPage(Config::NO_SITE_ACCESS_MESSAGE);
      }
    }

    $view = $this->renderView('newPageForm.html.twig', ['site' => $siteBase, 'cssVersion' => Config::CSS_VERSION]);

    if ($this->isBareboneRequest()) {
      return $view;
    } else {
      $this->setContent($view);
      return $this->renderPage();
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