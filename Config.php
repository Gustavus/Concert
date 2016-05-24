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
   * Flag to specify javascript content types
   */
  const SCRIPT_CONTENT_TYPE = 'scriptcontent';

  /**
   * Max file size we can edit. (in kb)
   */
  const MAX_EDITABLE_FILE_SIZE = 700000;

  /**
   * File size that starts getting slow when trying to edit. (in kb)
   *   Used for displaying a message and to determine if we should indent the html or not.
   */
  const PERFORMANCE_HIT_FILE_SIZE = 200000;

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
  const JS_VERSION = 38;

  /**
   * Autocomplete JS version
   */
  const AUTOCOMPLETE_JS_VERSION = 1;

  /**
   * tinyMCE version
   */
  const TINY_MCE_VERSION = '4.3.11';

  /**
   * Responsive Filemanager version
   */
  const RESPONSIVE_FILEMANAGER_VERSION = '9.9.7';

  /**
   * CSS version
   */
  const CSS_VERSION = 7;

  /**
   * HTTPD user (User running apache)
   */
  const HTTPD_USER = 'httpd';

  /**
   * Group of the HTTPD user
   */
  const HTTPD_GROUP = 'httpd';

  /**
   * Absolute location of the external file manager utility
   */
  const FILE_MANAGER_LOCATION = '/cis/www/concert/filemanager';

  /**
   * HTML comment that gets added after the closing tag for an editable div to make it easier to identify it.
   */
  const EDITABLE_DIV_CLOSING_IDENTIFIER = '<!--endeditablediv-->';

  /**
   * How long a file can be locked without touching it before the lock is released
   */
  const LOCK_DURATION = 86400; // 60*60*24 = 86400

  /**
   * Global switch to turn off concert
   */
  const GLOBAL_SHUTDOWN = false;

  // Templates

  /**
   * Base template
   */
  const DEFAULT_TEMPLATE = '/cis/lib/Gustavus/Concert/Templates/template.php';
  /**
   * Base template identifier
   */
  const DEFAULT_TEMPLATE_IDENTIFIER = 'GustavusConcertDefaultTemplate';

  /**
   * Base site_nav template
   */
  const SITE_NAV_TEMPLATE = '/cis/lib/Gustavus/Concert/Templates/site_nav.php';

  /**
   * .htaccess file to copy to any new media directory
   */
  const MEDIA_DIR_HTACCESS_TEMPLATE = '/cis/lib/Gustavus/Concert/Templates/.htaccess';

  // Draft Types

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
   * Message to display if someone is editing a site nav that is being inherited
   */
  const EDITING_SHARED_SITE_NAV_NOTE_START = '<strong><em>Warning!</em></strong> You are editing a menu that is shared by other pages and directories in';

  /**
   * Message to display if someone is creating a site nav that is being shared by
   */
  const CREATE_SHARED_SITE_NAV_NOTE_START = '<strong><em>Warning!</em></strong> You are creating a menu that will be shared with other pages and directories in';

  /**
   * Message to display to people who didn't modify a starter page before saving
   */
  const DEFAULT_PAGE_SAVED_MESSAGE = 'Oops! It looks like you didn\'t modify the template before saving.';

  /**
   * Message to display for special files. (symlinks or non-editable files)
   */
  const SPECIAL_FILE_MESSAGE = 'This is a special file that cannot be edited in Concert. Please contact <a href="mailto:web@gustavus.edu">Web Services</a> to edit this file.';

  /**
   * Message to display for special files. (symlinks or non-editable files)
   */
  const SPECIAL_FILE_COPY_MESSAGE = 'This is a special file that cannot be copied in Concert. Please contact <a href="mailto:web@gustavus.edu">Web Services</a> for more information.';

  /**
   * Message to use to prompt someone to continue editing their draft
   */
  const CONTINUE_EDITING_MESSAGE = 'It looks like you left this page before you finished editing it. Would you like to <a href="?concert=edit">continue</a>?';

  // Revision messages

  /**
   * Message to display if the page has been restored from a previous revision
   */
  const RESTORED_MESSAGE = 'The page has been restored.';

  /**
   * Message to display if the page revision restoration has been undone
   */
  const UNDO_RESTORE_MESSAGE = 'The page restoration has been undone.';

  /**
   * Message to display to people who can't manage revisions, but are trying
   */
  const NOT_ALLOWED_TO_MANAGE_REVISIONS = 'Oops! It appears that you don\'t have access to manage revisions for this page.';

  /**
   * Message to display to people who can't manage revisions, but are trying
   */
  const NOT_ALLOWED_TO_VIEW_REVISIONS = 'Oops! It appears that you don\'t have access to view revisions for this page.';

  // Draft messages

  /**
   * Note to let people know they are looking at a draft
   */
  const DRAFT_NOTE = 'Note: You are viewing a draft and not a published page.';

  /**
   * Note to let people know they are looking at a draft
   */
  const SITE_NAV_DRAFT_NOTE = 'Note: You are viewing a draft of the menu and not a published menu.';

  /**
   * Message to display if the draft doesn't exist.
   */
  const DRAFT_NON_EXISTENT = 'Oops! It looks like this draft doesn\'t exist.';

  /**
   * Message to display if no drafts exist.
   */
  const NO_DRAFTS_MESSAGE = 'Oops! It looks like there aren\'t any drafts to show';

  /**
   * Message to display if the draft isn't editable.
   */
  const DRAFT_NOT_EDITABLE_MESSAGE = 'Oops! It looks like you don\'t have access to edit this draft.';

  /**
   * Message to display if a non-owner of a draft tries to publish it
   */
  const NOT_ALLOWED_TO_PUBLISH_DRAFT_MESSAGE = 'Oops! It looks like you can\'t publish this draft because you aren\'t the owner.';

  /**
   * Message to use when the draft is older than the page the draft represents
   */
  const OUTDATED_DRAFT_MESSAGE = 'This draft was created from an older version of the page and may be out of date.';

  /**
   * Message to display if the owner of a pending draft couldn't be contacted
   */
  const DRAFT_REJECTION_NOT_SENT_MESSAGE = 'The owner of the draft couldn\'t be notified that their draft has been rejected.';

  /**
   * Message to display if the owner of a pending draft couldn't be contacted
   */
  const DRAFT_PUBLISHED_NOT_SENT_MESSAGE = 'The owner of the draft couldn\'t be notified that their draft has been published.';

  /**
   * Additional message to display to the owner of the draft telling them that they may need to discard it.
   */
  const OUTDATED_DRAFT_MESSAGE_OWNER_ADDITIONS = 'Saving this draft may overwrite changes made since its creation.';

  /**
   * Additional message to display to the owner of the draft telling them that they may need to discard it.
   */
  const DRAFT_EDITED_BY_COLLABORATOR_MESSAGE = 'A collaborator has edited your draft since your last save.';

  /**
   * Message to display if the file is too large for editing
   */
  const FILE_TOO_BIG_FOR_EDIT_MESSAGE = 'This file is too large to edit. Contact <a href="mailto:web@gustavus.edu">Web Services</a> for more information.';

  /**
   * Message to display if the file is so large that may cause editing to be slow
   */
  const LARGE_FILE_EDIT_MESSAGE = 'This file is very large and performance issues may be noticeable.';

  /**
   * Message to display when concert is disabled
   */
  const CONCERT_DISABLED_MESSAGE = 'Concert is currently disabled due to system maintenance.';


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
   * Stage for specifing that a pending draft has been published
   */
  const PUBLISH_PENDING_STAGE = 'publishPending';
  /**
   * Stage for specifing a revision restore stage
   */
  const RESTORE_STAGE = 'restore';
  /**
   * Stage for specifing an undo restore stage
   */
  const UNDO_RESTORE_STAGE = 'undoRestore';
  /**
   * Stage for specifing a media directory creation
   */
  const CREATE_MEDIA_DIRECTORY_STAGE = 'createMediaDir';


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
   * This person can edit and upload, but just doesn't have admin privileges.
   */
  const SITE_EDITOR_ACCESS_LEVEL  = 'editor';
  /**
   * This person can publish pending drafts for this site.
   */
  const SITE_PUBLISHER_ACCESS_LEVEL  = 'publisher';
  /**
   * Access level that can only save drafts (non publishing)
   */
  const AUTHOR_ACCESS_LEVEL = 'author';
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
   * Access level that can modify banners
   */
  const BANNER_ACCESS_LEVEL = 'banner';
  /**
   * Access level that can edit focus boxes
   */
  const FOCUS_BOX_ACCESS_LEVEL = 'focusBox';
  /**
   * Access level that can't edit anything
   */
  const NO_EDIT_ACCESS_LEVEL = 'noEdit';
  /**
   * Access level that can edit siteNavs
   */
  const SITE_NAV_ACCESS_LEVEL = 'siteNav';

  /**
   * All available access levels with brief description
   *
   * @var array
   */
  public static $availableAccessLevels = [
    self::SITE_ADMIN_ACCESS_LEVEL     => 'Site Admin',
    self::SITE_EDITOR_ACCESS_LEVEL    => 'Site Editor',
    self::SITE_PUBLISHER_ACCESS_LEVEL => 'Site Publisher',
    self::AUTHOR_ACCESS_LEVEL         => 'Site Author',
    self::NON_CREATION_ACCESS_LEVEL   => 'Non Creation',
    self::NON_DELETION_ACCESS_LEVEL   => 'Non Deletion',
    self::NO_UPLOAD_ACCESS_LEVEL      => 'No Upload',
    self::BANNER_ACCESS_LEVEL         => 'Banner',
    self::FOCUS_BOX_ACCESS_LEVEL      => 'Focus Box',
    self::NO_EDIT_ACCESS_LEVEL        => 'No Edit',
    self::SITE_NAV_ACCESS_LEVEL       => 'Site Nav',
    self::ADMIN_ACCESS_LEVEL          => 'Global Admin',
    self::SUPER_USER                  => 'Global Super User',
  ];

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
   * Global permissions for admins
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
    self::NO_EDIT_ACCESS_LEVEL,
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
    self::NO_EDIT_ACCESS_LEVEL,
  ];

  /**
   * AccessLevels that can't delete files
   *
   * @var array
   */
  public static $nonDeletionAccessLevels = [
    self::NON_DELETION_ACCESS_LEVEL,
    self::AUTHOR_ACCESS_LEVEL,
    self::NO_EDIT_ACCESS_LEVEL,
  ];

  /**
   * AccessLevels that can't edit the site nav
   *
   * @var array
   */
  public static $siteNavAccessLevels = [
    self::SUPER_USER,
    self::ADMIN_ACCESS_LEVEL,
    self::SITE_ADMIN_ACCESS_LEVEL,
    self::SITE_NAV_ACCESS_LEVEL,
  ];

  /**
   * AccessLevels that can edit source code
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
    self::SUPER_USER,
  ];

  /**
   * AccessLevels that can manage revisions
   *
   * @var array
   */
  public static $manageRevisionsAccessLevels = [
    self::SUPER_USER,
    self::ADMIN_ACCESS_LEVEL,
    self::SITE_ADMIN_ACCESS_LEVEL,
    self::SITE_PUBLISHER_ACCESS_LEVEL,
  ];

  /**
   * AccessLevels that can't view revisions
   *
   * @var array
   */
  public static $nonRevisionsAccessLevels = [
    self::NO_EDIT_ACCESS_LEVEL,
  ];

  /**
   * Access levels that can't upload files
   *
   * @var array
   */
  public static $nonUploadingAccessLevels = [
    self::NO_UPLOAD_ACCESS_LEVEL,
    self::NO_EDIT_ACCESS_LEVEL,
  ];

  /**
   * Access levels that can edit banners
   *
   * @var array
   */
  public static $manageBannersAccessLevels = [
    self::SUPER_USER,
    self::ADMIN_ACCESS_LEVEL,
    self::BANNER_ACCESS_LEVEL,
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
    'focusbox',
  ];

  /**
   * Array of parts that aren't editable for each access level
   *   For example:
   *   <code>
   *   ['admin' => ['focusbox']] // This means that admins can't edit the focusbox
   *   </code>
   *
   *   Note: The parts should all be lowercase.
   *   This won't be of use until we start getting access levels that can edit different pieces.
   * @var array
   */
  public static $nonEditablePartsByAccessLevel = [
    self::PUBLIC_ACCESS_LEVEL         => ['focusbox'],
    self::SITE_ADMIN_ACCESS_LEVEL     => ['focusbox'],
    self::SITE_EDITOR_ACCESS_LEVEL    => ['focusbox'],
    self::SITE_PUBLISHER_ACCESS_LEVEL => ['focusbox'],
    self::AUTHOR_ACCESS_LEVEL         => ['focusbox'],
    self::NON_CREATION_ACCESS_LEVEL   => ['focusbox'],
    self::NON_DELETION_ACCESS_LEVEL   => ['focusbox'],
    self::NO_UPLOAD_ACCESS_LEVEL      => ['focusbox'],
    self::BANNER_ACCESS_LEVEL         => ['focusbox'],
    self::SITE_NAV_ACCESS_LEVEL       => ['focusbox'],
    self::FOCUS_BOX_ACCESS_LEVEL      => [],
    self::ADMIN_ACCESS_LEVEL          => [],
    self::SUPER_USER                  => [],
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
   * Directory used to store temporary files
   *
   * @var string
   */
  public static $tmpDir = '/cis/www-etc/lib/Gustavus/Concert/tmp/';

  /**
   * Supported content types
   *
   * @var array
   */
  public static $contentTypes = [
    self::PHP_CONTENT_TYPE,
    self::OTHER_CONTENT_TYPE,
    self::SCRIPT_CONTENT_TYPE,
  ];

  /**
   * Supported content types
   *
   * @var array
   */
  public static $editableContentTypes = [
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
   * Default buttons to show when inserting editing resources
   *
   * @var array
   */
  public static $defaultEditingButtons = [
    'publish',
    //'savePrivateDraft', Disabled for now. You can only save a shared draft.
    'savePublicDraft',
    'discardDraft',
  ];

  /**
   * Draft types that can be used.
   *
   * @var array
   */
  public static $allowableDraftTypes = [
    Config::PUBLIC_DRAFT,
    //Config::PRIVATE_DRAFT, Disabled for now. You can only save a shared (public) draft.
    Config::PENDING_PUBLISH_DRAFT,
  ];

  /**
   * Defines all of the possible templates a person can create a page from
   *   Key is the identifier.
   *   Value is an array with keys of name and location.
   *
   * @var array
   */
  public static $templates = [
    self::DEFAULT_TEMPLATE_IDENTIFIER => [
      'name' => 'default',
      'location' => self::DEFAULT_TEMPLATE,
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
    'draftAction',
    'srcFilePath',
    'showUnMatchedTags',
    // Revisions
    'oldestRevisionNumber',
    'revisionNumber',
    'restore',
    'revisionsAction',
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
   * Folders to exclude when building a file tree for creating new pages.
   *
   * @var array
   */
  public static $fileTreeExcludedFolders = [
    'concertFiles',
  ];

  /**
   * Doc root we want to require for accessing concert.
   *   This prevents subdomains with a different doc root from being able to access concert since it wasn't designed to be used that way.
   *   Note: This is a private variable instead of a constant so we can change the doc root in tests.
   *
   * @var string
   */
  private static $requiredDocRoot = '/cis/www';

  /**
   * Sub folders that should exist in our upload directory
   *
   * @var array
   */
  public static $mediaSubFolders = [
    '/thumbs/',
    '/media/',
  ];

  /**
   * Gets the required doc root
   *
   * @return string
   */
  public static function getRequiredDocRoot()
  {
    return self::$requiredDocRoot;
  }
}