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
  Gustavus\Utility\File;

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
  private static $moshMenuAdded = false;

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

  /**
   * Adds JS to page
   *
   * @todo  remove this. Or convert to tinymce.
   *
   * @return  void
   */
  protected function addJS()
  {
    $this->addJavascripts(sprintf(
        '<script type="text/javascript">
          var CKEDITOR_BASEPATH = \'/js/ckeditor/\';
          Modernizr.load([
            "%s",
          ]);
        </script>',
        Resource::renderResource([['path' => '/js/ckeditor/ckeditor.js'], ['path' => '/js/ckeditor/adapters/jquery.js'], ['path' => Config::WEB_DIR . '/js/concert.js', 'version' => Config::JS_VERSION]])
    ));
  }

  /**
   * Adds CSS to page
   *
   * @return  void
   */
  protected function addCSS()
  {
    $this->addStylesheets(
        sprintf(
            '<link rel="stylesheet" type="text/css" href="%s" />',
            Resource::renderCSS(['path' => Config::WEB_DIR . '/css/concert.css', 'version' => Config::CSS_VERSION])
        )
    );
  }

  /**
   * Injects resources required for editing pages
   *
   * @param string $filePath FilePath of the file we are editing
   * @param  array $visibleButtons Array of buttons that we want to display
   * @param  array $additionalButtons Array of arrays of additional buttons to add. Sub-arrays must have indexes of 'url', 'id', and 'text'.
   * @return void
   */
  protected function insertEditingResources($filePath, array $visibleButtons = null, array $additionalButtons = null)
  {
    Filters::add('scripts', function($content) use ($filePath) {
        $script = sprintf(
            '<script type="text/javascript">
              Modernizr.load({
                load: [
                  "/js/jquery/ui/current/minified/jquery.ui.dialog.min.js",
                  "/js/jquery/ui/current/minified/jquery.ui.button.min.js",
                  "%s",
                  "%s"
                ],
                complete: function() {
                  Gustavus.Concert.filePath = "%s"
                }
              });
            </script>',
            Resource::renderResource(['path' => Config::WEB_DIR . '/js/tinymce/tinymce.min.js', 'version' => 0]),
            Resource::renderResource(['urlutil', ['path' => Config::WEB_DIR . '/js/concert.js', 'version' => Config::JS_VERSION]]),
            Config::removeDocRootFromPath($filePath)
        );
        return $content . $script;
    }, 11);

    Filters::add('head', function($content) {
        $css = sprintf(
            '<link rel="stylesheet" type="text/css" href="%s" />',
            Resource::renderCSS(['path' => Config::WEB_DIR . '/css/concert.css', 'version' => Config::CSS_VERSION])
        );
        return $content . $css;
    }, 11);

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
      $result = $this->forward('menus', ['forReferer' => false]);
      if (!empty($result)) {
        Filters::add('userBox', function($content) {
          return sprintf('%s<a href="%s" class="button red concertMenu thickbox">Concert</a>', $content, $this->buildUrl('menus'));
        });
      }
      self::$moshMenuAdded = true;
    }
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
  protected static function isForwardedFromSiteNav()
  {
    return (isset($_GET['forwardedFrom']) && $_GET['forwardedFrom'] === 'siteNav');
  }

  /**
   * Checks to see if the user is requesting anything for a site nav
   * @return boolean
   */
  protected static function isSiteNavRequest()
  {
    if (isset($_POST['filePath']) && strpos($_POST['filePath'], 'concertAction=siteNav') !== false) {
      return true;
    }
    return ((isset($_GET['concert']) && $_GET['concert'] === 'siteNav') || (isset($_GET['concertAction']) && $_GET['concertAction'] === 'siteNav'));
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
   * Checks to see if the user is viewing a public draft from the specified requestURI
   *
   * @param  string $requestURI
   * @return boolean
   */
  protected function userIsViewingPublicDraft($requestURI)
  {
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
    // var_dump($_GET);
    // exit;
    return ((isset($_GET['concert']) && $_GET['concert'] === 'delete') || (isset($_POST['concertAction']) && $_POST['concertAction']) === 'delete');
  }

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
   * Gets the path to redirect to if we want to change it.
   *
   * @return boolean
   */
  protected static function getEditRedirectPath()
  {
    if (self::isForwardedFromSiteNav()) {
      return $_SERVER['REQUEST_URI'];
    }
  }

  /**
   * Searches for the best site nav for the specified file
   *
   * @param  string $filePath FilePath of the file to get the site nav for
   * @return string|boolean String on success, false on failure
   */
  protected static function getSiteNavForFile($filePath)
  {
    return (new File('site_nav.php'))->find(dirname($filePath), false, 5)->getValue();
  }
}
