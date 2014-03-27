<?php
/**
 * @package CMS
 * @author  Billy Visto
 */

namespace Gustavus\CMS;

use Gustavus\CMS\FileConfiguration,
  Gustavus\CMS\Config,
  DateTime;

/**
 * Class for managing a specific file
 *
 * @package CMS
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
    // @todo add check to make sure they can edit
    //$fileName = str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->filePath);
    $fileName = sprintf('/cis/lib/Gustavus/CMS/Web/drafts/%s-%s', str_replace('/', '_', str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->filePath)), $this->username);

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
   * @return boolean
   */
  private function saveFile($destination, $file, $username = null)
  {
    return file_put_contents($destination, $file);
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

  public function test()
  {
    // require_once 'PHPParser/bootstrap.php';
    // $parser = new PHPParser_Parser(new PHPParser_Lexer);
    // // $traverser     = new PHPParser_NodeTraverser;
    // // $prettyPrinter = new PHPParser_PrettyPrinter_Zend;

    // $stmts = $parser->parse(file_get_contents($this->filePath));

    // // $stmts = $traverser->traverse($stmts);

    // // // pretty print
    // // var_dump('<?php ' . $prettyPrinter->prettyPrint($stmts));
    // // exit;
    // var_dump($stmts);
    // exit;

    $configuration = $this->buildConfiguration();
    //$configuration->getConfigurationParts()[1]->parseContent();
    //var_dump($configuration->buildFile());
    $this->saveFile('indexTest2.php', $configuration->buildFile());
    // $config = $this->buildFileConfigurationArray();
    // $file = $this->assembleFile($config);
    // $this->saveFile($file, 'indexTest.php');
  }

}

// $fileManager = new FileManager('index.php', 'billy');

// $fileManager->test();