<?php
/**
 * @package Concert
 * @author  Billy Visto
 */

namespace Gustavus\Concert;

use Gustavus\Concert\FileConfiguration,
  Gustavus\Concert\Config,
  Gustavus\Concert\PermissionsManager,
  Gustavus\Extensibility\Filters,
  Gustavus\Doctrine\DBAL,
  DateTime,
  RuntimeException;

/**
 * Class for managing a specific file
 *
 * @package Concert
 * @author  Billy Visto
 */
class FileManager
{
  /**
   * Location of the file we are editing
   * @var string
   */
  private $filePath;

  /**
   * Username of the person editing the file
   * @var string
   */
  private $username;

  /**
   * Array containing information about the file built from buildFileConfigurationArray
   * @var array
   */
  private $fileConfigurationArray;

  /**
   * FileConfiguration object representing the current file
   * @var FileConfiguration
   */
  private $fileConfiguration;

  /**
   * Flag for determining if a lock has been acquired
   * @var boolean
   */
  private $lockAcquired;

  /**
   * DBAL connection to use
   *
   * @var \Doctrine\DBAL\Connection
   */
  private $dbal;

  /**
   * Object constructor
   *
   * @param string $filePath Path to the file we are trying to edit
   * @param string $username Username of the person trying to edit the file
   */
  public function __construct($filePath, $username)
  {
    $this->filePath = $filePath;
    $this->username = $username;
  }

  /**
   * Builds a dbal connection if needed and returns it.
   *
   * @return \Doctrine\DBAL\Connection
   */
  private function getDBAL()
  {
    if (empty($this->dbal)) {
      $this->dbal = DBAL::getDBAL(Config::DB);
    }
    return $this->dbal;
  }

  /**
   * Breaks the file up into configurable parts.
   *   Separates php content from other content types
   *
   * @return Array  Array of Arrays keyed by content and phpcontent. They each contain arrays with indexes of the order they appear in the file.
   */
  private function buildFileConfigurationArray()
  {
    // lock acquired.
    $contents = file_get_contents($this->filePath);

    $matches = [];

    // finds any content between the php open/close tags and sets it to be named "phpcontent".
    $phpPiece = '(?:
      # look for newlines or spaces
      (?:\A[\h*\v*])?

      # look for an opening php tag
      (?:<\?)(?:php)?

      # capture everything until the end of the file or a closing php tag
      (?P<phpcontent>.+?
        (?=(?:\?\>|(?:\?\>)?[\h\v]*?\z))
      )
    )';

    // finds any content outside php tags and sets it to be named "content".
    $contentPiece = '(?:
      # look for newlines and not an opening php tag
      (?:\?\>|(?:\A(?!\<\?)))

      # capture until we see the end of the file or an opening php tag
      (?P<content>.+?)(?=<\?(?:php)?|[\h*|\v*]*?\z)
    )';

    // throw the two pieces together into one regex with s for PCRE_DOTALL and m for PCRE_MULTILINE
    $regex = sprintf('`%s|%s`smx', $phpPiece, $contentPiece);

    preg_match_all($regex, $contents, $matches);

    // $matches has a lot of extra information that we don't need, so lets get rid of it.
    $result = [];
    if (isset($matches['phpcontent'])) {
      $result['phpcontent'] = array_filter($matches['phpcontent']);
    }
    if (isset($matches['content'])) {
      $result['content'] = array_filter($matches['content']);
    }

    return $result;
  }

  /**
   * Gets the file configuration array if it exists. Calls buildFileConfiguration if it doesn't.
   *
   * @return Array Array with keys of phpcontent and content from buildFileConfigurationArray
   */
  private function getFileConfigurationArray()
  {
    if (!isset($this->fileConfigurationArray)) {
      $this->fileConfigurationArray = $this->buildFileConfigurationArray();
    }
    return $this->fileConfigurationArray;
  }

  /**
   * Builds a FileConfiguration object from the current file
   *
   * @return FileConfiguration
   */
  private function buildFileConfiguration()
  {
    return new FileConfiguration($this->getFileConfigurationArray());
  }

  /**
   * Gets the file configuration
   *
   * @return FileConfiguration
   */
  private function getFileConfiguration()
  {
    if (!isset($this->fileConfiguration)) {
      $this->fileConfiguration = $this->buildFileConfiguration();
    }
    return $this->fileConfiguration;
  }

  /**
   * Assembles the file from the FileConfiguration
   *
   * @param  boolean $forEditing Whether we want to assemble the file for editing or for saving.
   * @return string
   */
  public function assembleFile($forEditing = false)
  {
    return $this->getFileConfiguration()->buildFile($forEditing);
  }

  // File modifications

  /**
   * Edits the file with the specified contents
   *
   * @param  array  $edits Associative array keyed by fileConfigurationPart index
   * @return boolean True on success false on failure.
   */
  public function editFile(array $edits)
  {
    if (!$this->acquireLock()) {
      // user doesn't have a lock on this file. They shouldn't be able to do anything.
      return false;
    }
    if (isset($_SESSION['concertCMS']['nonEditableKeys'][$this->getFilePathHash()])) {
      // remove any edits that aren't supposed to be editable.
      // This eliminates people from modifying the html to give themselves more editable content
      foreach ($_SESSION['concertCMS']['nonEditableKeys'][$this->getFilePathHash()] as $key) {
        unset($edits[$key]);
      }
    }
    return $this->getFileConfiguration()->editFile($edits);
  }

  /**
   * Saves the current file
   *
   * @todo  should this exist?
   * @return boolean
   */
  private function save()
  {
    if (!$this->acquireLock()) {
      // user doesn't have a lock on this file. They shouldn't be able to do anything.
      return false;
    }
    return $this->saveFile($this->filePath, $this->assembleFile(false));
  }

  /**
   * Saves a file to the destination
   *
   * @param  string $destination Destination of the saved file
   * @param  string $file        File to save
   *
   * @throws  RuntimeException If $destination is not writable
   * @return boolean
   */
  private function saveFile($destination, $file)
  {
    if (is_writable(dirname($destination))) {
      return file_put_contents($destination, $file);
    } else {
      throw new RuntimeException("Unable to write file: {$destination}");
    }
  }

  /**
   * Makes and saves a draft file
   *
   * @return string|boolean String of the draft file. False if saving a draft failed.
   */
  public function makeEditableDraft()
  {
    if (!$this->acquireLock()) {
      // user doesn't have a lock on this file. They shouldn't be able to do anything.
      return false;
    }
    $this->setUpCheckEditableFilter();

    // @todo make use of the file hash and store drafts in the DB.
    //$fileName = str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->filePath);
    $fileName = sprintf('%s/%s-%s', Config::$draftDir, str_replace('/', '_', str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->filePath)), $this->username);

    if ($this->saveFile($fileName, $this->assembleFile(true))) {
      return $fileName;
    }
    return false;
  }

  /**
   * Saves a draft
   *
   * @todo  should a lock be kept open if a draft has been created?
   * @return string|boolean String of the draft file. False if saving a draft failed.
   */
  public function saveDraft()
  {
    if (!$this->acquireLock()) {
      // user doesn't have a lock on this file. They shouldn't be able to do anything.
      return false;
    }

    // @todo make use of the file hash and store drafts in the DB.
    $fileName = sprintf('%s/%s-%s', Config::$draftDir, str_replace('/', '_', str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->filePath)), $this->username);

    if ($this->saveFile($fileName, $this->assembleFile(false))) {
      return $fileName;
    }
    return false;
  }

  /**
   * Throws a file into a staging state waiting to be moved to it's actual location
   *
   * @return boolean True on success, false on failure.
   */
  public function stageFile()
  {
    if (!$this->acquireLock()) {
      // user doesn't have a lock on this file. They shouldn't be able to do anything.
      return false;
    }
    // throw the new file into the pending updates table and throw the file in the staging directory
    $fileHash = $this->getFilePathHash();
    $dbal = $this->getDBAL();

    $properties = [
      'destFilepath' => $this->filePath,
      'srcFilename'  => $fileHash,
      'username'     => $this->username,
      'date'         => new DateTime,
    ];

    $propertyTypes = [
      null,
      null,
      null,
      'datetime',
    ];

    if ($dbal->insert('stagedFiles', $properties, $propertyTypes)) {
      // now we just have to move the file
      if (!is_dir(Config::$stagingDir)) {
        mkdir(Config::$stagingDir);
      }
      if ($this->saveFile(Config::$stagingDir . $fileHash, $this->assembleFile(false))) {
        return Config::$stagingDir . $fileHash;
      }
    }
    // something happened
    return false;
  }

  /**
   * Publishes a file from the staging directory
   *   Note: This should only be called by our watcher script to publish files that get staged. This has to be done by root.
   *
   * @throws RuntimeException If the current user is not root
   * @return boolean True on success false otherwise
   */
  public function publishFile()
  {
    if ($this->username !== 'root') {
      throw new RuntimeException('Only root can publish files');
    }

    $result = $this->getStagedFileEntry();

    if (count($result) > 1) {
      $stagedUsername = null;
      foreach ($result as $resultPiece) {
        if ($stagedUsername === null) {
          // initial set.
          $stagedUsername = $resultPiece['username'];
        } else if ($stagedUsername !== $resultPiece['username']) {
          throw new RuntimeException(sprintf('More than one staged file entry for the file: "%s" was found for different users', basename($this->filePath)));
        }
      }
      // @todo add check to see if the other results are for the same person. If they are, let's use the last result.
    }

    $result = reset($result);

    // now we set our current username to be the username that staged the file, so we can check permissions and publish it for them
    $this->username = $result['username'];
    // this->filePath will more than likely live in a site that no one has access to.
    $srcFilePath = $this->filePath;
    $destination = $result['destFilepath'];
    $this->filePath = $destination;

    if (!$this->acquireLock()) {
      // current user doesn't have a lock on this file. They shouldn't be able to do anything.
      return false;
    }
    // all of our checks are out of the way. Now to move the file
    $group = $this->getGroupForFile($destination);
    $owner = $this->username;

    $this->ensureDirectoryExists(dirname($destination), $owner, $group);

    if (rename($srcFilePath, $destination)) {
      chgrp($destination, $group);
      chown($destination, $owner);

      if (!$this->markStagedFileAsPublished($srcFilePath)) {
        trigger_error(sprintf('The file: "%s" was moved to "%s", but could not be marked as published in the DB', $srcFilePath, $destination));
      }
      $this->destroyLock();
      return true;
    }
    return false;
  }

  /**
   * Gets the stagedFile entry from the DB
   *   <strong>Note:</strong> This should only be called by publishFile.
   *
   * @return boolean|array False if no result was found, array otherwise
   */
  private function getStagedFileEntry()
  {
    $dbal = $this->getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('destFilepath')
      ->addSelect('username')
      ->from('stagedFiles', 'sf')
      ->where('srcFilename = :srcFilename')
      ->andWhere('movedDate IS NULL')
      ->orderBy('date', 'DESC');

    return $dbal->fetchAll($qb->getSQL(), [':srcFilename' => basename($this->filePath)]);
  }

  /**
   * Marks a staged file as published
   *   <strong>Note:</strong> This should only be called by publishFile.
   *
   * @return boolean
   */
  private function markStagedFileAsPublished($stagedFilePath)
  {
    $dbal = $this->getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->update('stagedFiles')
      ->set('movedDate', ':movedDate')
      ->where('username = :username')
      ->andWhere('srcFilename = :srcFilename')
      ->andWhere('movedDate IS NULL');

    return $dbal->executeUpdate($qb->getSQL(), [':movedDate' => new \DateTime, ':username' => $this->username, ':srcFilename' => basename($stagedFilePath)], [':movedDate' => 'datetime', ':username' => null, ':srcFilename' => null]);
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
  private function getGroupForFile($filePath)
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
   * Ensures that the directory exists so we can save the file into that directory
   *   <strong>Note:</strong> This should only be called by publishFile.
   *
   * @param  string $directory Directory to make sure is in existence
   * @param  string $owner     Owner to set for the directory if we are creating a new one
   * @param  string $group     Group the directory should have if we are creating a new one
   *
   * @return boolean
   */
  private function ensureDirectoryExists($directory, $owner, $group)
  {
    if (!is_dir($directory)) {
      if (mkdir($directory, 0777, true)) {
        chgrp($directory, $group);
        chown($directory, $owner);
        return true;
      } else {
        return false;
      }
    }
    return true;
  }

  // Permission functionality

  /**
   * Checks to see if the current user can edit this part
   *
   * @param  string $partName
   * @return boolean
   */
  public function userCanEditPart($partName)
  {
    if (strpos($this->filePath, '/cis/www') === 0) {
      $filePath = substr($this->filePath, 8);
    } else {
      $filePath = $this->filePath;
    }
    return PermissionsManager::userCanEditPart($this->username, $filePath, $partName);
  }

  /**
   * Removes editable divs from the content so the user cannot edit this piece.
   *   Also throws the editable div's data-index into a session variable to verify that it doesn't exist in our edits.
   *
   *   <strong>Note:</strong> If a person's role changes when they are editing, they will have to log out and re log in to be able to edit sections they were no longer able to edit.
   *
   * @param  string $content The content to remove editable divs from
   * @return string
   */
  private function removeEditablePieces($content)
  {
    $indexPattern = '`<div[^<]+class[^<]+?editable[^>]+?data-index\=(?:\'|")([^>]+)(?:\'|")>`';
    preg_match_all($indexPattern, $content, $matches);
    if (isset($matches[1])) {
      $_SESSION['concertCMS']['nonEditableKeys'][$this->getFilePathHash()] = $matches[1];
    }

    $pattern = sprintf('`(?:<div[^<]+class[^<]+?editable[^>]+?>)|(?:</div>%s)`', Config::EDITABLE_DIV_CLOSING_IDENTIFIER);
    return preg_replace($pattern, '', $content);
  }

  /**
   * Sets up a filter that will remove the editable identifiers from sections that actually aren't editable by this person.
   *
   * @return  void
   */
  private function setUpCheckEditableFilter()
  {
    Filters::add('concertCMSCheckEditable', function($content, $tag) {
      if (!$this->userCanEditPart($tag)) {
        // strip editable piece
        return $this->removeEditablePieces($content);
        //return $strippedContent;
      }
      return $content;
    });
  }

  /**
   * Builds a hash from the current filename
   *
   * @return string
   */
  private function getFilePathHash()
  {
    return md5($this->filePath);
  }

  /**
   * Checks to see if the user has permission to edit this file
   * @return boolean
   */
  private function userCanEditFile()
  {
    if (strpos($this->filePath, '/cis/www') === 0) {
      $filePath = substr($this->filePath, 8);
    } else {
      $filePath = $this->filePath;
    }
    return PermissionsManager::userCanEditFile($this->username, $filePath);
  }

  // Lock functions

  /**
   * Creates a lock.
   *   <strong>Note:</strong> This doesn't check to see if you have permission to create a lock or not.
   *
   * @return boolean True on success, false on failure
   */
  private function createLock()
  {
    $dbal = $this->getDBAL();
    $properties = [
      'filepathHash' => $this->getFilePathHash(),
      'filepath'     => $this->filePath,
      'username'     => $this->username,
      'date'         => new \DateTime(),
    ];
    $propertyTypes = [
      null,
      null,
      null,
      'datetime',
    ];

    if ($dbal->insert('locks', $properties, $propertyTypes)) {
      return true;
    }
    return false;
  }

  /**
   * Creates a lock.
   *   <strong>Note:</strong> This doesn't check to see if you have permission to create a lock or not.
   *
   * @return boolean True on success, false on failure
   */
  private function updateLock()
  {
    $dbal = $this->getDBAL();
    $properties = [
      'filepathHash' => $this->getFilePathHash(),
      'filepath'     => $this->filePath,
      'username'     => $this->username,
      'date'         => new \DateTime(),
    ];
    $propertyTypes = [
      null,
      null,
      null,
      'datetime',
    ];

    $dbal->update('locks', $properties, ['username' => $this->username, 'filepathHash' => $this->getFilePathHash()], $propertyTypes);

    return true;
  }

  /**
   * Destroys the lock
   *   <strong>Note:</strong> This doesn't check to see if you have permission to create a lock or not.
   *
   * @return boolean Always returns true. If no rows were modified, there was no lock to delete, so we still return true as it has been "destroyed".
   */
  private function destroyLock()
  {
    $dbal = $this->getDBAL();

    if ($dbal->delete('locks', ['username' => $this->username, 'filepathHash' => $this->getFilePathHash()])) {
      return true;
    } else {
      // no lock was ever created. Return true since it has already been destroyed. (Or never existed)
      return true;
    }
  }

  /**
   * Fetches a lock from the DB
   *
   * @return array|boolean False if the lock doesn't exist, array of lock username and date if one exists.
   */
  private function getLockFromDB()
  {
    $dbal = $this->getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('username')
      ->addSelect('date')
      ->from('locks', 'l')
      ->where('filepathHash = :filepathHash');

    return $dbal->fetchAssoc($qb->getSQL(), [':filepathHash' => $this->getFilePathHash()]);
  }

  /**
   * Checks to see if the current user has a lock for the current file
   *
   * @return boolean
   */
  public function userHasLock()
  {
    $lock = $this->getLockFromDB();

    if ($lock && $lock['username'] === $this->username) {

      $minutes = $this->getLockDuration($lock['date']);

      if ($minutes > Config::LOCK_DURATION) {
        // user's lock has expired.
        return false;
      }

      return true;
    }
    return false;
  }

  /**
   * Stops the edit process and releases the lock
   *
   * @return void
   */
  public function stopEditing()
  {
    $this->destroyLock();
  }

  /**
   * Attempts to acquire a lock on the current file
   *
   * @return boolean|integer True if the lock was acquired. Minutes left until the lock expires if the lock couldn't be acquired.
   *
   * @todo  Test this with multiple simultaneous attempts
   */
  public function acquireLock()
  {
    if (isset($this->lockAcquired)) {
      // we have already tried to acquire a lock
      if ($this->lockAcquired) {
        // we have already acquired a lock.
        return true;
      }
      return false;
    }

    if (!$this->userCanEditFile()) {
      return false;
    }

    $lock = $this->getLockFromDB();

    if ($lock) {
      // lock exists
      // check to see if the lock belongs to the current user
      if ($this->username === $lock['username']) {
        // update our lock's time
        $this->lockAcquired = $this->updateLock();
        return $this->lockAcquired;
      }

      //check how long the lock has been left open.
      $minutes = $this->getLockDuration($lock['date']);

      if ($minutes > Config::LOCK_DURATION) {
        // we first need to destroy the expired lock.
        (new FileManager($this->filePath, $lock['username']))->stopEditing();
        // now we can create a new one.
        $this->lockAcquired = $this->createLock();
        return $this->lockAcquired;
      } else {
        $this->lockAcquired = false;
        return $this->lockAcquired;
        //return Config::LOCK_DURATION - $minutes; @todo Do something with the time remaining?
      }
    } else {
      // make a lock
      $this->lockAcquired = $this->createLock();
      return $this->lockAcquired;
    }
  }

  /**
   * Gets the number of minutes the lock has been open for.
   *
   * @param  string $date Date from mysql directly
   * @return integer Minutes the lock has been open for.
   */
  private function getLockDuration($date)
  {
    $lockDate = (new DateTime($date))->format('U');
    return (int) (time() - $lockDate);
  }
}