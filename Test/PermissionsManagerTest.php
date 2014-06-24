<?php
/**
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test;

use Gustavus\Concert\PermissionsManager,
  Gustavus\Concert\Config,
  Gustavus\Doctrine\DBAL,
  Gustavus\GACCache\Workers\ArrayFactoryWorker;

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
    $this->set('PermissionsManager', 'dbal', DBAL::getDBAL('testDB', $this->getDBH()));
    $this->set('PermissionsManager', 'cache', (new ArrayFactoryWorker())->buildDataStore());
  }

  /**
   * Tears down the environment for every test
   *
   * @return void
   */
  public function tearDown()
  {
    $this->set('PermissionsManager', 'dbal', null);
    $this->set('PermissionsManager', 'cache', null);
  }

  /**
   * @test
   */
  public function saveNewSiteIfNeeded()
  {
    $this->constructDB(['Sites']);
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
    $this->assertTrue($this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'admin']));
    // nothing should happen here. This state already exists.
    $this->assertTrue($this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'admin']));
    // new one would get created here.
    $this->assertTrue($this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'none']));

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
        'accessLevel'   => ['admin'],
        'includedFiles' => null,
        'excludedFiles' => null,
      ],
      '/arst' => [
        'accessLevel'   => ['admin'],
        'includedFiles' => ['files/*'],
        'excludedFiles' => ['private/*'],
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
    $this->assertTrue($this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', ['admin', 'test'], ['files/*', 'private/public/*'], ['private/*', 'protected/*']]));

    $permissions = $this->call('PermissionsManager', 'getAllPermissionsForUser', ['bvisto']);

    $expected = [
      '/billy' => [
        'accessLevel'   => ['admin', 'test'],
        'includedFiles' => ['files/*', 'private/public/*'],
        'excludedFiles' => ['private/*', 'protected/*'],
      ],
    ];


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
        'accessLevel'   => ['admin'],
        'includedFiles' => ['files/*', 'images/*'],
        'excludedFiles' => ['secure/*', 'protected/private.php'],
      ],
      '/arst' => [
        'accessLevel'   => ['admin'],
        'includedFiles' => ['files/*'],
        'excludedFiles' => ['private/*'],
      ],
    ];

    $this->assertSame($expected, $actual);
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
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'admin']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*']);

    $expected = ['accessLevel' => ['admin'], 'includedFiles' => null, 'excludedFiles' => null];

    $this->assertSame($expected, PermissionsManager::getUserPermissionsForSite('bvisto', '/billy'));

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
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*,files/private.php']);

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
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*', 'private/*,secure']);

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
  public function adjustPermissionFiles()
  {
    $files = ['/arst', '/private//*', 'secure', 'files/private.php'];
    $expected = ['arst/*', 'private/*', 'secure/*', 'files/private.php'];

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
}