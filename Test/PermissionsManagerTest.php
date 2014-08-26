<?php
/**
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test;

use Gustavus\Concert\PermissionsManager,
  Gustavus\Concert\Config;

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
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'siteAdmin']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'siteAdmin', 'files/*', 'private/*']);

    $expected = ['accessLevel' => ['siteAdmin'], 'includedFiles' => null, 'excludedFiles' => null];

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
  public function userCanEditSiteNav()
  {
    $origSiteNavAccessLevels = Config::$siteNavAccessLevels;
    Config::$siteNavAccessLevels = ['admin'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*,private/files', 'private/*,secure']);

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
    $origSiteNavAccessLevels = Config::$siteNavAccessLevels;
    Config::$siteNavAccessLevels = ['admin'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'test', 'files/*,private/files', 'private/*,secure']);

    $this->assertFalse(PermissionsManager::userCanEditRawHTML('bvisto', '/arst/protected/arst.php'));

    Config::$siteNavAccessLevels = $origSiteNavAccessLevels;
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditRawHTML()
  {
    $origSiteNavAccessLevels = Config::$siteNavAccessLevels;
    Config::$siteNavAccessLevels = ['admin'];
    $this->constructDB(['Sites', 'Permissions']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/arst', 'admin', 'files/*,private/files', 'private/*,secure']);

    $this->assertTrue(PermissionsManager::userCanEditRawHTML('bvisto', '/arst/protected/arst.php'));

    Config::$siteNavAccessLevels = $origSiteNavAccessLevels;
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
}