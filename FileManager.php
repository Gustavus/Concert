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
   * Location of the source file we are basing our new file off of
   * @var string
   */
  private $srcFilePath;

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
   * Flag that lets us know if the user is editing a public draft or not
   *
   * @var boolean
   */
  private $userIsEditingPublicDraft = false;

  /**
   * Object constructor
   *
   * @param string $username Username of the person trying to edit the file
   * @param string $filePath Path to the file we are trying to edit
   * @param string $srcFilePath Path to the file we are creating a file from
   * @param Connection $dbal Doctrine connection to use
   */
  public function __construct($username, $filePath, $srcFilePath = null, $dbal = null)
  {
    $this->filePath = $filePath;
    $this->username = $username;
    $this->srcFilePath = $srcFilePath;
    $this->dbal = $dbal;
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
    if (isset($this->srcFilePath) && file_exists($this->srcFilePath)) {
      $contents = file_get_contents($this->srcFilePath);
    } else if (file_exists($this->filePath)) {
      $contents = file_get_contents($this->filePath);
    } else {
      throw new RuntimeException(sprintf('filePath: %s or srcFilePath: %s do not exist', $this->filePath, $this->srcFilePath));
    }

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

  // Draft Functionality

  /**
   * Builds the file name to use for drafts. This is the hash of the filePath hash with the username of the current user added onto it
   *
   * @param  string $username Username to get the draft file name for
   * @return string
   */
  public function getDraftFileName($username = null, $fromDraftDir = false)
  {
    if ($username === null) {
      $username = $this->username;
    }
    $fileName = $this->getFilePathHash(sprintf('%s-%s', $this->getFilePathHash(), $username));

    return ($fromDraftDir) ? Config::$draftDir . $fileName : $fileName;
  }

  /**
   * Makes and saves a draft file
   *
   * @param boolean $fromExistingDraft Whether to make an editable draft from an existing draft or not
   * @return string|boolean String of the draft file. False if saving a draft failed.
   */
  public function makeEditableDraft($fromExistingDraft = false)
  {
    if (!$this->acquireLock()) {
      // user doesn't have a lock on this file. They shouldn't be able to do anything.
      return false;
    }
    $this->setUpCheckEditableFilter();

    // save the draft in our editable draft folder
    if (!is_dir(Config::$editableDraftDir)) {
      mkdir(Config::$editableDraftDir);
    }

    $fileName = Config::$editableDraftDir . $this->getDraftFileName();

    if ($fromExistingDraft && $this->draftExists()) {
      $result = $this->getDraftForUser($this->username);
      $draftFilePath = $this->getDraftFileName();

      $fm = new FileManager($this->username, Config::$draftDir . $draftFilePath);
      $fileContents = $fm->assembleFile(true);
    } else {
      $fileContents = $this->assembleFile(true);
    }

    if ($this->saveFile($fileName, $fileContents)) {
      return $fileName;
    }
    return false;
  }

  /**
   * Saves a draft
   *
   * @todo  should a lock be kept open if a draft has been created?
   * @param string $type Draft type (private, pendingPublish, public)
   * @return string|boolean String of the draft file. False if saving a draft failed.
   */
  public function saveDraft($type, array $additionalUsers = null)
  {
    if (!$this->acquireLock()) {
      // user doesn't have a lock on this file. They shouldn't be able to do anything.
      return false;
    }

    if ($this->userIsEditingPublicDraft) {
      // we simply want to save our current configuration
      return $this->saveFile($this->filePath, $this->assembleFile(false));
    }

    // @todo make use of the file hash and store drafts in the DB.
    $draftName = $this->getFilePathHash();
    $draftFileName = $this->getDraftFileName();
    $dbal = $this->getDBAL();

    if ($additionalUsers !== null && $additionalUsers !== false) {
      $additionalUsers = implode(',', $additionalUsers);
    }

    $properties = [
      'draftFileName'   => $draftFileName,
      'destFilepath'    => $this->filePath,
      'draftName'       => $draftName,
      'username'        => $this->username,
      'type'            => $type,
      'date'            => new DateTime,
    ];

    $propertyTypes = [
      null,
      null,
      null,
      null,
      null,
      'datetime',
    ];

    if (!empty($additionalUsers)) {
      // if additionalUsers is false, it means we want to remove them
      $properties['additionalUsers'] = ($additionalUsers !== false) ? $additionalUsers : null;
      $propertyTypes[] = null;
    }

    $draft = $this->getDraftForUser($this->username);
    if (!empty($draft)) {
      $result = $dbal->update('drafts', $properties, ['draftFileName' => $draft['draftFileName']], $propertyTypes);
    } else {
      $result = $dbal->insert('drafts', $properties, $propertyTypes);
    }
    if ($result) {
      // now we just have to save the draft
      if (!is_dir(Config::$draftDir)) {
        mkdir(Config::$draftDir);
      }
      $fileName = Config::$draftDir . $draftFileName;

      if ($this->saveFile($fileName, $this->assembleFile(false))) {
        return $fileName;
      }
    }
    // something failed
    return false;
  }

  /**
   * Destroys any draft leftovers for the current file
   *
   * @param  string $draftFileName Draft to destroy
   * @return void
   */
  public function destroyDraft($draftFileName = null)
  {
    if ($draftFileName === null) {
      $draftFileName = $this->getDraftFileName();
    }
    $draft = $this->getDraft($draftFileName);

    $dbal = $this->getDBAL();

    $dbal->delete('drafts', ['draftFileName' => $draftFileName]);
    foreach ([Config::$draftDir, Config::$editableDraftDir] as $draftDir) {
      $fileName = $draftDir . $draftFileName;
      if (file_exists($fileName)) {
        unlink($fileName);
      }
    }
  }

  /**
   * Gets all the drafts for the current file
   *   Filters by type if $type is specified
   *
   * @param  string $type Type of drafts to get
   * @return array
   */
  private function getDrafts($type = null)
  {
    $dbal = $this->getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('destFilepath')
      ->addSelect('draftFileName')
      ->addSelect('type')
      ->addSelect('username')
      ->addSelect('additionalUsers')
      ->from('drafts', 'd')
      ->where('draftName = ?')
      ->andWhere('publishedDate IS NULL')
      ->orderBy('date', 'DESC');

    $properties = [$this->getFilePathHash()];
    $propertyTypes = [null];

    if ($type !== null) {
      if (is_array($type)) {
        $qb->andWhere('type IN (?)');
        $propertyTypes[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
      } else {
        $qb->andWhere('type = ?');
        $propertyTypes[] = null;
      }
      $properties[] = $type;
    }

    $result = $dbal->fetchAll($qb->getSQL(), $properties, $propertyTypes);
    foreach ($result as &$resultPiece) {
      if (!empty($resultPiece['additionalUsers'])) {
        $resultPiece['additionalUsers'] = explode(',', $resultPiece['additionalUsers']);
      }
    }
    return $result;
  }

  /**
   * Gets the current draft for the specified user
   *
   * @param  string $username Username to get the draft for
   * @return array
   */
  private function getDraftForUser($username)
  {
    $dbal = $this->getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('destFilepath')
      ->addSelect('draftFileName')
      ->addSelect('type')
      ->addSelect('username')
      ->addSelect('additionalUsers')
      ->from('drafts', 'd')
      ->where('draftFileName = :draftFileName')
      ->andWhere('publishedDate IS NULL');

    $result = $dbal->fetchAssoc($qb->getSQL(), [':draftFileName' => $this->getDraftFileName($username)]);
    if (!empty($result['additionalUsers'])) {
      $result['additionalUsers'] = explode(',', $result['additionalUsers']);
    }
    return $result;
  }

  /**
   * Gets the specified public draft
   *
   * @param  string $draftFileName Name of the draft to get
   * @return array
   */
  public function getDraft($draftFileName)
  {
    $dbal = $this->getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('destFilepath')
      ->addSelect('draftFileName')
      ->addSelect('type')
      ->addSelect('username')
      ->addSelect('additionalUsers')
      ->from('drafts', 'd')
      ->where('draftFileName = :draftFileName')
      ->andWhere('publishedDate IS NULL');

    $result =  $dbal->fetchAssoc($qb->getSQL(), [':draftFileName' => $draftFileName]);
    if (!empty($result['additionalUsers'])) {
      $result['additionalUsers'] = explode(',', $result['additionalUsers']);
    }
    return $result;
  }

  /**
   * Checks to see if a draft exists for the current file
   *
   * @return boolean
   */
  public function draftExists()
  {
    $result = $this->getDrafts();

    if (empty($result)) {
      return false;
    } else {
      return true;
    }
  }

  /**
   * Checks to see if the user has a draft open for this file
   *
   * @return boolean
   */
  public function userHasOpenDraft()
  {
    if (!$this->acquireLock()) {
      return false;
    }

    $draft = $this->getDraftForUser($this->username);
    return !empty($draft);
  }

  /**
   * Finds all the drafts the current user has access to edit
   *
   * @param  string $draft Draft filename to get
   * @return array|null
   */
  public function findDraftsForCurrentUser($draftFileName = null)
  {
    $drafts = [];
    $userDraft = $this->getDraftForUser($this->username);
    if (!empty($userDraft)) {
      // add the current user's draft
      $userDraft['username'] = $this->username;
      $drafts = array_merge($drafts, [$userDraft]);
    }

    if (PermissionsManager::userCanPublishPendingDrafts($this->username, $this->filePath)) {
      // user has access to publish pending drafts
      $pendingDrafts = $this->getDrafts([Config::PENDING_PUBLISH_DRAFT, Config::PUBLIC_DRAFT]);
      if (!empty($pendingDrafts)) {
        // add drafts the user can publish as well as all public ones
        $drafts = array_merge($drafts, $pendingDrafts);
      }
    } else {
      //now add all public drafts
      $publicDrafts = $this->getDrafts(Config::PUBLIC_DRAFT);
      if (!empty($publicDrafts)) {
        $drafts = array_merge($drafts, $publicDrafts);
      }
    }

    if (!empty($draftFileName)) {
      foreach ($drafts as $draft) {
        if ($this->getDraftFileName($draft['username']) === $draftFileName) {
          return [$draft];
        }
      }
      // person was requesting a specific draft, but that draft doesn't exist in any drafts they have access to.
      return null;
    }

    return (!empty($drafts)) ? $drafts : null;
  }

  // Staging and publishing functionality

  /**
   * Throws a file into a staging state waiting to be moved to it's actual location
   *
   * @param boolean $asDraft Whether to stage the file as a draft or not
   * @return boolean True on success, false on failure.
   */
  public function stageFile($asDraft = false)
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

    // get stagedFile waiting to be published
    $result = $this->getStagedFileEntry();

    if (empty($result)) {
      // nothing to publish
      return false;
    }

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
      $this->destroyDraft();
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
      ->andWhere('publishedDate IS NULL')
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
      ->set('publishedDate', ':publishedDate')
      ->where('username = :username')
      ->andWhere('srcFilename = :srcFilename')
      ->andWhere('publishedDate IS NULL');

    return $dbal->executeUpdate(
        $qb->getSQL(),
        [
          ':publishedDate' => new \DateTime,
          ':username'      => $this->username,
          ':srcFilename'   => basename($stagedFilePath),
        ],
        [
          ':publishedDate' => 'datetime',
          ':username'      => null,
          ':srcFilename'   => null,
        ]
    );
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
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    if (strpos($this->filePath, $docRoot) === 0) {
      $filePath = substr($this->filePath, strlen($docRoot));
    } else {
      $filePath = $this->filePath;
    }
    if (($accessLevel = $this->forceAccessLevel())) {
      return PermissionsManager::accessLevelCanEditPart($accessLevel, $partName);
    }
    return PermissionsManager::userCanEditPart($this->username, $filePath, $partName);
  }

  /**
   * Checks to see if we should force an access level or not
   *
   * @return string|boolean String of the access level to force. False otherwise
   */
  private function forceAccessLevel()
  {
    if ($this->userIsEditingPublicDraft) {
      return Config::PUBLIC_ACCESS_LEVEL;
    }
    return false;
  }

  /**
   * Sets a flag so we know that the user is editing a public draft
   */
  public function setUserIsEditingPublicDraft()
  {
    $this->userIsEditingPublicDraft = true;
  }

  /**
   * Checks to see if the user has permission to edit this file
   * @return boolean
   */
  private function userCanEditFile()
  {
    if ($this->userIsEditingPublicDraft) {
      return true;
    }
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    if (strpos($this->filePath, $docRoot) === 0) {
      $filePath = substr($this->filePath, strlen($docRoot));
    } else {
      $filePath = $this->filePath;
    }
    return PermissionsManager::userCanEditFile($this->username, $filePath);
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
    //https://beta.gac.edu/concert/drafts/edit/a146af36e57c6a7c5103e9335a2c2388
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
   * @param  string $filePath File path to hash instead of the default
   * @return string
   */
  private function getFilePathHash($filePath = null)
  {
    return (!empty($filePath)) ? md5($filePath) : md5($this->filePath);
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
        (new FileManager($lock['username'], $this->filePath, null, $this->getDBAL()))->stopEditing();
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