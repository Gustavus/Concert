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
  Gustavus\Doctrine\DBAL,
  DateTime;

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
   * @param  boolean $canManageRevisions Whether the current user can manage revisions or not. They won't be able to restore revisions if they can't manage them.
   * @return API
   */
  public static function getRevisionsAPI($filePath, $dbal, $canManageRevisions = false)
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
      'allowRestore'      => $canManageRevisions,
    );

    return new RevisionsAPI($params);
  }

  /**
   * Builds the upload location for the current user and page being edited
   *
   * @return string|null null if an uploadLocation couldn't be created
   */
  public static function getUploadLocation()
  {
    if (isset($_SESSION['concertCMS']['currentParentSiteBase'])) {
      $uploadLocation = str_replace('//', '/', $_SESSION['concertCMS']['currentParentSiteBase'] . '/concertFiles/');
      self::ensureUploadDirectoriesExist($uploadLocation);
      return $uploadLocation;
    } else {
      return null;
    }
  }

  /**
   * Ensures that our upload directories exist.
   *
   * @param  string $uploadLocation Absolute path to the upload location
   * @return void
   */
  private static function ensureUploadDirectoriesExist($uploadLocation)
  {
    $staged = false;
    foreach (['thumbs/', 'media/'] as $folder) {
      if (!is_dir($uploadLocation . $folder)) {
        $fm = new FileManager(Gatekeeper::getUsername(), $uploadLocation . $folder, null, self::getDBAL());
        if ($fm->stageFile(Config::CREATE_HTTPD_DIRECTORY_STAGE, '')) {
          $staged = true;
        }
        $fm->stopEditing();
      }
    }

    if (!file_exists($uploadLocation . '.htaccess')) {
      $fm = new FileManager(Gatekeeper::getUsername(), self::addDocRootToPath($uploadLocation) . '.htaccess', null, self::getDBAL());
      if ($fm->stageFile(Config::CREATE_HTTPD_DIR_HTACCESS_STAGE, '')) {
        $staged = true;
      }
      $fm->stopEditing();
    }

    if ($staged && PHP_SAPI !== 'cli') {
      // we staged something. give it a second to publish the file.
      sleep(1);
    }
  }

  /**
   * Checks to see if a draft has been edited by a collaborator
   *
   * @param  array $draft Array representing a draft
   * @return boolean
   */
  public static function sharedDraftHasBeenEditedByCollaborator($draft)
  {
    $draftPath = Config::$draftDir . $draft['draftFilename'];
    // add a little time to the draft time just in case there was a lag somewhere
    $draftTimestamp = (int) (new DateTime($draft['date']))->modify('+30 seconds')->format('U');
    $draftFileTimestamp = filemtime($draftPath);

    if ($draftTimestamp < $draftFileTimestamp) {
      // this draft has been edited since it was modified by the owner
      return true;
    }
    return false;
  }

  /**
   * Gets the best group to use for files
   *   If the file doesn't already exist, we will want to look backwards through the directories and use the closest directory's group.
   *   <strong>Note:</strong> This should only be called by publishFile.
   *
   * @param  string $filePath Path of the file to guess the group for
   *
   * @return string
   */
  public static function getGroupForFile($filePath)
  {
    if (file_exists($filePath)) {
      $path = $filePath;
    } else {
      $dir = dirname($filePath);
      while (!is_dir($dir)) {
        $dir = dirname($dir);
      }
      $path = $dir;
    }

    $gid       = filegroup($path);
    $groupInfo = posix_getgrgid($gid);
    $group     = $groupInfo['name'];
    return $group;
  }

  /**
   * Grabs the first block of php code
   *
   * @param  string $page Either the page contents or a file path
   * @param  boolean $isPageContent Tells us if the page specified in $page is a file path or page contents.
   * @return string
   */
  public static function getFirstPHPBlock($page, $isPageContent = false)
  {
    $firstPHPRegex = '`(?:
      # Make sure we are at the beginning of the file
      ^
      # look for newlines or spaces
      (?:\A[\h*\v*])?

      # look for an opening php tag
      (
        (?:<\?)(?:php)?

        # capture everything until the end of the file or a closing php tag
        .+?
          (?:\?>|(?:\?>)?[\h\v]*?\z)
      )
    )`smx';
    //s for PCRE_DOTALL, m for PCRE_MULTILINE, and x for PCRE_EXTENDED

    if (!$isPageContent) {
      $page = file_get_contents($page);
    }

    preg_match($firstPHPRegex, $page, $matches);

    if (isset($matches[1])) {
      return $matches[1];
    } else {
      return null;
    }
  }

  /**
   * Checks to see if the page can be edited
   *
   * @param  string $page Either the page contents or a file path
   * @param  boolean $isPageContent Tells us if the page specified in $page is a file path or page contents.
   * @return boolean
   */
  public static function isPageEditable($page, $isPageContent = false)
  {
    if (!$isPageContent) {
      $page = file_get_contents($page);
    }

    $firstPHPBlock = self::getFirstPHPBlock($page, true);
    if (strpos($firstPHPBlock, '$templatePreferences') !== false && preg_match('`TemplateBuilder\\\Builder(?:\h*as\h*([^;]+))?`sx', $firstPHPBlock, $matches)) {
      // we also need to verify that builder::init is called.
      if (isset($matches[1])) {
        // template builder is included as an alias
        $builderAlias = $matches[1];
      } else {
        $builderAlias = 'Builder';
      }
      if (strpos($page, sprintf('%s::init()', $builderAlias)) !== false) {
        return true;
      }
    }
    return false;
  }
}