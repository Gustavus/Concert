<?php
/**
 * @package  ConcertCMS
 * @author  Billy Visto
 */

namespace Gustavus\ConcertCMS;

/**
 * Configuration class
 *
 * @package  ConcertCMS
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
  const ROUTING_LOCATION = '/cis/lib/Gustavus/ConcertCMS/routing.php';

  /**
   * DB name
   */
  const DB  = 'concertCMS';

  /**
   * directory from the web root this app lives at
   */
  const WEB_DIR = '/concert/';

  /**
   * Directory drafts are saved in
   */
  const DRAFT_DIR = '/cis/www-etc/lib/Gustavus/ConcertCMS/drafts/';

  /**
   * Directory staged files waiting to be published are saved in
   */
  const STAGING_DIR = '/cis/www-etc/lib/Gustavus/ConcertCMS/staging/';

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
   * Checks to see if the current user can edit a page.
   *
   * @return boolean
   * @todo  finish
   */
  public static function canEditPage()
  {
    return true;
  }

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