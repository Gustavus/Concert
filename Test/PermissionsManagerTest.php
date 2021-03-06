<?php
/**
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test;

use Gustavus\Concert\PermissionsManager,
  Gustavus\Concert\Config,
  Gustavus\Concert\Utility,
  DateTime;

/**
 * Class to test PermissionManager class
 *
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class PermissionsManagerTest extends TestBase
{
  /**
   * Sets up environment for every test
   *
   * @return void
   */
  public function setUp()
  {
    parent::setUp();
  }

  /**
   * Tears down the environment for every test
   *
   * @return void
   */
  public function tearDown()
  {
    parent::tearDown();
  }

  /**
   * @test
   */
  public function saveNewSiteIfNeeded()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->assertSame('1', $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/billy']));
    // verifies that a new one isn't created.
    $this->assertSame('1', $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/billy']));
    // new site
    $this->assertSame('2', $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/jerry']));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveUserPermissions()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->assertTrue($this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', ['admin', 'test']]));
    // nothing should happen here. This state already exists.
    $this->assertTrue($this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'admin']));
    // new one would get created here.
    $this->assertTrue($this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'none']));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function deleteUserFromSite()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->assertTrue($this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'admin']));
    $this->assertNotEmpty(PermissionsManager::getUsersSites('bvisto'));

    $this->assertTrue(PermissionsManager::deleteUserFromSite('bvisto', '/billy'));
    // no site to remove from
    $this->assertFalse(PermissionsManager::deleteUserFromSite('bvisto', '/billy'));

    $this->assertEmpty(PermissionsManager::getUsersSites('bvisto'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function deleteUserFromSiteNoSite()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->assertEmpty(PermissionsManager::getUsersSites('bvisto'));

    // no site to remove from, they are already deleted
    $this->assertTrue(PermissionsManager::deleteUserFromSite('bvisto', '/billy'));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function getAllPermissionsForUser()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'admin']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*']);

    $actual = $this->call('PermissionsManager', 'getAllPermissionsForUser', ['bvisto']);

    $expected = [
      '/billy' => [
        'includedFiles'  => null,
        'excludedFiles'  => null,
        'expirationDate' => null,
        'accessLevel'    => ['admin'],
      ],
      '/arst' => [
        'includedFiles'  => ['files/*'],
        'excludedFiles'  => ['private/*'],
        'expirationDate' => null,
        'accessLevel'    => ['admin'],
      ],
    ];

    $this->assertSame($expected, $actual);

    // update one to verify that updates work
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'none']);

    $expected['/billy']['accessLevel'] = ['none'];

    $actual = $this->call('PermissionsManager', 'getAllPermissionsForUser', ['bvisto']);
    $this->assertSame($expected, $actual);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveAndGetUserPermissionsArrays()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->assertTrue($this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test', ['files/*', 'private/public/*'], ['private/*', 'protected/*']]));

    $permissions = $this->call('PermissionsManager', 'getAllPermissionsForUser', ['bvisto']);

    $expected = [
      '/billy' => [
        'includedFiles'  => ['files/*', 'private/public/*'],
        'excludedFiles'  => ['private/*', 'protected/*'],
        'expirationDate' => null,
        'accessLevel'    => ['test'],
      ],
    ];

    $this->assertSame($expected, $permissions);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveAndGetUserPermissionsWithExpirationDate()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $expirationDate = new DateTime('+1 day');
    $this->assertTrue(PermissionsManager::saveUserPermissions('bvisto', '/billy', 'test', ['files/*', 'private/public/*'], ['private/*', 'protected/*'], $expirationDate));

    $permissions = $this->call('PermissionsManager', 'getAllPermissionsForUser', ['bvisto']);

    $expected = [
      '/billy' => [
        'includedFiles'  => ['files/*', 'private/public/*'],
        'excludedFiles'  => ['private/*', 'protected/*'],
        'expirationDate' => $expirationDate->format('Y-m-d H:i:s'),
        'accessLevel'    => ['test'],
      ],
    ];

    $this->assertSame($expected, $permissions);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveAndGetUserPermissionsWithExpiredExpirationDate()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $expirationDate = new DateTime('-1 day');
    $this->assertTrue(PermissionsManager::saveUserPermissions('bvisto', '/billy', 'test', ['files/*', 'private/public/*'], ['private/*', 'protected/*'], $expirationDate));

    $permissions = $this->call('PermissionsManager', 'getAllPermissionsForUser', ['bvisto']);

    $this->assertNull($permissions);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveAndGetUserPermissionsWithExpiredSite()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $expirationDate = new DateTime('-1 day');
    $this->assertTrue(PermissionsManager::saveUserPermissions('bvisto', '/billy', 'test', ['files/*', 'private/public/*'], ['private/*', 'protected/*'], $expirationDate));
    $this->assertTrue(PermissionsManager::saveUserPermissions('bvisto', '/arst', 'test', ['files/*', 'private/public/*'], ['private/*', 'protected/*']));

    $permissions = $this->call('PermissionsManager', 'getAllPermissionsForUser', ['bvisto']);

    $expected = [
      '/arst' => [
        'includedFiles'  => ['files/*', 'private/public/*'],
        'excludedFiles'  => ['private/*', 'protected/*'],
        'expirationDate' => null,
        'accessLevel'    => ['test'],
      ],
    ];

    $this->assertSame($expected, $permissions);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveAndGetUserPermissionsWithExpiredSiteFromCache()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $expirationDate = new DateTime('+1 second');
    $this->assertTrue(PermissionsManager::saveUserPermissions('bvisto', '/billy', 'test', ['files/*', 'private/public/*'], ['private/*', 'protected/*'], $expirationDate));
    $this->assertTrue(PermissionsManager::saveUserPermissions('bvisto', '/arst', 'test', ['files/*', 'private/public/*'], ['private/*', 'protected/*']));

    $permissions = $this->call('PermissionsManager', 'getAllPermissionsForUser', ['bvisto']);

    $expected = [
      '/billy' => [
        'includedFiles'  => ['files/*', 'private/public/*'],
        'excludedFiles'  => ['private/*', 'protected/*'],
        'expirationDate' => $expirationDate->format('Y-m-d H:i:s'),
        'accessLevel'    => ['test'],
      ],
      '/arst' => [
        'includedFiles'  => ['files/*', 'private/public/*'],
        'excludedFiles'  => ['private/*', 'protected/*'],
        'expirationDate' => null,
        'accessLevel'    => ['test'],
      ],
    ];

    $this->assertSame($expected, $permissions);

    // let the cached expiration date become expired.
    sleep(2);
    $permissions = $this->call('PermissionsManager', 'getAllPermissionsForUser', ['bvisto']);

    $expected = [
      '/arst' => [
        'includedFiles'  => ['files/*', 'private/public/*'],
        'excludedFiles'  => ['private/*', 'protected/*'],
        'expirationDate' => null,
        'accessLevel'    => ['test'],
      ],
    ];

    $this->assertSame($expected, $permissions);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveAndGetUserPermissionsExcludedSiteFiles()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->assertNotFalse($this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/billy', 'concourseApp/*']));
    $this->assertTrue($this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test']));

    $permissions = $this->call('PermissionsManager', 'getAllPermissionsForUser', ['bvisto']);

    $expected = [
      '/billy' => [
        'includedFiles'  => null,
        'excludedFiles'  => ['concourseApp/*'],
        'expirationDate' => null,
        'accessLevel'    => ['test'],
      ],
    ];

    $this->assertSame($expected, $permissions);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveAndGetUserPermissionsExcludedSiteFilesWithUserExcludes()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->assertNotFalse($this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/billy', ['concourseApp/', 'private/*']]));
    $this->assertTrue($this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test', ['files/*', 'private/public/*'], ['private/*', 'protected/*']]));

    $permissions = $this->call('PermissionsManager', 'getAllPermissionsForUser', ['bvisto']);

    $expected = [
      '/billy' => [
        'includedFiles'  => ['files/*', 'private/public/*'],
        'excludedFiles'  => ['private/*', 'protected/*', 'concourseApp/'],
        'expirationDate' => null,
        'accessLevel'    => ['test'],
      ],
    ];

    $this->assertSame($expected, $permissions);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveAndGetUserPermissionsExcludedSiteFilesWithInheritedSitePerms()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->assertNotFalse($this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/billy', ['concourseApp/', 'arst/private/*']]));
    $this->assertTrue($this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/arst/', 'test', [], []]));

    $permissions = $this->call('PermissionsManager', 'getAllPermissionsForUser', ['bvisto']);

    $expected = [
      '/billy/arst/' => [
        'includedFiles'  => null,
        'excludedFiles'  => ['private/*'],
        'expirationDate' => null,
        'accessLevel'    => ['test'],
      ],
    ];

    $this->assertSame($expected, $permissions);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function getAllPermissionsForUserIncludeAndExcludeFileArrays()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'admin', ['files/*', 'images/*'], ['secure/*', 'protected/private.php']]);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*']);

    $actual = $this->call('PermissionsManager', 'getAllPermissionsForUser', ['bvisto']);

    $expected = [
      '/billy' => [
        'includedFiles'  => ['files/*', 'images/*'],
        'excludedFiles'  => ['secure/*', 'protected/private.php'],
        'expirationDate' => null,
        'accessLevel'    => ['admin'],
      ],
      '/arst' => [
        'includedFiles'  => ['files/*'],
        'excludedFiles'  => ['private/*'],
        'expirationDate' => null,
        'accessLevel'    => ['admin'],
      ],
    ];

    $this->assertSame($expected, $actual);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function getAllPermissionsForUserInheritingSubSitesPerms()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'testing']);

    PermissionsManager::saveNewSiteIfNeeded('/billy/arst/', 'index.php');

    $actual = $this->call('PermissionsManager', 'getAllPermissionsForUser', ['bvisto']);

    $expected = [
      '/billy' => [
        'includedFiles'  => null,
        'excludedFiles'  => ['arst/index.php'],
        'expirationDate' => null,
        'accessLevel'    => ['testing'],
      ],
    ];

    $this->assertSame($expected, $actual);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function removeExpiredPermissions()
  {
    $expirationDate = new DateTime('+1 day');

    $permissions = [
      '/billy' => [
        'includedFiles'  => ['files/*', 'private/public/*'],
        'excludedFiles'  => ['private/*', 'protected/*'],
        'expirationDate' => $expirationDate->format('Y-m-d G:i:s'),
        'accessLevel'    => ['test'],
      ],
      '/arst' => [
        'includedFiles'  => null,
        'excludedFiles'  => null,
        'expirationDate' => (new DateTime('-2 days'))->format('Y-m-d G:i:s'),
        'accessLevel'    => ['test'],
      ],
      '/test' => [
        'includedFiles'  => null,
        'excludedFiles'  => null,
        'expirationDate' => null,
        'accessLevel'    => ['test'],
      ],
    ];

    $actual = $this->call('PermissionsManager', 'removeExpiredPermissions', [$permissions]);

    $expected = [
      '/billy' => [
        'includedFiles'  => ['files/*', 'private/public/*'],
        'excludedFiles'  => ['private/*', 'protected/*'],
        'expirationDate' => $expirationDate->format('Y-m-d G:i:s'),
        'accessLevel'    => ['test'],
      ],
      '/test' => [
        'includedFiles'  => null,
        'excludedFiles'  => null,
        'expirationDate' => null,
        'accessLevel'    => ['test'],
      ],
    ];

    $this->assertSame($expected, $actual);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function removeExpiredPermissionsEmpty()
  {
    $expirationDate = new DateTime('+1 day');

    $permissions = [];

    $actual = $this->call('PermissionsManager', 'removeExpiredPermissions', [$permissions]);

    $this->assertSame(null, $actual);

    $actual = $this->call('PermissionsManager', 'removeExpiredPermissions', [null]);

    $this->assertSame(null, $actual);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function removeExpiredPermissionsAllExpired()
  {
    $expirationDate = new DateTime('+1 day');

    $permissions = [
      '/arst' => [
        'includedFiles'  => null,
        'excludedFiles'  => null,
        'expirationDate' => (new DateTime('-2 days'))->format('Y-m-d G:i:s'),
        'accessLevel'    => ['test'],
      ],
      '/test' => [
        'includedFiles'  => null,
        'excludedFiles'  => null,
        'expirationDate' => (new DateTime('-5 hours'))->format('Y-m-d G:i:s'),
        'accessLevel'    => ['test'],
      ],
    ];

    $actual = $this->call('PermissionsManager', 'removeExpiredPermissions', [$permissions]);

    $this->assertSame(null, $actual);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function haveSitePermissionsExpiredEmptyExpirationDate()
  {
    $expirationDate = new DateTime('+1 day');

    $sitePerms = [
      'includedFiles'  => null,
      'excludedFiles'  => null,
      'expirationDate' => null,
      'accessLevel'    => ['test'],
    ];

    $actual = $this->call('PermissionsManager', 'haveSitePermissionsExpired', [$sitePerms]);

    $this->assertFalse($actual);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function haveSitePermissionsExpiredExpired()
  {
    $expirationDate = new DateTime('+1 day');

    $sitePerms = [
      'includedFiles'  => null,
      'excludedFiles'  => null,
      'expirationDate' => (new DateTime('+2 days'))->format('Y-m-d G:i:s'),
      'accessLevel'    => ['test'],
    ];

    $actual = $this->call('PermissionsManager', 'haveSitePermissionsExpired', [$sitePerms]);

    $this->assertFalse($actual);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function haveSitePermissionsExpired()
  {
    $expirationDate = new DateTime('+1 day');

    $sitePerms = [
      'includedFiles'  => null,
      'excludedFiles'  => null,
      'expirationDate' => (new DateTime('-2 days'))->format('Y-m-d G:i:s'),
      'accessLevel'    => ['test'],
    ];

    $actual = $this->call('PermissionsManager', 'haveSitePermissionsExpired', [$sitePerms]);

    $this->assertTrue($actual);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function getUsersSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'admin']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*']);

    $this->assertSame(['/billy', '/arst'], PermissionsManager::getUsersSites('bvisto'));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function buildCacheKey()
  {
    $this->assertContains('bvisto', $this->call('PermissionsManager', 'buildCacheKey', ['bvisto']));
  }

  /**
   * @test
   */
  public function getUserPermissionsForSite()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'siteAdmin']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'siteAdmin', 'files/*', 'private/*']);

    $expected = [
      'includedFiles'  => null,
      'excludedFiles'  => null,
      'expirationDate' => null,
      'accessLevel'    => ['siteAdmin']
    ];

    $this->assertSame($expected, PermissionsManager::getUserPermissionsForSite('bvisto', '/billy'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getUserPermissionsForSiteSuperUser()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', Config::SUPER_USER]);

    $this->assertSame(Config::$superUserPermissions, PermissionsManager::getUserPermissionsForSite('bvisto', '/billy'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getUserPermissionsForSiteSuperUserWithExcludedFiles()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/billy', ['index.php']]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', Config::SUPER_USER]);

    $expectedPerms = Config::$superUserPermissions;
    $expectedPerms['excludedFiles'] = ['index.php'];
    $this->assertSame($expectedPerms, PermissionsManager::getUserPermissionsForSite('bvisto', '/billy'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getUserPermissionsForSiteAdmin()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', Config::ADMIN_ACCESS_LEVEL]);

    $this->assertSame(Config::$adminPermissions, PermissionsManager::getUserPermissionsForSite('bvisto', '/billy'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getUserPermissionsForSiteAdminWithExcludedFiles()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/billy', ['index.php']]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', Config::ADMIN_ACCESS_LEVEL]);

    $expectedPerms = Config::$adminPermissions;
    $expectedPerms['excludedFiles'] = ['index.php'];
    $this->assertSame($expectedPerms, PermissionsManager::getUserPermissionsForSite('bvisto', '/billy'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getUserPermissionsForSiteNoMatches()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test']);

    $this->assertNull(PermissionsManager::getUserPermissionsForSite('bvisto', '/arst'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getUserPermissionsForSiteNone()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', Config::SUPER_USER]);

    $this->assertNull(PermissionsManager::getUserPermissionsForSite('jerry', '/billy'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userHasAccessToSite()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*']);

    $this->assertTrue($this->call('PermissionsManager', 'userHasAccessToSite', ['bvisto', '/arst/private/public.php']));

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst/private/', 'admin', 'files/*']);

    $this->assertTrue($this->call('PermissionsManager', 'userHasAccessToSite', ['bvisto', '/arst/private/public.php']));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userHasAccessToSiteNone()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*']);

    $this->assertFalse($this->call('PermissionsManager', 'userHasAccessToSite', ['bvisto', '/test/private/public.php']));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function findUsersSiteForFile()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*']);

    $this->assertSame('/arst', $this->call('PermissionsManager', 'findUsersSiteForFile', ['bvisto', '/arst/private/public.php']));

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst/private/', 'admin', 'files/*']);

    $this->assertSame('/arst/private/', $this->call('PermissionsManager', 'findUsersSiteForFile', ['bvisto', '/arst/private/public.php']));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function findUsersSiteForFileNone()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*']);

    $this->assertEmpty($this->call('PermissionsManager', 'findUsersSiteForFile', ['jerry', '/arst/private/public.php']));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function findUsersSiteForFileSitesExistButNoMatches()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert', 'test', 'files/*', 'private/*']);

    $this->assertNull($this->call('PermissionsManager', 'findUsersSiteForFile', ['bvisto', '/arst/private/public.php']));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function findUsersSiteForFileMultipleSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst/private/', 'admin', 'files/*', 'private/*']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst/private/arst/', 'admin', 'files/*', 'private/*']);

    $this->assertSame('/arst/private/', $this->call('PermissionsManager', 'findUsersSiteForFile', ['bvisto', '/arst/private/public.php']));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function findClosestSiteForFileNone()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst/private/', 'admin', 'files/*', 'private/*']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst/private/arst/', 'admin', 'files/*', 'private/*']);

    $this->assertNull(PermissionsManager::findClosestSiteForFile('/billy/private/public.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function findClosestSiteForFile()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst/private/', 'admin', 'files/*', 'private/*']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst/private/arst/', 'admin', 'files/*', 'private/*']);

    $this->assertSame('/arst/private/', PermissionsManager::findClosestSiteForFile('/arst/private/public.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getSitesFromBase()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/arst/']);

    $actual = $this->call('PermissionsManager', 'getSitesFromBase', ['/arst']);

    $this->assertSame(['/arst/private/', '/arst/private/arst/'], $actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getSitesFromBaseIncludingPerms()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/', 'concourse']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/arst/']);

    $actual = $this->call('PermissionsManager', 'getSitesFromBase', ['/arst', true]);

    $this->assertSame([['siteRoot' => '/arst/private/', 'excludedFiles' => 'concourse'], ['siteRoot' => '/arst/private/arst/', 'excludedFiles' => null]], $actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getInheritedPermissionsForSite()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/', 'concourse']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/arst/']);

    $actual = $this->call('PermissionsManager', 'getInheritedPermissionsForSite', ['/arst/private/arst/']);

    $this->assertSame(['excludedFiles' => []], $actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getInheritedPermissionsForSiteMultiplesNoIntersections()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/', 'gtsOnly']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/', 'concourse,private']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/arst/']);

    $actual = $this->call('PermissionsManager', 'getInheritedPermissionsForSite', ['/arst/private/arst/']);

    $this->assertSame(['excludedFiles' => []], $actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getInheritedPermissionsForSiteMultiples()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/', 'gtsOnly']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/', 'concourse,private']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/arst/']);

    $actual = $this->call('PermissionsManager', 'getInheritedPermissionsForSite', ['/arst/private/']);
    $actual['excludedFiles'] = array_values($actual['excludedFiles']);

    $this->assertSame(['excludedFiles' => ['concourse', 'private']], $actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getInheritedPermissionsForSiteMultiplesWithOwnExcluded()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/', 'gtsOnly']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/', 'concourse,private']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/arst/', 'index.php']);

    $actual = $this->call('PermissionsManager', 'getInheritedPermissionsForSite', ['/arst/private/']);
    $actual['excludedFiles'] = array_values($actual['excludedFiles']);

    $this->assertSame(['excludedFiles' => ['concourse', 'private', 'arst/index.php']], $actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getInheritedPermissionsForSiteOnlySite()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/arst/', 'index.php']);

    $actual = $this->call('PermissionsManager', 'getInheritedPermissionsForSite', ['/arst/private/arst/']);

    $this->assertSame(['excludedFiles' => ['index.php']], $actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getInheritedPermissionsForSiteParentExcludesAllChildSitePages()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/camps/', 'tlc/*']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/camps/tlc/']);

    $actual = $this->call('PermissionsManager', 'getInheritedPermissionsForSite', ['/camps/tlc']);

    $this->assertSame(['excludedFiles' => []], $actual);

    $actual = $this->call('PermissionsManager', 'getInheritedPermissionsForSite', ['/camps']);

    $this->assertSame(['excludedFiles' => ['tlc/*']], $actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getInheritedPermissionsForSiteIndex()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/athletics/', 'index.php, athletictraining/this.php']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/athletics/athletictraining/', '/testing/arst.php']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/athletics/athletictraining/testing/']);

    $actual = $this->call('PermissionsManager', 'getInheritedPermissionsForSite', ['/athletics/athletictraining']);

    $this->assertSame(['excludedFiles' => ['this.php', 'testing/arst.php']], $actual);

    $actual = $this->call('PermissionsManager', 'getInheritedPermissionsForSite', ['/athletics/athletictraining/testing/']);

    $this->assertSame(['excludedFiles' => ['arst.php']], $actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getInheritedPermissionsForSiteNone()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/arst/']);

    $actual = $this->call('PermissionsManager', 'getInheritedPermissionsForSite', ['/arst/private/arst/', true]);

    $this->assertNull($actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function findUsersSiteForFileSuperUser()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/arst/private/arst/']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '*', Config::SUPER_USER]);

    $this->assertSame('/arst/private/', $this->call('PermissionsManager', 'findUsersSiteForFile', ['bvisto', '/arst/private/public.php']));


    $this->assertSame('/arst/private/arst/', $this->call('PermissionsManager', 'findUsersSiteForFile', ['bvisto', '/arst/private/arst/public.php']));
    $this->assertSame(null, $this->call('PermissionsManager', 'findUsersSiteForFile', ['bvisto', '/arst/public.php']));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function checkIncludedAndExcludedFilesForAccessNothingExcluded()
  {
    $sitePerms = [
      'accessLevel' => 'test',
      'excludedFiles' => null,
      'includedFiles' => null,
    ];

    $this->assertTrue($this->call('PermissionsManager', 'checkIncludedAndExcludedFilesForAccess', ['/arst/private/public.php', '/arst/', $sitePerms]));
  }

  /**
   * @test
   */
  public function checkIncludedAndExcludedFilesForAccessExcluded()
  {
    $sitePerms = [
      'accessLevel' => 'test',
      'excludedFiles' => ['/private/*'],
      'includedFiles' => null,
    ];

    $this->assertFalse($this->call('PermissionsManager', 'checkIncludedAndExcludedFilesForAccess', ['/arst/private/public.php', '/arst/', $sitePerms]));
  }

  /**
   * @test
   */
  public function checkIncludedAndExcludedFilesForAccessIncluded()
  {
    $sitePerms = [
      'accessLevel' => 'test',
      'excludedFiles' => ['/private/*'],
      'includedFiles' => ['/private/arst/*'],
    ];

    $this->assertTrue($this->call('PermissionsManager', 'checkIncludedAndExcludedFilesForAccess', ['/arst/private/arst/public.php', '/arst/', $sitePerms]));
  }

  /**
   * @test
   */
  public function checkIncludedAndExcludedFilesForAccessAllExcludedButOneFolder()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'index.php, public/*', '*']);

    $sitePerms = PermissionsManager::getUserPermissionsForSite('bvisto', '/arst');

    $this->assertTrue($this->call('PermissionsManager', 'checkIncludedAndExcludedFilesForAccess', ['/arst/public/arst/testing.php', '/arst/', $sitePerms]));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function checkIncludedAndExcludedFilesForAccessSpecificExcluded()
  {
    $sitePerms = [
      'accessLevel' => 'test',
      'excludedFiles' => ['/private/*', '/private/arst/public.php'],
      'includedFiles' => ['/private/arst/*'],
    ];

    $this->assertFalse($this->call('PermissionsManager', 'checkIncludedAndExcludedFilesForAccess', ['/arst/private/arst/public.php', '/arst/', $sitePerms]));
  }

  /**
   * @test
   */
  public function checkIncludedAndExcludedFilesForAccessSpecificIncluded()
  {
    $sitePerms = [
      'accessLevel' => 'test',
      'excludedFiles' => ['/private/*'],
      'includedFiles' => ['/private/arst/public.php'],
    ];

    $this->assertTrue($this->call('PermissionsManager', 'checkIncludedAndExcludedFilesForAccess', ['/arst/private/arst/public.php', '/arst/', $sitePerms]));
  }

  /**
   * @test
   */
  public function checkIncludedAndExcludedFilesForAccessExcludedWildCard()
  {
    $sitePerms = [
      'accessLevel' => 'test',
      'excludedFiles' => ['/private/*'],
      'includedFiles' => null,
    ];

    $this->assertFalse($this->call('PermissionsManager', 'checkIncludedAndExcludedFilesForAccess', ['/arst/private/arst/public.php', '/arst/', $sitePerms]));
  }

  /**
   * @test
   */
  public function checkIncludedAndExcludedFilesForAccessExcludedWildCardEverything()
  {
    $sitePerms = [
      'accessLevel' => 'test',
      'excludedFiles' => ['/*'],
      'includedFiles' => null,
    ];

    $this->assertFalse($this->call('PermissionsManager', 'checkIncludedAndExcludedFilesForAccess', ['/arst/private/arst/public.php', '/arst/', $sitePerms]));
  }

  /**
   * @test
   */
  public function checkIncludedAndExcludedFilesForAccessExcludedEverything()
  {
    $sitePerms = [
      'accessLevel' => 'test',
      'excludedFiles' => ['/'],
      'includedFiles' => null,
    ];

    $this->assertFalse($this->call('PermissionsManager', 'checkIncludedAndExcludedFilesForAccess', ['/arst/private/arst/public.php', '/arst/', $sitePerms]));
  }

  /**
   * @test
   */
  public function checkIncludedAndExcludedFilesForAccessExcludedFileWithoutSlashes()
  {
    $sitePerms = [
      'accessLevel' => 'test',
      'excludedFiles' => ['public.php'],
      'includedFiles' => null,
    ];

    $this->assertFalse($this->call('PermissionsManager', 'checkIncludedAndExcludedFilesForAccess', ['/arst/public.php', 'arst', $sitePerms]));
  }

  /**
   * @test
   */
  public function checkIncludedAndExcludedFilesForAccessExcludedFolderWithoutSlashes()
  {
    $sitePerms = [
      'accessLevel' => 'test',
      'excludedFiles' => ['public'],
      'includedFiles' => null,
    ];

    $this->assertFalse($this->call('PermissionsManager', 'checkIncludedAndExcludedFilesForAccess', ['/arst/public/index.php', 'arst', $sitePerms]));
  }

  /**
   * @test
   */
  public function accessLevelExistsInArray()
  {
    $accessLevels = ['test', 'admin'];
    $adminAccessLevels = ['admin', 'arst'];

    $this->assertTrue($this->call('PermissionsManager', 'accessLevelExistsInArray', [$accessLevels, $adminAccessLevels]));
  }

  /**
   * @test
   */
  public function accessLevelExistsInArrayFalse()
  {
    $accessLevels = ['test', 'admin'];
    $adminAccessLevels = ['admins'];

    $this->assertFalse($this->call('PermissionsManager', 'accessLevelExistsInArray', [$accessLevels, $adminAccessLevels]));
  }

  /**
   * @test
   */
  public function userCanEditFileNoExcludes()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', null]);

    $this->assertTrue(PermissionsManager::userCanEditFile('bvisto', '/arst/private/public.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFileNoAccessLevel()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', '', 'files/*', ]);

    $this->assertFalse(PermissionsManager::userCanEditFile('bvisto', '/arst/private/public.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFileSpecificFileExcluded()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'siteAdmin', 'files/*', 'private/*,files/private.php']);

    $this->assertFalse(PermissionsManager::userCanEditFile('bvisto', '/arst/files/private.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFileSpecificFileIncluded()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*,private/private.php', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanEditFile('bvisto', '/arst/private/private.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFileExcluded()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'siteAdmin', 'files/*', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanEditFile('bvisto', '/arst/private/public.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFileIncluded()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanEditFile('bvisto', '/arst/files/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFileNoRuleMatched()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanEditFile('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFileNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    // force a cache refresh
    PermissionsManager::getUserPermissionsForSite('bvisto', 'test', true);

    $this->assertFalse(PermissionsManager::userCanEditFile('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFileNonEditableAccessLevel()
  {
    $origNonEditableAccessLevels = Config::$nonEditableAccessLevels;
    Config::$nonEditableAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanEditFile('bvisto', '/arst/protected/arst.php'));

    Config::$nonEditableAccessLevels = $origNonEditableAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFileOnlyExcludes()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', null, 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanEditFile('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFileIncludedMoreSpecificThanExcluded()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanEditFile('bvisto', '/arst/private/files/arst.jpg'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanCreatePageNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    // force a cache refresh
    PermissionsManager::getUserPermissionsForSite('bvisto', 'test', true);

    $this->assertFalse(PermissionsManager::userCanCreatePage('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanCreatePageNoAccessLevels()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', '', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanCreatePage('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanCreatePageNonCreationAccessLevel()
  {
    $origNonCreationAccessLevels = Config::$nonCreationAccessLevels;
    Config::$nonCreationAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanCreatePage('bvisto', '/arst/protected/arst.php'));

    Config::$nonCreationAccessLevels = $origNonCreationAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanCreatePageExcluded()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanCreatePage('bvisto', '/arst/private/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanCreatePage()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanCreatePage('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanCreatePageInSiteNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    // force a cache refresh
    PermissionsManager::getUserPermissionsForSite('bvisto', 'test', true);

    $this->assertFalse(PermissionsManager::userCanCreatePage('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanCreatePageInSite()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test']);

    $this->assertTrue(PermissionsManager::userCanCreatePageInSite('bvisto', '/arst/private/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanCreatePageInSiteCurrentPageExcluded()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanCreatePageInSite('bvisto', '/arst/private/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanCreatePageInSiteNonCreationAccessLevel()
  {
    $origNonCreationAccessLevels = Config::$nonCreationAccessLevels;
    Config::$nonCreationAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanCreatePageInSite('bvisto', '/arst/protected/arst.php'));

    Config::$nonCreationAccessLevels = $origNonCreationAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanDeletePageNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    // force a cache refresh
    PermissionsManager::getUserPermissionsForSite('bvisto', 'test', true);

    $this->assertFalse(PermissionsManager::userCanDeletePage('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanDeletePageNoAccessLevels()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', '', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanDeletePage('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanDeletePageNonDeletionAccessLevel()
  {
    $origNonDeletionAccessLevels = Config::$nonDeletionAccessLevels;
    Config::$nonDeletionAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test,focusBox', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanDeletePage('bvisto', '/arst/protected/arst.php'));

    Config::$nonDeletionAccessLevels = $origNonDeletionAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanDeletePageExcluded()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanDeletePage('bvisto', '/arst/private/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanDeletePage()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanDeletePage('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanPublishPendingDraftsNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    // force a cache refresh
    PermissionsManager::getUserPermissionsForSite('bvisto', 'test', true);

    $this->assertFalse(PermissionsManager::userCanPublishPendingDrafts('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanPublishPendingDraftsNoAccessLevels()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', '', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanPublishPendingDrafts('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanPublishPendingDraftsNonPublishAccessLevel()
  {
    $origPublishPendingDraftsAL = Config::$publishPendingDraftsAccessLevels;
    Config::$publishPendingDraftsAccessLevels = ['admin'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanPublishPendingDrafts('bvisto', '/arst/protected/arst.php'));

    Config::$publishPendingDraftsAccessLevels = $origPublishPendingDraftsAL;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanPublishPendingDraftsExcluded()
  {
    $origPublishPendingDraftsAL = Config::$publishPendingDraftsAccessLevels;
    Config::$publishPendingDraftsAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanPublishPendingDrafts('bvisto', '/arst/private/arst.php'));

    Config::$publishPendingDraftsAccessLevels = $origPublishPendingDraftsAL;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanPublishPendingDrafts()
  {
    $origPublishPendingDraftsAL = Config::$publishPendingDraftsAccessLevels;
    Config::$publishPendingDraftsAccessLevels = ['siteAdmin'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'siteAdmin', 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanPublishPendingDrafts('bvisto', '/arst/protected/arst.php'));

    Config::$publishPendingDraftsAccessLevels = $origPublishPendingDraftsAL;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanPublishFileNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    // force a cache refresh
    PermissionsManager::getUserPermissionsForSite('bvisto', 'test', true);

    $this->assertFalse(PermissionsManager::userCanPublishFile('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanPublishFileNoAccessLevels()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', '', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanPublishFile('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanPublishFileNonPublishAccessLevel()
  {
    $origNonPublishingAL = Config::$nonPublishingAccessLevels;
    Config::$nonPublishingAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanPublishFile('bvisto', '/arst/protected/arst.php'));

    Config::$nonPublishingAccessLevels = $origNonPublishingAL;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanPublishFileExcluded()
  {
    $origNonPublishingAL = Config::$nonPublishingAccessLevels;
    Config::$nonPublishingAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'arst', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanPublishFile('bvisto', '/arst/private/arst.php'));

    Config::$nonPublishingAccessLevels = $origNonPublishingAL;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanPublishFile()
  {
    $origNonPublishingAL = Config::$nonPublishingAccessLevels;
    Config::$nonPublishingAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanPublishFile('bvisto', '/arst/protected/arst.php'));

    Config::$nonPublishingAccessLevels = $origNonPublishingAL;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditSiteNavNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    // force a cache refresh
    PermissionsManager::getUserPermissionsForSite('bvisto', 'test', true);

    $this->assertFalse(PermissionsManager::userCanEditSiteNav('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditSiteNavNoAccessLevels()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', '', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanEditSiteNav('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditSiteNavFalse()
  {
    $origSiteNavAccessLevels = Config::$siteNavAccessLevels;
    Config::$siteNavAccessLevels = ['admin'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanEditSiteNav('bvisto', '/arst/protected/arst.php'));

    Config::$siteNavAccessLevels = $origSiteNavAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditSiteNavExcluded()
  {
    $origSiteNavAccessLevels = Config::$siteNavAccessLevels;
    Config::$siteNavAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanEditSiteNav('bvisto', '/arst/private/arst.php'));

    Config::$siteNavAccessLevels = $origSiteNavAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditSiteNav()
  {
    $origSiteNavAccessLevels = Config::$siteNavAccessLevels;
    Config::$siteNavAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanEditSiteNav('bvisto', '/arst/protected/arst.php'));

    Config::$siteNavAccessLevels = $origSiteNavAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditRawHTMLNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    // force a cache refresh
    PermissionsManager::getUserPermissionsForSite('bvisto', 'test', true);

    $this->assertFalse(PermissionsManager::userCanEditRawHTML('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditRawHTMLNoAccessLevels()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', '', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanEditRawHTML('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditRawHTMLFalse()
  {
    $origEditRawHTMLAccessLevels = Config::$editRawHTMLAccessLevels;
    Config::$editRawHTMLAccessLevels = ['admin'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanEditRawHTML('bvisto', '/arst/protected/arst.php'));

    Config::$editRawHTMLAccessLevels = $origEditRawHTMLAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditRawHTMLExcluded()
  {
    $origEditRawHTMLAccessLevels = Config::$editRawHTMLAccessLevels;
    Config::$editRawHTMLAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanEditRawHTML('bvisto', '/arst/private/arst.php'));

    Config::$editRawHTMLAccessLevels = $origEditRawHTMLAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditRawHTML()
  {
    $origEditRawHTMLAccessLevels = Config::$editRawHTMLAccessLevels;
    Config::$editRawHTMLAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanEditRawHTML('bvisto', '/arst/protected/arst.php'));

    Config::$editRawHTMLAccessLevels = $origEditRawHTMLAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanUploadNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    // force a cache refresh
    PermissionsManager::getUserPermissionsForSite('bvisto', 'test', true);

    $this->assertFalse(PermissionsManager::userCanUpload('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanUploadNoAccessLevels()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', '', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanUpload('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanUploadFalse()
  {
    $origNonUploadingAccessLevels = Config::$nonUploadingAccessLevels;
    Config::$nonUploadingAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanUpload('bvisto', '/arst/protected/arst.php'));

    Config::$nonUploadingAccessLevels = $origNonUploadingAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanUploadExcluded()
  {
    $origNonUploadingAccessLevels = Config::$nonUploadingAccessLevels;
    Config::$nonUploadingAccessLevels = ['arst'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanUpload('bvisto', '/arst/private/arst.php'));

    Config::$nonUploadingAccessLevels = $origNonUploadingAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanUpload()
  {
    $origNonUploadingAccessLevels = Config::$nonUploadingAccessLevels;
    Config::$nonUploadingAccessLevels = ['arst'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanUpload('bvisto', '/arst/protected/arst.php'));

    Config::$nonUploadingAccessLevels = $origNonUploadingAccessLevels;
    $this->destructDB();
  }


  /**
   * @test
   */
  public function userCanManageRevisionsNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    // force a cache refresh
    PermissionsManager::getUserPermissionsForSite('bvisto', 'test', true);

    $this->assertFalse(PermissionsManager::userCanManageRevisions('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanManageRevisionsNoAccessLevels()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', '', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanManageRevisions('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanManageRevisionsFalse()
  {
    $origManageRevisionsAccessLevels = Config::$manageRevisionsAccessLevels;
    Config::$manageRevisionsAccessLevels = ['admin'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanManageRevisions('bvisto', '/arst/protected/arst.php'));

    Config::$manageRevisionsAccessLevels = $origManageRevisionsAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanManageRevisionsExcluded()
  {
    $origManageRevisionsAccessLevels = Config::$manageRevisionsAccessLevels;
    Config::$manageRevisionsAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanManageRevisions('bvisto', '/arst/private/arst.php'));

    Config::$manageRevisionsAccessLevels = $origManageRevisionsAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanManageRevisions()
  {
    $origManageRevisionsAccessLevels = Config::$manageRevisionsAccessLevels;
    Config::$manageRevisionsAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanManageRevisions('bvisto', '/arst/protected/arst.php'));

    Config::$manageRevisionsAccessLevels = $origManageRevisionsAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanViewRevisionsNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    // force a cache refresh
    PermissionsManager::getUserPermissionsForSite('bvisto', 'test', true);

    $this->assertFalse(PermissionsManager::userCanViewRevisions('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanViewRevisionsNoAccessLevels()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', '', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanViewRevisions('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanViewRevisionsFalse()
  {
    $origNonRevisionsAccessLevels = Config::$nonRevisionsAccessLevels;
    Config::$nonRevisionsAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test,focusBox', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanViewRevisions('bvisto', '/arst/protected/arst.php'));

    Config::$nonRevisionsAccessLevels = $origNonRevisionsAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanViewRevisionsExcluded()
  {
    $origNonRevisionsAccessLevels = Config::$nonRevisionsAccessLevels;
    Config::$nonRevisionsAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanViewRevisions('bvisto', '/arst/private/arst.php'));

    Config::$nonRevisionsAccessLevels = $origNonRevisionsAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanViewRevisions()
  {
    $origNonRevisionsAccessLevels = Config::$nonRevisionsAccessLevels;
    Config::$nonRevisionsAccessLevels = ['test'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanViewRevisions('bvisto', '/arst/protected/arst.php'));

    Config::$nonRevisionsAccessLevels = $origNonRevisionsAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanManageBannersNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    // force a cache refresh
    PermissionsManager::getUserPermissionsForSite('bvisto', 'test', true);

    $this->assertFalse(PermissionsManager::userCanManageBanners('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanManageBannersNoAccessLevels()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', '', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanManageBanners('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanManageBannersFalse()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanManageBanners('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanManageBannersExcluded()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanManageBanners('bvisto', '/arst/private/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanManageBanners()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', Config::BANNER_ACCESS_LEVEL, 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanManageBanners('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanViewSiteStructureNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    // force a cache refresh
    PermissionsManager::getUserPermissionsForSite('bvisto', 'test', true);

    $this->assertFalse(PermissionsManager::userCanViewSiteStructure('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanViewSiteStructureNoAccessLevels()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', '', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanViewSiteStructure('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanViewSiteStructureFalse()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanViewSiteStructure('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanViewSiteStructureExcluded()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', Config::SITE_ADMIN_ACCESS_LEVEL, 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanViewSiteStructure('bvisto', '/arst/private/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanViewSiteStructureNotAllowed()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', Config::SITE_PUBLISHER_ACCESS_LEVEL, 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanViewSiteStructure('bvisto', '/arst/private/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanViewSiteStructure()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', Config::SITE_ADMIN_ACCESS_LEVEL, 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanViewSiteStructure('bvisto', '/arst/protected/arst.php'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function adjustPermissionFiles()
  {
    $files = ['/arst', '/private//*', 'secure', 'files/private.php'];
    $expected = ['arst/*', 'private/*', 'secure/*', 'files/private.php'];

    $this->assertSame($expected, $this->call('PermissionsManager', 'adjustPermissionFiles', [$files]));
  }

  /**
   * @test
   */
  public function adjustPermissionFilesEverythingWildCard()
  {
    $files = ['/arst', '/*', 'secure', 'files/private.php', '/', '*'];
    $expected = ['arst/*', '/*', 'secure/*', 'files/private.php', '/*', '/*'];

    $this->assertSame($expected, $this->call('PermissionsManager', 'adjustPermissionFiles', [$files]));
  }

  /**
   * @test
   */
  public function userCanEditPart()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test']);
    $origNonEditablePartsByAccessLevel = Config::$nonEditablePartsByAccessLevel;
    Config::$nonEditablePartsByAccessLevel = ['test' => ['content']];

    $this->assertFalse(PermissionsManager::userCanEditPart('bvisto', '/arst/test.php', 'content'));
    $this->assertTrue(PermissionsManager::userCanEditPart('bvisto', '/arst/test.php', 'title'));

    Config::$nonEditablePartsByAccessLevel = $origNonEditablePartsByAccessLevel;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditPartNonRestrictive()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test']);
    $origNonEditablePartsByAccessLevel = Config::$nonEditablePartsByAccessLevel;
    Config::$nonEditablePartsByAccessLevel = ['arst' => ['content']];

    $this->assertTrue(PermissionsManager::userCanEditPart('bvisto', '/arst/test.php', 'content'));
    $this->assertTrue(PermissionsManager::userCanEditPart('bvisto', '/arst/test.php', 'title'));

    Config::$nonEditablePartsByAccessLevel = $origNonEditablePartsByAccessLevel;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditPartNoAccessLevels()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', '']);

    $this->assertFalse(PermissionsManager::userCanEditPart('bvisto', '/arst/test.php', 'title'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditPartNoMatchingSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', '']);

    $this->assertFalse(PermissionsManager::userCanEditPart('bvisto', '/arstst/test.php', 'title'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditPartNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);

    $this->assertFalse(PermissionsManager::userCanEditPart('bvisto', '/arst/test.php', 'title'));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function accessLevlCanEditPart()
  {
    $origNonEditablePartsByAccessLevel = Config::$nonEditablePartsByAccessLevel;
    Config::$nonEditablePartsByAccessLevel = ['test' => ['content']];

    $this->assertTrue(PermissionsManager::accessLevelCanEditPart('Test', 'title'));
    $this->assertFalse(PermissionsManager::accessLevelCanEditPart('test', 'content'));

    Config::$nonEditablePartsByAccessLevel = $origNonEditablePartsByAccessLevel;
  }

  /**
   * @test
   */
  public function accessLevlCanEditPartMultipleLevels()
  {
    $origNonEditablePartsByAccessLevel = Config::$nonEditablePartsByAccessLevel;
    Config::$nonEditablePartsByAccessLevel = ['test' => ['focusbox'], 'focusBox' => []];

    $this->assertFalse(PermissionsManager::accessLevelCanEditPart('test', 'focusbox'));
    $this->assertTrue(PermissionsManager::accessLevelCanEditPart('focusBox', 'focusbox'));
    $this->assertTrue(PermissionsManager::accessLevelCanEditPart(['test', 'focusBox'], 'title'));

    Config::$nonEditablePartsByAccessLevel = $origNonEditablePartsByAccessLevel;
  }

  /**
   * @test
   */
  public function getAccessLevelsFromPermissions()
  {
    $perms = [
      '/billy/concert' => [
        'accessLevel'   => ['admin'],
        'includedFiles' => null,
        'excludedFiles' => null,
      ],
      '*' => [
        'accessLevel'   => [Config::SUPER_USER],
        'includedFiles' => null,
        'excludedFiles' => null,
      ]
    ];

    $expected = ['admin', Config::SUPER_USER];
    $this->assertSame($expected, $this->call('PermissionsManager', 'getAccessLevelsFromPermissions', [$perms]));
  }

  /**
   * @test
   */
  public function userCanEditDraft()
  {
    $draft = [
      'username'        => 'bvisto',
      'additionalUsers' => ['jerry'],
      'type'            => Config::PUBLIC_DRAFT,
    ];

    $this->assertTrue(PermissionsManager::userCanEditDraft('jerry', $draft));
  }

  /**
   * @test
   */
  public function userCanEditDraftFalse()
  {
    $draft = [
      'username'        => 'bvisto',
      'additionalUsers' => ['jerry'],
      'type'            => Config::PUBLIC_DRAFT,
    ];

    $this->assertFalse(PermissionsManager::userCanEditDraft('testuser', $draft));
  }

  /**
   * @test
   */
  public function userOwnsDraft()
  {
    $draft = [
      'username'        => 'bvisto',
      'additionalUsers' => ['jerry'],
      'type'            => Config::PUBLIC_DRAFT,
    ];

    $this->assertTrue(PermissionsManager::userOwnsDraft('bvisto', $draft));
  }

  /**
   * @test
   */
  public function isUserSuperUser()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '*', Config::SUPER_USER]);

    $this->assertTrue($this->call('PermissionsManager', 'isUserSuperUser', ['bvisto']));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function isUserAdmin()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '*', Config::ADMIN_ACCESS_LEVEL]);

    $this->assertTrue($this->call('PermissionsManager', 'isUserAdmin', ['bvisto']));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findParentSiteForFile()
  {
    $file = '/cis/www/billy/concert/test/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/test/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', Config::SITE_ADMIN_ACCESS_LEVEL]);

    $result = PermissionsManager::findParentSiteForFile(Utility::removeDocRootFromPath($file));

    $this->assertSame('/billy/', $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findSitesContainingFile()
  {
    $file = '/cis/www/billy/concert/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/test/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', Config::SITE_ADMIN_ACCESS_LEVEL]);

    $result = $this->call('PermissionsManager', 'findSitesContainingFile', [Utility::removeDocRootFromPath($file)]);

    $this->assertSame(['/billy/', '/billy/concert/'], $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findSitesContainingFileIncludingPerms()
  {
    $file = '/cis/www/billy/concert/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['/billy/', 'concourse/']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/test/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', Config::SITE_ADMIN_ACCESS_LEVEL]);

    $result = $this->call('PermissionsManager', 'findSitesContainingFile', [Utility::removeDocRootFromPath($file), true]);

    $this->assertSame([['siteRoot' => '/billy/', 'excludedFiles' => 'concourse/'], ['siteRoot' => '/billy/concert/', 'excludedFiles' => null]], $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findSitesContainingFileNoneFound()
  {
    $file = '/cis/www/arst/concert/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arsts/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/test/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', Config::SITE_ADMIN_ACCESS_LEVEL]);

    $result = $this->call('PermissionsManager', 'findSitesContainingFile', [Utility::removeDocRootFromPath($file)]);

    $this->assertEmpty($result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findSitesContainingFileNoSites()
  {
    $file = '/cis/www/arst/concert/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/test/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', Config::SITE_ADMIN_ACCESS_LEVEL]);

    $result = $this->call('PermissionsManager', 'findSitesContainingFile', [Utility::removeDocRootFromPath($file)]);

    $this->assertNull($result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findSitesContainingFileNoFile()
  {
    $file = false;
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/test/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', Config::SITE_ADMIN_ACCESS_LEVEL]);

    $result = $this->call('PermissionsManager', 'findSitesContainingFile', [Utility::removeDocRootFromPath($file)]);

    $this->assertNull($result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function sortSitesByDepth()
  {
    $file = '/cis/www/billy/concert/test/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/test/', Config::SITE_ADMIN_ACCESS_LEVEL]);

    $sites = $this->call('PermissionsManager', 'findSitesContainingFile', [Utility::removeDocRootFromPath($file)]);

    $result = $this->call('PermissionsManager', 'sortSitesByDepth', [$sites]);

    $this->assertNotSame($sites, $result);

    $this->assertSame(['/billy/concert/test/', '/billy/concert/', '/billy/'], $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findUsersForSiteByAccessLevel()
  {
    $file = '/cis/www/billy/concert/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', Config::SITE_ADMIN_ACCESS_LEVEL]);

    $result = $this->call('PermissionsManager', 'findUsersForSiteByAccessLevel', ['/billy/concert/', Config::$publishPendingDraftsAccessLevels]);

    $this->assertSame(['bvisto'], $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findUsersForSiteByAccessLevelCloseMatches()
  {
    $file = '/cis/www/billy/concert/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', ['admins', 'test']]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', ['administrator', 'test']]);

    $result = $this->call('PermissionsManager', 'findUsersForSiteByAccessLevel', ['/billy/concert/', 'admin']);

    $this->assertSame([], $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findUsersForSiteByAccessLevelSingleLevel()
  {
    $file = '/cis/www/billy/concert/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', Config::SITE_ADMIN_ACCESS_LEVEL]);

    $result = $this->call('PermissionsManager', 'findUsersForSiteByAccessLevel', ['/billy/concert/', Config::SITE_ADMIN_ACCESS_LEVEL]);

    $this->assertSame(['bvisto'], $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findUsersForSiteByAccessLevelMultiple()
  {
    $file = '/cis/www/billy/concert/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', '/billy/concert/', Config::SITE_PUBLISHER_ACCESS_LEVEL]);

    $result = $this->call('PermissionsManager', 'findUsersForSiteByAccessLevel', ['/billy/concert/', Config::$publishPendingDraftsAccessLevels]);

    $this->assertSame(['bvisto', 'jerry'], $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findPublishersForFile()
  {
    $file = '/cis/www/billy/concert/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', Config::SITE_ADMIN_ACCESS_LEVEL]);

    $result = PermissionsManager::findPublishersForFile(Utility::removeDocRootFromPath($file));

    $this->assertSame(['bvisto'], $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findPublishersForFileSuperUser()
  {
    $file = '/cis/www/billy/concert/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', Config::SUPER_USER]);

    $result = PermissionsManager::findPublishersForFile(Utility::removeDocRootFromPath($file));

    $this->assertSame(['bvisto'], $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findPublishersForFileNone()
  {
    $file = '/cis/www/billy/concert/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/', Config::SUPER_USER]);

    $result = PermissionsManager::findPublishersForFile(Utility::removeDocRootFromPath($file));

    $this->assertNull($result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findPublishersForFileOnlyEditorExists()
  {
    $file = '/cis/www/billy/concert/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', Config::SITE_EDITOR_ACCESS_LEVEL]);

    $result = PermissionsManager::findPublishersForFile(Utility::removeDocRootFromPath($file));

    $this->assertNull($result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findPublishersForFileMultiple()
  {
    $file = '/cis/www/billy/concert/index.php';
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', Config::SITE_ADMIN_ACCESS_LEVEL]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', '/billy/concert/', Config::SITE_PUBLISHER_ACCESS_LEVEL]);

    $result = PermissionsManager::findPublishersForFile(Utility::removeDocRootFromPath($file));

    $this->assertSame(['bvisto', 'jerry'], $result);
    $this->destructDB();
  }
}