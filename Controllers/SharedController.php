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
  Gustavus\Concert\Utility,
  Gustavus\Concert\FileManager,
  Gustavus\Concert\PermissionsManager,
  Gustavus\Extensibility\Filters,
  Gustavus\Concourse\RoutingUtil,
  Campus,
  Gustavus\Utility\File,
  Gustavus\Utility\Number,
  Gustavus\Utility\Set,
  Gustavus\Utility\PageUtil,
  DateTime,
  Twig_SimpleFunction;

/**
 * Controller to handle shared functionality for other controllers
 *
 * @package Concert
 * @subpackage Controllers
 * @author  Billy Visto
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
   * Messages to display to the user
   *
   * @var string
   */
  private static $messages = [];

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
    if (!empty($this->localNavigation)) {
      return $this->localNavigation;
    }

    if (PermissionsManager::isUserSuperUser($this->getLoggedInUsername()) || PermissionsManager::isUserAdmin($this->getLoggedInUsername())) {
      $isGlobalAdmin = true;
      $globalDashboard = [
        'url'  => $this->buildUrl('globalDashboard', ['dashboardType' => 'global']),
        'text' => 'Global Dashboard',
      ];

      $permissions = [
        'heading'        => true,
        'text'           => 'Permissions',
        'toggleChildren' => true,
        'items'          => [
          [
            'url'  => $this->buildUrl('sites'),
            'text' => 'View Sites',
          ],
          [
            'url'  => $this->buildUrl('createSite'),
            'text' => 'Create Site',
          ],
          [
            'url'  => $this->buildUrl('userSearch'),
            'text' => 'User Search',
          ],
        ],
      ];
    } else {
      $isGlobalAdmin   = false;
      $globalDashboard = [];
      $permissions     = [];
    }

    $localNavigation = array_filter([
      [
        'url'  => $this->buildUrl('dashboard'),
        'text' => 'Dashboard',
      ],
      $globalDashboard,
      [
        'url'  => $this->buildUrl('recentActivity'),
        'text' => 'Recent Activity',
      ],
      [
        'url'       => $this->buildUrl('listAllSites'),
        'text'      => 'View all Concert Sites',
        'visibleTo' => [
          'Concert' => [
            'all',
            'callbacks' => [
              [
                'callback'   => 'hasDepartment',
                'parameters' => 'Gustavus Technology Services'
              ]
            ]
          ]
        ]
      ],
      [
        'heading'        => true,
        'text'           => 'Help',
        'toggleChildren' => true,
        'items'          => [
          [
            'url'  => 'https://gustavus.edu/gts/Concert',
            'text' => 'Instructions'
          ],
          [
            'url'       => 'https://gustavus.edu/gts/Restricted:ConcertTroubleshooting',
            'text'      => 'Troubleshooting',
            'visibleTo' => [
              'concert' => [
                'all',
                'callbacks' => [
                  [
                    'callback'   => 'hasDepartment',
                    'parameters' => 'Gustavus Technology Services'
                  ]
                ]
              ]
            ]
          ],
        ],
      ],
      $permissions,
    ]);

    return $localNavigation;
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
   * Gets the twig environment with our AfterGacTwigExtension
   *
   * {@inheritdoc}
   */
  protected function getTwigEnvironment($viewDir = null, $viewNamespace = null)
  {
    Filters::add('twigEnvironmentSetUp', function($twigEnv) {
      $twigEnv->addFunction(new Twig_SimpleFunction('getFullNameFromUsername', [$this, 'getFullNameFromUsername']));
      $twigEnv->addFunction(new Twig_SimpleFunction('removeDocRootFromPath', '\Gustavus\Concert\Utility::removeDocRootFromPath'));
      return $twigEnv;
    });
    return parent::getTwigEnvironment($viewDir, $viewNamespace);
  }

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
   * @param string $redirectPath Path to redirect to on edit
   * @param  array $visibleButtons Array of buttons that we want to display
   * @param  array $additionalButtons Array of arrays of additional buttons to add. Sub-arrays must have indexes of 'url', 'id', and 'text'.
   * @param  array $additionalJSOptions Associative array of additional properties to insert into the javascript
   * @return void
   */
  protected function insertEditingResources($filePath, $redirectPath = null, array $visibleButtons = null, array $additionalButtons = null, array $additionalJSOptions = null)
  {
    // set things up for fileManager
    // set the language file to use since filemanager tries to use relative paths.
    $_SESSION['RF']['language_file'] = Config::FILE_MANAGER_LOCATION . '/lang/en_EN.php';

    $filePathFromDocRoot = Utility::removeDocRootFromPath($filePath);

    $destFilepath = $filePath;
    if (self::userIsEditingPublicDraft($filePath)) {
      // we need to find the site base a different way
      $draftName = self::guessDraftName();
      if (!empty($draftName)) {
        $fm = new FileManager($this->getLoggedInUsername(), Config::$draftDir . $draftName, null, $this->getDB());
        $draft = $fm->getDraft($draftName);
        if (!empty($draft)) {
          // our destFilepath is actually the destFilepath of the draft
          $destFilepath = $draft['destFilepath'];
        }
      }
      $siteBase = PermissionsManager::findClosestSiteForFile(Utility::removeDocRootFromPath($destFilepath));

    } else {
      $siteBase = PermissionsManager::findUsersSiteForFile($this->getLoggedInUsername(), $filePathFromDocRoot);
    }

    if (!empty($siteBase)) {
      $siteAccessKey = md5($siteBase);
      if (!isset($_SESSION['concertCMS']['siteAccessKeys'][$siteAccessKey])) {
        $_SESSION['concertCMS']['siteAccessKeys'][$siteAccessKey] = $siteBase;
      }
    } else {
      $siteAccessKey = 'noKey';
    }


    if (empty($redirectPath)) {
      $redirectPath = $filePath;
    }
    $redirectPath = Utility::removeDocRootFromPath($redirectPath);

    $tinyMCEPath = Resource::renderResource(['path' => sprintf('%s/js/tinymce_%s/tinymce.min.js', Config::WEB_DIR, Config::TINY_MCE_VERSION), 'version' => Config::TINY_MCE_VERSION]);
    $resources = [
      'js' => [
        '/js/jquery/ui/current/minified/jquery.ui.dialog.min.js',
        '/js/jquery/ui/current/minified/jquery.ui.button.min.js',
        $tinyMCEPath,
        Resource::renderResource(['urlutil', 'dropdown', ['path' => Config::WEB_DIR . '/js/concert.js', 'version' => Config::JS_VERSION]]),
      ],
    ];

    $allowCode = PermissionsManager::userCanEditRawHTML($this->getLoggedInUsername(), $filePathFromDocRoot);

    if (!isset($originalFilePath)) {
      $originalFilePath = $filePath;
    }

    Filters::add('scripts', function($content) use ($originalFilePath, $redirectPath, $resources, $allowCode, $siteAccessKey, $additionalJSOptions, $tinyMCEPath) {
      if (PermissionsManager::isUserAdmin($this->getLoggedInUsername()) || PermissionsManager::isUserSuperUser($this->getLoggedInUsername())) {
        $isAdmin = 'true';
      } else {
        $isAdmin = 'false';
      }

      if (!empty($additionalJSOptions)) {
        $additionalJSOptions = (new Set($additionalJSOptions))->toSentence('Gustavus.Concert.{{ key }} = {% if value == \'true\' or value == \'false\' %}{{ value }}{% else %}"{{ value }}"{% endif %};', '', 0, ' ');
      } else {
        $additionalJSOptions = '';
      }

      $script = sprintf(
          '<script type="text/javascript">
            Modernizr.load({
              load: [
                "%1$s"
              ],
              complete: function() {
                Gustavus.Concert.filePath = "%2$s";
                Gustavus.Concert.redirectPath = "%3$s";
                Gustavus.Concert.allowCode = %4$s;
                Gustavus.Concert.isAdmin = %5$s;
                Gustavus.Concert.isSiteNavRequest = %6$s;
                Gustavus.Concert.tinyMCEDefaultConfig.filemanager_access_key = "%7$s";
                Gustavus.Concert.tinyMCEDefaultConfig.external_filemanager_path = "/concert/filemanager/%7$s/";
                Gustavus.Concert.tinyMCEPath = "%8$s";
                %9$s
                Gustavus.Concert.init();
              }
            });
          </script>',
          implode('","', $resources['js']),
          $originalFilePath,
          $redirectPath,
          $allowCode ? 'true' : 'false',
          $isAdmin,
          self::isSiteNavRequest() ? 'true' : 'false',
          $siteAccessKey,
          $tinyMCEPath,
          $additionalJSOptions
      );
      return $content . $script;
    }, 11);

    self::markResourcesAdded($resources['js']);

    $cssResource = Resource::renderCSS(['dropdown-css', ['path' => Config::WEB_DIR . '/css/concert.css', 'version' => Config::CSS_VERSION]]);
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

    $userCanPublishFile = PermissionsManager::userCanPublishFile($this->getLoggedInUsername(), Utility::removeDocRootFromPath($destFilepath));

    if (!empty(self::$visibleEditingButtons)) {
      $visibleButtons = self::$visibleEditingButtons;
      // reset this back to null
      self::$visibleEditingButtons = null;
    }

    if ($visibleButtons === null) {
      $visibleButtons = Config::$defaultEditingButtons;
    }

    if (($pos = array_search('discardDraft', $visibleButtons)) !== false && !(new FileManager($this->getLoggedInUsername(), $destFilepath, null, $this->getDB()))->userHasOpenDraft()) {
      unset($visibleButtons[$pos]);
    }

    Filters::add('scripts', function($content) use ($userCanPublishFile, $visibleButtons, $additionalButtons) {
      return $content . $this->renderView(
          'actionButtons.js.twig',
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
  protected static function markResourcesAdded($resources, $type = 'js')
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
  protected static function isResourceAdded($resource, $type = 'js')
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
   * Adds the menu to interact with Concert
   *
   * @param   $menuParams Params to pass to the menus forward index
   * @return  void
   */
  protected function addMoshMenu(array $menuParams = [])
  {
    if (!self::$moshMenuAdded) {
      // add messages to the menu
      Filters::add('scripts', function($content) {
        return $content . $this->renderView('messages.js.twig', ['messages' => $this->getConcertMessages()]);
      });

      $menuParams['forReferer'] = false;
      $result = $this->forward('menus', $menuParams);
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
        self::addTemplatePref(['globalNotice' => ['vanilla' => ['notice' => $result, 'dismissable' => false]]]);
      }
      self::$moshMenuAdded = true;
    }
  }

  /**
   * Adds a preference to the global template preference array
   *
   * @param array $pref Array of the new preference to add
   * @return void
   */
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
  public function getConcertMessages()
  {
    $sessionMessage = self::getConcertSessionMessage();
    if (!empty($sessionMessage)) {
      // add session messages
      if (empty(self::$messages)) {
        self::$messages = [$sessionMessage];
      } else {
        self::$messages[] = $sessionMessage;
      }
    }

    return self::$messages;
  }

  /**
   * Adds a message to the page
   *
   * @param string  $message Message to add
   * @param string  $type Message type. Either error, alert, or message (default)
   * @return  void
   */
  protected function addConcertMessage($message, $type = 'message')
  {
    if (empty($message)) {
      return false;
    }

    if (!in_array($type, ['error', 'alert', 'message'])) {
      // default type to message if it is an unsupported type
      $type = 'message';
    }
    $message = [
      'type'    => $type,
      'message' => $message,
    ];

    if (!isset(self::$messages)) {
      self::$messages = [$message];
    } else {
      self::$messages[] = $message;
    }
  }

  /**
   * Builds a message to be thrown into colorbox or anywhere else
   *
   * @param string  $message Message to add
   * @param string  $type Message type. Either error, alert, or message (default)
   * @return string
   */
  protected static function buildConcertMessageDiv($message, $type = 'message')
  {
    if (empty($message)) {
      return false;
    }

    if (!in_array($type, ['error', 'alert', 'message'])) {
      // default type to message if it is an unsupported type
      $type = 'message';
    }

    return sprintf('<div class="concert-message concert-%s">%s</div>', $type, $message);
  }

  /**
   * Sets a message for the page
   *
   * @param string  $message Message to set
   * @param string  $type Message type. Either error, alert, or message (default)
   * @return  void
   */
  protected function setConcertMessage($message, $type = 'message')
  {
    if (!empty($message)) {
      if (!in_array($type, ['error', 'alert', 'message'])) {
        // default type to message if it is an unsupported type
        $type = 'message';
      }
      $message = [
        'type'    => $type,
        'message' => $message,
      ];
      self::$messages = [$message];
    } else {
      self::$messages = [];
    }
  }

  /**
   * Sets the concert message to be displayed on the next time the page is loaded
   *
   * @param string  $message message to display
   * @param boolean $type Message type. Either error, alert, or message (default)
   * @param string $location Location of the page the message is to be displayed on
   * @return  void
   */
  public static function setConcertSessionMessage($message = '', $type = 'message', $location = null)
  {
    PageUtil::startSessionIfNeeded();
    $location = PageUtil::buildMessageKey($location);

    if (!in_array($type, ['error', 'alert', 'message'])) {
      // default type to message if it is an unsupported type
      $type = 'message';
    }

    $_SESSION['concertMessages'][$location] = ['type' => $type, 'message' => $message];
  }

  /**
   * Gets the concert session message out of the session for the current page if it has one
   *
   * @param  string $location Location of the current page. Uses $_SERVER['SCRIPT_NAME'] if nothing set.
   * @return array|null null if nothing exists
   */
  public static function getConcertSessionMessage($location = null)
  {
    PageUtil::startSessionIfNeeded();
    $key = PageUtil::buildMessageKey($location);

    if (isset($_SESSION['concertMessages'][$key])) {
      $message = $_SESSION['concertMessages'][$key];
      unset($_SESSION['concertMessages'][$key]);
      return $message;
    }
    return null;
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
        $name = $this->getFullNameFromUsername($owner);

        $message = sprintf('%s %s currently holds the lock.', $message, $name);
        // lock couldn't be acquired due to lack of permissions or something else.
      }
    }

    return $message;
  }

  /**
   * Builds a message to display on successful publish
   *
   * @param  string $file Path to the file we published
   * @return string
   */
  protected function buildPublishSuccessMessage($file)
  {
    return sprintf('Contratulations! You have successfully published %s.<br /><a href="%s" class="concert-button primary">View Recent Activity</a>', Utility::removeDocRootFromPath($file), $this->buildUrl('recentActivity'));
  }

  /**
   * Builds a message to display after successfully saving a draft
   *
   * @param  array $draft Draft that has been saved
   * @param  boolean $showPublicUrl Whether to show the url users can access your draft with or not
   * @return string
   */
  protected function buildDraftSavedSuccessMessage(array $draft, $showPublicUrl = true)
  {
    if ($showPublicUrl && $draft['type'] === Config::PUBLIC_DRAFT) {
      $messageAdditions = sprintf(' Other users can see it by going to: <a href="%1$s">%1$s</a>.', $this->buildUrl('drafts', ['draftName' => $draft['draftFilename']], '', true));
    } else {
      $messageAdditions = '';
    }
    return sprintf(
        'Contratulations! You have successfully saved a draft for %s. %s<br /><a href="%s" class="concert-button primary">View Recent Activity</a>',
        Utility::removeDocRootFromPath($draft['destFilepath']),
        $messageAdditions,
        $this->buildUrl('recentActivity')
    );
  }

  /**
   * Gets the full name of the person from the specified username
   *
   * @param  string $username Username to get the full name for
   * @return string
   */
  public function getFullNameFromUsername($username)
  {
    $peoplePuller = Campus::People($this->getApiKey());
    $peoplePuller->setUsername($username);
    $person = $peoplePuller->current();
    if (is_object($person)) {
      return $person->getFullName();
    } else {
      return $username;
    }
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
        $name = $this->getFullNameFromUsername($openDraft['username']);
      } else {
        $forceHave = true;
        $name = count($draftUsers) === 0 ? 'You' : 'you';
      }
      $draftUsers[] = $name;
    }
    $verb = ($forceHave && $draftCount === 1) ? 'have' : (new Number($draftCount))->toQuantity('has', 'have');
    return sprintf('%s %s a draft open for this page.', (new Set($draftUsers))->toSentence(), $verb);
  }

  /**
   * Adds a concert message saying that the draft is out of date if the page has been modified since the draft was saved.
   *
   * @param array $draft Draft to check for
   * @return  void
   */
  public function addOutdatedDraftMessageIfNeeded($draft)
  {
    $draftTimeStamp = (int) (new DateTime($draft['date']))->format('U');

    if (file_exists($draft['destFilepath']) && $draftTimeStamp < filemtime($draft['destFilepath'])) {
      if ($draft['username'] === $this->getLoggedInUsername()) {
        $message = sprintf('%s %s', Config::OUTDATED_DRAFT_MESSAGE, Config::OUTDATED_DRAFT_MESSAGE_OWNER_ADDITIONS);
      } else {
        $message = Config::OUTDATED_DRAFT_MESSAGE;
      }
      $this->addConcertMessage($message, true);
    }
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
    return (isset($_GET['concert']) && ($_GET['concert'] === 'stopEditing' || $_GET['concert'] === 'stopEditingSiteNav'));
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
   * Checks to see if the user is done editing a draft
   *
   * @return boolean
   */
  protected static function userIsDoneEditingDraft()
  {
    return (isset($_GET['draftAction']) && $_GET['draftAction'] === 'stopEditing');
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
    return ((isset($_GET['concert']) && ($_GET['concert'] === 'siteNav' || $_GET['concert'] === 'stopEditingSiteNav')) || (isset($_GET['concertAction']) && $_GET['concertAction'] === 'siteNav') || self::userIsRequestingSiteNavLockRelease());
  }

  /**
   * Checks if the user is requesting to release the lock for a site nav
   * @return boolean
   */
  protected static function userIsRequestingSiteNavLockRelease()
  {
    return (isset($_POST['concertAction']) && $_POST['concertAction'] === 'stopEditingSiteNav');
  }

  /**
   * Checks to see if the current site nav is the global site nav or not
   * @param  string $siteNav Site nav to check
   * @return boolean
   */
  protected static function isGlobalNav($siteNav)
  {
    return (Utility::addDocRootToPath($siteNav) === Utility::addDocRootToPath('site_nav.php'));
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
    return ((isset($_POST['concertAction']) && $_POST['concertAction'] === 'stopEditing') || self::userIsRequestingSiteNavLockRelease());
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
    if (self::userIsSavingPublicDraft() && isset($_POST['filePath'])) {
      // user is trying to save the draft. We know that the specified file gets sent as well, so let's see if this file is representing public draft
      $requestURI = $_POST['filePath'];
    }

    if (strpos($requestURI, '?') !== false) {
      // we need to break the requestURI up
      $parts = parse_url($requestURI);
      if (isset($parts['path'])) {
        // we only need the path as we aren't doing anything with query params here.
        $requestURI = $parts['path'];
      }
    }
    $requestURI = rawurldecode($requestURI);

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
    $concertRoot = Utility::addDocRootToPath(Config::WEB_DIR);

    if ($requestURI === null) {
      $requestURI = $_SERVER['REQUEST_URI'];
    }
    $requestURI = Utility::addDocRootToPath($requestURI);

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
   * Checks to see if the request was forwarded from the site nav controller.
   *
   * @return boolean
   */
  protected static function isForwardedFromSiteNav()
  {
    return (isset($_GET['forwardedFrom']) && $_GET['forwardedFrom'] === 'siteNav');
  }

  /**
   * Checks to see if the request was forwarded from the draft controller.
   *
   * @return boolean
   */
  protected static function isForwardedFromDraft()
  {
    return (isset($_GET['forwardedFrom']) && $_GET['forwardedFrom'] === 'draft');
  }

  /**
   * Checks to see if we are forwarding internally
   *
   * @return boolean
   */
  protected static function isInternalForward()
  {
    return (self::isForwardedFromSiteNav() || self::isForwardedFromDraft());
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
      $parts = parse_url(rawurldecode($_SERVER['REQUEST_URI']));
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
