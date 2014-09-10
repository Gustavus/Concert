<?php
/**
 * @package  Concert
 * @author  Billy Visto
 */

namespace Gustavus\Concert;

use Gustavus\Concert\Config,
  Gustavus\Revisions\API as RevisionsAPI,
  Gustavus\Utility\PageUtil,
  Gustavus\Gatekeeper\Gatekeeper,
  Gustavus\Doctrine\DBAL;

/**
 * Class containing utility functions
 *
 * @package  Concert
 * @author  Billy Visto
 */
class Utility
{
  /**
   * Doctrine DBAL connection
   *
   * @var \Doctrine\DBAL\Connection
   */
  private static $dbal;

  /**
   * Gets the DBAL connection
   *
   * @return \Doctrine\DBAL\Connection
   */
  private static function getDBAL()
  {
    if (empty(self::$dbal)) {
      self::$dbal = DBAL::getDBAL(Config::DB);
    }
    return self::$dbal;
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
   * @param  boolean $fromRoot Whether we want the absolute path or not
   * @param  boolean $forThumbs Whether we are getting the location of thumbnails or not.
   * @return string
   */
  public static function getUploadLocation($fromRoot = false, $forThumbs = false)
  {
    $referer = PageUtil::getReferer();
    var_dump($referer, $_POST, $_GET);

    $parts = parse_url($referer);

    // $parts['path'] will have a leading slash. We want to remove the trailing slash from the doc root
    $filePath = $parts['path'];
    if (strpos($filePath, '.php') === false) {
      $filePath = str_replace('//', '/', $filePath . DIRECTORY_SEPARATOR . 'index.php');
    }

    $siteBase = PermissionsManager::findParentSiteForFile($filePath);
    if (empty($siteBase)) {
      return null;
    }

    $mediaDir = $siteBase . '/files/';
    if ($forThumbs) {
      $fileDir = $mediaDir . 'thumbs/';
    } else {
      $fileDir = $mediaDir . 'media/';
    }

    $fileDirAbs = self::addDocRootToPath($fileDir);
    if (!is_dir($fileDirAbs)) {
      $fm = new FileManager(Gatekeeper::getUsername(), $fileDirAbs, null, self::getDBAL());
      $fm->stageFile(Config::CREATE_HTTPD_DIRECTORY_STAGE, '');
      // give it a second to stage and publish the file.
      // @todo is this the best way to do this? Do we always want one created? Or only when they try to use filemanager? Will it sit empty, or be used?
      //
      // We can create it before even adding the fileManager plugin from the Shared controller. We would still need something here as a fallback, though.
      sleep(1);
    }
    if (!file_exists(self::addDocRootToPath($mediaDir) . '.htaccess')) {
      $fm = new FileManager(Gatekeeper::getUsername(), self::addDocRootToPath($mediaDir) . '.htaccess', null, self::getDBAL());
      $fm->stageFile(Config::PUBLISH_STAGE, file_get_contents(Config::MEDIA_DIR_HTACCESS_TEMPLATE));
    }

    return ($fromRoot) ? $fileDirAbs : $fileDir;
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
}