<?php
/**
 * @package Concert
 * @author  Billy Visto
 */

namespace Gustavus\Concert;

use Gustavus\Concert\Config,
  Gustavus\Doctrine\DBAL,
  Gustavus\GACCache\GlobalCache,
  Gustavus\Utility\Set;

/**
 * Class for managing a specific file
 *
 * @todo  add functions to save to db and what not?
 *
 * @package Concert
 * @author  Billy Visto
 */
class PermissionsManager
{
  /**
   * DBAL connection to use
   *
   * @var \Doctrine\DBAL\Connection
   */
  private static $dbal;

  /**
   * Cache to use for storing and retrieving permissions
   *
   * @var \Gustavus\GACCache\CacheDataStore
   */
  private static $cache;

  /**
   * Gets the current cache
   *
   * @return \Gustavus\GACCache\CacheDataStore
   */
  private static function getCache()
  {
    if (empty(self::$cache)) {
      self::$cache = GlobalCache::getGlobalDataStore();
    }
    return self::$cache;
  }

  /**
   * Builds the key to use for caching permissions
   *
   * @param  string $username Username of person
   * @return string
   */
  private static function buildCacheKey($username)
  {
    return 'concertSitePermissions-' . $username;
  }

  /**
   * Checks whether the user has access to edit the specified file.
   *   If a file is specified directly in excludedFiles, the file can't be touched.
   *   If a file is specified directly in includedFiles, but not excludedFiles, the file can be edited.
   *   If a wildcard or directory is found in both rules, excludedFiles will override includedFiles for this file.
   *
   * @param  string $username Username to check
   * @param  string $filePath Absolute path from the doc root to the file in question
   * @return boolean
   */
  public static function userCanEditFile($username, $filePath)
  {
    $site = self::findUsersSiteForFile($username, $filePath);
    if (empty($site)) {
      return false;
    }
    $sitePerms = self::getUserPermissionsForSite($username, $site);

    if (is_array($sitePerms['accessLevel'])) {
      // make sure the array contains non-empty values
      $sitePerms['accessLevel'] = array_filter($sitePerms['accessLevel']);
    }

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits editing.
    foreach ($sitePerms['accessLevel'] as $accessLevel) {
      if (in_array($accessLevel, Config::$nonEditableAccessLevels)) {
        // the current user's access level doesn't allow editing
        return false;
      }
    }

    if (is_array($sitePerms['excludedFiles'])) {
      $sitePerms['excludedFiles'] = array_filter($sitePerms['excludedFiles']);
    }
    if (empty($sitePerms['excludedFiles'])) {
      // the user has access to all files on the site.
      return true;
    }

    // now we need to look at their excludedFiles and includedFiles.
    // first we will make sure they are all uniform.
    $sitePerms['excludedFiles'] = self::adjustPermissionFiles($sitePerms['excludedFiles']);

    // find the location of the file in respect to the siteRoot.
    // make sure our site has a "/" at the beginning and end for searching
    $site = str_replace('//', '/', sprintf('/%s/', $site));
    // make sure filePath has a leading "/";
    $filePath   = str_replace('//', '/', '/' . $filePath);
    $pathInSite = trim(substr($filePath, strlen($site) - 1), '/');

    if (in_array($pathInSite, $sitePerms['excludedFiles'])) {
      // a specific file was in excluded files. We can't edit this one.
      return false;
    }
    // make sure includedFiles is uniform
    if (is_array($sitePerms['includedFiles'])) {
      $sitePerms['includedFiles'] = self::adjustPermissionFiles(array_filter($sitePerms['includedFiles']));
    } else {
      $sitePerms['includedFiles'] = [];
    }
    if (!empty($sitePerms['includedFiles']) && in_array($pathInSite, $sitePerms['includedFiles'])) {
      // file is listed in our included files
      return true;
    }

    // now we need to actually search through the permission arrays and match wildcards.
    $filePathArray = explode('/', $pathInSite);
    $filePathSearch = '';
    // storage for the latest rule the file name was found in.
    $foundRule = null;

    foreach ($filePathArray as $filePathPart) {
      $filePathSearch .= $filePathPart . '/';
      $filePathWildCardSearch = $filePathSearch . '*';
      // now we have our current path's wildcard. Let's see if it exists anywhere.
      if (in_array($filePathWildCardSearch, $sitePerms['includedFiles'])) {
        $foundRule = 'included';
      }
      if (in_array($filePathWildCardSearch, $sitePerms['excludedFiles'])) {
        $foundRule = 'excluded';
      }
    }
    if ($foundRule === 'excluded') {
      return false;
    }
    // rule was either not specified or found in our included files. They have access
    return true;
  }

  /**
   * Checks to see if the specified user can create new pages or not
   *
   * @param  string $username Username to check
   * @param  string $filePath Absolute path from the doc root to the file in question
   * @return boolean
   */
  public static function userCanCreatePage($username, $filePath)
  {
    $site = self::findUsersSiteForFile($username, $filePath);
    if (empty($site)) {
      return false;
    }
    $sitePerms = self::getUserPermissionsForSite($username, $site);

    if (is_array($sitePerms['accessLevel'])) {
      // make sure the array contains non-empty values
      $sitePerms['accessLevel'] = array_filter($sitePerms['accessLevel']);
    }

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits creating new pages.
    foreach ($sitePerms['accessLevel'] as $accessLevel) {
      if (in_array($accessLevel, Config::$nonCreationAccessLevels)) {
        // the current user's access level doesn't allow creating
        return false;
      }
    }
    return true;
  }

  /**
   * Checks to see if the specified user can create new pages or not
   *
   * @param  string $username Username to check
   * @param  string $filePath Absolute path from the doc root to the file in question
   * @return boolean
   */
  public static function userCanDeletePage($username, $filePath)
  {
    $site = self::findUsersSiteForFile($username, $filePath);
    if (empty($site)) {
      return false;
    }
    $sitePerms = self::getUserPermissionsForSite($username, $site);

    if (is_array($sitePerms['accessLevel'])) {
      // make sure the array contains non-empty values
      $sitePerms['accessLevel'] = array_filter($sitePerms['accessLevel']);
    }

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits creating new pages.
    foreach ($sitePerms['accessLevel'] as $accessLevel) {
      if (in_array($accessLevel, Config::$nonDeletionAccessLevels)) {
        // the current user's access level doesn't allow creating
        return false;
      }
    }
    return true;
  }

  /**
   * Checks to see if the specified user can create new pages or not
   *
   * @param  string $username Username to check
   * @param  string $filePath Absolute path from the doc root to the file in question
   * @return boolean
   */
  public static function userCanPublishPendingDrafts($username, $filePath)
  {
    $site = self::findUsersSiteForFile($username, $filePath);
    if (empty($site)) {
      return false;
    }
    $sitePerms = self::getUserPermissionsForSite($username, $site);

    if (is_array($sitePerms['accessLevel'])) {
      // make sure the array contains non-empty values
      $sitePerms['accessLevel'] = array_filter($sitePerms['accessLevel']);
    }

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits publishing drafts for people.
    foreach ($sitePerms['accessLevel'] as $accessLevel) {
      if (in_array($accessLevel, Config::$publishPendingDraftsAccessLevels)) {
        // the current user's access level doesn't allow creating
        return true;
      }
    }
    return false;
  }

  /**
   * Checks to see if the specified user can create new pages or not
   *
   * @param  string $username Username to check
   * @param  string $filePath Absolute path from the doc root to the file in question
   * @return boolean
   */
  public static function userCanPublishFile($username, $filePath)
  {
    $site = self::findUsersSiteForFile($username, $filePath);
    if (empty($site)) {
      return false;
    }
    $sitePerms = self::getUserPermissionsForSite($username, $site);

    if (is_array($sitePerms['accessLevel'])) {
      // make sure the array contains non-empty values
      $sitePerms['accessLevel'] = array_filter($sitePerms['accessLevel']);
    }

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits creating new pages.
    foreach ($sitePerms['accessLevel'] as $accessLevel) {
      if (in_array($accessLevel, Config::$nonPublishingAccessLevels)) {
        // the current user's access level doesn't allow creating
        return false;
      }
    }
    return true;
  }

  /**
   * Checks to see if the specified user can create new pages or not
   *
   * @param  string $username Username to check
   * @param  string $filePath Absolute path from the doc root to the file in question
   * @return boolean
   */
  public static function userCanEditSiteNav($username, $filePath)
  {
    $site = self::findUsersSiteForFile($username, $filePath);
    if (empty($site)) {
      return false;
    }
    $sitePerms = self::getUserPermissionsForSite($username, $site);

    if (is_array($sitePerms['accessLevel'])) {
      // make sure the array contains non-empty values
      $sitePerms['accessLevel'] = array_filter($sitePerms['accessLevel']);
    }

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits creating new pages.
    foreach ($sitePerms['accessLevel'] as $accessLevel) {
      if (in_array($accessLevel, Config::$siteNavAccessLevels)) {
        // the current user's access level doesn't allow creating
        return true;
      }
    }
    return false;
  }

  /**
   * Adjusts permissions so they are all uniform to make checking easier
   *
   * @param  array $files array of files to check
   * @return array
   */
  private static function adjustPermissionFiles($files)
  {
    foreach ($files as &$file) {
      $isDir = (strpos($file, '.') === false) ? true : false;
      $file = trim(str_replace('//', '/', $file), '/');

      if ($isDir && substr($file, count($file) - 3) !== '/*') {
        // treat directories as wildcards.
        $file .= '/*';
      }
    }
    return $files;
  }

  /**
   * Finds the user's closest site for the specified filePath.
   *
   * @param  string $username Username to search for
   * @param  string $filePath Path to the file we are searching for a site for.
   * @return string|null String if a site is found, null otherwise.
   */
  public static function findUsersSiteForFile($username, $filePath)
  {
    $filePathArray = explode('/', str_replace('//', '/', $filePath));

    if (self::isUserAdmin($username) || self::isUserSuperUser($username)) {
      // user might not have a site for this file, but they have global access to all sites.
      // We need to find sites for them
      $sites = self::getSitesFromBase($filePathArray[0]);
    } else {
      $sites = self::getUsersSites($username);
    }
    if (empty($sites)) {
      return null;
    }
    $adjustedSites = [];
    // force a trailing "/" at the beginning and end of every siteRoot for searching
    foreach ($sites as $key => $site) {
      $site = sprintf('/%s/', $site);
      $adjustedSites[$key] = str_replace('//', '/', $site);
    }

    // used to build the file path back together while searching for sites that match.
    $filePathSearch = '/';
    $foundSite = null;

    foreach ($filePathArray as $pathPiece) {
      $filePathSearch .= $pathPiece . '/';
      $filePathSearch =  str_replace('//', '/', $filePathSearch);
      if (($foundKey = array_search($filePathSearch, $adjustedSites)) !== false) {
        // we found a site. Let's save this site.
        $foundSite = $sites[$foundKey];
      }
    }
    // now we should have our closest match. Return it.
    return $foundSite;
  }

  /**
   * Gets the permissions that the specified user has for a site.
   *
   * @param  string $username Username of the user to get permissions for
   * @param  string $siteRoot Root path of the site
   * @param  boolean $refreshCache Whether to refresh the cache or not
   * @return array|null Array if the user has permissions for the site, null otherwise
   */
  public static function getUserPermissionsForSite($username, $siteRoot, $refreshCache = false)
  {
    $perms = self::getAllPermissionsForUser($username, $refreshCache);

    if (empty($perms)) {
      return null;
    }

    if (self::isUserSuperUser($username)) {
      return Config::$superUserPermissions;
    }
    if (self::isUserAdmin($username)) {
      return Config::$adminPermissions;
    }
    if (isset($perms[$siteRoot])) {
      return $perms[$siteRoot];
    }

    return null;
  }

  /**
   * Checks to see if the user is a super user.
   *
   * @param  string   $username Username to check
   * @return boolean
   */
  private static function isUserSuperUser($username)
  {
    $perms = self::getAllPermissionsForUser($username);

    if (empty($perms)) {
      return false;
    }
    $accessLevels = self::getAccessLevelsFromPermissions($perms);
    return in_array(Config::SUPER_USER, $accessLevels);
  }

  /**
   * Checks to see if the user is a super user.
   *
   * @param  string   $username Username to check
   * @return boolean
   */
  private static function isUserAdmin($username)
  {
    $perms = self::getAllPermissionsForUser($username);

    if (empty($perms)) {
      return false;
    }
    $accessLevels = self::getAccessLevelsFromPermissions($perms);
    return in_array(Config::ADMIN_ACCESS_LEVEL, $accessLevels);
  }

  /**
   * Gets the access levels a person has from all permissions
   *
   * @param  array  $permissions Permissions array
   * @return array
   */
  private static function getAccessLevelsFromPermissions(array $permissions)
  {
    $accessLevels = [];
    foreach ($permissions as $permission) {
      foreach ((array) $permission['accessLevel'] as $accessLevel) {
        $accessLevels[] = $accessLevel;
      }
    }
    return $accessLevels;
  }

  /**
   * Gets all the sites the current user has access to.
   *
   * @param  string $username Username to look for sites for
   * @param  boolean $refreshCache Whether to refresh the cache or not
   * @return array|null Array if the user has permissions for sites, null otherwise
   */
  public static function getUsersSites($username, $refreshCache = false)
  {
    $perms = self::getAllPermissionsForUser($username, $refreshCache);

    if (!is_array($perms)) {
      return null;
    }
    return array_keys($perms);
  }

  /**
   * Checks to see if the current user can edit this part
   *
   * @param string $username Username to check
   * @param strilg $filePath Path to the file to check
   * @param string $partName Name of the template piece to check
   * @return boolean
   */
  public static function userCanEditPart($username, $filePath, $partName)
  {
    $siteRoot = self::findUsersSiteForFile($username, $filePath);
    if (empty($siteRoot)) {
      return false;
    }

    $sitePerms = self::getUserPermissionsForSite($username, $siteRoot);

    $accessLevels = $sitePerms['accessLevel'];

    if (is_array($accessLevels)) {
      $accessLevels = array_filter($accessLevels);
    }

    if (empty($accessLevels)) {
      // this user doesn't have an access level
      return false;
    }

    return self::accessLevelCanEditPart($accessLevels, $partName);
  }

  /**
   * Checks to see if an access level or levels have access to edit a specific page part.
   *
   * @param array|string $accessLevels AccessLevels to check
   * @param string $partName Name of the template piece to check
   * @return boolean
   */
  public static function accessLevelCanEditPart($accessLevels, $partName)
  {
    $partName = strtolower($partName);

    $editableParts = Config::$editableParts;
    $nonEditableParts = [];
    $hasNonRestrictiveLevel = false;
    foreach ((array) $accessLevels as $accessLevel) {
      if (isset(Config::$nonEditablePartsByAccessLevel[$accessLevel])) {
        foreach (Config::$nonEditablePartsByAccessLevel[$accessLevel] as $part) {
          $nonEditableParts[] = $part;
        }
      } else {
        $hasNonRestrictiveLevel = true;
        break;
      }
    }

    if (!$hasNonRestrictiveLevel) {
      $editableParts = array_diff($editableParts, $nonEditableParts);
    }

    return in_array($partName, $editableParts);
  }

  // DB functions

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
   * Gets all the sites that exist inside the base site specified
   *
   * @param  string $siteBase Base directory to search for sites in
   * @return array
   */
  private static function getSitesFromBase($siteBase)
  {
    $dbal = self::getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->select('s.siteRoot')
      ->from('sites', 's')
      ->where('s.siteRoot LIKE :siteBase');

    $result = $dbal->fetchAll($qb->getSQL(), [':siteBase' => $siteBase . '%%']);
    return (new Set($result))->flattenValues()->getValue();
  }

  /**
   * Gets all permissions for a user
   *
   *   Returns an array with keys of siteRoots. Those keys contain arrays with keys of accessLevel, includedFiles, and excludedFiles. Those keys will either be an array of values, or null.
   *   ie.
   *   <code>[
   *     '/billy' => [
   *       'accessLevel'   => ['admin'],
   *       'includedFiles' => ['files/*', 'images/*'],
   *       'excludedFiles' => ['secure/*', 'protected/private.php'],
   *     ],
   *     '/arst' => [*
   *       'accessLevel'   => ['admin'],
   *       'includedFiles' => null,
   *       'excludedFiles' => ['private/*'],
   *     ],
   *   ];</code>
   *
   * @param  string $username Username to find permissions for
   * @param  boolean $refreshCache Whether to refresh the cache or not
   * @return array Array with keys of the siteRoot and values of the sites permission arrays.
   */
  public static function getAllPermissionsForUser($username, $refreshCache = false)
  {
    if (!$refreshCache) {
      $cachedResult = self::getCache()->getValue(self::buildCacheKey($username), $found);
      if ($found) {
        return $cachedResult;
      }
    }
    $ttl = 43200; // 60*60*12 = 43200

    $dbal = self::getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->select('p.accessLevel')
      ->addSelect('p.includedFiles')
      ->addSelect('p.excludedFiles')
      ->addSelect('s.siteRoot')
      ->from('permissions', 'p')
      ->innerJoin('p', 'sites', 's', 'p.site_id = s.id')
      ->where('p.username = :username');

    $result = $dbal->fetchAll($qb->getSQL(), [':username' => $username]);

    if (!$result) {
      // person doesn't have any permissions. We should save this so we don't check again.
      self::getCache()->setValue(self::buildCacheKey($username), null, $ttl);
      return null;
    }

    $returnArray = [];

    foreach ($result as $sitePerms) {
      $returnArray[$sitePerms['siteRoot']] = [
        'accessLevel'   => ($sitePerms['accessLevel']) ? explode(',', $sitePerms['accessLevel']) : null,
        'includedFiles' => ($sitePerms['includedFiles']) ? explode(',', $sitePerms['includedFiles']) : null,
        'excludedFiles' => ($sitePerms['excludedFiles']) ? explode(',', $sitePerms['excludedFiles']) : null,
      ];
    }

    self::getCache()->setValue(self::buildCacheKey($username), $returnArray, $ttl);

    return $returnArray;
  }

  /**
   * Checks if a user can edit the specified draft
   *
   * @param  string $username Username of the user to check
   * @param  array  $draft    Draft to check
   * @return boolean
   */
  public static function userCanEditDraft($username, $draft)
  {
    if ((!empty($draft['additionalUsers']) && in_array($username, $draft['additionalUsers']) && $draft['type'] === Config::PUBLIC_DRAFT) || $username === $draft['username']) {
      // user either owns the draft or has access to edit this public draft.
      return true;
    } else {
      return false;
    }
  }

  /**
   * Checks to see if the specified username is the owner of the draft
   *
   * @param  string $username Username of the user to check
   * @param  array  $draft    Draft to check
   * @return boolean
   */
  public static function userOwnsDraft($username, $draft)
  {
    return ($draft['username'] === $username);
  }

  // DB actions

  /**
   * Delete user from site
   *
   * @param  string $username Username of the user to delete
   * @param  string $siteRoot Root of the site to delete the user from
   * @return boolean
   */
  public static function deleteUserFromSite($username, $siteRoot)
  {
    $siteId = self::getSiteId($siteRoot);

    if ($siteId === null) {
      // the specified site root doesn't exist. There can't be a user here.
      return true;
    }

    $dbal = self::getDBAL();
    $result = $dbal->delete('permissions', ['site_id' => $siteId, 'username' => $username]);

    if ($result > 0) {
      self::getCache()->clearValue(self::buildCacheKey($username));
      return true;
    }
    return false;
  }

  /**
   * Saves the specified permissions for the specified user
   * @param  string $username      Username of the user
   * @param  string $siteRoot      Root of the site to give permissions to the user for
   * @param  string $accessLevel   Level of access the user has to the site
   * @param  string|array $includedFiles Files the user has access to.
   *   Note: This overrides any files that match an excludedFiles pattern, but not a file directly.
   * @param  string|array $excludedFiles Files the user doesn't have access to
   *
   * @throws  UnexpectedValueException If the siteId isn't found or created
   * @return boolean  True on success or if the specified permissions are already set. False on failure
   */
  public static function saveUserPermissions($username, $siteRoot, $accessLevel, $includedFiles = null, $excludedFiles = null)
  {
    $siteId = self::saveNewSiteIfNeeded($siteRoot);
    if (!$siteId) {
      throw new UnexpectedValueException('$siteId doesn\'t appear to be a valid id.');
    }

    if (is_array($accessLevel)) {
      // convert this to a comma separated string of access levels
      $accessLevel = implode(',', $accessLevel);
    }

    if (is_array($includedFiles)) {
      // convert this to a comma separated string of filenames
      $includedFiles = implode(',', $includedFiles);
    }

    if (is_array($excludedFiles)) {
      // convert this to a comma separated string of filenames
      $excludedFiles = implode(',', $excludedFiles);
    }

    $dbal = self::getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->select('accessLevel')
      ->addSelect('includedFiles')
      ->addSelect('excludedFiles')
      ->from('permissions', 'p')
      ->where('username = :username')
      ->andWhere('site_id = :siteId');

    $result = $dbal->fetchAssoc($qb->getSQL(), [':username' => $username, ':siteId' => $siteId]);

    if ($result && $result['accessLevel'] === $accessLevel && $result['includedFiles'] === $includedFiles && $result['excludedFiles'] === $excludedFiles) {
      // nothing to do. User already exists in the requested state
      return true;
    }

    if ($result) {
      $result = $dbal->update('permissions', ['accessLevel' => $accessLevel, 'includedFiles' => $includedFiles, 'excludedFiles' => $excludedFiles], ['username' => $username, 'site_id' => $siteId]);
    } else {
      $result = $dbal->insert('permissions', ['username' => $username, 'site_id' => $siteId, 'accessLevel' => $accessLevel, 'includedFiles' => $includedFiles, 'excludedFiles' => $excludedFiles]);
    }

    if ($result) {
      // clear this person out of cache so they get the new values
      self::getCache()->clearValue(self::buildCacheKey($username));
      return true;
    }

    return false;
  }

  /**
   * Gets the site id for the requested site root
   *
   * @param  string $siteRoot Base url of the site to get the id for
   * @return string|null String of the id if found. Null otherwise
   */
  private static function getSiteId($siteRoot)
  {
    $dbal = self::getDBAL();

    $qb = $dbal->createQueryBuilder();

    $qb->addSelect('id')
      ->from('sites', 's')
      ->where('siteRoot = :siteRoot');

    $result = $dbal->fetchAssoc($qb->getSQL(), [':siteRoot' => $siteRoot]);

    if (isset($result['id'])) {
      return $result['id'];
    }
    return null;
  }

  /**
   * Saves a new site if it doesn't yet exist.
   *
   * @param  string $siteRoot Base url of the site to create
   *
   * @throws  RuntimeException If something failed when inserting
   * @return string|boolean  ID of the already existing site or a newly one. False if something failed.
   */
  private static function saveNewSiteIfNeeded($siteRoot)
  {
    $id = self::getSiteId($siteRoot);

    if ($id !== null) {
      return $id;
    } else {
      $dbal = self::getDBAL();
      // we need to create this site.
      $insertResult = $dbal->insert('sites', ['siteRoot' => $siteRoot]);
      if ($insertResult) {
        return $dbal->lastInsertId();
      } else {
        throw new RuntimeException('Inserting a new site didn\'t update any rows.');
      }
    }
  }
}