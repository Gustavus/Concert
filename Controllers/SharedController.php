<?php
/**
 * @package Concert
 * @subpackage Controllers
 * @author  Billy Visto
 */
namespace Gustavus\Concert\Controllers;

use Gustavus\Concourse\Controller as ConcourseController,
  Gustavus\Resources\Resource,
  Gustavus\Concert\Config,
  Gustavus\Concert\FileManager,
  Gustavus\Concert\PermissionsManager,
  Gustavus\Extensibility\Filters,
  Gustavus\Concourse\RoutingUtil,
  Campus,
  Gustavus\Utility\File,
  Gustavus\Utility\Number,
  Gustavus\Utility\Set;

/**
 * Controller to handle shared functionality for other controllers
 *
 * @package Concert
 * @subpackage Controllers
 * @author  Billy Visto
 *
 * @todo  write tests
 */
class SharedController extends ConcourseController
{
  /**
   * @var string $applicationTitle
   */
  protected $applicationTitle = 'Concert';

  /**
   * Flag to determine if we have already added our mosh menu
   *
   * @var boolean
   */
  protected static $moshMenuAdded = false;

  /**
   * Resources that have been added to the page so far
   *
   * @var array
   */
  private static $addedResources = [];

  /**
   * Message to display to the user
   *
   * @var string
   */
  private static $message = '';

  /**
   * Array of visible buttons to show when inserting editing resources.
   *   Note: this will override anything passed into insertEditingResources().
   *
   * @var array
   */
  protected static $visibleEditingButtons = null;

  /**
   * {@inheritdoc}
   */
  protected function getLocalNavigation()
  {
    return $this->localNavigation;
  }

  /**
   * {@inheritdoc}
   */
  protected function getRoutingConfiguration()
  {
    return Config::ROUTING_LOCATION;
  }

  /**
   * Gets Doctrine's DBAL connection for Concert
   *
   * @return \Doctrine\DBAL\Connection
   */
  protected function getDB()
  {
    return $this->getDBAL(Config::DB);
  }

  /**
   * Calls parent's renderView throwing in the full path to the views
   *
   * {@inheritdoc}
   */
  protected function renderView($template, array $args = array(), $modifyEnvironment = true)
  {
    return parent::renderView('/cis/lib/Gustavus/Concert/Views/' . $template, $args, $modifyEnvironment);
  }

  // /**
  //  * Adds JS to page
  //  *
  //  * @todo  remove this. Or convert to tinymce.
  //  *
  //  * @return  void
  //  */
  // protected function addJS()
  // {
  //   $this->addJavascripts(sprintf(
  //       '<script type="text/javascript">
  //         var CKEDITOR_BASEPATH = \'/js/ckeditor/\';
  //         Modernizr.load([
  //           "%s",
  //         ]);
  //       </script>',
  //       Resource::renderResource([['path' => '/js/ckeditor/ckeditor.js'], ['path' => '/js/ckeditor/adapters/jquery.js'], ['path' => Config::WEB_DIR . '/js/concert.js', 'version' => Config::JS_VERSION]])
  //   ));
  // }

  // /**
  //  * Adds CSS to page
  //  *
  //  * @return  void
  //  */
  // protected function addCSS()
  // {
  //   $this->addStylesheets(
  //       sprintf(
  //           '<link rel="stylesheet" type="text/css" href="%s" />',
  //           Resource::renderCSS(['path' => Config::WEB_DIR . '/css/concert.css', 'version' => Config::CSS_VERSION])
  //       )
  //   );
  // }

  /**
   * Overrides visible buttons inserted by insertEditingResources
   *
   * @param  array $visibleButtons Array of buttons that we want to be visible
   * @return void
   */
  protected static function overrideVisibleEditingButtons($visibleButtons)
  {
    self::$visibleEditingButtons = $visibleButtons;
  }

  /**
   * Injects resources required for editing pages
   *
   * @param string $filePath FilePath of the file we are editing
   * @param  array $visibleButtons Array of buttons that we want to display
   * @param  array $additionalButtons Array of arrays of additional buttons to add. Sub-arrays must have indexes of 'url', 'id', and 'text'.
   * @return void
   */
  protected function insertEditingResources($filePath, $redirectPath = null, array $visibleButtons = null, array $additionalButtons = null)
  {
    if (empty($redirectPath)) {
      $redirectPath = $filePath;
    }
    $redirectPath = Config::removeDocRootFromPath($redirectPath);

    if (!empty(self::$visibleEditingButtons)) {
      $visibleButtons = self::$visibleEditingButtons;
      // reset this back to null
      self::$visibleEditingButtons = null;
    }

    $resources = [
      'js' => [
        '/js/jquery/ui/current/minified/jquery.ui.dialog.min.js',
        '/js/jquery/ui/current/minified/jquery.ui.button.min.js',
        Resource::renderResource(['path' => Config::WEB_DIR . '/js/tinymce/tinymce.min.js', 'version' => 0]),
        Resource::renderResource(['urlutil', ['path' => Config::WEB_DIR . '/js/concert.js', 'version' => Config::JS_VERSION]]),
      ],
    ];

    Filters::add('scripts', function($content) use ($filePath, $redirectPath, $resources) {
        $script = sprintf(
            '<script type="text/javascript">
              Modernizr.load({
                load: [
                  "%s"
                ],
                complete: function() {
                  Gustavus.Concert.filePath = "%s"
                  Gustavus.Concert.redirectPath = "%s"
                }
              });
            </script>',
            implode('","', $resources['js']),
            Config::removeDocRootFromPath($filePath),
            $redirectPath
        );
        return $content . $script;
    }, 11);

    self::markResourcesAdded($resources['js']);


    $cssResource = Resource::renderCSS(['path' => Config::WEB_DIR . '/css/concert.css', 'version' => Config::CSS_VERSION]);
    if (!self::isResourceAdded($cssResource, 'css')) {
      Filters::add('head', function($content) use ($cssResource) {
          $css = sprintf(
              '<link rel="stylesheet" type="text/css" href="%s" />',
              $cssResource
          );
          return $content . $css;
      }, 11);

      self::markResourcesAdded([$cssResource], 'css');
    }

    $userCanPublishFile = PermissionsManager::userCanPublishFile($this->getLoggedInUsername(), Config::removeDocRootFromPath($filePath));

    if ($visibleButtons === null) {
      $visibleButtons = Config::$defaultEditingButtons;
    }

    Filters::add('body', function($content) use ($userCanPublishFile, $visibleButtons, $additionalButtons) {
      return $content . $this->renderView(
          'actionButtons.html.twig',
          [
            'userCanPublishFile' => $userCanPublishFile,
            'visibleButtons'     => $visibleButtons,
            'additionalButtons'  => $additionalButtons,
          ]
      );
    }, 9999);
  }

  /**
   * Marks a resource as added
   *
   * @param  array $resources Array of resources that have been added
   * @param  string $type     Type of resources we are marking
   * @return void
   */
  private static function markResourcesAdded($resources, $type = 'js')
  {
    if (!isset(self::$addedResources[$type]) || !is_array(self::$addedResources[$type])) {
      self::$addedResources[$type] = $resources;
    } else {
      self::$addedResources[$type] = array_merge(self::$addedResources[$type], $resources);
    }
  }

  /**
   * Checks to see if the current resource has been added or not
   *
   * @param  string  $resource Resource to check
   * @param  string  $type     Type of resource to check
   * @return boolean
   */
  private static function isResourceAdded($resource, $type = 'js')
  {
    return (isset(self::$addedResources[$type]) && in_array($resource, self::$addedResources[$type]));
  }

  /**
   * Displays the page.
   *   It either calls loadAndEvaluate from File or $this->renderPage()
   *
   * @param  string  $page     Page to evaluate. Ignored if not evaluating.
   * @param  boolean $evaluate Whether to evaluate this page or not.
   * @return string
   */
  protected function displayPage($page = null, $evaluate = false)
  {
    if (!$evaluate) {
      return $this->renderPage();
    }
    return (new File($page))->loadAndEvaluate();
  }

  /**
   * Adds action buttons for moshing
   *
   * @param  string $filePath Path to the file we are moshing for
   * @return  void
   *
   * @todo  do we need this or want it?
   */
  // protected function addMoshingActions($filePath)
  // {
  //   if (self::userIsEditing() || self::userIsSaving()) {
  //     Filters::add('userBox', function($content) {
  //       // @todo make this remove concert stuff from the url
  //       return $content . '<a href="?concert=stopEditing" class="button red concertEditPage">Stop Editing</a>';
  //     });
  //   } else {
  //     Filters::add('userBox', function($content) {
  //       return $content . '<a href="?concert=edit" class="button red concertEditPage">Edit Page</a>';
  //     });
  //   }
  // }

  /**
   * Adds the menu to interact with Concert
   *
   * @return  void
   */
  protected function addMoshMenu()
  {
    if (!self::$moshMenuAdded) {
      // add messages to the menu
      Filters::add('scripts', function($content) {
        return $content . $this->renderView('messages.js.twig', ['messages' => $this->getConcertMessage()]);
      });

      $result = $this->forward('menus', ['forReferer' => false]);
      if (!empty($result)) {
        $cssResource = Resource::renderCSS(['path' => Config::WEB_DIR . '/css/concert.css', 'version' => Config::CSS_VERSION]);
        if (!self::isResourceAdded($cssResource, 'css')) {
          Filters::add('head', function($content) use ($cssResource) {
              $css = sprintf(
                  '<link rel="stylesheet" type="text/css" href="%s" />',
                  $cssResource
              );
              return $content . $css;
          }, 11);

          self::markResourcesAdded([$cssResource], 'css');
        }
        self::addTemplatePref(['globalNotice' => ['notice' => ['notice' => $result, 'dismissable' => false]]]);

        // disabled thickbox version.
        // Filters::add('userBox', function($content) {
        //   return sprintf('%s<a href="%s" class="button red concertMenu thickbox">Concert</a>', $content, $this->buildUrl('menus'));
        // });
      }
      self::$moshMenuAdded = true;
    }
  }

  private static function addTemplatePref($pref)
  {
    global $templatePreferences;
    if (!is_array($templatePreferences)) {
      $templatePreferences = [];
    }
    $templatePreferences = array_merge($templatePreferences, $pref);
  }

  /**
   * This forces our doc root so we aren't building urls to the error handler for creating new pages, etc.
   *
   * {@inheritdoc}
   */
  public function buildUrl($alias, array $parameters = array(), $baseDir = '', $fullUrl = false)
  {
    return parent::buildUrl($alias, $parameters, Config::WEB_DIR, $fullUrl);
  }

  /**
   * Gets the messages we have accumulated this request
   *
   * @return string
   */
  public function getConcertMessage()
  {
    return self::$message;
  }

  /**
   * Adds a message to the page
   *
   * @param string  $message Message to add
   * @param boolean $isError Whether it is an error message or not
   * @return  void
   */
  protected function addConcertMessage($message, $isError = false)
  {
    if (!empty($message)) {
      self::$message .= sprintf('<p class="%s">%s</p>', ($isError ? 'error' : 'message'), $message);
    }
  }

  /**
   * Sets a message for the page
   *
   * @param string  $message Message to set
   * @param boolean $isError Whether it is an error message or not
   * @return  void
   */
  protected function setConcertMessage($message, $isError = false)
  {
    if (!empty($message)) {
      $message = sprintf('<p class="%s">%s</p>', ($isError ? 'error' : 'message'), $message);
    }
    self::$message = $message;
  }

  /**
   * Sets $_GET to the specified array
   *   Re-marks as moshed if we have already moshed.
   *
   * @param  array $newGET array of GET parameters
   * @return void
   */
  protected static function setGET($newGET)
  {
    $markMoshed = self::alreadyMoshed();

    $_GET = $newGET;
    if ($markMoshed) {
      self::markMoshed();
    }
  }

  /**
   * Renders a message when someone cannot acquire a lock
   *
   * @param  string|FileManager $file Path to the file they couldn't get a lock for, or a FileManager representing the file.
   * @return string
   */
  protected function renderLockNotAcquiredMessage($file)
  {
    $message = Config::LOCK_NOT_ACQUIRED_MESSAGE;

    if ($file instanceof FileManager) {
      $fm = $file;
    } else {
      $fm = new FileManager($this->getLoggedInUsername(), $file, null, $this->getDB());
    }

    if (!$fm->userCanEditFile()) {
      $message .= ' ' . Config::NOT_ALLOWED_TO_EDIT_MESSAGE_FOR_LOCK;
    } else {

      $owner = $fm->getLockOwner();
      if ($owner && $owner !== $this->getLoggedInUsername()) {
        $peoplePuller = Campus::People($this->getApiKey());
        $peoplePuller->setUsername($owner);
        $person = $peoplePuller->current();
        if (is_object($person)) {
          $name = $person->getFullName();
        } else {
          $name = $owner;
        }

        $message = sprintf('%s %s currently holds the lock.', $message, $name);
        // lock couldn't be acquired due to lack of permissions or something else.
      }
    }

    return $message;
  }

  /**
   * Renders a message for alerting a user that another user has a draft open for this page
   *
   * @param  string|FileManager $file Path to the file or FileManager representing the file
   * @return string
   */
  protected function renderOpenDraftMessage($file)
  {
    if ($file instanceof FileManager) {
      $fm = $file;
    } else {
      $fm = new FileManager($this->getLoggedInUsername(), $file, null, $this->getDB());
    }

    $openDrafts = $fm->getDrafts();
    $draftCount = count($openDrafts);

    if ($draftCount === 1 && reset($openDrafts)['username'] === $this->getLoggedInUsername()) {
      return '';
    }

    $draftUsers = [];
    $forceHave = false;
    foreach ($openDrafts as $openDraft) {
      if ($openDraft['username'] !== $this->getLoggedInUsername()) {
        $peoplePuller = Campus::People($this->getApiKey());
        $peoplePuller->setUsername($openDraft['username']);
        $person = $peoplePuller->current();
        if (is_object($person)) {
          $name = $person->getFullName();
        } else {
          // user couldn't be found in the campus api.
          $name = $openDraft['username'];
        }
      } else {
        $forceHave = true;
        $name = count($draftUsers) === 0 ? 'You' : 'you';
      }
      $draftUsers[] = $name;
    }
    $verb = ($forceHave && $draftCount === 1) ? 'have' : (new Number($draftCount))->toQuantity('has', 'have');
    return sprintf('%s %s a draft open for this page.', (new Set($draftUsers))->toSentence(), $verb);
  }


  // Action checks


  /**
   * Checks to see if we have already moshed in this request or not
   *
   * @return boolean
   */
  protected static function alreadyMoshed()
  {
    return (isset($_GET['concertMoshed']) && $_GET['concertMoshed'] === 'true');
  }

  /**
   * Sets a variable saying that we have already moshed this request.
   *
   * @return void
   */
  protected static function markMoshed()
  {
    $_GET['concertMoshed'] = 'true';
  }

  /**
   * Checks to see if the user wants to edit the page
   *
   * @return boolean
   */
  protected static function userIsEditing()
  {
    return (isset($_GET['concert']) && $_GET['concert'] === 'edit');
  }

  /**
   * Checks to see if the user wants to edit the page
   *
   * @return boolean
   */
  protected static function userIsDoneEditing()
  {
    return (isset($_GET['concert']) && $_GET['concert'] === 'stopEditing');
  }

  /**
   * Checks to see if the user is currently saving an edit
   *
   * @return boolean
   */
  protected static function userIsSaving()
  {
    return (isset($_POST['concertAction']) && $_POST['concertAction'] === 'save');
  }

  /**
   * Checks to see if the user is saving a draft
   *
   * @return boolean
   */
  protected static function userIsSavingDraft()
  {
    return (self::userIsSavingPublicDraft() || self::userIsSavingPrivateDraft());
  }

  /**
   * Checks to see if the user is trying to save a public draft
   *
   * @return boolean
   */
  protected static function userIsSavingPublicDraft()
  {
    return (isset($_POST['saveAction']) && $_POST['saveAction'] === 'savePublicDraft');
  }

  /**
   * Checks to see if the user is trying to save a protected draft
   *
   * @return boolean
   */
  protected static function userIsSavingPrivateDraft()
  {
    return (isset($_POST['saveAction']) && $_POST['saveAction'] === 'savePrivateDraft');
  }

  /**
   * Checks to see if the user wants to view a draft
   *
   * @return boolean
   */
  protected static function userIsViewingDraft()
  {
    return (isset($_GET['concert']) && $_GET['concert'] === 'viewDraft');
  }

  /**
   * Checks to see if the user wants to delete a draft
   *
   * @return boolean
   */
  protected static function userIsDeletingDraft()
  {
    return (isset($_POST['saveAction']) && $_POST['saveAction'] === 'discardDraft');
  }

  /**
   * Checks to see if the user is editing a draft
   *
   * @return boolean
   */
  protected static function userIsEditingDraft()
  {
    return (isset($_GET['concert']) && $_GET['concert'] === 'editDraft');
  }

  /**
   * Checks to see if the user is editing the site nav
   *
   * @return boolean
   */
  protected static function userIsCreatingSiteNav()
  {
    return (isset($_GET['concert']) && $_GET['concert'] === 'createSiteNav');
  }

  /**
   * Checks to see if the user is requesting anything for a site nav
   * @return boolean
   */
  protected static function isSiteNavRequest()
  {
    if (isset($_POST['filePath']) && (strpos($_POST['filePath'], 'site_nav.php') !== false || strpos($_POST['filePath'], 'concertAction=siteNav') !== false)) {
      return true;
    }
    return ((isset($_GET['concert']) && $_GET['concert'] === 'siteNav') || (isset($_GET['concertAction']) && $_GET['concertAction'] === 'siteNav'));
  }

  /**
   * Checks to see if the current site nav is the global site nav or not
   * @param  string $siteNav Site nav to check
   * @return boolean
   */
  protected static function isGlobalNav($siteNav)
  {
    return (Config::addDocRootToPath($siteNav) === Config::addDocRootToPath('site_nav.php'));
  }

  /**
   * Checks to see if the user is wanting to do things with drafts
   *
   * @return boolean
   */
  protected static function isDraftRequest()
  {
    return (self::userIsViewingDraft() || self::userIsSavingDraft() || self::userIsDeletingDraft() || self::userIsEditingDraft() || self::userIsAddingUsersToDraft());
  }

  /**
   * Checks to see if the user wants to release their lock
   *
   * @return boolean
   */
  protected static function isRequestingLockRelease()
  {
    return (isset($_POST['concertAction']) && $_POST['concertAction'] === 'stopEditing');
  }

  /**
   * Checks to see if the user is requesting to query something
   *
   * @return boolean
   */
  protected static function isRequestingQuery()
  {
    return (isset($_POST['concertAction']) && $_POST['concertAction'] === 'query');
  }

  /**
   * Checks to see if the user is viewing a public draft from the specified requestURI
   *
   * @param  string $requestURI
   * @return boolean
   */
  protected function userIsViewingPublicDraft($requestURI)
  {
    if (strpos($requestURI, '?') !== false) {
      // we need to break the requestURI up
      $parts = parse_url($requestURI);
      if (isset($parts['path'])) {
        // we only need the path as we aren'd doing anything with query params here.
        $requestURI = $parts['path'];
      }
    }

    $viewPublicDraftUrl = $this->buildUrl('drafts', ['draft' => basename($requestURI)]);

    if (strpos($requestURI, $viewPublicDraftUrl) !== false) {
      // user is viewing a public draft from concert root
      return true;
    } else if (self::userIsViewingDraft()) {
      // we need to do some extra checks to see if the user is viewing a public draft.
      $draftName = self::getDraftFromRequest();

      if (empty($draftName)) {
        return false;
      }

      $fm = new FileManager($this->getLoggedInUsername(), $requestURI, null, $this->getDB());
      $draft = $fm->getDraft($draftName);

      return ($draft && $draft['type'] === Config::PUBLIC_DRAFT);
    }
    return false;
  }

  /**
   * Checks to see if the user is editing a public draft
   *
   * @param  string $requestURI The uri to the page the user is sitting at
   * @return boolean
   */
  protected function userIsEditingPublicDraft($requestURI)
  {
    if (strpos($requestURI, '?') !== false) {
      // we need to break the requestURI up
      $parts = parse_url($requestURI);
      if (isset($parts['path'])) {
        // we only need the path as we aren'd doing anything with query params here.
        $requestURI = $parts['path'];
      }
    }

    $editDraftUrl = $this->buildUrl('editDraft', ['draftName' => basename($requestURI)]);

    if (strpos($requestURI, $editDraftUrl) !== false) {
      return true;
    } else if (self::userIsEditingDraft()) {
      // we need to do some extra checks to see if the user is viewing a public draft.
      $draftName = self::getDraftFromRequest();

      if (empty($draftName)) {
        return false;
      }

      $fm = new FileManager($this->getLoggedInUsername(), $requestURI, null, $this->getDB());
      $draft = $fm->getDraft($draftName);

      return ($draft && $draft['type'] === Config::PUBLIC_DRAFT);
    }
    return false;
  }

  /**
   * Checks to see if the user is adding users to their public draft
   *
   * @param  string $requestURI
   * @return boolean
   */
  protected static function userIsAddingUsersToDraft($requestURI = null)
  {
    $addUsersToDraftUrl = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'addUsersToDraft', ['draftName' => self::guessDraftName($requestURI)], Config::WEB_DIR);

    return (($addUsersToDraftUrl === $requestURI) || (isset($_GET['concert']) && $_GET['concert'] === 'addUsers'));
  }

  /**
   * Checks to see if the user is trying to delete a file
   *
   * @return boolean
   */
  protected static function userIsDeleting()
  {
    return ((isset($_GET['concert']) && $_GET['concert'] === 'delete') || (isset($_POST['concertAction']) && $_POST['concertAction']) === 'delete');
  }

  /**
   * Checks to see if a revision request has been requested
   *
   * @return boolean
   */
  protected static function isRevisionRequest()
  {
    return (isset($_GET['concert']) && $_GET['concert'] === 'revisions');
  }

  /**
   * Checks to see if the request is coming from the concert root or not.
   *
   * @param  string $requestURI
   * @return boolean
   */
  protected static function isRequestFromConcertRoot($requestURI = null)
  {
    $concertRoot = Config::addDocRootToPath(Config::WEB_DIR);

    if ($requestURI === null) {
      $requestURI = $_SERVER['REQUEST_URI'];
    }
    $requestURI = Config::addDocRootToPath($requestURI);

    return (strpos($requestURI, $concertRoot) === 0);
  }

  /**
   * Checks to see if the request is a barebone request or not
   *
   * @return boolean
   */
  protected static function isBareboneRequest()
  {
    return isset($_GET['barebones']);
  }

  /**
   * Checks to see if the user is editing the site nav
   *
   * @return boolean
   */
  protected static function isForwardedFromSiteNav()
  {
    return (isset($_GET['forwardedFrom']) && $_GET['forwardedFrom'] === 'siteNav');
  }

  /**
   * Checks to see if we are forwarding internally
   *
   * @return boolean
   */
  protected static function isInternalForward()
  {
    return (self::isForwardedFromSiteNav());
  }


  // Functions to get things from the request


  /**
   * Gets the requested draft from the url
   *
   * @return string|null
   */
  protected static function getDraftFromRequest()
  {
    if (isset($_GET['concertDraft'])) {
      return $_GET['concertDraft'];
    }
    return null;
  }

  /**
   * Gets the draft name from the request
   *
   * @param  string $requestURI
   * @return string
   */
  protected static function guessDraftName($requestURI = null)
  {
    $draft = self::getDraftFromRequest();

    if (!$draft && $requestURI !== null) {
      $parts = parse_url($requestURI);
      $draft = basename($parts['path']);
    }

    if (!$draft && isset($_SERVER['REQUEST_URI'])) {
      $parts = parse_url($_SERVER['REQUEST_URI']);
      $draft = basename($parts['path']);
    }
    return $draft;
  }

  /**
   * Gets the query that is being requested from POST
   *
   * @return string|null String if the query request is found, null otherwise
   */
  protected static function getQueryFromRequest()
  {
    if (self::isRequestingQuery() && isset($_POST['query'])) {
      return $_POST['query'];
    }
    return null;
  }

  /**
   * Gets the path to redirect to if we want to change it.
   *
   * @return string|null Null if we couldn't find one
   */
  protected static function findRedirectPath()
  {
    if (self::isForwardedFromSiteNav()) {
      return $_SERVER['REQUEST_URI'];
    }
    return null;
  }

  /**
   * Searches for the best site nav for the specified file
   *
   * @param  string $filePath FilePath of the file to get the site nav for
   * @return string|boolean String on success, false on failure
   */
  protected static function getSiteNavForFile($filePath)
  {
    if (strpos($filePath, 'site_nav.php') !== false) {
      // the file in question is a site nav file.
      return $filePath;
    }
    if (!is_dir($filePath)) {
      // we only want the dirname if we aren't already looking at a directory
      $filePath = dirname($filePath);
    }
    return (new File('site_nav.php'))->find($filePath, false, 5)->getValue();
  }

  /**
   * Checks to see if the site nav could currently be shared by other pages in this directory
   *
   * @param  string  $siteNav Path to the site nav we want to check.
   * @return boolean
   */
  protected static function isSiteNavShared($siteNav)
  {
    $currDir         = dirname($siteNav);
    if (!is_dir($currDir)) {
      return false;
    }
    $currDirContents = scandir($currDir);

    if (count($currDirContents) > 3) {
      // 2 for . and .. and 1 more for the current page we are viewing
      return true;
    }
    return false;
  }
}
