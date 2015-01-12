<?php
/**
 * @package Concert
 * @author  Billy Visto
 */

namespace Gustavus\Concert;

use Gustavus\Concert\Config,
  Gustavus\Doctrine\DBAL,
  Gustavus\GACCache\GlobalCache,
  Gustavus\Utility\Set,
  DateTime,
  UnexpectedValueException;

/**
 * Class for managing permissions
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
   * Time to live for caching
   *   60*60*12 = 43200
   * @var integer
   */
  private static $ttl = 43200;

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
   * Sanitizes keys for caching.
   *
   * @param  string $key
   * @return string sanitized key
   */
  private static function sanitizeKey($key)
  {
    return preg_replace('`[^A-Za-z0-9\-_.+@]`', '_', $key);
  }

  /**
   * Clears the permission cache for the specified user.
   *
   * @param  string $username Username to clear cache for.
   * @return void
   */
  private static function clearUsersCachedPermissions($username)
  {
    self::getCache()->clearValue(self::buildCacheKey($username));
  }

  /**
   * Checks to see if the specified file is accessible from the sitePerms for the site
   *   If a file is specified directly in excludedFiles, the file can't be touched.
   *   If a file is specified directly in includedFiles, but not excludedFiles, the file can be edited.
   *   If a wildcard or directory is found in both rules, excludedFiles will override includedFiles for this file.
   *
   * @param  string $filePath  Path of the file in question
   * @param  string $site      Site we are searching in
   * @param  array  $sitePerms Permissions the user has for the current site
   * @return boolean
   */
  private static function checkIncludedAndExcludedFilesForAccess($filePath, $site, $sitePerms)
  {
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
    $site = preg_replace('`/+`', '/', sprintf('/%s/', $site));
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

      if (!empty($sitePerms['includedFiles']) && in_array($pathInSite, $sitePerms['includedFiles'])) {
        // file is listed in our included files
        return true;
      }
    } else {
      $sitePerms['includedFiles'] = [];
    }

    // now we need to actually search through the permission arrays and match wildcards.
    $filePathArray = explode('/', $pathInSite);
    $filePathSearch = '';
    // storage for the latest rule the file name was found in.
    $foundRule = null;

    // first check if the site is excluding or including everything.
    if (in_array('/*', $sitePerms['includedFiles'])) {
      $foundRule = 'included';
    }
    if (in_array('/*', $sitePerms['excludedFiles'])) {
      $foundRule = 'excluded';
    }

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
   * Checks whether the user has access to edit the specified file.
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

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits editing.
    if (self::accessLevelExistsInArray($sitePerms['accessLevel'], Config::$nonEditableAccessLevels)) {
      // the current user's access level doesn't allow editing
      return false;
    }

    return self::checkIncludedAndExcludedFilesForAccess($filePath, $site, $sitePerms);
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

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits creating new pages.
    if (self::accessLevelExistsInArray($sitePerms['accessLevel'], Config::$nonCreationAccessLevels)) {
      // the current user's access level doesn't allow creating
      return false;
    }
    return self::checkIncludedAndExcludedFilesForAccess($filePath, $site, $sitePerms);
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

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits deleting pages.
    if (self::accessLevelExistsInArray($sitePerms['accessLevel'], Config::$nonDeletionAccessLevels)) {
      // the current user's access level doesn't allow deleting
      return false;
    }
    return self::checkIncludedAndExcludedFilesForAccess($filePath, $site, $sitePerms);
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

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits publishing drafts for people.
    if (self::accessLevelExistsInArray($sitePerms['accessLevel'], Config::$publishPendingDraftsAccessLevels)) {
      return self::checkIncludedAndExcludedFilesForAccess($filePath, $site, $sitePerms);
    }
    // the current user's access level doesn't allow publishing drafts
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

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits publishing files.
    if (self::accessLevelExistsInArray($sitePerms['accessLevel'], Config::$nonPublishingAccessLevels)) {
      // the current user's access level doesn't allow publishing
      return false;
    }
    return self::checkIncludedAndExcludedFilesForAccess($filePath, $site, $sitePerms);
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

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits editing site navs.
    if (self::accessLevelExistsInArray($sitePerms['accessLevel'], Config::$siteNavAccessLevels)) {
      return self::checkIncludedAndExcludedFilesForAccess($filePath, $site, $sitePerms);
    }
    // the current user's access level doesn't allow editing site navs
    return false;
  }

  /**
   * Checks to see if the specified user can edit raw html or not
   *
   * @param  string $username Username to check
   * @param  string $filePath Absolute path from the doc root to the file in question
   * @return boolean
   */
  public static function userCanEditRawHTML($username, $filePath)
  {
    $site = self::findUsersSiteForFile($username, $filePath);
    if (empty($site)) {
      return false;
    }
    $sitePerms = self::getUserPermissionsForSite($username, $site);

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits editing raw html.
    if (self::accessLevelExistsInArray($sitePerms['accessLevel'], Config::$editRawHTMLAccessLevels)) {
      return self::checkIncludedAndExcludedFilesForAccess($filePath, $site, $sitePerms);
    }
    // the current user's access level doesn't allow editing raw html.
    return false;
  }

  /**
   * Checks to see if the specified user can upload files or not
   *
   * @param  string $username Username to check
   * @param  string $filePath Absolute path from the doc root to the file in question
   * @return boolean
   */
  public static function userCanUpload($username, $filePath)
  {
    $site = self::findUsersSiteForFile($username, $filePath);
    if (empty($site)) {
      return false;
    }
    $sitePerms = self::getUserPermissionsForSite($username, $site);

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits uploading.
    if (self::accessLevelExistsInArray($sitePerms['accessLevel'], Config::$nonUploadingAccessLevels)) {
      // nope. They cannot upload.
      return false;
    }
    return self::checkIncludedAndExcludedFilesForAccess($filePath, $site, $sitePerms);
  }

  /**
   * Checks to see if the specified user can manage revisions or not
   *
   * @param  string $username Username to check
   * @param  string $filePath Absolute path from the doc root to the file in question
   * @return boolean
   */
  public static function userCanManageRevisions($username, $filePath)
  {
    $site = self::findUsersSiteForFile($username, $filePath);
    if (empty($site)) {
      return false;
    }
    $sitePerms = self::getUserPermissionsForSite($username, $site);

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits editing raw html.
    if (self::accessLevelExistsInArray($sitePerms['accessLevel'], Config::$manageRevisionsAccessLevels)) {
      return self::checkIncludedAndExcludedFilesForAccess($filePath, $site, $sitePerms);
    }
    // the current user's access level doesn't allow editing raw html.
    return false;
  }

  /**
   * Checks to see if the specified user can view revisions or not
   *
   * @param  string $username Username to check
   * @param  string $filePath Absolute path from the doc root to the file in question
   * @return boolean
   */
  public static function userCanViewRevisions($username, $filePath)
  {
    $site = self::findUsersSiteForFile($username, $filePath);
    if (empty($site)) {
      return false;
    }
    $sitePerms = self::getUserPermissionsForSite($username, $site);

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits viewing revisions.
    if (self::accessLevelExistsInArray($sitePerms['accessLevel'], Config::$nonRevisionsAccessLevels)) {
      // the current user's access level doesn't allow viewing revisions
      return false;
    }
    return self::checkIncludedAndExcludedFilesForAccess($filePath, $site, $sitePerms);
  }

  /**
   * Checks to see if the specified user can manage banners or not
   *
   * @param  string $username Username to check
   * @param  string $filePath Absolute path from the doc root to the file in question
   * @return boolean
   */
  public static function userCanManageBanners($username, $filePath)
  {
    $site = self::findUsersSiteForFile($username, $filePath);
    if (empty($site)) {
      return false;
    }
    $sitePerms = self::getUserPermissionsForSite($username, $site);

    if (empty($sitePerms['accessLevel'])) {
      // the user doesn't have an access level for this site.
      return false;
    }
    // We need to check to see if their accessLevel permits editing banners.
    if (self::accessLevelExistsInArray($sitePerms['accessLevel'], Config::$manageBannersAccessLevels)) {
      return self::checkIncludedAndExcludedFilesForAccess($filePath, $site, $sitePerms);
    }
    // the current user's access level doesn't allow editing banners
    return false;
  }

  /**
   * Checks to see if the specified accessLevels can be found in $arrayToCheck
   * @param  array $accessLevels AccessLevels to try finding in the specified array
   * @param  array $arrayToCheck Array to search for accessLevels in
   * @return boolean
   */
  private static function accessLevelExistsInArray(array $accessLevels, array $arrayToCheck)
  {
    foreach ($accessLevels as $accessLevel) {
      if (in_array($accessLevel, $arrayToCheck)) {
        // the user has an access level that exists in the specified array
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
      if ($file === '/*') {
        // we are already excluding everything properly.
        continue;
      } else if ($file === '*') {
        // shorthand way for excluding everything. Lets make this a little more uniform.
        $file = '/*';
        continue;
      }
      $isDir = (strpos($file, '.') === false) ? true : false;
      $file = trim(str_replace('//', '/', $file), '/');

      if ($isDir && substr($file, -2) !== '/*') {
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
    $filePath = str_replace('//', '/', $filePath);

    if (self::isUserAdmin($username) || self::isUserSuperUser($username)) {
      // user might not have a site for this file, but they have global access to all sites.
      // We need to find sites for them
      $sites = self::findSitesContainingFile($filePath);
    } else {
      $sites = self::getUsersSites($username);
    }
    if (empty($sites)) {
      return null;
    }

    $sites = self::sortSitesByDepth($sites);
    foreach ($sites as $site) {
      if (strpos($filePath, $site) !== false) {
        return $site;
      }
    }
    return null;
  }

  /**
   * Finds the closest site for the specified filePath.
   *
   * @param  string $filePath Path to the file we are searching for a site for.
   * @return string|null String if a site is found, null otherwise.
   */
  public static function findClosestSiteForFile($filePath)
  {
    $filePath = str_replace('//', '/', $filePath);

    $sites = self::findSitesContainingFile($filePath);
    if (empty($sites)) {
      return null;
    }

    $sites = self::sortSitesByDepth($sites);
    foreach ($sites as $site) {
      if (strpos($filePath, $site) !== false) {
        return $site;
      }
    }
    return null;
  }

  /**
   * Finds the top-most parent site for this file.
   *
   * @param  string $filePath Path to the file we are searching for sites for.
   * @return array|null Array if sites are found, null otherwise.
   */
  public static function findParentSiteForFile($filePath)
  {
    $filePath = str_replace('//', '/', $filePath);

    $sites = self::findSitesContainingFile($filePath);

    if (empty($sites)) {
      return null;
    }

    $sites = self::sortSitesByDepth($sites);

    return end($sites);
  }

  /**
   * Finds all of the sites that contain the current file.
   *
   * @param  string $filePath Path to the file we are searching for sites for.
   * @param  boolean $includeSitePerms Whether to include site specific permissions or not.
   * @return array|null Array if sites are found, null otherwise.
   */
  private static function findSitesContainingFile($filePath, $includeSitePerms = false)
  {
    $filePathArray = explode('/', str_replace('//', '/', $filePath));

    $searchKey = (empty($filePathArray[0])) ? 1 : 0;
    $sites = self::getSitesFromBase('/' . $filePathArray[$searchKey], $includeSitePerms);
    if (empty($sites)) {
      return null;
    }
    $foundSites = [];
    foreach ($sites as $site) {
      if (($includeSitePerms && strpos($filePath, $site['siteRoot']) !== false) || (!$includeSitePerms && strpos($filePath, $site) !== false)) {
        $foundSites[] = $site;
      }
    }
    return $foundSites;
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
      $superUserPerms = Config::$superUserPermissions;

      $siteLevelPerms = self::getInheritedPermissionsForSite($siteRoot);
      if (!empty($siteLevelPerms['excludedFiles'])) {
        // excluded files exist on the site. We want superUsers to respect these
        $superUserPerms['excludedFiles'] = $siteLevelPerms['excludedFiles'];
      }
      return $superUserPerms;
    }
    if (self::isUserAdmin($username)) {
      $adminPerms = Config::$adminPermissions;

      $siteLevelPerms = self::getInheritedPermissionsForSite($siteRoot);
      if (!empty($siteLevelPerms['excludedFiles'])) {
        // excluded files exist on the site. We want admins to respect these
        $adminPerms['excludedFiles'] = $siteLevelPerms['excludedFiles'];
      }
      return $adminPerms;
    }
    if (isset($perms[$siteRoot])) {
      return $perms[$siteRoot];
    }

    return null;
  }

  /**
   * Sorts the sites by depth
   *
   * @param  array $sites Array of sites to sort
   * @return array Sorted array. The deepest sites will be first.
   */
  private static function sortSitesByDepth(array $sites)
  {
    usort($sites, function($a, $b) {
      return strlen($b) - strlen($a);
    });
    return $sites;
  }

  /**
   * Finds publishers for the specified site that have one of the specified access levels
   *
   * @param  string $site Site to find users for
   * @param  array  $accessLevel Access level of the users to get
   * @return array
   */
  private static function findUsersForSiteByAccessLevel($site, $accessLevel)
  {
    $dbal = self::getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->select('p.username')
      ->addSelect('p.accessLevel')
      ->from('permissions', 'p')
      ->innerJoin('p', 'sites', 's', 'p.site_id = s.id')
      ->where('s.siteRoot = :site');

    $params = [':site' => $site];

    // we need accessLevel to be an array for below
    $accessLevel = (array) $accessLevel;
    $ors = [];
    $i = 1;
    foreach ($accessLevel as $level) {
      $ors[] = $qb->expr()->like('p.accessLevel', ':accessLevel' . $i);
      $params[':accessLevel' . $i++] = "%{$level}%";
    }
    $qb->andWhere(call_user_func_array([$qb->expr(), 'orX'], $ors));

    $result = $dbal->fetchAll($qb->getSQL(), $params);

    $usernames = [];
    foreach ($result as $resultPiece) {
      if (self::accessLevelExistsInArray(explode(',', $resultPiece['accessLevel']), $accessLevel)) {
        $usernames[] = $resultPiece['username'];
      }
    }
    return $usernames;
  }

  /**
   * Finds people who have access to publish pending drafts for the current file
   *
   * @param  string $filePath File to find publishers for
   * @return array|null Array of publisher usernames or null if none found
   */
  public static function findPublishersForFile($filePath)
  {
    $sites = self::findSitesContainingFile($filePath);

    if (empty($sites)) {
      return null;
    }

    $sites = self::sortSitesByDepth($sites);

    foreach ($sites as $site) {
      $publishers = self::findUsersForSiteByAccessLevel($site, Config::$publishPendingDraftsAccessLevels);
      if (!empty($publishers)) {
        // publishers exist for this site.
        return $publishers;
      }
    }
    // no publishers found.
    return null;
  }

  /**
   * Checks to see if the user is a super user.
   *
   * @param  string   $username Username to check
   * @return boolean
   */
  public static function isUserSuperUser($username)
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
  public static function isUserAdmin($username)
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
   * @param  boolean $includeSitePerms Whether to include site specific permissions or not.
   * @param  boolean $includeSiteId Whether to include the site id or not.
   * @return array
   */
  public static function getSitesFromBase($siteBase, $includeSitePerms = false, $includeSiteId = false)
  {
    // make the key that the current request will exist in inside of our cached results
    $currRequestCacheKey = self::sanitizeKey(sprintf(
        'concertSitesFromBase-%s-%s-%s',
        $siteBase,
        ($includeSitePerms ? 'true' : 'false'),
        ($includeSiteId ? 'true' : 'false')
    ));

    $sitesFromBaseCacheKey = 'concertSitesFromBaseResults';
    $cachedSitesFromBase = self::getCache()->getValue($sitesFromBaseCacheKey, $found);
    if ($found) {
      // we now need to see if our key is in the resultset
      if (isset($cachedSitesFromBase[$currRequestCacheKey])) {
        return $cachedSitesFromBase[$currRequestCacheKey];
      }
    }
    $dbal = self::getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->select('s.siteRoot')
      ->from('sites', 's')
      ->where('s.siteRoot LIKE :siteBase')
      ->orderBy('s.siteRoot');

    if ($includeSitePerms) {
      $qb->addSelect('s.excludedFiles');
    }
    if ($includeSiteId) {
      $qb->addSelect('s.id');
    }

    $result = $dbal->fetchAll($qb->getSQL(), [':siteBase' => $siteBase . '%%']);
    if (!$includeSitePerms) {
      $result = (new Set($result))->flattenValues()->getValue();
    }
    // now we need to store our result in cache.
    if ($found) {
      // we have cached sites, but not this particular request.
      $cachedSitesFromBase[$currRequestCacheKey] = $result;
    } else {
      $cachedSitesFromBase = [$currRequestCacheKey => $result];
    }
    self::getCache()->setValue($sitesFromBaseCacheKey, $cachedSitesFromBase, self::$ttl);
    return $result;
  }

  /**
   * Clears the cache for all getSitesFromBase requests
   *
   * @return void
   */
  private static function clearSitesFromBaseCache()
  {
    self::getCache()->clearValue('concertSitesFromBaseResults');
  }

  /**
   * Gets all the sites that exist for a specific user
   *
   * @param  string $username Username to get sites for
   * @param  boolean $includeSitePerms Whether to include site specific permissions or not.
   * @param  boolean $includeSiteId Whether to include the site id or not.
   * @return array
   */
  public static function getSitesForUser($username, $includeSitePerms = false, $includeSiteId = false)
  {
    $dbal = self::getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->select('s.siteRoot')
      ->from('sites', 's')
      ->innerJoin('s', 'permissions', 'p', 'p.site_id = s.id')
      ->where('p.username = :username');

    if ($includeSitePerms) {
      $qb->addSelect('s.excludedFiles');
    }
    if ($includeSiteId) {
      $qb->addSelect('s.id');
    }

    $result = $dbal->fetchAll($qb->getSQL(), [':username' => $username]);
    if ($includeSitePerms) {
      return $result;
    }
    return (new Set($result))->flattenValues()->getValue();
  }

  /**
   * Gets permissions inherited from parent sites for the specified site
   *
   * @param  string $siteBase Site base to get inherited permissions for
   * @return array|null  Array if any exist with keys of permissions. Null if none exist.
   */
  private static function getInheritedPermissionsForSite($siteBase)
  {
    $parentSites = self::findSitesContainingFile(str_replace('//', '/', $siteBase . DIRECTORY_SEPARATOR . 'index.php'), true);
    if (empty($parentSites)) {
      return null;
    }

    $perms = [];
    foreach ($parentSites as $parentSite) {
      if (!empty($parentSite['excludedFiles'])) {
        if (!isset($perms['excludedFiles'])) {
          $perms['excludedFiles'] = explode(',', $parentSite['excludedFiles']);
        } else {
          $perms['excludedFiles'] = array_unique(array_merge($perms['excludedFiles'], explode(',', $parentSite['excludedFiles'])));
        }
      }
    }
    return empty($perms) ? null : $perms;
  }

  /**
   * Checks to see if permissions for a site have expired
   *
   * @param  array $sitePerms Array of site permissions
   * @return boolean True if permissions have expired. False otherwise.
   */
  private static function haveSitePermissionsExpired($sitePerms)
  {
    if (empty($sitePerms['expirationDate'])) {
      return false;
    }
    $expirationDate = new DateTime($sitePerms['expirationDate']);
    if ((int) $expirationDate->format('U') < time()) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * Removes any expired permissions a person may have.
   *
   * @param  array $permissions Array of permissions from getAllPermissionsForUser
   * @return array|null
   */
  private static function removeExpiredPermissions($permissions)
  {
    if (empty($permissions)) {
      return null;
    }

    $permissions = array_filter($permissions, function($sitePerms) {
      return !self::haveSitePermissionsExpired($sitePerms);
    });

    return empty($permissions) ? null : $permissions;
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
    if (!$refreshCache && (!isset($_GET['refresh']) || $_GET['refresh'] === 'false')) {
      $cachedResult = self::getCache()->getValue(self::buildCacheKey($username), $found);
      if ($found) {
        return self::removeExpiredPermissions($cachedResult);
      }
    }

    $dbal = self::getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->select('p.accessLevel')
      ->addSelect('p.includedFiles')
      ->addSelect('p.excludedFiles')
      ->addSelect('p.expirationDate')
      ->addSelect('s.siteRoot')
      ->addSelect('s.excludedFiles as siteExcludedFiles')
      ->from('permissions', 'p')
      ->innerJoin('p', 'sites', 's', 'p.site_id = s.id')
      ->where('p.username = :username');

    $result = $dbal->fetchAll($qb->getSQL(), [':username' => $username]);

    if (!$result) {
      // person doesn't have any permissions. We should save this so we don't check again.
      self::getCache()->setValue(self::buildCacheKey($username), null, self::$ttl);
      return null;
    }

    $returnArray = [];

    foreach ($result as $sitePerms) {
      if (self::haveSitePermissionsExpired($sitePerms)) {
        // their permissions have expired.
        continue;
      }
      $returnArray[$sitePerms['siteRoot']] = [
        'includedFiles' => ($sitePerms['includedFiles']) ? explode(',', $sitePerms['includedFiles']) : null,
        'excludedFiles' => ($sitePerms['excludedFiles']) ? explode(',', $sitePerms['excludedFiles']) : null,
        'expirationDate' => ($sitePerms['expirationDate']) ?: null,
      ];

      $inheritedPerms = self::getInheritedPermissionsForSite($sitePerms['siteRoot']);

      if (!empty($sitePerms['siteExcludedFiles'])) {
        $sitePerms['siteExcludedFiles'] = explode(',', $sitePerms['siteExcludedFiles']);
      }

      if (!empty($inheritedPerms['excludedFiles'])) {
        // we have inhrited permissions to add to our site.
        if (empty($sitePerms['siteExcludedFiles'])) {
          $sitePerms['siteExcludedFiles'] = $inheritedPerms['excludedFiles'];
        } else {
          // we need to merge them together.
          $sitePerms['siteExcludedFiles'] = array_unique(array_merge($sitePerms['siteExcludedFiles'], $inheritedPerms['excludedFiles']));
        }
      }

      if (!empty($sitePerms['siteExcludedFiles'])) {
        if (!is_array($returnArray[$sitePerms['siteRoot']]['excludedFiles'])) {
          $returnArray[$sitePerms['siteRoot']]['excludedFiles'] = $sitePerms['siteExcludedFiles'];
        } else {
          $returnArray[$sitePerms['siteRoot']]['excludedFiles'] = array_unique(array_merge($returnArray[$sitePerms['siteRoot']]['excludedFiles'], $sitePerms['siteExcludedFiles']));
        }
      }

      // now add accessLevels to our array
      if ($sitePerms['accessLevel']) {
        $accessLevels = array_filter(explode(',', $sitePerms['accessLevel']));
        $returnArray[$sitePerms['siteRoot']]['accessLevel'] = !empty($accessLevels) ? $accessLevels : null;
      } else {
        $returnArray[$sitePerms['siteRoot']]['accessLevel'] = null;
      }
    }

    self::getCache()->setValue(self::buildCacheKey($username), $returnArray, self::$ttl);

    return empty($returnArray) ? null : $returnArray;
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
   * @param  boolean $siteRootIsSiteId Flag to specify that the specified siteRoot is actually the site's ID.
   * @return boolean
   */
  public static function deleteUserFromSite($username, $siteRoot, $siteRootIsSiteId = false)
  {
    if ($siteRootIsSiteId) {
      $siteId = $siteRoot;
    } else {
      $siteId = self::getSiteId($siteRoot);
    }

    if ($siteId === null) {
      // the specified site root doesn't exist. There can't be a user here.
      return true;
    }

    $dbal = self::getDBAL();
    $result = $dbal->delete('permissions', ['site_id' => $siteId, 'username' => $username]);

    if ($result > 0) {
      self::clearUsersCachedPermissions($username);
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
   * @param  DateTime $expirationDate Date this person's permissions expire
   * @param  boolean $siteRootIsSiteId Flag to specify that the specified siteRoot is actually the site's ID.
   *
   * @throws  UnexpectedValueException If the siteId isn't found or created
   * @return boolean  True on success or if the specified permissions are already set. False on failure
   */
  public static function saveUserPermissions($username, $siteRoot, $accessLevel, $includedFiles = null, $excludedFiles = null, DateTime $expirationDate = null, $siteRootIsSiteId = false)
  {
    if ($siteRootIsSiteId) {
      $siteId = $siteRoot;
    } else {
      $siteId = self::saveNewSiteIfNeeded($siteRoot);
    }
    if (!$siteId) {
      throw new UnexpectedValueException('$siteId doesn\'t appear to be a valid id.');
    }

    if (is_array($includedFiles)) {
      // convert this to a comma separated string of filenames
      $includedFiles = implode(',', $includedFiles);
    }

    if (is_array($excludedFiles)) {
      // convert this to a comma separated string of filenames
      $excludedFiles = implode(',', $excludedFiles);
    }

    if (is_array($accessLevel)) {
      $accessLevel = implode(',', $accessLevel);
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

    $properties = ['accessLevel' => $accessLevel, 'includedFiles' => $includedFiles, 'excludedFiles' => $excludedFiles, 'expirationDate' => $expirationDate];

    if ($result) {
      $result = $dbal->update('permissions', ['accessLevel' => $accessLevel, 'includedFiles' => $includedFiles, 'excludedFiles' => $excludedFiles, 'expirationDate' => $expirationDate], ['username' => $username, 'site_id' => $siteId], [null, null, null, 'datetime']);
    } else {
      $result = $dbal->insert('permissions', ['username' => $username, 'site_id' => $siteId, 'accessLevel' => $accessLevel, 'includedFiles' => $includedFiles, 'excludedFiles' => $excludedFiles, 'expirationDate' => $expirationDate], [null, null, null, null, null, 'datetime']);
    }

    if ($result) {
      // clear this person out of cache so they get the new values
      self::clearUsersCachedPermissions($username);
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
  public static function getSiteId($siteRoot)
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
   * @param  string|array $excludedFiles Files the user doesn't have access to
   *
   * @throws  RuntimeException If something failed when inserting
   * @return string|boolean  ID of the already existing site or a newly one. False if something failed.
   */
  public static function saveNewSiteIfNeeded($siteRoot, $excludedFiles = null)
  {
    $id = self::getSiteId($siteRoot);

    if ($id !== null) {
      return $id;
    } else {
      if (is_array($excludedFiles)) {
        // convert this to a comma separated string of filenames
        $excludedFiles = implode(',', $excludedFiles);
      }
      $dbal = self::getDBAL();
      // we need to create this site.
      $dbal->beginTransaction();
      $insertResult = $dbal->insert('sites', ['siteRoot' => $siteRoot, 'excludedFiles' => $excludedFiles]);
      $lastInsertId = $dbal->lastInsertId();
      $dbal->commit();
      // clear global admins cached permissions
      self::clearAdminsFromCache();
      // clear our sitesFromBase results
      self::clearSitesFromBaseCache();
      if ($insertResult > 0) {
        return $lastInsertId;
      } else {
        throw new RuntimeException('Inserting a new site didn\'t update any rows.');
      }
    }
  }

  /**
   * Saves a new site if it doesn't yet exist.
   *
   * @param  integer $siteId Id of the site to update
   * @param  string|array $excludedFiles Files the user doesn't have access to
   *
   * @return integer Number of rows affected
   */
  public static function updateSite($siteId, $excludedFiles = null)
  {
    if (is_array($excludedFiles)) {
      // convert this to a comma separated string of filenames
      $excludedFiles = implode(',', $excludedFiles);
    }
    $dbal = self::getDBAL();

    self::clearCacheForSite($siteId);
    // clear global admins cached permissions
    self::clearAdminsFromCache();
    self::clearSitesFromBaseCache();

    return $dbal->update('sites', ['excludedFiles' => $excludedFiles], ['id' => $siteId]);
  }

  /**
   * Deletes a specified site
   *
   * @param  integer $siteId ID of the site to delete
   * @return void
   */
  public static function deleteSite($siteId)
  {
    $dbal = self::getDBAL();

    self::clearCacheForSite($siteId);
    self::clearAdminsFromCache();
    $dbal->delete('sites', ['id' => $siteId]);
    $dbal->delete('permissions', ['site_id' => $siteId]);
    // clear our sitesFromBase results
    self::clearSitesFromBaseCache();
  }

  /**
   * Moves a site to a new siteRoot
   *
   * @param  integer $siteId     ID of the site to move
   * @param  string $newSiteRoot New site root
   * @return integer             Number or rows affected
   */
  public static function moveSite($siteId, $newSiteRoot)
  {
    $dbal = self::getDBAL();

    self::clearAdminsFromCache();
    self::clearSitesFromBaseCache();
    self::clearCacheForSite($siteId);

    return $dbal->update('sites', ['siteRoot' => $newSiteRoot], ['id' => $siteId]);
  }

  /**
   * Gets all admints and super users
   *
   * @return array Array of usernames of admins and super users
   */
  private static function getAllSuperUsersAndAdmins()
  {
    $dbal = self::getDBAL();
    $qb = $dbal->createQueryBuilder();
    $qb->select('username')
      ->from('permissions', 'p')
      ->where('accessLevel = :superUserLevel')
      ->orWhere('accessLevel = :adminLevel');

    $result = $dbal->fetchAll($qb->getSQL(), [':superUserLevel' => Config::SUPER_USER, ':adminLevel' => Config::ADMIN_ACCESS_LEVEL]);

    return (new Set($result))->flattenValues()->getValue();
  }

  /**
   * Clears all admins from cache
   *
   * @return void
   */
  private static function clearAdminsFromCache()
  {
    $admins = self::getAllSuperUsersAndAdmins();
    foreach ($admins as $admin) {
      // clear all permissions for admins.
      self::clearUsersCachedPermissions($admin);
    }
  }

  /**
   * Clears all cached permissions for all users attached to a site
   *
   * @param  integer $siteId Site id to clear cache for
   * @return void
   */
  private static function clearCacheForSite($siteId)
  {
    $dbal = self::getDBAL();
    $qb = $dbal->createQueryBuilder();
    $qb->select('username')
      ->from('permissions', 'p')
      ->where('site_id = :siteId');

    $result = $dbal->fetchAll($qb->getSQL(), [':siteId' => $siteId]);

    foreach ((new Set($result))->flattenValues()->getValue() as $username) {
      self::clearUsersCachedPermissions($username);
    }
  }
}