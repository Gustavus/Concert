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
  const LOCK_DURATION = 86400; // 60*60*24

  /**
   * Directory drafts are saved in
   *
   * @var string
   */
  public static $draftDir = '/cis/www-etc/lib/Gustavus/Concert/drafts/';

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
   * AccessLevels that can't edit files
   *
   * @var array
   */
  public static $nonEditableAccessLevels = [];

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
    'admin' => ['focusbox'],
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
}