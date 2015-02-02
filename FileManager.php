<?php
/**
 * @package Concert
 * @author  Billy Visto
 */

namespace Gustavus\Concert;

use Gustavus\Concert\FileConfiguration,
  Gustavus\Concert\Config,
  Gustavus\Concert\Utility,
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
   * Revisions API
   *
   * @var \Gustavus\Revisions\API
   */
  private $revisionsAPI;

  /**
   * Flag that lets us know if the user is editing a draft or not
   *
   * @var boolean
   */
  private $userIsEditingDraft = false;

  /**
   * Flag that lets us know if the user is editing a public draft or not
   *
   * @var boolean
   */
  private $userIsEditingPublicDraft = false;

  /**
   * Flag that lets us know if the user is editing a draft of the current file or not
   *
   * @var boolean
   */
  private $userIsEditingDraftForFile = false;

  /**
   * Storage for cached drafts so we don't need to keep making requests to get them
   *   Keyed by draftFilename
   *
   * @var array
   */
  private static $cachedDrafts = [];

  /**
   * Storage for cached drafts by name so we don't need to keep making requests to get them
   *   Keyed by draftName
   *
   * @var array
   */
  private static $cachedDraftsByName = [];

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
    $this->username    = $username;
    $this->filePath    = str_replace('//', '/', $filePath);
    $this->srcFilePath = $srcFilePath;
    $this->dbal        = $dbal;
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
   * @throws RuntimeException If no file exists to build the configuration for
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

    return self::separateContentByType($contents);
  }

  /**
   * Breaks apart the specified content into php pieces and normal content pieces
   *
   * @param  string $contents Content to separate
   * @return array  Array of Arrays keyed by content and phpcontent. They each contain arrays with indexes of the order they appear in the content.
   */
  public static function separateContentByType($contents)
  {
    $matches = [];

    // finds any content between the php open/close tags and sets it to be named "phpcontent".
    $phpPiece = '(?:
      # look for newlines or spaces
      (?:\A[\h*\v*])?

      # look for an opening php tag
      (?:<\?)(?:php)?

      # capture everything until the end of the file or a closing php tag
      (?P<phpcontent>.+?
        (?=(?:\?>|(?:\?>)?[\h\v]*?\z))
      )
    )';

    // finds any content between script tags
    $scriptPiece = '(?:
      # capture everything between script tags including the tags themselves
      (?P<scriptcontent>(?=<script(?:[^>]+)?>).+?)
      (?=</script>)
    )';

    // finds any content outside php and script tags and sets it to be named "content".
    $contentPiece = '(?:
      # look for newlines and not an opening php or script tag
      (?:\?\>|</script>|(?:\A(?!(?:<\?|<script(?:[^>]+)?>))))

      # make sure our next characters aren\'t opening script or php tags
      (?!(?:<\?|<script(?:[^>]+)?>))

      # capture until we see the end of the file, an opening php tag, or an opening script tag
      (?P<content>.+?)(?=<\?(?:php)?|<script(?:[^>]+)?>|[\h*|\v*]*?\z)
    )';

    // throw the two pieces together into one regex with s for PCRE_DOTALL, m for PCRE_MULTILINE, and x for PCRE_EXTENDED
    $regex = sprintf('`%s|%s|%s`smx', $phpPiece, $scriptPiece, $contentPiece);

    preg_match_all($regex, $contents, $matches);

    // $matches has a lot of extra information that we don't need, so lets get rid of it.
    $result = [];
    if (isset($matches['phpcontent'])) {
      $result[Config::PHP_CONTENT_TYPE] = array_filter($matches['phpcontent']);
    }
    if (isset($matches['scriptcontent'])) {
      $result[Config::SCRIPT_CONTENT_TYPE] = array_filter($matches['scriptcontent']);
      foreach ($result[Config::SCRIPT_CONTENT_TYPE] as &$scriptContent) {
        // script closing tags don't get included in our capture
        if (!empty($scriptContent) && strpos($scriptContent, '<script') !== false) {
          $scriptContent .= '</script>';
        }
      }
    }
    if (isset($matches['content'])) {
      $result[Config::OTHER_CONTENT_TYPE] = array_filter($matches['content']);
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
  private function assembleFile($forEditing = false)
  {
    $file = $this->getFileConfiguration()->buildFile($forEditing);

    // remove things the template adds in.
    $file = preg_replace('`\<\!\-\-templateInsertion\-\-\>.+?\<\!\-\-endTemplateInsertion\-\-\>`', '', $file);

    $file = preg_replace('`(class=.+?)currentPage(.+?)`', '$1$2', $file);
    $file = str_replace(' class=""', '', $file);

    return $file;
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
    $this->getFileConfiguration()->editFile($edits);
    // editFile only tells us that the file was edited. We might be dealing with publishing a draft that isn't being edited
    return true;
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
      return (file_put_contents($destination, $file) !== false);
    } else {
      throw new RuntimeException("Unable to write file: {$destination}");
    }
  }

  // Draft Functionality

  /**
   * Builds the file name to use for drafts. This is the hash of the filePath hash with the username of the current user added onto it
   *
   * @param  string $username Username to get the draft file name for
   * @param  boolean $fromDraftDir Whether we want to get the full path to the draft or not
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
      mkdir(Config::$editableDraftDir, 0777, true);
    }

    $fileName = Config::$editableDraftDir . $this->getDraftFileName();

    if ($fromExistingDraft && $this->draftExists()) {
      $result = $this->getDraftForUser($this->username);
      $draftFilePath = $this->getDraftFileName();

      $fm = new FileManager($this->username, Config::$draftDir . $draftFilePath, null, $this->getDBAL());
      $fileContents = $fm->assembleFile(true);
    } else {
      $fileContents = $this->assembleFile(true);
    }

    // Replace magic constants with their interpreted value.
    // If we don't do this, the magic constants will point to our editable draft folder, and paths using these will be broken.
    $this->replaceMagicConstants($fileContents);

    if ($this->saveFile($fileName, $fileContents)) {
      return $fileName;
    }
    return false;
  }

  /**
   * Removes magic php constants from assembled file and replaces them with their interpreted value.
   *   This is necessary if the file we are editing uses __DIR__ to specify file paths.
   *
   * @param  string &$fileContents Contents to remove magic constants from
   * @return void
   */
  private function replaceMagicConstants(&$fileContents)
  {
    $newDir = dirname($this->filePath);

    $fileContents = str_replace('__DIR__', sprintf('\'%s\'', $newDir), $fileContents);
    $fileContents = str_replace('__FILE__', sprintf('\'%s\'', $this->filePath), $fileContents);
  }

  /**
   * Saves a draft
   *
   * @param string $type Draft type (private, pendingPublish, public)
   * @param array $additionalUsers Additional users to assign to this draft
   * @return string|boolean String of the draft file. False if saving a draft failed.
   */
  public function saveDraft($type, array $additionalUsers = null)
  {
    if (!in_array($type, Config::$allowableDraftTypes)) {
      // draft type is not allowed.
      return false;
    }
    if (!$this->acquireLock()) {
      // user doesn't have a lock on this file. They shouldn't be able to do anything.
      return false;
    }

    if ($this->userIsEditingPublicDraft) {
      // we simply want to save our current configuration
      return $this->saveFile($this->filePath, $this->assembleFile(false));
    }

    $draftName = $this->getFilePathHash();
    $draftFilename = $this->getDraftFileName();

    $dbal = $this->getDBAL();

    if ($additionalUsers !== null && $additionalUsers !== false) {
      $additionalUsers = implode(',', $additionalUsers);
    }

    $properties = [
      'draftFilename'   => $draftFilename,
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
      $result = $dbal->update('drafts', $properties, ['draftFilename' => $draft['draftFilename']], $propertyTypes);
    } else {
      $result = $dbal->insert('drafts', $properties, $propertyTypes);
    }
    // clear our draft caches
    unset(self::$cachedDrafts[$draftFilename]);
    self::$cachedDraftsByName = [];
    if ($result) {
      // now we just have to save the draft
      if (!is_dir(Config::$draftDir)) {
        mkdir(Config::$draftDir, 0777, true);
      }
      $fileName = Config::$draftDir . $draftFilename;

      if ($this->saveFile($fileName, $this->assembleFile(false))) {
        return $fileName;
      }
    }
    // something failed
    return false;
  }

  /**
   * Adds users to a draft
   *
   * @param string $draftName       Name of the draft to add users to
   * @param array  $additionalUsers Additional users to add
   * @return  boolean  True on success, false otherwise
   */
  public function addUsersToDraft($draftName, array $additionalUsers)
  {
    $draft = $this->getDraft($draftName);
    if (empty($draft) || !PermissionsManager::userOwnsDraft($this->username, $draft) || $draft['type'] !== Config::PUBLIC_DRAFT) {
      return false;
    }

    $dbal = $this->getDBAL();

    if (!empty($additionalUsers)) {
      $additionalUsers = rtrim(implode(',', $additionalUsers), ',');
      $properties = ['additionalUsers' => $additionalUsers];
    } else {
      $properties = ['additionalUsers' => null];
    }

    $result = $dbal->update('drafts', $properties, ['draftFilename' => $draft['draftFilename']]);
    // clear our draft caches
    unset(self::$cachedDrafts[$draft['draftFilename']]);
    self::$cachedDraftsByName = [];
    return ($result > 0);
  }

  /**
   * Destroys any draft leftovers for the current file
   *
   * @param  string $draftFilename Draft to destroy
   * @return void
   */
  public function destroyDraft($draftFilename = null)
  {
    if ($draftFilename === null) {
      $draftFilename = $this->getDraftFileName();
    }

    $dbal = $this->getDBAL();

    $dbal->delete('drafts', ['draftFilename' => $draftFilename]);

    // destroy lock for draft.
    $fm = new FileManager($this->username, Config::$draftDir . $draftFilename, null, $this->getDBAL());
    $fm->destroyLock();

    // clear our draft caches
    unset(self::$cachedDrafts[$draftFilename]);
    self::$cachedDraftsByName = [];

    foreach ([Config::$draftDir, Config::$editableDraftDir] as $draftDir) {
      $fileName = $draftDir . $draftFilename;
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
   * @return array Array of arrays with keys of destFilepath, draftFilename, type, username, and additionalUsers
   */
  public function getDrafts($type = null)
  {
    $draftName = $this->getFilePathHash();

    if ($type === null) {
      $cacheKey = $draftName;
    } else {
      if (is_array($type)) {
        $cacheTypeKey = implode('-', $type);
      } else {
        $cacheTypeKey = $type;
      }
      $cacheKey = sprintf('%s-%s', $draftName, $cacheTypeKey);
    }
    if (isset(self::$cachedDraftsByName[$cacheKey])) {
      return self::$cachedDraftsByName[$cacheKey];
    }

    $dbal = $this->getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('destFilepath')
      ->addSelect('draftFilename')
      ->addSelect('type')
      ->addSelect('username')
      ->addSelect('additionalUsers')
      ->addSelect('date')
      ->from('drafts', 'd')
      ->where('draftName = ?')
      ->orderBy('date', 'DESC');

    $properties = [$draftName];
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
    self::$cachedDraftsByName[$cacheKey] = $result;
    return $result;
  }

  /**
   * Gets the current draft for the specified user
   *
   * @param  string $username Username to get the draft for
   * @return array Array with keys of destFilepath, draftFilename, type, username, and additionalUsers
   */
  public function getDraftForUser($username)
  {
    $draftFilename = $this->getDraftFileName($username);
    if (isset(self::$cachedDrafts[$draftFilename])) {
      return self::$cachedDrafts[$draftFilename];
    }
    $dbal = $this->getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('destFilepath')
      ->addSelect('draftFilename')
      ->addSelect('type')
      ->addSelect('username')
      ->addSelect('additionalUsers')
      ->addSelect('date')
      ->from('drafts', 'd')
      ->where('draftFilename = :draftFilename');

    $result = $dbal->fetchAssoc($qb->getSQL(), [':draftFilename' => $draftFilename]);
    if (!empty($result['additionalUsers'])) {
      $result['additionalUsers'] = explode(',', $result['additionalUsers']);
    }
    self::$cachedDrafts[$draftFilename] = $result;
    return $result;
  }

  /**
   * Gets the specified public draft
   *
   * @param  string $draftFilename Name of the draft to get. If not specified, it will get the draft filename for the current file and user
   * @return array Array with keys of destFilepath, draftFilename, type, username, and additionalUsers
   */
  public function getDraft($draftFilename = null)
  {
    if ($draftFilename === null) {
      $draftFilename = $this->getDraftFileName();
    }
    if (isset(self::$cachedDrafts[$draftFilename])) {
      return self::$cachedDrafts[$draftFilename];
    }
    $dbal = $this->getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('destFilepath')
      ->addSelect('draftFilename')
      ->addSelect('type')
      ->addSelect('username')
      ->addSelect('additionalUsers')
      ->addSelect('date')
      ->from('drafts', 'd')
      ->where('draftFilename = :draftFilename');

    $result =  $dbal->fetchAssoc($qb->getSQL(), [':draftFilename' => $draftFilename]);
    if ($result['type'] === Config::PRIVATE_DRAFT && !PermissionsManager::userOwnsDraft($this->username, $result)) {
      // person can't view this private draft.
      self::$cachedDrafts[$draftFilename] = false;
      return false;
    }
    if (!empty($result['additionalUsers'])) {
      $result['additionalUsers'] = explode(',', $result['additionalUsers']);
    }
    self::$cachedDrafts[$draftFilename] = $result;
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
    $draft = $this->getDraftForUser($this->username);
    return !empty($draft);
  }

  /**
   * Finds all the drafts the current user has access to edit
   *
   * @param  string $draftFilename Filename of the draft to get
   * @return array|null
   */
  public function findDraftsForCurrentUser($draftFilename = null)
  {
    $drafts = [];
    $userDraft = $this->getDraftForUser($this->username);
    if (!empty($userDraft)) {
      // add the current user's draft
      $userDraft['username'] = $this->username;
      $drafts[$userDraft['draftFilename']] = $userDraft;
    }

    if (PermissionsManager::userCanPublishPendingDrafts($this->username, Utility::removeDocRootFromPath($this->filePath))) {
      // user has access to publish pending drafts
      $pendingDrafts = $this->getDrafts([Config::PENDING_PUBLISH_DRAFT, Config::PUBLIC_DRAFT]);
      if (!empty($pendingDrafts)) {
        // add drafts the user can publish as well as all public ones
        foreach ($pendingDrafts as $pendingDraft) {
          $drafts[$pendingDraft['draftFilename']] = $pendingDraft;
        }
      }
    } else {
      //now add all public drafts
      $publicDrafts = $this->getDrafts(Config::PUBLIC_DRAFT);
      if (!empty($publicDrafts)) {
        foreach ($publicDrafts as $publicDraft) {
          $drafts[$publicDraft['draftFilename']] = $publicDraft;
        }
      }
    }

    if (!empty($draftFilename)) {
      foreach ($drafts as $draft) {
        if ($this->getDraftFileName($draft['username']) === $draftFilename) {
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
   * @param string $action  Action we want the staged file to represent
   * @param string $fileContents  Contents of the file to stage. <strong>Note:</strong> This should only be used for revisions.
   * @param string $stagedFileName Name to stage the file as. <strong>Note:</strong> This should only be used internally.
   * @return boolean True on success, false on failure.
   */
  public function stageFile($action = null, $fileContents = null, $stagedFileName = null)
  {
    if (!$this->acquireLock()) {
      // user doesn't have a lock on this file. They shouldn't be able to do anything.
      return false;
    }

    if ($action === null) {
      // set the dest file path to the current file path if nothing is specified
      $action = Config::PUBLISH_STAGE;
    }

    // throw the new file into the pending updates table and throw the file in the staging directory

    // the specified file name must be a 32 character hash
    if ($stagedFileName === null || strlen($stagedFileName) !== 32) {
      $stagedFileName = $this->getFilePathHash();
    }
    $dbal = $this->getDBAL();

    $properties = [
      'destFilepath' => $this->filePath,
      'srcFilename'  => $stagedFileName,
      'username'     => $this->username,
      'action'       => $action,
      'date'         => new DateTime,
    ];

    $propertyTypes = [
      null,
      null,
      null,
      null,
      'datetime',
    ];

    if ($dbal->insert('stagedFiles', $properties, $propertyTypes)) {
      // now we just have to move the file
      if (!is_dir(Config::$stagingDir)) {
        mkdir(Config::$stagingDir, 0777, true);
      }
      $fileContents = ($fileContents === null) ? $this->assembleFile(false) : $fileContents;
      if ($this->saveFile(Config::$stagingDir . $stagedFileName, $fileContents)) {
        return true;
      }
    }
    // something happened
    return false;
  }

  /**
   * Stages a file for deletion
   *
   * @return boolean
   */
  public function stageForDeletion()
  {
    return $this->stageFile(Config::DELETE_STAGE);
  }

  /**
   * Stages a pending draft to be published
   *
   * @param  string $draftOwner Username of the user owning the draft to be published
   * @return boolean
   */
  public function stagePublishPendingDraft($draftOwner)
  {
    // stage the file with the contents of the draft, and the filename of the draft's name
    return $this->stageFile(Config::PUBLISH_PENDING_STAGE, file_get_contents($this->getDraftFileName($draftOwner, true)), $this->getDraftFileName($draftOwner, false));
  }

  /**
   * Saves a revision for the current filePath
   *
   * @param  string $message  Message to put with the revision
   * @return boolean
   */
  private function saveRevision($message = '')
  {
    // make sure our memory limit is high enough for saving revisions
    ini_set('memory_limit', '512M');

    $revisionsAPI = $this->getRevisionsAPI();

    if (file_exists($this->filePath)) {
      $content = file_get_contents($this->filePath);
    } else {
      $content = '';
    }

    $revisionInfo = [
      'page' => $content,
    ];

    return $revisionsAPI->saveRevision($revisionInfo, $message, $this->username);
  }

  /**
   * Gets the current API for revisions. Builds it is it isn't built yet
   *
   * @return Gustavus\Revisions\API
   */
  private function getRevisionsAPI()
  {
    if (empty($this->revisionsAPI)) {
      $this->revisionsAPI = Utility::getRevisionsAPI($this->filePath, $this->getDBAL());
    }
    return $this->revisionsAPI;
  }

  /**
   * Saves an initial revision if the current page doesn't yet have any revisions and currently exists.
   *
   * @return boolean
   */
  private function saveInitialRevisionIfNeeded()
  {
    $revisionsAPI = $this->getRevisionsAPI();

    if ($revisionsAPI->getRevisionCount() === 0 && file_exists($this->filePath)) {
      // no revisions exist yet, and this file exists. We need to save an initial revision.
      return $this->saveRevision('Initial version');
    }
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

    if ($result['action'] === Config::DELETE_STAGE) {
      // we are trying to delete this file
      return $this->deleteFile();
    } else if ($result['action'] === Config::CREATE_HTTPD_DIRECTORY_STAGE) {
      // we are wanting to create a directory writable by the httpd user.
      if ($this->ensureDirectoryExists($result['destFilepath'], Config::HTTPD_USER, Config::HTTPD_GROUP)) {
        unlink($this->filePath);
        $this->username = $result['username'];
        if (!$this->markStagedFileAsPublished($this->filePath)) {
          trigger_error(sprintf('The directory: "%s" was created for the staged file "%s", but could not be marked as published in the DB', $result['destFilepath'], $this->filePath));
        }
        return true;
      }
      return false;
    } else if ($result['action'] === Config::CREATE_HTTPD_DIR_HTACCESS_STAGE) {
      // make sure the directory exists.
      $this->ensureDirectoryExists(dirname($result['destFilepath']), Config::HTTPD_USER, Config::HTTPD_GROUP);
      if (symlink(Config::MEDIA_DIR_HTACCESS_TEMPLATE, $result['destFilepath'])) {
        unlink($this->filePath);
        $this->username = $result['username'];
        if (!$this->markStagedFileAsPublished($this->filePath)) {
          trigger_error(sprintf('The directory: "%s" was created for the staged file "%s", but could not be marked as published in the DB', $result['destFilepath'], $this->filePath));
        }
        return true;
      }
    }

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
    $group = Utility::getGroupForFile($destination);
    $owner = $this->username;

    // make sure the destination directory exists in case someone is adding a directory
    $this->ensureDirectoryExists(dirname($destination), $owner, $group);

    $this->saveInitialRevisionIfNeeded();
    if (rename($srcFilePath, $destination)) {
      chgrp($destination, $group);
      chown($destination, $owner);

      if (!$this->markStagedFileAsPublished($srcFilePath)) {
        trigger_error(sprintf('The file: "%s" was moved to "%s", but could not be marked as published in the DB', $srcFilePath, $destination));
      }
      if ($result['action'] === Config::PUBLISH_PENDING_STAGE) {
        $draftFilename = basename($srcFilePath);
        $draft = $this->getDraft($draftFilename);
        // We are publishing a pending draft. We need to get the draftName to use.
        $this->saveRevision(self::buildRevisionMessage($result['action'], $draft['username']));
        $this->destroyDraft($draftFilename);
      } else {
        $this->saveRevision(self::buildRevisionMessage($result['action']));
        $this->destroyDraft();
      }
      $this->destroyLock();
      return true;
    }
    return false;
  }

  /**
   * Builds the revision message based off of the action
   *
   * @param  string $action Action to build the message for.
   * @param  string $username Username of the person we are performing an action for
   * @return string
   */
  private static function buildRevisionMessage($action, $username = null)
  {
    switch ($action) {
      case Config::RESTORE_STAGE:
          return 'File restored';
      case Config::UNDO_RESTORE_STAGE:
          return 'File restoration undone';
      case Config::DELETE_STAGE:
          return 'File deleted';
      case Config::PUBLISH_PENDING_STAGE:
          return 'Pending draft published for ' . $username;
      case Config::PUBLISH_STAGE:
      default:
          return 'File published';
    }
  }

  /**
   * Deletes a file
   *
   * @throws RuntimeException If the current user is not root
   * @return boolean
   */
  private function deleteFile()
  {
    if ($this->username !== 'root') {
      throw new RuntimeException('Only root can publish files');
    }

    // get stagedFile waiting to be published
    $result = $this->getStagedFileEntry();

    if (empty($result)) {
      // nothing to delete
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

    if ($result['action'] !== Config::DELETE_STAGE) {
      // the latest staging for this file isn't a delete.
      return false;
    }

    $srcFilePath = $this->filePath;
    $this->filePath = $result['destFilepath'];

    // now we set our current username to be the username that staged the file, so we can check permissions and delete it for them
    $this->username = $result['username'];

    if (!$this->acquireLock()) {
      // current user doesn't have a lock on this file. They shouldn't be able to do anything.
      return false;
    }

    $this->saveInitialRevisionIfNeeded();
    if ($this->removeFile()) {
      $this->saveRevision(self::buildRevisionMessage($result['action']));

      unlink($srcFilePath);
      if (!$this->markStagedFileAsPublished($srcFilePath)) {
        trigger_error(sprintf('The file: "%s" was moved to "%s", but could not be marked as published in the DB', $srcFilePath, $this->filePath));
      }
      $this->destroyLock();
      return true;
    } else {
      unlink($srcFilePath);
      $this->destroyLock();
      return false;
    }
  }

  /**
   * Removes the specified file.
   *   Safe check in place to not delete the site
   *
   * @return boolean
   */
  private function removeFile()
  {
    $site = PermissionsManager::findUsersSiteForFile($this->username, Utility::removeDocRootFromPath($this->filePath));

    if (empty($site) || strpos($this->filePath, $site) === false) {
      // we can't find a site for the file to remove.
      // Don't do anything
      return false;
    }
    if (substr($site, -1) !== '/') {
      $site = dirname($site);
    }

    if ($this->filePath === $site) {
      // we are at the base of our site. We don't want to delete this.
      return false;
    }

    if (is_dir($this->filePath)) {
      if (self::removeFiles($this->filePath)) {
        return self::removeEmptyParentDirectories($this->filePath, $site);
      }
    } else {
      if (unlink($this->filePath)) {
        // we also need to remove the directory if this was the only file here.
        return self::removeEmptyParentDirectories($this->filePath, $site);
      }
    }
    return false;
  }

  /**
   * Recursively removes empty parent directories up to the specified site
   *
   * @param  string $file File or Directory to remove the parents for.
   * @param  string $site Site the operations are being run for
   * @return boolean
   */
  private static function removeEmptyParentDirectories($file, $site)
  {
    if (is_dir($file)) {
      $dir = $file;
    } else {
      $dir = dirname($file);
    }

    if (rtrim($dir, '/') === rtrim($site, '/')) {
      // we are at the base of our site. We don't want to delete this.
      return true;
    }

    if (count(scandir($dir)) === 2) {
      rmdir($dir);
      return self::removeEmptyParentDirectories(dirname($dir), $site);
    }
    return true;
  }

  /**
   * Removes all files from a directory, but not the directory itself
   * @param  string $dir Directory to remove files from
   * @return boolean
   */
  private static function removeFiles($dir)
  {
    $files = scandir($dir);
    foreach ($files as $file) {
      if ($file === '.' || $file === '..') {
        continue;
      }
      $file = $dir . '/' . $file;
      if (is_dir($file)) {
        self::removeFiles($file);
        $result = rmdir($file);
        continue;
      }
      $result = unlink($file);
    }
    return $result;
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
      ->addSelect('action')
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
   * @param  string $stagedFilePath Path of the staged file to mark as published
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
          ':publishedDate' => new DateTime,
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
   * Ensures that the directory exists so we can save the file into that directory
   *   <strong>Note:</strong> This should only be called by publishFile.
   *
   * @param  string $directory Directory to make sure is in existence
   * @param  string $owner     Owner to set for the directory if we are creating a new one (if the current user is root)
   * @param  string $group     Group the directory should have if we are creating a new one
   *
   * @return boolean
   */
  private function ensureDirectoryExists($directory, $owner, $group)
  {
    if (!is_dir($directory)) {
      if (mkdir($directory, 0777, true)) {
        chgrp($directory, $group);

        $pwuData = posix_getpwuid(posix_geteuid());
        $currentUser = $pwuData['name'];

        if ($currentUser === 'root') {
          chown($directory, $owner);
        }
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
    if (($accessLevel = $this->forceAccessLevel())) {
      return PermissionsManager::accessLevelCanEditPart($accessLevel, $partName);
    }
    $filePath = Utility::removeDocRootFromPath($this->filePath);
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
   * Sets a flag so we know that the user is editing a draft
   *
   * @return  void
   */
  public function setUserIsEditingDraft()
  {
    $this->userIsEditingDraft = true;
  }

  /**
   * Sets a flag so we know that the user is editing a public draft
   *
   * @return  void
   */
  public function setUserIsEditingPublicDraft()
  {
    $this->userIsEditingPublicDraft = true;
    // a public draft is also a draft.
    $this->setUserIsEditingDraft();
  }

  /**
   * Sets a flag so we know that the user is editing a draft that represents this file.
   *
   * @return  void
   */
  public function setUserIsEditingDraftForFile()
  {
    $this->userIsEditingDraftForFile = true;
  }

  /**
   * Checks to see if the user has permission to edit this file
   * @return boolean
   */
  public function userCanEditFile()
  {
    if ($this->userIsEditingPublicDraft) {
      // we need to check that the user has access to edit this draft.
      $draft = $this->getDraft(basename($this->filePath));

      if ($draft === false && $this->userIsEditingDraftForFile) {
        // user is editing a draft that represents this file.
        return true;
      }

      return PermissionsManager::userCanEditDraft($this->username, $draft);
    } else if ($this->userIsEditingDraft && !$this->userIsEditingDraftForFile) {
      // user is editing a draft and our current FileManager is the draft and not the file the draft represents
      $draft = $this->getDraft(basename($this->filePath));
      if ($draft) {
        return PermissionsManager::userCanEditDraft($this->username, $draft);
      }
    }

    $filePath = Utility::removeDocRootFromPath($this->filePath);
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
    $indexPattern = '`<div[^>]+class[^>]+?editable[^>]+?data-index\=(?:\'|")([^>]+)(?:\'|")>`';
    preg_match_all($indexPattern, $content, $matches);
    if (isset($matches[1])) {
      $_SESSION['concertCMS']['nonEditableKeys'][$this->getFilePathHash()] = $matches[1];
    }

    $pattern = sprintf('`(?:<div[^>]+class[^>]+?editable[^>]+?>)|(?:</div>%s)`', Config::EDITABLE_DIV_CLOSING_IDENTIFIER);

    $result = preg_replace($pattern, '', $content);

    $trimmedResult = trim($result);
    if (empty($trimmedResult)) {
      return '';
    }
    return $result;
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
  public function getFilePathHash($filePath = null)
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
      'date'         => new DateTime(),
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
      'date'         => new DateTime(),
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
   * @param  boolean $isInternalCall  Flag to specify destroyLock is called within destroyLock
   * @return boolean Always returns true. If no rows were modified, there was no lock to delete, so we still return true as it has been "destroyed".
   */
  private function destroyLock($isInternalCall = false)
  {
    if (!$isInternalCall && $this->userIsEditingDraft && ($draft = $this->getDraft(basename($this->filePath)))) {
      // we need to make sure to destroy the lock for the file this draft represents
      $fm = new FileManager($this->username, $draft['destFilepath'], null, $this->getDBAL());
      $fm->setUserIsEditingDraft();
      $fm->setUserIsEditingDraftForFile();
      if ($this->userIsEditingPublicDraft) {
        $fm->setUserIsEditingPublicDraft();
      }
      $fm->destroyLock(true);
    } else if (!$isInternalCall && ($draft = $this->getDraft())) {
      // we want to look to see if the user has a draft for this file and release the lock on the draft.

      $fm = new FileManager($this->username, Config::$draftDir . $draft['draftFilename'], null, $this->getDBAL());
      $fm->setUserIsEditingDraft();
      if ($this->userIsEditingPublicDraft) {
        $fm->setUserIsEditingPublicDraft();
      }
      $fm->destroyLock(true);
    }

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
   * Gets the username of the person who owns the current lock
   *
   * @return string|boolean Username of the owner or false if there is no lock
   */
  public function getLockOwner()
  {
    $lock = $this->getLockFromDB();

    if ($lock) {
      return $lock['username'];
    } else {
      return false;
    }
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
   * @return boolean
   */
  public function stopEditing()
  {
    return $this->destroyLock();
  }

  /**
   * Attempts to acquire a lock on the current file
   *
   * @param boolean $forDraft Whether we are trying to acquire a lock for a draft. <strong>Note:</strong> This should only be used internally.
   * @return boolean|integer True if the lock was acquired. Minutes left until the lock expires if the lock couldn't be acquired.
   */
  public function acquireLock($forDraft = false)
  {
    if ($this->isNonEditableFile()) {
      // file cannot be edited
      return false;
    }

    if (isset($this->lockAcquired)) {
      // we have already tried to acquire a lock
      if ($this->lockAcquired) {
        // we have already acquired a lock.
        return true;
      }
      return false;
    }

    if ($this->userIsEditingDraft && !$forDraft) {
      return $this->acquireLockForDraft();
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
      }
    } else {
      // make a lock
      $this->lockAcquired = $this->createLock();
      return $this->lockAcquired;
    }
  }

  /**
   * Acquires a lock for editing drafts
   *
   * @return boolean
   */
  private function acquireLockForDraft()
  {
    if ($this->userIsEditingPublicDraft) {
      // we need to search for a certain draft in case this person isn't the owner
      $draft = $this->getDraft(basename($this->filePath));
    } else {
      // user is editing a draft that they own
      $draft = $this->getDraft();
    }

    if ($draft) {
      if ($this->filePath === $draft['destFilepath']) {
        // Our current FileManager doesn't represent a draft.
        // We need to build a FileManager representing the draft so we can get a lock.
        $fm = new FileManager($this->username, Config::$draftDir . $draft['draftFilename'], null, $this->getDBAL());
      } else {
        // Our current FileManager represents a draft
        // We want to build a new FileManager for the file the draft represents so we can lock that file.
        $fm = new FileManager($this->username, $draft['destFilepath'], null, $this->getDBAL());
        $fm->setUserIsEditingDraftForFile();
      }

      $fm->setUserIsEditingDraft();
      if ($this->userIsEditingPublicDraft) {
        $fm->setUserIsEditingPublicDraft();
      }

      if ($this->acquireLock(true) && $fm->acquireLock(true)) {
        return true;
      } else {
        $this->stopEditing();
        $fm->stopEditing();
        return false;
      }
    } else if (!$this->userIsEditingPublicDraft && $this->userIsEditingDraft) {
      // user might be trying to save a draft for the first time.
      return $this->acquireLock(true);
    }

    return false;
  }

  /**
   * Checks to see if this file could be edited if the user actually has permission
   *   Non editable files include: Sym Links
   *
   * @return boolean
   */
  public function isNonEditableFile()
  {
    if (is_link($this->filePath)) {
      return true;
    }
    return false;
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