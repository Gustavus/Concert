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
  Gustavus\Concert\PermissionsManager,
  Gustavus\Utility\PageUtil,
  Gustavus\Utility\GACString,
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
   * Whether we are allowing this file to be edited or not
   *
   * @var integer
   */
  private $blockEditing = false;

  /**
   * Current menu
   *   Array of groups.
   *     Groups contain keys of weights and the items for each weight
   *       Items contain keys of:
   *       <ul>
   *         <li>id</li>
   *         <li>classes</li>
   *         <li>url</li>
   *         <li>thickbox: {boolean} Whether to use thickbox or not</li>
   *         <li>text</li>
   *         <li>thickboxData: Associative array of data attributes to pass to thickbox</li>
   *       </ul>
   *
   * ie.
   * <code>
   * [
   *   'concert' => [
   *     '20' => [
   *       [
   *         'url'     => '?concert=edit',
   *         'classes' => 'edit',
   *         'text'    => 'Edit',
   *       ],
   *       [
   *         'url'     => '?concert=stopEditing',
   *         'classes' => 'stopEditing',
   *         'text'    => 'Stop Editing',
   *       ],
   *     ],
   *     '1' => [
   *       [
   *         'url'  => '?concert=quit',
   *         'text' => 'Quit',
   *       ],
   *     ],
   *   ],
   * ];
   * </code>
   *
   *
   * @var array
   */
  private $menu;

  /**
   * Weights for menu groups
   * @var array
   */
  private static $groupWeights = [
    'file'   => 0,
    'drafts' => 1,
    'menu'   => 2,
    'help'   => 3,
  ];

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
    if (isset($params['showMenu'])) {
      $showMenu = $params['showMenu'];
    } else {
      $showMenu = isset($_GET['concert']);
    }

    if (!$showMenu) {
      $refererParts = parse_url(PageUtil::getReferer());
      if (isset($refererParts['query'])) {
        $queryParams = (new GACString($refererParts['query']))->splitQueryString()->getValue();
        if (isset($queryParams['concert']) && (!isset($_COOKIE['quitConcert']) || $_COOKIE['quitConcert'] === '0')) {
          $showMenu = true;
        }
      }
    }
    if (isset($_COOKIE['quitConcert'])) {
      // remove the cookie that tells us that we just quit and don't want to re-show the menu
      setcookie('quitConcert', '0', -1);
    }

    $this->analyzeReferer($forReferer);
    $this->addRefererParamsToGet();

    if (file_exists($this->filePath) && ($this->userIsEditing() || $this->userIsEditingDraft())) {
      $fileSize = filesize($this->filePath);

      if ($fileSize > Config::MAX_EDITABLE_FILE_SIZE) {
        // file is too big for us to edit
        $this->blockEditing = true;
        $this->addConcertMessage(Config::FILE_TOO_BIG_FOR_EDIT_MESSAGE, 'error');
      } else if ($fileSize > Config::PERFORMANCE_HIT_FILE_SIZE) {
        // file is so big that the user might experience slowness while editing
        $this->addConcertMessage(Config::LARGE_FILE_EDIT_MESSAGE, 'alert');
      }
    }

    $this->addDraftButtons();
    $this->addPublicDraftButtons();
    $this->addEditButtons();
    $this->addSiteNavButtons();
    $this->addMiscellaneousButtons();

    if (!empty($this->menu)) {
      $this->sortMenu();

      $pathFromDocRoot = Utility::removeDocRootFromPath($this->filePath);
      $query = $this->queryParams;
      self::removeConcertQueryParams($query);
      $quitURL = (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue();

      return $this->renderView('menu.html.twig',
          [
            'menu'     => $this->menu,
            'showMenu' => $showMenu,
            'quitURL'  => $quitURL,
          ]
      );
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
  private function addMenuItem($item, $group = 'file', $weight = 0)
  {
    // set the item's weight
    $item['weight'] = $weight;

    if ($group === null) {
      $group = 'file';
    }

    $type = ($group === 'actionButtons') ? 'buttons': 'menu';

    if (!isset($this->menu[$group])) {
      $this->menu[$group] = [
        'weight' => (isset(self::$groupWeights[$group]) ? self::$groupWeights[$group] : 0),
        'items'  => [],
        'type'   => $type,
      ];
    }

    $this->menu[$group]['items'][] = $item;
  }

  /**
   * Sorts the current menu by the specified weights
   *
   * @return void
   */
  private function sortMenu()
  {
    if (empty($this->menu)) {
      return false;
    }

    $weightSorter = function($a, $b) {
      if ($a['weight'] === $b['weight']) {
        return 0;
      } else if ($a['weight'] > $b['weight']) {
        return 1;
      } else {
        return -1;
      }
    };

    uasort($this->menu, $weightSorter);

    foreach ($this->menu as $group => &$groupContents) {
      usort($groupContents['items'], $weightSorter);
    }
  }

  /**
   * Adds miscellaneous buttons
   *
   * @return  void
   */
  private function addMiscellaneousButtons()
  {
    $pathFromDocRoot = Utility::removeDocRootFromPath($this->filePath);
    $query = $this->queryParams;

    if (PermissionsManager::userCanViewRevisions($this->getLoggedInUsername(), $pathFromDocRoot)) {
      $params = $query;
      self::removeConcertQueryParams($params);
      $params['concert'] = 'revisions';
      $item = [
        'text'     => 'View Page Revisions',
        'url'      => (new GACString($pathFromDocRoot))->addQueryString($params)->buildUrl()->getValue(),
        'thickbox' => false,
      ];

      $this->addMenuItem($item);
    }


    if (isset($query['concert'])) {
      // add quit button
      self::removeConcertQueryParams($query);

      $item = [
        'text'     => 'Quit Concert',
        'classes'  => 'quitConcert',
        'url'      => (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
        'thickbox' => false,
      ];

      $this->addMenuItem($item, null, 50);
    }

    $item = [
      'text'     => 'Recent Activity',
      'url'      => $this->buildUrl('recentActivity'),
      'thickbox' => true,
    ];
    $this->addMenuItem($item, null, 49);

    $item = [
      'text'     => 'Concert Basics',
      'url'      => 'https://gustavus.edu/gts/Concert',
      'newTab'   => true,
      'thickbox' => false,
    ];
    $this->addMenuItem($item, 'help', 1);

    $item = [
      'text'     => 'Submit an Issue',
      'url'      => 'mailto:web+concertFeedback@gustavus.edu?subject=Concert%20Feedback',
      'thickbox' => false,
    ];
    $this->addMenuItem($item, 'help', 2);

    $item = [
      'text'     => 'Dashboard',
      'url'      => $this->buildUrl('dashboard'),
      'newTab'   => true,
      'thickbox' => false,
    ];
    $this->addMenuItem($item, 'help', 3);

    if (PermissionsManager::userCanViewSiteStructure($this->getLoggedInUsername(), $pathFromDocRoot)) {
      // add the option for viewing the site structure
      $item = [
        'text'         => 'View Files',
        'url'          => $this->buildUrl('siteStructure'),
        'thickbox'     => true,
        'thickboxData' => ['height' => '400px', 'width' => '400px'],
      ];
      $this->addMenuItem($item, 'file', 2);
    }

    if (self::userIsEditing() && (PermissionsManager::isUserAdmin($this->getLoggedInUsername()) || PermissionsManager::isUserSuperUser($this->getLoggedInUsername()))) {
      $query = $this->queryParams;
      if (isset($query['showUnMatchedTags'])) {
        unset($query['showUnMatchedTags']);
        $item = [
          'text'     => 'Hide unmatched tags',
          'url'      => (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
          'thickbox' => false,
        ];
        $this->addMenuItem($item, 'help', 5);
      } else {
        // add a button to show un matched tags
        $query['showUnMatchedTags'] = 'true';
        $item = [
          'text'     => 'Show unmatched tags',
          'url'      => (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
          'thickbox' => false,
        ];
        $this->addMenuItem($item, 'help', 5);
      }
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
    if (!$this->blockEditing && $this->userIsViewingPublicDraft(Utility::removeDocRootFromPath($this->filePath))) {
      $draftName = self::guessDraftName($this->filePath);
      $draft = $this->getFileManager()->getDraft($draftName);
      if (!$this->blockEditing && PermissionsManager::userCanEditDraft($this->getLoggedInUsername(), $draft)) {

        if (self::isRequestFromConcertRoot($this->filePath)) {
          $url = $this->buildUrl('editDraft', ['draftName' => $draft['draftFilename']]);
        } else {
          $query = $this->queryParams;
          self::removeConcertQueryParams($query);
          $query['concert'] = 'editDraft';
          $query['concertDraft'] = $draft['draftFilename'];
          $url = (new GACString(Utility::removeDocRootFromPath($this->filePath)))->addQueryString($query)->buildUrl()->getValue();
        }

        $item = [
          'text'     => 'Edit Draft',
          'url'      => $url,
          'classes'  => 'blue',
          'thickbox' => false,
        ];

        $this->addMenuItem($item, 'drafts');

        // now add this to our actionButtons
        $this->addMenuItem($item, 'actionButtons');

        if (file_exists($this->filePath)) {
          $item = [
            'text'     => 'View Current Page',
            'url'      => Utility::removeDocRootFromPath($this->filePath),
            'classes'  => 'secondary',
            'extraHTML'  => 'target="_blank"',
          ];
          $this->addMenuItem($item, 'actionButtons', 5);
        }
      }
    } else if ($this->userIsEditingPublicDraft(Utility::removeDocRootFromPath($this->filePath))) {
      $draftName = self::guessDraftName($this->filePath);
      $draft = $this->getFileManager()->getDraft($draftName);

      if (self::isRequestFromConcertRoot($this->filePath)) {
        $url = $this->buildUrl('drafts', ['draftName' => $draft['draftFilename']]) . '?draftAction=stopEditing';
      } else {
        $query = $this->queryParams;
        self::removeConcertQueryParams($query);
        if (isset($this->queryParams['concertDraft'])) {
          $query['concertDraft'] = $this->queryParams['concertDraft'];
        }
        $query['concert'] = 'viewDraft';
        $query['draftAction'] = 'stopEditing';
        $url = (new GACString(Utility::removeDocRootFromPath($this->filePath)))->addQueryString($query)->buildUrl()->getValue();
      }

      $item = [
        'text'     => 'Stop Editing Draft',
        'url'      => $url,
        'classes'  => 'red',
        'thickbox' => false,
      ];

      $this->addMenuItem($item, 'drafts');
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
      if (self::userIsAddingUsersToDraft(Utility::removeDocRootFromPath($this->filePath))) {
        if (self::isRequestFromConcertRoot($this->filePath)) {
          $url = $this->buildUrl('drafts', ['draftName' => $draft['draftFilename']]);
        } else {
          $query = $this->queryParams;
          self::removeConcertQueryParams($query);
          $query['concert']      = 'viewDraft';
          $query['concertDraft'] = $draft['draftFilename'];
          if (self::isSiteNavRequest()) {
            $query['concertAction'] = 'siteNav';
          }
          $url = (new GACString(Utility::removeDocRootFromPath($this->filePath)))->addQueryString($query)->buildUrl()->getValue();
        }
        $item = [
          'text'     => 'View Draft',
          'url'      => $url,
          'thickbox' => false,
        ];

        $this->addMenuItem($item, 'drafts');
      } else if (!self::isSiteNavRequest()) {
        if (self::isRequestFromConcertRoot($this->filePath)) {
          $url = $this->buildUrl('addUsersToDraft', ['draftName' => $draft['draftFilename']]);
        } else {
          $query = $this->queryParams;
          self::removeConcertQueryParams($query);
          $query['concert'] = 'addUsers';
          $query['concertDraft'] = $draft['draftFilename'];
          $url = (new GACString(Utility::removeDocRootFromPath($this->filePath)))->addQueryString($query)->buildUrl()->getValue();
        }
        $item = [
          'text'         => 'Add Collaborators to Your Draft',
          'url'          => $url,
          'classes'      => 'green',
          'thickboxData' => ['height' => '400px'],
        ];

        $this->addMenuItem($item, 'drafts');
      }
    }

    $drafts = $this->getFileManager()->findDraftsForCurrentUser();

    if (!empty($drafts)) {
      $query = $this->queryParams;
      self::removeConcertQueryParams($query);
      $query['concert'] = 'viewDraft';

      $pathFromDocRoot = Utility::removeDocRootFromPath($this->filePath);

      $item = [
        'text' => 'View All Drafts',
        'url'  => (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
      ];

      $this->addMenuItem($item, 'drafts');
    }
  }

  /**
   * Adds buttons for editing actions
   *
   * @return  void
   */
  private function addEditButtons()
  {
    $pathFromDocRoot = Utility::removeDocRootFromPath($this->filePath);
    $query = $this->queryParams;
    self::removeConcertQueryParams($query);

    if (PermissionsManager::userCanCreatePageInSite($this->getLoggedInUsername(), $pathFromDocRoot)) {
      $item = [
        'text'          => 'Create New Page',
        'url'           => $this->buildUrl('newPageMenu'),
        'thickbox'      => true,
        'thickboxData' => ['height' => '400px', 'trapfocus' => 'false'],
      ];

      $this->addMenuItem($item, null, 20);
    }

    if (!self::isSiteNavRequest() && file_exists($this->filePath) && PermissionsManager::userCanDeletePage($this->getLoggedInUsername(), $pathFromDocRoot)) {
      $query['concert'] = 'delete';
      $item = [
        'text'     => 'Delete Page',
        'url'      => (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
        'thickbox' => true,
        'classes'  => 'red',
      ];

      $this->addMenuItem($item, null, 10);
    }

    if (!self::isSiteNavRequest() && (self::userIsEditing() || self::userIsSaving())) {
      $query['concert'] = 'stopEditing';
      $item = [
        'text'     => 'Stop Editing',
        'url'      => (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
        'thickbox' => false,
        'classes'  => 'red',
      ];

      $this->addMenuItem($item);
    } else {
      if ($this->blockEditing) {
        return;
      }

      $draft = $this->getFileManager()->getDraftForUser($this->getLoggedInUsername());

      if (!self::isSiteNavRequest() && !empty($draft) && PermissionsManager::userOwnsDraft($this->getLoggedInUsername(), $draft)) {
        if (self::isRequestFromConcertRoot($this->filePath)) {
          $url = $this->buildUrl('editDraft', ['draftName' => $draft['draftFilename']]);
        } else {
          $query = $this->queryParams;
          self::removeConcertQueryParams($query);
          $query['concert'] = 'edit';
          $url = (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue();
        }
        $item = [
          'text'     => 'Continue Draft',
          'url'      => $url,
          'thickbox' => false,
        ];

        $this->addMenuItem($item);
        if (!self::userIsViewingPublicDraft($this->filePath) && !self::userIsEditingDraft()) {
          // now add this to our actionButtons if they aren't viewing a public draft or editing a draft.
          $this->addMenuItem($item, 'actionButtons');
        }
      } else {
        $query['concert'] = 'edit';
        $item = [
          'text'     => 'Edit Page',
          'url'      => (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
          'thickbox' => false,
          'classes'  => 'blue',
        ];

        if (PermissionsManager::userCanEditFile($this->getLoggedInUsername(), $pathFromDocRoot)) {
          $this->addMenuItem($item);
          $buttonDisabled = false;
        } else {
          $buttonDisabled = true;
        }
        if (!(self::isSiteNavRequest() && self::userIsEditing()) && !self::userIsViewingDraft($this->filePath) && !self::userIsEditingDraft()) {
          // now add this to our actionButtons if they aren't viewing a public draft.
          if ($buttonDisabled) {
            $item['classes'] = 'disabled';
          }
          $this->addMenuItem($item, 'actionButtons');
        }
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
    $pathFromDocRoot = Utility::removeDocRootFromPath($this->filePath);

    $siteNav = self::getSiteNavForFile($this->filePath);
    $siteNavFromDocRoot = Utility::removeDocRootFromPath($siteNav);

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

    if (self::isSiteNavRequest() && (self::userIsEditing() || self::userIsCreatingSiteNav())) {
      $query = $this->queryParams;
      self::removeConcertQueryParams($query);
      $query['concert']       = 'stopEditingSiteNav';
      $url = (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue();
      $item = [
        'text'     => 'Stop Editing Menu',
        'url'      => $url,
        'thickbox' => false,
      ];

      $this->addMenuItem($item, 'menu', 20);
      // disabled drafts for siteNavs
      // } else if (($draft = $fm->getDraftForUser($this->getLoggedInUsername())) !== false) {
      //   // the user has a draft of the site nav they can continue editing.
      //   $query = $this->queryParams;
      //   $query['concert'] = 'edit';
      //   $query['concertAction'] = 'siteNav';
      //   $url = (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue();
      //   $item = [
      //     'text'     => 'Continue draft',
      //     'url'      => $url,
      //     'thickbox' => false,
      //   ];

      //   $this->addMenuItem($item, 'menu', 20);
    }

    if (!self::isGlobalNav($siteNav) && PermissionsManager::userCanEditSiteNav($this->getLoggedInUsername(), $siteNavFromDocRoot) && !((self::isSiteNavRequest() && self::userIsEditing()) || self::userIsCreatingSiteNav())) {
      // the site nav exists in the current site.
      //
      // give them the option to edit the current site nav or the inherited nav.
      $query = $this->queryParams;
      self::removeConcertQueryParams($query);
      $query['concert']       = 'edit';
      $query['concertAction'] = 'siteNav';
      $url = (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue();

      $item = [
        'text'     => $isInheritedNav ? 'Edit Inherited Menu' : 'Edit Menu',
        'url'      => $url,
        'thickbox' => false,
      ];

      if (self::isSiteNavShared($siteNav)) {
        // we need to verify that they know this is a shared site nav.
        $html = $this->renderView('confirmEditSharedSiteNav.html.twig', ['message' => Utility::buildSharedSiteNavNote(dirname($siteNav), false), 'editUrl' => $url]);
        $html = rawurlencode($html);
        $item['thickbox'] = true;
        $item['thickboxData'] = ['html' => $html];
      }

      $this->addMenuItem($item, 'menu', 20);
    }

    if (!((self::isSiteNavRequest() && self::userIsEditing()) || self::userIsCreatingSiteNav()) && $isInheritedNav) {
      $query = $this->queryParams;
      self::removeConcertQueryParams($query);
      $query['concert']       = 'createSiteNav';
      $query['concertAction'] = 'siteNav';
      $url = (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue();
      $item = [
        'text'     => 'Create Menu',
        'url'      => $url,
        'thickbox' => false,
      ];

      if (self::isSiteNavShared($siteNav)) {
        // we need to verify that they know this is a shared site nav.
        $html = $this->renderView('confirmEditSharedSiteNav.html.twig', ['message' => Utility::buildSharedSiteNavNote(dirname($currentDirSiteNavFile), true), 'editUrl' => $url]);
        $html = rawurlencode($html);
        $item['thickbox'] = true;
        $item['thickboxData'] = ['html' => $html];
      }

      $this->addMenuItem($item, 'menu', 20);
    }

    if (!self::isGlobalNav($siteNav) && PermissionsManager::userCanViewRevisions($this->getLoggedInUsername(), $siteNavFromDocRoot) &&(!self::isRevisionRequest() || !self::isSiteNavRequest())) {
      $query = $this->queryParams;
      self::removeConcertQueryParams($query);
      $query['concert']         = 'revisions';
      $query['concertAction']   = 'siteNav';
      $item = [
        'text'     => $isInheritedNav ? 'View Inherited Menu Revisions' : 'View Menu Revisions',
        'url'      => (new GACString($pathFromDocRoot))->addQueryString($query),
        'thickbox' => false,
      ];
      $this->addMenuItem($item, 'menu', 20);
    }



    // disabled since drafts are disabled
    // if ($isParentNav) {
    //   // the site nav lives outside of our current site. Lets see if they have a draft for their current file
    //   $fm = new FileManager($this->getLoggedInUsername(), dirname($this->filePath) . DIRECTORY_SEPARATOR . 'site_nav.php', null, $this->getDB());
    // } else {
    //   // the site nav lives inside our current site.
    //   $fm = new FileManager($this->getLoggedInUsername(), $siteNav, null, $this->getDB());
    // }
    // $query = $this->queryParams;

    // disabled drafts for siteNavs
    // if ($this->isSiteNavRequest()) {
    //   $drafts = $fm->findDraftsForCurrentUser();

    //   if (!empty($drafts)) {
    //     $query = $this->queryParams;
    //     $query['concert'] = 'viewDraft';
    //     if (self::isSiteNavRequest()) {
    //       $query['concertAction'] = 'siteNav';
    //       $text = 'View all local menu drafts';
    //     } else {
    //       $text = 'View all drafts';
    //     }

    //     unset($query['concertDraft']);
    //     $pathFromDocRoot = Utility::removeDocRootFromPath($this->filePath);

    //     $item = [
    //       'text' => 'View all drafts',
    //       'url'  => (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
    //     ];

    //     $this->addMenuItem($item, 'menu', 20);
    //   }
    // }


    // Disabled. We aren't allowing people to delete site navs
    // if ($siteBase . 'site_nav.php' !== $siteNavFromDocRoot && file_exists($siteNav) && $userCanEditSiteNav && PermissionsManager::userCanDeletePage($this->getLoggedInUsername(), $siteNavFromDocRoot)) {
    //   // the site nav isn't the base nav for this site, and the user has permissions, so give them the option
    //   $query = $this->queryParams;
    //   $query['concert'] = 'delete';
    //   $query['concertAction'] = 'siteNav';
    //   $item = [
    //     'text'     => 'Delete Local menu',
    //     'url'      => (new GACString($pathFromDocRoot))->addQueryString($query)->buildUrl()->getValue(),
    //     'thickbox' => true,
    //     'classes'  => 'red',
    //   ];

    //   $this->addMenuItem($item, 'menu', 20);
    // }
  }

  /**
   * Checks whether the specified file is a valid type to display in the site structure
   *
   * @param  string $file Name of the file to check
   * @param  boolean $isDir Whether this file is a directory or not
   * @return boolean
   */
  private function fileCanBeShownInStructure($file, $isDir)
  {
    if (preg_match('`^\.`', $file)) {
      // we don't want to show any files that start with a dot
      return false;
    }
    if ($isDir) {
      // our dir won't have an extension
      return true;
    }
    // array of extensions we will show to people
    $whiteListExts = [
      'doc','docx','rtf', 'pdf', 'xls', 'xlsx', 'csv','fla','ppt','pptx', // files
      'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'svg', // images
      'mov', 'mpeg', 'm4v', 'mp4', 'avi', 'mpg', 'wma', 'flv', 'webm', // videos
      'mp3', 'm4a', 'ac3', 'aiff', 'mid', 'ogg', 'wav', // music
      'php'
    ];

    $ext = preg_replace('/^.*\./', '', $file);
    return in_array($ext, $whiteListExts);
  }

  /**
   * Renders the html for our file tree plugin
   *
   * @param  boolean $forSrcFile Whether this is a tree for the src file or not.
   * @param  boolean $forSiteStructure Whether this is a tree for displaying the site structure.
   * @return string
   */
  private function renderFileTree($forSrcFile = false, $forSiteStructure = false)
  {
    $this->analyzeReferer();

    if (isset($_GET['dir'])) {
      $dir = urldecode($_GET['dir']);
    } else {
      $dir = dirname(Utility::removeDocRootFromPath($this->filePath));
    }

    $root = $_SERVER['DOCUMENT_ROOT'];
    $absDir = $root . $dir;
    $return = '';

    $newFileHTML = sprintf('<li class="file ext_php"><a href="#" rel="%s">+newFile</a></li>', htmlentities($dir . 'concertNewFile'));

    $newFolderHTML = sprintf('<li class="directory collapsed"><a href="#" rel="%s">+newFolder</a></li>', htmlentities($dir . 'concertNewFolder/'));

    if (file_exists($absDir)) {
      $foundFiles = array_filter(scandir($absDir), function($var) {
        return $var !== '.' && $var !== '..';
      });
      $files = [];
      foreach ($foundFiles as $file) {
        if (((substr($file, strlen($file) - 4) === '.php' && strpos($file, 'site_nav.php') === false) || is_dir($absDir . $file) && !$forSiteStructure) || ($forSiteStructure && $this->fileCanBeShownInStructure($file, is_dir($absDir . $file)))) {
          $files[] = $file;
        }
      }

      if (!$forSiteStructure || count($files) > 0) {
        $return .= '<ul class="jqueryFileTree" style="display: none;">';
        if ($forSrcFile && !isset($_GET['excludeTemplates']) && !$forSiteStructure) {
          // we want our default templates on top
          foreach (Config::$templates as $templateIdentifier => $templateProperties) {
            $return .= sprintf('<li class="file ext_html"><a href="#" rel="%s" class="selected">%s Template</a></li>', $templateIdentifier, (new GACString($templateProperties['name']))->titleCase()->getValue());
          }
        }
        // All dirs
        foreach ($files as $file) {
          if (file_exists($absDir . $file) && $file != '.' && $file != '..' && is_dir($absDir . $file) && !in_array($file, Config::$fileTreeExcludedFolders)) {
            if ($forSrcFile && count(scandir($absDir . $file)) <= 2) {
              continue;
            }
            $return .= sprintf('<li class="directory collapsed"><a href="#" rel="%s">%s</a></li>', htmlentities($dir . $file . DIRECTORY_SEPARATOR), htmlentities($file));
          }
        }
        // All files
        foreach ($files as $file) {
          if (file_exists($absDir . $file) && $file != '.' && $file != '..' && !is_dir($absDir . $file)) {
            $ext = preg_replace('/^.*\./', '', $file);
            if ($forSiteStructure) {
              $path = htmlentities($dir . $file);
              if ($ext === 'php' && $file !== 'site_nav.php') {
                // we can also add a delete option, and add concert to the url
                $deletePath = $path . '?concert=delete';
                $deleteButton = sprintf('<a href="" rel="%s" class="deleteFileButton" title="Delete %s">x</a>', $deletePath, $path);

                $path .= '?concert';
              } else {
                $deleteButton = '';
              }
              $return .= sprintf('<li class="file ext_%s"><a href="#" rel="%s">%s</a>%s</li>', $ext, $path, htmlentities($file), $deleteButton);
            } else if ($forSrcFile) {
              $return .= sprintf('<li class="file ext_%s"><a href="#" rel="%s">%s</a></li>', $ext, htmlentities($dir . $file), htmlentities($file));
            } else {
              $return .= sprintf('<li class="file ext_%s disabled">%s</li>', $ext, htmlentities($file));
            }
          }
        }
        if (!$forSrcFile && !$forSiteStructure) {
          $return .= $newFileHTML;
          $return .= $newFolderHTML;
        }
        $return .= '</ul>';
      } else if ($forSiteStructure) {
        $return .= '<ul class="jqueryFileTree"><li class="emptyMessage">No contents are available</li></ul>';
      }
    } else {
      // the directory doesn't exist.
      if ($forSiteStructure) {
        $return .= '<ul class="jqueryFileTree"><li class="emptyMessage">No contents are available</li></ul>';
      } else if (!$forSrcFile) {
        // We want to give them the option to create a new index.php file.
        $newIndexFileHTML = sprintf('<li class="file ext_php"><a href="#" rel="%s" class="selected">index.php</a></li>', htmlentities($dir . 'index.php'));
        $return = sprintf('<ul class="jqueryFileTree" style="display: none;">%s%s%s</ul>', $newIndexFileHTML, $newFileHTML, $newFolderHTML);
      } else if (!isset($_GET['excludeTemplates'])) {
        // We only want them to be able to copy a template since no files exist in the directory.
        $return .= '<ul class="jqueryFileTree" style="display: none;">';
        foreach (Config::$templates as $templateIdentifier => $templateProperties) {
          $return .= sprintf('<li class="file ext_html"><a href="#" rel="%s" class="selected">%s Template</a></li>', $templateIdentifier, (new GACString($templateProperties['name']))->titleCase()->getValue());
        }
        $return .= '</ul>';
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
    if (!empty($params) && isset($params['fileTree']) && ($params['fileTree'] === 'toFile' || $params['fileTree'] === 'fromFile')) {
      return $this->renderFileTree($params['fileTree'] === 'fromFile');
    }

    if (self::isBareboneRequest()) {
      $this->analyzeReferer();
    } else {
      $this->analyzeReferer(false);
    }

    $siteBase = PermissionsManager::findUsersSiteForFile($this->getLoggedInUsername(), Utility::removeDocRootFromPath($this->filePath));
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
   * Renders the site structure
   *
   * @param  array $params array of params from router
   * @return string
   */
  public function renderSiteStructure($params = null)
  {
    if (!empty($params) && isset($params['files']) && $params['files'] === 'files') {
      return $this->renderFileTree(false, true);
    }

    if (self::isBareboneRequest()) {
      $this->analyzeReferer();
    } else {
      $this->analyzeReferer(false);
    }

    $siteBase = PermissionsManager::findUsersSiteForFile($this->getLoggedInUsername(), Utility::removeDocRootFromPath($this->filePath));
    if (empty($siteBase)) {
      // user doesn't have access to this site.
      if (self::isBareboneRequest()) {
        return false;
      } else {
        return $this->renderErrorPage(Config::NO_SITE_ACCESS_MESSAGE);
      }
    }

    $view = $this->renderView('siteStructure.html.twig', ['site' => $siteBase, 'cssVersion' => Config::CSS_VERSION]);

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
        $referer = rawurldecode($_SERVER['REQUEST_URI']);
      } else {
        $referer = null;
      }
    }

    $parts = parse_url($referer);

    if (isset($parts['query'])) {
      $this->queryParams = (new GACString($parts['query']))->splitQueryString()->getValue();
    } else {
      $this->queryParams = [];
    }

    // $parts['path'] will have a leading slash. We want to remove the trailing slash from the doc root
    $this->filePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $parts['path'];
    if (strpos($this->filePath, '.php') === false) {
      $filePath = str_replace('//', '/', $this->filePath . DIRECTORY_SEPARATOR . 'index.php');
      if (file_exists($filePath)) {
        // we don't want to add index.php to the path if it doesn't exist otherwise they could get an error page when clicking on some menu options.
        // ie. Editing the site nav within a Concourse app. Usually these apps don't have index.php, so adding index.php to it breaks the request.
        $this->filePath = $filePath;
      }
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