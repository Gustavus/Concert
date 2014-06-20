<?php
/**
 * @package ConcertCMS
 * @author  Billy Visto
 */

namespace Gustavus\ConcertCMS;

use Gustavus\ConcertCMS\FileConfiguration,
  Gustavus\ConcertCMS\Config,
  Gustavus\Extensibility\Filters,
  DateTime,
  RuntimeException;

/**
 * Class for managing a specific file
 *
 * @todo  add functions to save to db and what not?
 *
 * @package ConcertCMS
 * @author  Billy Visto
 */
class FileManager
{
  const LOCK_DURATION = 10;

  const LOCK_SUFFIX = '.lock';

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
   * Object constructor
   *
   * @param string $filePath Path to the file we are trying to edit
   * @param string $username Username of the person trying to edit the file
   */
  public function __construct($filePath, $username)
  {
    $this->filePath = $filePath;
    $this->lockFilePath = $filePath . self::LOCK_SUFFIX;
    $this->username = $username;
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
   * Makes and saves a draft file
   *
   * @return string|boolean String of the draft file. False if saving a draft failed.
   */
  public function makeDraft()
  {
    $this->setUpCheckEditableFilter();
    // @todo add check to make sure they can edit
    //$fileName = str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->filePath);
    $fileName = sprintf('%s/%s-%s', Config::DRAFT_DIR, str_replace('/', '_', str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->filePath)), $this->username);

    if ($this->saveFile($fileName, $this->assembleFile(true))) {
      return $fileName;
    }
    return false;
  }

  /**
   * Edits the file with the specified contents
   *
   * @param  array  $edits Associative array keyed by fileConfigurationPart index
   * @return boolean True on success false on failure.
   */
  public function editFile(array $edits)
  {
    if (isset($_SESSION['concertCMS']['nonEditableKeys'])) {
      // remove any edits that aren't supposed to be editable.
      // This eliminates people from modifying the html to give themselves more editable content
      foreach ($_SESSION['concertCMS']['nonEditableKeys'] as $key) {
        unset($edits[$key]);
      }
    }
    // @todo check lock?
    return $this->getFileConfiguration()->editFile($edits);
  }

  /**
   * Saves the current file
   *
   * @return boolean
   */
  public function save()
  {
    // @todo check lock?
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
  private function saveFile($destination, $file, $username = null)
  {
    if (is_writable(dirname($destination))) {
      return file_put_contents($destination, $file);
    } else {
      throw new RuntimeException("Unable to write file: {$destination}");
    }
  }

  // Lock functions

  /**
   * Creates a lock file.
   *   <strong>Note:</strong> This doesn't check to see if you have permission to create a lock or not.
   *
   * @return boolean True on success, false on failure
   * @todo  this should put something into the lock table
   */
  public function createLock()
  {
    $lockContents = json_encode(['lockedDate' => (new DateTime)->format('m/d/Y H:i:s'), 'lockedBy' => $this->username]);
    if ($this->saveFile($this->lockFilePath, $lockContents, $this->username)) {
      return true;
    }
    return false;
  }

  /**
   * Destroys the lock file
   *
   * @return boolean True on success, false on failure
   * @todo  this should do something with the lock table
   */
  public function destroyLock()
  {
    if (file_exists($this->lockFilePath)) {
      return unlink($this->lockFilePath);
    }
    // file doesn't exist. no need to do anything.
    return true;
  }

  /**
   * Attempts to acquire a lock on the current file
   *
   * @return boolean|integer True if the lock was acquired. Minutes left until the lock expires if the lock couldn't be acquired.
   *
   * @todo  Should we re-create the lock if it belongs to the current user?
   * @todo  This should put something in the lock table
   */
  public function acquireLock()
  {
    // search for lock on this filePath
    if (file_exists($this->lockFilePath)) {
      $lockProperties = $this->getLockFileProperties();
      // check to see if the lock file belongs to the current user
      if ($this->username === $this->getUsernameFromLockProperties($lockProperties)) {
        // re-create our lock
        return $this->createLock();
      }
      // check how long the lock has been left open for.
      // $fileMTime = filemtime($this->lockFilePath);
      // $fileMDate = new DateTime('@' . $fileMTime);
      $lockDate = $this->getDateFromLockProperties($lockProperties);
      var_dump($lockDate);
      $diff = $lockDate->diff(new DateTime);
      $minutes = $diff->format('%I');
      if ($minutes > self::LOCK_DURATION) {
        return $this->createLock();
      } else {
        return self::LOCK_DURATION - $minutes;
      }
    } else {
      // make a lock
      return $this->createLock();
    }
  }

  /**
   * Parses the lockfile into a property array
   *
   * @return array Array with keys of "lockedBy" and "lockedDate".
   * @todo  this should do something with the lock table
   */
  private function getLockFileProperties()
  {
    $file = file_get_contents($this->lockFilePath);
    return json_decode($file, true);
  }

  /**
   * Gets the username the lock file belongs to from the lock file's properties
   * @param  Array   $lockProperties array of lock file properties
   * @return string|null  username if it exists in the lockfile, null otherwise
   * @todo  this should do something with the lock table
   */
  private function getUsernameFromLockProperties($lockProperties)
  {
    if (isset($lockProperties['lockedBy'])) {
      return $lockProperties['lockedBy'];
    }
    return null;
  }

  /**
   * Gets the username the lock file belongs to from the lock file's properties
   * @param  Array   $lockProperties array of lock file properties
   * @return string|null  username if it exists in the lockfile, null otherwise
   * @todo  this should do something with the lock table
   */
  private function getDateFromLockProperties($lockProperties)
  {
    if (isset($lockProperties['lockedDate'])) {
      return new DateTime($lockProperties['lockedDate']);
    }
    return null;
  }

  // publishing, staging, and draft functionality

  /**
   * Publishes a file from the staging directory
   *
   * @return boolean True on success false otherwise
   */
  public function publishFile()
  {
    // look to DB and make sure the current file is waiting to be published.
    //
  }

  public function stageFile()
  {
    // throw the new file into the pending updates table and throw the file in the staging directory
  }

  public function userCanEditFile()
  {
    // checks to see if the user has access to edit the page
  }

  public function getUserAccessLevel()
  {
    # code...
  }

  /**
   * Checks to see if the current user can edit this part
   *
   * @param  string $partName
   * @return boolean
   */
  public function userCanEditPart($partName)
  {
    // @todo get the user's access level. These levels will have certain editable pieces. Just check against that. :)
    if ($partName === 'FocusBox') {
      return false;
    }
    return true;

  }

  /**
   * Removes editable divs from the content so the user cannot edit this piece.
   *   Also throws the editable div's data-index into a session variable to verify that it doesn't exist in our edits.
   *
   * @param  string $content The content to remove editable divs from
   * @return string
   */
  private function removeEditablePieces($content)
  {
    $indexPattern = '`<div[^<]+class[^<]+?editable[^>]+?data-index\=(?:\'|")([^>]+)(?:\'|")>`';
    preg_match_all($indexPattern, $content, $matches);
    if (isset($matches[1])) {
      $_SESSION['concertCMS']['nonEditableKeys'] = $matches[1];
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
}