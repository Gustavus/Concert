<?php
/**
 * @package  Concert
 * @author  Billy Visto
 */

namespace Gustavus\Concert;

use Gustavus\Concert\Config,
  Gustavus\Revisions\API as RevisionsAPI,
  Gustavus\Utility\PageUtil;

/**
 * Class containing utility functions
 *
 * @package  Concert
 * @author  Billy Visto
 */

class Utility
{
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
    $messageStart = ($creation) ? Config::CREATE_SHARED_SITE_NAV_NOTE_START : Config::EDITING_SHARED_SITE_NAV_NOTE_START;
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
    // note: changing this will ruin past revisions. (Unless you update them in the table)
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

  /**
   * Builds the upload location for the current user and page being edited
   *
   * @return string
   */
  public static function getUploadLocation()
  {
    $referer = PageUtil::getReferer();

    $parts = parse_url($referer);

    // $parts['path'] will have a leading slash. We want to remove the trailing slash from the doc root
    $filePath = $parts['path'];
    if (strpos($filePath, '.php') === false) {
      $filePath = str_replace('//', '/', $filePath . DIRECTORY_SEPARATOR . 'index.php');
    }
    return ($fromRoot) ? self::addDocRootToPath($filePath): $filePath;
    // $dir = self::removeDocRootFromPath(self::FILE_MANAGER_LOCATION);
    // var_dump($dir);
    // $dirs = array_filter(explode('/', $dir));
    // // relative path from filemanager to doc root
    // $relativeToDocRoot = str_repeat('../', count($dirs));

    // var_dump($_SERVER);


    // @todo make this dynamic per project
    $currentProjectUploadDir = '/concert/files';
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