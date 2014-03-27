<?php
/**
 * @package  CMS
 * @author  Billy Visto
 */

namespace Gustavus\CMS;

/**
 * Configuration class
 *
 * @package  CMS
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
  const ROUTING_LOCATION = '/cis/lib/Gustavus/CMS/routing.php';

  /**
   * DB name
   */
  const DB  = 'cms';

  /**
   * directory from the web root this app lives at
   */
  const WEB_DIR = '/cms/';

  /**
   * Directory drafts are saved in
   */
  const DRAFT_DIR = '/cms/drafts/';

  /**
   * Directory drafts are saved in
   */
  const ABSOLUTE_DRAFT_DIR = '/cis/lib/Gustavus/CMS/Web/drafts/';

  /**
   * JS version
   */
  const JS_VERSION = 1;

  /**
   * CSS version
   */
  const CSS_VERSION = 1;

  public static $contentTypes = [
    self::PHP_CONTENT_TYPE,
    self::OTHER_CONTENT_TYPE,
  ];

  public static $editablePHPNodeTypes = [
    'Expr_Assign',
    'Expr_AssignConcat',
  ];

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
}