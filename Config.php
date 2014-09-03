<?php
/**
 * @package  Concert
 * @author  Billy Visto
 */

namespace Gustavus\Concert;

use Gustavus\Revisions\API as RevisionsAPI;

/**
 * Configuration class
 *
 * @package  Concert
 * @author  Billy Visto
 */
class Config
{
  /**
   * Whether or not we are allowing php edits
   */
  const ALLOW_PHP_EDITS = false;

  /**
   * Flag to specify php content types
   */
  const PHP_CONTENT_TYPE = 'phpcontent';

  /**
   * Flag to specify non-php content types
   */
  const OTHER_CONTENT_TYPE = 'content';

  /**
   * routing file's location
   */
  const ROUTING_LOCATION = '/cis/lib/Gustavus/Concert/routing.php';

  /**
   * DB name
   */
  const DB  = 'concert';

  /**
   * directory from the web root this app lives at
   */
  const WEB_DIR = '/concert/';

  /**
   * JS version
   */
  const JS_VERSION = 1;

  /**
   * Autocomplete JS version
   */
  const AUTOCOMPLETE_JS_VERSION = 1;

  /**
   * tinyMCE version
   */
  const TINY_MCE_VERSION = '4.0.20';

  /**
   * CSS version
   */
  const CSS_VERSION = 1;

  /**
   * Absolute location of the external file manager utility
   */
  const FILE_MANAGER_LOCATION = '/cis/www/concert/filemanager';

  /**
   * HTML comment that gets added after the closing tag for an editable div to make it easier to identify it
   */
  const EDITABLE_DIV_CLOSING_IDENTIFIER = '<!--endeditablediv-->';

  /**
   * How long a file can be locked without touching it before the lock is released
   */
  const LOCK_DURATION = 86400; // 60*60*24 = 86400

  /**
   * Base template
   */
  const DEFAULT_TEMPLATE_PAGE = '/cis/lib/Gustavus/Concert/Templates/template.php';

  /**
   * Base template
   */
  const DEFAULT_TEMPLATE_PAGE_IDENTIFIER = 'GustavusConcertDefaultTemplate';

  /**
   * Base site_nav template
   */
  const SITE_NAV_TEMPLATE = '/cis/lib/Gustavus/Concert/Templates/site_nav.php';

  /**
   * Private draft type identifier
   */
  const PRIVATE_DRAFT = 'private';

  /**
   * Public draft type identifier
   */
  const PUBLIC_DRAFT = 'public';

  /**
   * Draft type identifier for drafts waiting to be published
   */
  const PENDING_PUBLISH_DRAFT = 'pendingPublish';


  // Messages


  /**
   * Message to display to people who can't edit pages
   */
  const NOT_ALLOWED_TO_EDIT_MESSAGE = 'Oops! It appears that you don\'t have access to edit this page.';

  /**
   * Message to display to people who can't edit pages
   */
  const NOT_ALLOWED_TO_DELETE_MESSAGE = 'It looks like you aren\'t able to delete this page.';

  /**
   * Message to display to people who are trying to create new pages, but can't
   */
  const NOT_ALLOWED_TO_CREATE_MESSAGE = 'Oops! It appears that you don\'t have access to create this page.';

  /**
   * Message to display to people who can't edit pages
   */
  const NO_SITE_ACCESS_MESSAGE = 'Oops! It looks like you aren\'t able to edit this site.';

  /**
   * Message to display to people who can't get a lock for a page
   *   Usually followed by the user who currently owns the lock
   */
  const LOCK_NOT_ACQUIRED_MESSAGE = 'Oops! We couldn\'t acquire a lock for this page.';

  /**
   * Message to display to people who can't get a lock because they can't edit pages
   */
  const NOT_ALLOWED_TO_EDIT_MESSAGE_FOR_LOCK = 'It appears that you don\'t have access to edit this page.';

  /**
   * Generic message to display if something unexpected happens
   */
  const GENERIC_ERROR_MESSAGE = 'Oops! Something unexpected happened. Please try your request again, or contact <a href="mailto:web@gustavus.edu">web@gustavus.edu</a>.';

  /**
   * Note to let people know they are looking at a draft
   */
  const DRAFT_NOTE = 'Note: You are viewing a draft and not a published page.';

  /**
   * Note to let people know they are looking at a draft
   */
  const SITE_NAV_DRAFT_NOTE = 'Note: You are viewing a draft of the site nav and not a published site nav.';

  /**
   * Message to display if the draft doesn't exist.
   */
  const DRAFT_NON_EXISTENT = 'Oops! It looks like this draft doesn\'t exist.';

  /**
   * Message to display if someone is editing a site nav that is being inherited
   */
  const EDITING_SHARED_SITE_NAV_NOTE_START = '<span style="color: red">Warning!</span> You are editing a site nav that will be shared by other pages and directories in';

  /**
   * Message to display if someone is creating a site nav that is being shared by
   */
  const CREATE_SHARED_SITE_NAV_NOTE_START = '<span style="color: red">Warning!</span> You are creating a site nav that will be shared with other pages and directories in';

  /**
   * Message to display to people who didn't modify a starter page before saving
   */
  const DEFAULT_PAGE_SAVED_MESSAGE = 'Oops! It looks like you didn\'t modify the template before saving.';

  /**
   * Message to display if the page has been restored from a previous revision
   */
  const RESTORED_MESSAGE = 'The page has been restored.';

  /**
   * Message to display if the page revision restoration has been undone
   */
  const UNDO_RESTORE_MESSAGE = 'The page restoration has been undone.';

  // Staged file stages


  /**
   * Stage for specifing a deletion
   */
  const DELETE_STAGE = 'delete';

  /**
   * Stage for specifing a publish stage
   */
  const PUBLISH_STAGE = 'publish';

  /**
   * Stage for specifing a revision restore stage
   */
  const RESTORE_STAGE = 'restore';

  /**
   * Stage for specifing an undo restore stage
   */
  const UNDO_RESTORE_STAGE = 'undoRestore';


  // Access Levels
  // Note: When adding an access level, make sure to add it to the appropriate arrays below

  // Global access levels
  /**
   * Admin access level. This person is a global administrator for all sites.
   */
  const ADMIN_ACCESS_LEVEL  = 'admin';
  /**
   * Super User access level. This person is a global administrator for all sites with access to edit pages and drafts.
   */
  const SUPER_USER  = 'superUser';
  // Per-Site access levels
  /**
   * Public access level. Used whenever a "public" item is being edited.
   */
  const PUBLIC_ACCESS_LEVEL = 'public';
  /**
   * Admin access level. This person is an administrator for this site.
   */
  const SITE_ADMIN_ACCESS_LEVEL  = 'siteAdmin';
  /**
   * This person can publish pending drafts for this site.
   */
  const SITE_PUBLISHER_ACCESS_LEVEL  = 'publisher';
  /**
   * Access level that can only save drafts (non publishing)
   */
  const AUTHOR_ACCESS_LEVEL = 'author';
  /**
   * Access level that can only view sites (non editing)
   */
  const VIEWER_ACCESS_LEVEL = 'viewer';
  /**
   * Access level that can't create new files
   */
  const NON_CREATION_ACCESS_LEVEL = 'noCreate';
  /**
   * Access level that can't delete files
   */
  const NON_DELETION_ACCESS_LEVEL = 'noDelete';
  /**
   * Access level that can't upload files
   */
  const NO_UPLOAD_ACCESS_LEVEL = 'noUpload';

  /**
   * Global permissions for super users
   *
   * @var array
   */
  public static $superUserPermissions = [
    'accessLevel'   => [self::SUPER_USER],
    'includedFiles' => null,
    'excludedFiles' => null,
  ];

  /**
   * Global permissions for super users
   *
   * @var array
   */
  public static $adminPermissions = [
    'accessLevel'   => [self::ADMIN_ACCESS_LEVEL],
    'includedFiles' => null,
    'excludedFiles' => null,
  ];

  /**
   * AccessLevels that can't edit files
   *
   * @var array
   */
  public static $nonEditableAccessLevels = [
    self::VIEWER_ACCESS_LEVEL,
  ];

  /**
   * AccessLevels that can't publish files
   *
   * @var array
   */
  public static $nonPublishingAccessLevels = [
    self::AUTHOR_ACCESS_LEVEL,
  ];

  /**
   * AccessLevels that can't create new files
   *
   * @var array
   */
  public static $nonCreationAccessLevels = [
    self::NON_CREATION_ACCESS_LEVEL,
  ];

  /**
   * AccessLevels that can't delete files
   *
   * @var array
   */
  public static $nonDeletionAccessLevels = [
    self::NON_DELETION_ACCESS_LEVEL,
    self::AUTHOR_ACCESS_LEVEL,
  ];

  /**
   * AccessLevels that can't edit the site nav
   *
   * @var array
   */
  public static $siteNavAccessLevels = [
    self::SITE_ADMIN_ACCESS_LEVEL,
    self::ADMIN_ACCESS_LEVEL,
    self::SUPER_USER,
  ];

  /**
   * AccessLevels that can't edit the site nav
   *
   * @var array
   */
  public static $editRawHTMLAccessLevels = [
    self::ADMIN_ACCESS_LEVEL,
    self::SUPER_USER,
  ];

  /**
   * AccessLevels that can publish drafts for other people
   *
   * @var array
   */
  public static $publishPendingDraftsAccessLevels = [
    self::SITE_ADMIN_ACCESS_LEVEL,
    self::SITE_PUBLISHER_ACCESS_LEVEL,
  ];

  /**
   * Access levels that can't upload files
   *
   * @var array
   */
  public static $nonUploadingAccessLevels = [
    self::NO_UPLOAD_ACCESS_LEVEL,
  ];

  /**
   * Directory drafts are saved in
   *
   * @var string
   */
  public static $draftDir = '/cis/www-etc/lib/Gustavus/Concert/drafts/';

  /**
   * Directory editable drafts are saved in
   *
   * @var string
   */
  public static $editableDraftDir = '/cis/www-etc/lib/Gustavus/Concert/editableDrafts/';

  /**
   * Directory staged files waiting to be published are saved in
   *
   * @var string
   */
  public static $stagingDir = '/cis/www-etc/lib/Gustavus/Concert/staging/';

  /**
   * Supported content types
   *
   * @var array
   */
  public static $contentTypes = [
    self::PHP_CONTENT_TYPE,
    self::OTHER_CONTENT_TYPE,
  ];

  /**
   * Editable php node types from PHPParser
   *
   * @var array
   */
  public static $editablePHPNodeTypes = [
    'Expr_Assign',
    'Expr_AssignConcat',
  ];

  /**
   * Editable php expr types from PHPParser
   *
   * @var array
   */
  public static $editablePHPExprTypes = [
    'Scalar_String',
  ];

  /**
   * Template parts that are editable
   *   Note: These should all be lowercase
   *
   * @var array
   */
  public static $editableParts = [
    'title',
    'subtitle',
    'body',
    'content',
    'localnavigation',
  ];

  /**
   * Array of parts that aren't editable for each access level
   *   For example:
   *   <code>
   *   ['admin' => ['focusbox']] // This means that admins can't edit the focusbox
   *   </code>
   *   Note: The parts should all be lowercase
   * @var array
   */
  public static $nonEditablePartsByAccessLevel = [
    'admin'  => ['focusbox'],
    'public' => ['focusbox'],
  ];

  /**
   * Default buttons to show when inserting editing resources
   *
   * @var array
   */
  public static $defaultEditingButtons = [
    'publish',
    'savePrivateDraft',
    'savePublicDraft',
    'discardDraft',
  ];

  /**
   * Defines all of the possible templates a person can create a page from
   *   Key is the identifier.
   *   Value is an array with keys of name and location.
   *
   * @var array
   */
  public static $templates = [
    self::DEFAULT_TEMPLATE_PAGE_IDENTIFIER => [
      'name' => 'default',
      'location' => self::DEFAULT_TEMPLATE_PAGE,
    ],
  ];

  /**
   * Array of posible GET keys used for navigating concert
   *
   * @var array
   */
  public static $concertGETKeys = [
    'concertMoshed',
    'concert',
    'concertAction',
    'forwardedFrom',
    'concertDraft',
  ];

  /**
   * Emails that get emails for development purposes
   *
   * @var array
   */
  public static $devEmails = [
    'bvisto+concert@gustavus.edu'
  ];

  /**
   * Admin emails that get sent notifications for certain actions.
   *   Notification actions include:
   *     <ul>
   *       <li>No publishers found for a site</li>
   *     </ul>
   *
   * @var array
   */
  public static $adminEmails = [
    'web+concert@gustavus.edu'
  ];

  /**
   * Builds the upload location for the current user and page being edited
   *
   * @return string
   */
  public static function getUploadLocation()
  {
    return '/cis/www/concert/files/';
    $dir = str_replace('/cis/www/', '', self::FILE_MANAGER_LOCATION);
    $dirs = explode('/', $dir);
    // relative path from filemanager to doc root
    $relativeToDocRoot = str_repeat('../', count($dirs));


    // @todo make this dynamic per project
    $currentProjectUploadDir = '/cms/files';
    return $relativeToDocRoot . $currentProjectUploadDir;
  }

  /**
   * Builds the upload location for the current user and page being edited
   *
   * @return string
   */
  public static function getUploadThumbLocation()
  {
    return '/cis/www/concert/thumbs/';
  }

  /**
   * Removes the doc root from the file path
   *
   * @param  string $filePath File path to remove doc root from
   * @return string
   */
  public static function removeDocRootFromPath($filePath)
  {
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    if (strpos($filePath, $docRoot) === 0) {
      return substr($filePath, strlen($docRoot));
    } else {
      return $filePath;
    }
  }

  /**
   * Adds the doc root from the file path
   *
   * @param  string $filePath File path to add the doc root to
   * @return string
   */
  public static function addDocRootToPath($filePath)
  {
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    if (strpos($filePath, $docRoot) !== 0) {
      return str_replace('//', '/', $docRoot . DIRECTORY_SEPARATOR . $filePath);
    } else {
      return $filePath;
    }
  }

  /**
   * Builds a message for editing or creating a shared site nav
   *
   * @param  string  $siteNavDir Directory the site nav we are creating or editing lives in
   * @param  boolean $creation   Whether we are creating a new site nav or editing a curren one
   * @return string
   */
  public static function buildSharedSiteNavNote($siteNavDir, $creation = false)
  {
    $messageStart = ($creation) ? self::CREATE_SHARED_SITE_NAV_NOTE_START : self::EDITING_SHARED_SITE_NAV_NOTE_START;
    return sprintf('%s "%s/".', $messageStart, self::removeDocRootFromPath($siteNavDir));
  }

  /**
   * Gets the revisionsAPI for us to work with revisions
   *
   * @param  string $filePath Full path to the file
   * @param  \Doctrine\DBAL\Connection $dbal     Doctrine connection to use
   * @return API
   */
  public static function getRevisionsAPI($filePath, $dbal)
  {
    $filePathHash = md5($filePath);

    $params = array(
      'dbName'            => 'concert',
      'revisionsTable'    => 'revision',
      'revisionDataTable' => 'revisionData',
      'table'             => $filePathHash,
      'rowId'             => 0,
      'splitStrategy'     => 'sentenceOrTag',
      'dbal'              => $dbal,
    );

    return new RevisionsAPI($params);
  }
}