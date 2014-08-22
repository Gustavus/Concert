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
   *   Array of groups.
   *     Groups contain keys of weights and the items for each weight
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
    $this->addSiteNavButtons();
    $this->addMiscellaneousButtons();

    if (!empty($this->menu)) {
      ksort($this->menu);
      return $this->renderView('menu.html.twig', ['menu' => $this->menu, 'showMenu' => isset($_GET['concert'])]);
    }
    return '';
  }

  /**
   * Adds an item to the menu
   *
   * @param array  $item   Array representing an item
   * @param string $group  Group to put the item in
   * @param integer $weight Weight of the item for sorting
   * @return void
   */
  private function addMenuItem($item, $group = 'concert', $weight = 0)
  {
    if (!isset($this->menu[$group][$weight])) {
      $this->menu[$group][$weight] = [];
    }
    $this->menu[$group][$weight][] = $item;
  }

  /**
   * Adds miscellaneous buttons
   *
   * @return  void
   */
  private function addMiscellaneousButtons()
  {
    $pathFromDocRoot = Config::removeDocRootFromPath($this->filePath);
    $query = $this->queryParams;

    if (PermissionsManager::userCanEditFile($this->getLoggedInUsername(), $pathFromDocRoot)) {
      // @todo change this to our separate access level for revisions
      $params = $query;
      $params['concert'] = 'revisions';
      $item = [
        'id'       => 'viewRevisions',
        'text'     => 'View page revisions',
        'url'      => (new String($pathFromDocRoot))->addQueryString($params)->buildUrl()->getValue(),
        'thickbox' => false,
      ];

      $this->addMenuItem($item);
    }


    if (isset($query['concert'])) {
      // add quit button
      foreach (Config::$concertGETKeys as $key) {
        unset($query[$key]);
      }

      $item = [
        'id'       => 'quitConcert',
        'text'     => 'Quit Concert',
        'url'      => (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
        'thickbox' => false,
      ];

      $this->addMenuItem($item);
    }
  }

  /**
   * Adds draft editing buttons to the current menu
   *
   * @return void
   */
  private function addPublicDraftButtons()
  {
    if (self::isSiteNavRequest()) {
      return false;
    }
    if ($this->userIsViewingPublicDraft(Config::removeDocRootFromPath($this->filePath))) {
      $draftName = self::guessDraftName($this->filePath);
      $draft = $this->getFileManager()->getDraft($draftName);
      if (PermissionsManager::userCanEditDraft($this->getLoggedInUsername(), $draft)) {

        if (self::isRequestFromConcertRoot($this->filePath)) {
          $url = $this->buildUrl('editDraft', ['draftName' => $draft['draftFilename']]);
        } else {
          $query = $this->queryParams;
          $query['concert'] = 'editDraft';
          $url = (new String(Config::removeDocRootFromPath($this->filePath)))->addQueryString($query)->buildUrl()->getValue();
        }

        $item = [
          'id'       => 'concertEditDraft',
          'text'     => 'Edit Draft',
          'url'      => $url,
          'classes'  => 'blue',
          'thickbox' => false,
        ];

        $this->addMenuItem($item);
      }
    } else if ($this->userIsEditingPublicDraft(Config::removeDocRootFromPath($this->filePath))) {
      $draftName = self::guessDraftName($this->filePath);
      $draft = $this->getFileManager()->getDraft($draftName);

      if (self::isRequestFromConcertRoot($this->filePath)) {
        $url = $this->buildUrl('drafts', ['draftName' => $draft['draftFilename']]);
      } else {
        $query = $this->queryParams;
        $query['concert'] = 'viewDraft';
        $url = (new String(Config::removeDocRootFromPath($this->filePath)))->addQueryString($query)->buildUrl()->getValue();
      }

      $item = [
        'id'       => 'concertStopEditingDraft',
        'text'     => 'Stop Editing Draft',
        'url'      => $url,
        'classes'  => 'red',
        'thickbox' => false,
      ];

      $this->addMenuItem($item);
    }
  }

  /**
   * Adds draft editing buttons to the current menu
   *
   * @return void
   */
  private function addDraftButtons()
  {
    if (self::isSiteNavRequest()) {
      // we don't want to do anything with drafts for site_navs
      return;
    }
    // add button to add users to draft if possible.
    $draft = $this->getFileManager()->getDraftForUser($this->getLoggedInUsername());

    if (empty($draft)) {
      // we might be viewing a public draft.
      $currentDraft = $this->getFileManager()->getDraft(self::guessDraftName());
      if (!empty($currentDraft) && PermissionsManager::userOwnsDraft($this->getLoggedInUsername(), $currentDraft)) {
        $draft = $currentDraft;
      }
    }

    if (!empty($draft) && $draft['type'] === Config::PUBLIC_DRAFT) {
      if (self::userIsAddingUsersToDraft(Config::removeDocRootFromPath($this->filePath))) {
        if (self::isRequestFromConcertRoot($this->filePath)) {
          $url = $this->buildUrl('drafts', ['draftName' => $draft['draftFilename']]);
        } else {
          $query = $this->queryParams;
          $query['concert'] = 'viewDraft';
          $query['concertDraft'] = $draft['draftFilename'];
          if (self::isSiteNavRequest()) {
            $query['concertAction'] = 'siteNav';
          }
          $url = (new String(Config::removeDocRootFromPath($this->filePath)))->addQueryString($query)->buildUrl()->getValue();
        }
        $item = [
          'id'       => 'viewDraft',
          'text'     => 'View Draft',
          'url'      => $url,
          'thickbox' => false,
        ];

        $this->addMenuItem($item);
      } else if (!self::isSiteNavRequest()) {
        if (self::isRequestFromConcertRoot($this->filePath)) {
          $url = $this->buildUrl('addUsersToDraft', ['draftName' => $draft['draftFilename']]);
        } else {
          $query = $this->queryParams;
          $query['concert'] = 'addUsers';
          $query['concertDraft'] = $draft['draftFilename'];
          $url = (new String(Config::removeDocRootFromPath($this->filePath)))->addQueryString($query)->buildUrl()->getValue();
        }
        $item = [
          'id'           => 'addUsersToDraft',
          'text'         => 'Add users to your draft',
          'url'          => $url,
          'classes'      => 'green',
          'thickboxData' => ['height' => '400px'],
        ];

        $this->addMenuItem($item);
      }
    }

    $drafts = $this->getFileManager()->findDraftsForCurrentUser();

    if (!empty($drafts)) {
      $query = $this->queryParams;
      $query['concert'] = 'viewDraft';

      unset($query['concertDraft']);
      $pathFromDocRoot = Config::removeDocRootFromPath($this->filePath);

      $item = [
        'id'   => 'viewDrafts',
        'text' => 'View all drafts',
        'url'  => (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
      ];

      $this->addMenuItem($item);
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
    unset($query['concertAction']);
    if (!PermissionsManager::userCanEditFile($this->getLoggedInUsername(), $pathFromDocRoot)) {
      return;
    }

    if (PermissionsManager::userCanCreatePage($this->getLoggedInUsername(), $pathFromDocRoot)) {
      $item = [
        'id'            => 'createPage',
        'text'          => 'Create New Page',
        'url'           => $this->buildUrl('newPageMenu'),
        'thickbox'      => true,
        'thickboxData' => ['height' => '400px'],
      ];

      $this->addMenuItem($item);
    }

    if (file_exists($this->filePath) && PermissionsManager::userCanDeletePage($this->getLoggedInUsername(), $pathFromDocRoot)) {
      $query['concert'] = 'delete';
      $item = [
        'id'       => 'deletePage',
        'text'     => 'Delete Page',
        'url'      => (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
        'thickbox' => true,
        'classes'  => 'red',
      ];

      $this->addMenuItem($item);
    }

    if (self::userIsEditing() || self::userIsSaving()) {
      $query['concert'] = 'stopEditing';
      $item = [
        'id'       => 'stopEditing',
        'text'     => 'Stop Editing',
        'url'      => (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
        'thickbox' => false,
        'classes'  => 'red',
      ];

      $this->addMenuItem($item);
    } else {

      $draft = $this->getFileManager()->getDraftForUser($this->getLoggedInUsername());

      if (!empty($draft) && PermissionsManager::userOwnsDraft($this->getLoggedInUsername(), $draft)) {
        if (self::isRequestFromConcertRoot($this->filePath)) {
          $url = $this->buildUrl('editDraft', ['draftName' => $draft['draftFilename']]);
        } else {
          $query = $this->queryParams;
          $query['concert'] = 'edit';
          $url = (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue();
        }
        $item = [
          'id'       => 'editDraft',
          'text'     => 'Continue draft',
          'url'      => $url,
          'thickbox' => false,
        ];

        $this->addMenuItem($item);
      } else {
        $query['concert'] = 'edit';
        $item = [
          'id'       => 'startEditing',
          'text'     => 'Edit Page',
          'url'      => (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
          'thickbox' => false,
          'classes'  => 'blue',
        ];

        $this->addMenuItem($item);
      }
    }
  }

  /**
   * Adds site nav buttons
   *
   * @return  void
   */
  private function addSiteNavButtons()
  {
    $pathFromDocRoot = Config::removeDocRootFromPath($this->filePath);

    $siteNav = self::getSiteNavForFile($this->filePath);
    $siteNavFromDocRoot = Config::removeDocRootFromPath($siteNav);

    $siteBase = PermissionsManager::findUsersSiteForFile($this->getLoggedInUsername(), $pathFromDocRoot);
    $userCanEditSiteNav = PermissionsManager::userCanEditSiteNav($this->getLoggedInUsername(), $siteNavFromDocRoot);

    $currentDirSiteNavFile = dirname($this->filePath) . DIRECTORY_SEPARATOR . 'site_nav.php';

    if ($currentDirSiteNavFile === $siteNav) {
      // the found site nav lives in the current file's directory.
      $isInheritedNav = false;
    } else {
      $isInheritedNav = true;
    }

    if (!$userCanEditSiteNav) {
      if (strpos($siteNavFromDocRoot, $siteBase) !== 0) {
        // The user can't edit the parent nav, but we can give them the option to create one for this site.
        if (!PermissionsManager::userCanEditSiteNav($this->getLoggedInUsername(), $pathFromDocRoot)) {
          // user can't even edit the site nav for the current site
          return;
        }
      } else {
        // not the parent nav. The user can't do anything
        return;
      }
    }

    // user can edit or create a site nav.

    // disabled since drafts are disabled
    // if ($isParentNav) {
    //   // the site nav lives outside of our current site. Lets see if they have a draft for their current file
    //   $fm = new FileManager($this->getLoggedInUsername(), dirname($this->filePath) . DIRECTORY_SEPARATOR . 'site_nav.php', null, $this->getDB());
    // } else {
    //   // the site nav lives inside our current site.
    //   $fm = new FileManager($this->getLoggedInUsername(), $siteNav, null, $this->getDB());
    // }
    $query = $this->queryParams;

    // disabled drafts for siteNavs
    // if ($this->isSiteNavRequest()) {
    //   $drafts = $fm->findDraftsForCurrentUser();

    //   if (!empty($drafts)) {
    //     $query = $this->queryParams;
    //     $query['concert'] = 'viewDraft';
    //     if (self::isSiteNavRequest()) {
    //       $query['concertAction'] = 'siteNav';
    //       $text = 'View all local navigation drafts';
    //     } else {
    //       $text = 'View all drafts';
    //     }

    //     unset($query['concertDraft']);
    //     $pathFromDocRoot = Config::removeDocRootFromPath($this->filePath);

    //     $item = [
    //       'id'   => 'viewDrafts',
    //       'text' => 'View all drafts',
    //       'url'  => (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
    //     ];

    //     $this->addMenuItem($item, 'localNavigation', 20);
    //   }
    // }

    if (self::isSiteNavRequest() && (self::userIsEditing() || self::userIsCreatingSiteNav())) {
      $query = $this->queryParams;
      // @todo with concertAction being siteNav, this will keep giving us site nav options. What to do since adding edit buttons doesn't do anything if it is a site-nav request
      $query['concertAction'] = 'siteNav';
      $query['concert'] = 'stopEditing';
      $url = (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue();
      $item = [
        'id'       => 'stopEditingSiteNav',
        'text'     => 'Stop editing local navigation',
        'url'      => $url,
        'thickbox' => false,
      ];

      $this->addMenuItem($item, 'localNavigation', 20);
    // disabled drafts for siteNavs
    // } else if (($draft = $fm->getDraftForUser($this->getLoggedInUsername())) !== false) {
    //   // the user has a draft of the site nav they can continue editing.
    //   $query = $this->queryParams;
    //   $query['concert'] = 'edit';
    //   $query['concertAction'] = 'siteNav';
    //   $url = (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue();
    //   $item = [
    //     'id'       => 'continueSiteNavDraft',
    //     'text'     => 'Continue draft',
    //     'url'      => $url,
    //     'thickbox' => false,
    //   ];

    //   $this->addMenuItem($item, 'localNavigation', 20);
    }

    if (!(self::userIsEditing() || self::userIsCreatingSiteNav())) {
      // the site nav exists in the current site.
      $query = $this->queryParams;
      $query['concert'] = 'edit';
      $query['concertAction'] = 'siteNav';
      $url = (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue();

      $item = [
        'id'       => 'editSiteNav',
        'text'     => $isInheritedNav ? 'Edit inherited local navigation' : 'Edit local navigation',
        'url'      => $url,
        'thickbox' => false,
      ];

      if (self::isSiteNavShared($siteNav)) {
        // we need to verify that they know this is a shared site nav.
        $html = $this->renderView('confirmEditSharedSiteNav.html.twig', ['message' => Config::buildSharedSiteNavNote(dirname($siteNav), false), 'editUrl' => $url]);
        $html = rawurlencode($html);
        $item['thickbox'] = true;
        $item['thickboxData'] = ['html' => $html];
      }

      $this->addMenuItem($item, 'localNavigation', 20);
    }

    if (!(self::userIsEditing() || self::userIsCreatingSiteNav()) && $isInheritedNav) {
      $query = $this->queryParams;
      $query['concert'] = 'createSiteNav';
      $query['concertAction'] = 'siteNav';
      $url = (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue();
      $item = [
        'id'       => 'createSiteNav',
        'text'     => 'Create local navigation',
        'url'      => $url,
        'thickbox' => false,
      ];

      if (self::isSiteNavShared($siteNav)) {
        // we need to verify that they know this is a shared site nav.
        $html = $this->renderView('confirmEditSharedSiteNav.html.twig', ['message' => Config::buildSharedSiteNavNote(dirname($currentDirSiteNavFile), true), 'editUrl' => $url]);
        $html = rawurlencode($html);
        $item['thickbox'] = true;
        $item['thickboxData'] = ['html' => $html];
      }

      $this->addMenuItem($item, 'localNavigation', 20);
    }

    // if ($isParentNav && !self::isGlobalNav($siteNav)) {
    //   // Give them the option to edit the parent
    //   $query = $this->queryParams;
    //   $query['concert'] = 'edit';
    //   $query['concertAction'] = 'editParentSiteNav';
    //   $url = (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue();
    //   $item = [
    //     'id'       => 'editParentSiteNav',
    //     'text'     => 'Edit inherited local navigation',
    //     'url'      => $url,
    //     'thickbox' => false,
    //   ];

    //   $this->addMenuItem($item, 'localNavigation', 20);
    // }

    if ($siteBase . 'site_nav.php' !== $siteNavFromDocRoot && file_exists($siteNav) && $userCanEditSiteNav && PermissionsManager::userCanDeletePage($this->getLoggedInUsername(), $siteNavFromDocRoot)) {
      // the site nav isn't the base nav for this site, and the user has permissions, so give them the option
      $query = $this->queryParams;
      $query['concert'] = 'delete';
      $query['concertAction'] = 'siteNav';
      $item = [
        'id'       => 'deletePage',
        'text'     => 'Delete Local Navigation',
        'url'      => (new String($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
        'thickbox' => true,
        'classes'  => 'red',
      ];

      $this->addMenuItem($item, 'localNavigation', 20);
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

    if (self::isBareboneRequest()) {
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
      if (self::isBareboneRequest()) {
        return false;
      } else {
        return $this->renderErrorPage(Config::NO_SITE_ACCESS_MESSAGE);
      }
    }

    $view = $this->renderView('newPageForm.html.twig', ['site' => $siteBase, 'cssVersion' => Config::CSS_VERSION]);

    if (self::isBareboneRequest()) {
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
    if (strpos($this->filePath, '.php') === false) {
      $this->filePath = str_replace('//', '/', $this->filePath . DIRECTORY_SEPARATOR . 'index.php');
    }


    $origGET = $_GET;
    $_GET = $this->queryParams;
    if (self::isSiteNavRequest() && self::isForwardedFromSiteNav()) {
      $this->filePath = $file = self::getSiteNavForFile($this->filePath);
    }
    $_GET = $origGET;
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