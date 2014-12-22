<?php
/**
 * @package  Concert
 * @subpackage Scripts\Mover
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Scripts\Mover;

use Gustavus\Concert\Utility,
  Gustavus\Concert\Config,
  Gustavus\Concert\FileManager,
  Gustavus\Concert\PermissionsManager,
  Gustavus\Doctrine\DBAL,
  RuntimeException;

/**
 * Class to handle moving page to a new location
 *
 * @package  Concert
 * @subpackage Scripts\Mover
 * @author  Billy Visto
 */
class PageMover
{
  /**
   * Original path to the page we are moving
   *
   * @var string
   */
  private $filePath;

  /**
   * Destination of the file's new location
   *
   * @var string
   */
  private $destinationPath;

  /**
   * Flag to determine if we are actually moving files on the filesystem or just moving configurations
   *
   * @var boolean
   */
  private $touchFilesystem;

  /**
   * Array of any errors encountered
   *
   * @var array
   */
  private $errors = [];

  /**
   * Placeholder for any sites that we have adjusted configurations for
   *
   * @var array
   */
  private $adjustedSites = [];

  /**
   * Placeholder for stats of files that we have adjusted
   *
   * @var array
   */
  private $adjustedFiles = [];

  /**
   * Placeholder for any files that we have moved
   *
   * @var array
   */
  private $movedFiles = [];

  /**
   * DBAL connection to use
   *
   * @var \Doctrine\DBAL\Connection
   */
  private static $dbal;

  /**
   * Constructor
   *
   * @param string $filePath Path to the file to move
   * @param string $destinationPath Path to the file's new destination to move
   * @param  boolean $touchFilesystem Whether to touch the filesystem or not.
   *   Will be false if the user has manually moved the directory and just needs to update configurations.
   *
   * @throws  RuntimeException If the specified file doesn't exist
   * @throws  RuntimeException If the destination path already exists
   *
   * @return  void
   */
  public function __construct($filePath, $destinationPath, $touchFilesystem = true)
  {
    if (!file_exists($filePath) && $touchFilesystem) {
      throw new RuntimeException(sprintf('The specified file: "%s" doesn\'t exist', $filePath));
    }
    if (file_exists($destinationPath) && $touchFilesystem) {
      throw new RuntimeException(sprintf('The specified destination: "%s" already exists', $destinationPath));
    }

    if (is_dir($filePath)) {
      // we need to make sure they both have a trailing slash.
      $filePath        .= '/';
      $destinationPath .= '/';
    }

    $this->filePath        = preg_replace('`/+`', '/', $filePath);
    $this->destinationPath = preg_replace('`/+`', '/', $destinationPath);
    $this->touchFilesystem = $touchFilesystem;
  }

  /**
   * Builds a dbal connection if needed and returns it.
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
   * Moves revisions to point to the new file location
   *
   * @param  string $originalPath  Original file location
   * @param  string $newPath       New file location
   * @return integer Number of affected rows
   */
  private static function adjustRevisions($originalPath, $newPath)
  {
    $originalPathHash = Utility::buildRevisionsFileHash($originalPath);
    $newPathHash      = Utility::buildRevisionsFileHash($newPath);

    $dbal = self::getDBAL();

    return $dbal->update('revision', ['`table`' => $newPathHash], ['`table`' => $originalPathHash]);
  }

  /**
   * Adjusts drafts to point to the new file location
   *
   * @param  string $originalPath  Original file location
   * @param  string $newPath       New file location
   *
   * @throws  RuntimeException If draft couldn't be moved
   * @return integer Number of drafts adjusted
   */
  private function adjustDrafts($originalPath, $newPath)
  {
    $dbal = self::getDBAL();
    // find drafts that exist for the current file
    $fm = new FileManager('root', $originalPath, null, $dbal);
    $draftName = $fm->getFilePathHash();
    $drafts = $fm->getDrafts();

    // make a new FileManager for the new path
    $newFm = new FileManager('root', $newPath, null, $dbal);
    $newDraftName = $newFm->getFilePathHash();
    $successes = 0;

    foreach ($drafts as $draft) {
      $originalDraftFilename = $draft['draftFilename'];
      $newDraftFilename = $newFm->getDraftFileName($draft['username']);

      $dbal->beginTransaction();
      $result = $dbal->update(
          'drafts',
          [
            'destFilepath'  => $newPath,
            'draftFilename' => $newDraftFilename,
            'draftName'     => $newDraftName
          ],
          [
            'draftFilename' => $originalDraftFilename,
            'username'      => $draft['username'],
            'draftName'     => $draftName
          ]
      );

      try {
        $dbal->commit();
      } catch (Exception $e) {
        $dbal->rollback();
        $this->errors[] = $e->getMessage();
        return false;
      }

      if ($result) {
        // actually move the draft.
        if (!rename(Config::$draftDir . $originalDraftFilename, Config::$draftDir . $newDraftFilename)) {
          $this->errors[] = sprintf('The draft: %s couldn\'t be renamed to: %s', $originalDraftFilename, $newDraftFilename);
        }
        ++$successes;
      }
    }
    return $successes;
  }

  /**
   * Handles moving of a site's siteRoot
   *
   * @param  string $siteLocation    Location of the current site
   * @param  string $newSiteLocation New location of the site
   * @return boolean
   */
  private function adjustSite($siteLocation, $newSiteLocation)
  {
    if (!isset($this->adjustedSites[$siteLocation])) {
      $siteId = PermissionsManager::getSiteId(Utility::removeDocRootFromPath($siteLocation));

      if (PermissionsManager::moveSite($siteId, preg_replace('`/+`', '/', Utility::removeDocRootFromPath($newSiteLocation))) > 0) {
        $this->adjustedSites[] = $siteLocation;
        return true;
      }
    }
    return false;
  }

  /**
   * Adjusts a single file's pointers to resolve to the new location
   *
   * @param  string $filePath     Path of the file to adjust
   * @param  string $newFilePath Destination of the new file location
   * @return boolean
   */
  private function adjustFile($filePath, $newFilePath)
  {
    $revisionsUpdated = self::adjustRevisions($filePath, $newFilePath);
    $draftsUpdated    = $this->adjustDrafts($filePath, $newFilePath);

    $this->adjustedFiles[$filePath] = [
      'newLocation'      => $newFilePath,
      'revisionsUpdated' => $revisionsUpdated,
      'draftsUpdated'    => $draftsUpdated,
    ];
    return true;
  }

  /**
   * Adjusts all staged files to the new location
   *
   * @return integer Number of successfully adjusted files
   */
  private function adjustStagedFiles()
  {
    $this->adjustDeletedFiles();

    // adjust actual staged file entries.
    $dbal = self::getDBAL();

    $qb = $dbal->createQueryBuilder();

    $qb->addSelect('destFilepath')
      ->addSelect('id')
      ->addSelect('srcFilename')
      ->from('stagedFiles', 'sf');

    $destFilepath = $this->filePath;
    if (is_dir($this->destinationPath)) {
      // we might have multipe files
      $qb->where($qb->expr()->like('destFilepath', ':destFilepath'));
      $destFilepath .= '%';
    } else {
      $qb->where('destFilepath = :destFilepath');
    }

    $stagedFiles = $dbal->fetchAll($qb->getSQL(), [':destFilepath' => $destFilepath]);
    // create a fileManager so we can use it to build our fileHashes
    $fm = new FileManager('root', $destFilepath, null, $dbal);
    $successes = 0;

    foreach ($stagedFiles as $stagedFile) {
      $newDest = str_replace($this->filePath, $this->destinationPath, $stagedFile['destFilepath']);
      $newData = [
        'destFilepath' => $newDest,
        'srcFilename'  => $fm->getFilePathHash($newDest),
      ];

      $dbal->beginTransaction();
      $result = $dbal->update('stagedFiles', $newData, ['id' => $stagedFile['id']]);

      try {
        $dbal->commit();
      } catch (Exception $e) {
        $dbal->rollback();
        $this->errors[] = $e->getMessage();
        return false;
      }

      if ($result) {
        ++$successes;
        if (!file_exists(Config::$stagingDir . $stagedFile['srcFilename'])) {
          continue;
        }
        // actually move the stagedFile.
        if (!rename(Config::$stagingDir . $stagedFile['srcFilename'], Config::$stagingDir . $newData['srcFilename'])) {
          $this->errors[] = sprintf('The staged file: %s couldn\'t be renamed to: %s', Config::$stagingDir . $stagedFile['srcFilename'], Config::$stagingDir . $newData['srcFilename']);
        }
      }
    }
    return $successes;
  }

  /**
   * Gets deleted files from the location prior to being moved.
   *   Note: Checks things against the destination, so this should be ran after files have been moved.
   *
   * @return array Array of deleted files with keys of destFilepath and srcFilename
   */
  private function getDeletedFiles()
  {
    $dbal = self::getDBAL();

    $qb = $dbal->createQueryBuilder();

    $qb->addSelect('destFilepath')
      ->from('stagedFiles', 'sf')
      ->where('action = :deleteAction')
      ->andWhere('publishedDate IS NOT NULL');

    $destFilepath = $this->filePath;
    if (is_dir($this->destinationPath)) {
      // we might have multipe files
      $qb->andWhere($qb->expr()->like('destFilepath', ':destFilepath'));
      $destFilepath .= '%';
    } else {
      $qb->andWhere('destFilepath = :destFilepath');
    }

    return $dbal->fetchAll($qb->getSQL(), [':deleteAction' => Config::DELETE_STAGE, ':destFilepath' => $destFilepath]);
  }

  /**
   * Adjusts all deleted files that have been staged and published
   *   This allows you to maintain revisions or drafts to files that have been deleted.
   *
   * @return void
   */
  private function adjustDeletedFiles()
  {
    $dbal = self::getDBAL();
    $deletedFiles = $this->getDeletedFiles();

    foreach ($deletedFiles as $deletedFile) {
      $oldPath = $deletedFile['destFilepath'];
      $newPath = str_replace($this->filePath, $this->destinationPath, $oldPath);
      $this->adjustFile($oldPath, $newPath);
    }
  }

  /**
   * Adjusts all moved files
   *
   * @param  string $filePath    Original path
   * @param  string $newFilePath New path
   * @return boolean
   */
  private function adjustMovedFiles($filePath, $newFilePath)
  {
    if (is_file($newFilePath)) {
      $this->adjustFile($filePath, $newFilePath);
      return true;
    } else if (is_dir($newFilePath)) {

      $dirContents = scandir($newFilePath);
      foreach ($dirContents as $file) {
        if ($file === '.' || $file === '..') {
          continue;
        }
        if (is_dir($newFilePath . $file)) {
          $this->adjustMovedFiles($filePath . $file . '/', $newFilePath . $file . '/');
        } else if (is_file($newFilePath . $file)) {
          $this->adjustFile($filePath . $file, $newFilePath . $file);
        }
      }
      return true;
    }
    return false;
  }

  /**
   * Moves files on the filesystem
   *
   * @return void
   */
  private function moveFiles()
  {
    if (is_dir($this->filePath) && strpos($this->destinationPath, $this->filePath) === 0) {
      // we are trying to move the file within the current directory.
      // We can't just call rename because you can't move a directory within itself.

      mkdir($this->destinationPath, 0775, true);
      $group = Utility::getGroupForFile($this->filePath);

      $oid     = fileowner($this->filePath);
      $pwuData = posix_getpwuid($oid);
      $owner   = $pwuData['name'];


      chgrp($this->destinationPath, $group);
      chown($this->destinationPath, $owner);

      $dirContents = scandir($this->filePath);
      foreach ($dirContents as $file) {
        // loop through all contents of current directory and move them to the new directory
        if ($file === '.' || $file === '..' || $this->filePath . $file . '/' === $this->destinationPath) {
          continue;
        }
        if (rename($this->filePath . $file, $this->destinationPath . $file)) {
          $this->movedFiles[$this->filePath . $file] = $this->destinationPath . $file;
        }
      }
      $this->adjustMovedFiles($this->filePath, $this->destinationPath);
    } else if (rename($this->filePath, $this->destinationPath)) {
      // Files have been successfully moved.
      // Now we need to adjust any pointers to these files
      $this->movedFiles[$this->filePath] = $this->destinationPath;
      $this->adjustMovedFiles($this->filePath, $this->destinationPath);
    } else {
      $this->errors[] = 'Nothing moved';
    }
  }

  /**
   * Moves the file or files
   *
   * @return boolean
   */
  public function move()
  {
    if ($this->touchFilesystem) {
      $this->moveFiles();
    } else {
      $this->adjustMovedFiles($this->filePath, $this->destinationPath);
    }

    // adjust staged files
    $this->adjustStagedFiles();

    // Adjust sites if needed
    if (is_dir($this->destinationPath)) {
      // we moved a directory. We might need to update any sites that exist for the directory
      $pathFromDocRoot = Utility::removeDocRootFromPath($this->filePath);
      $destFromDocRoot = Utility::removeDocRootFromPath($this->destinationPath);
      // We need to know what sites exist in this directory so we can update them accordingly
      $subSites = PermissionsManager::getSitesFromBase($pathFromDocRoot);

      // now we need to update any sites that were moved
      foreach ($subSites as $subSite) {
        $newSubSite = str_replace($pathFromDocRoot, $destFromDocRoot, $subSite);
        $this->adjustSite($subSite, $newSubSite);
      }
    }

    return [
      'adjustedFiles' => $this->adjustedFiles,
      'adjustedSites' => $this->adjustedSites,
      'movedFiles'    => $this->movedFiles,
      'errors'        => $this->errors,
    ];
  }
}