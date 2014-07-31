<?php
/**
 * @package  Concert
 * @author  Billy Visto
 */

namespace Gustavus\Concert;

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
  const TEMPLATE_PAGE = '/cis/lib/Gustavus/Concert/Templates/template.php';

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

  /**
   * Message to display to people who can't edit pages
   */
  const NOT_ALLOWED_TO_EDIT_MESSAGE = 'It looks like you aren\'t able to edit this page.';

  /**
   * Message to display to people who can't edit pages
   */
  const NO_SITE_ACCESS_MESSAGE = 'Oops! It looks like you aren\'t able to edit this site.';

  /**
   * Message to display to people who can't get a lock for a page
   */
  const LOCK_NOT_AQUIRED_MESSAGE = 'It looks like we couldn\'t acquire a lock to edit this page.';

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
   * Stage for specifing a deletion
   */
  const DELETE_STAGE = 'delete';

  /**
   * Stage for specifing a publish stage
   */
  const PUBLISH_STAGE = 'publish';


  // Access Levels

  /**
   * Public access level. Used whenever a "public" item is being edited.
   */
  const PUBLIC_ACCESS_LEVEL = 'public';
  /**
   * Admin access level. This person is an administrator for this site.
   */
  const SITE_ADMIN_ACCESS_LEVEL  = 'siteAdmin';
  /**
   * Admin access level. This person is a global administrator for all sites.
   */
  const ADMIN_ACCESS_LEVEL  = 'admin';
  /**
   * Super User access level. This person is a global administrator for all sites with access to edit pages and drafts.
   */
  const SUPER_USER  = 'superUser';

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
  public static $nonEditableAccessLevels = [];

  /**
   * AccessLevels that can't publish files
   *
   * @var array
   */
  public static $nonPublishingAccessLevels = [];

  /**
   * AccessLevels that can't create new files
   *
   * @var array
   */
  public static $nonCreationAccessLevels = [];

  /**
   * AccessLevels that can't delete files
   *
   * @var array
   */
  public static $nonDeletionAccessLevels = [];

  /**
   * AccessLevels that can publish drafts for other people
   *
   * @var array
   */
  public static $publishPendingDraftsAccessLevels = [
    'siteAdmin'
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
   *
   * @var array
   */
  public static $templates = [
    'default' => self::TEMPLATE_PAGE,
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
}