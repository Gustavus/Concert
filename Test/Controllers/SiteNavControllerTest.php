<?php
/**
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test\Controllers;

use Gustavus\Test\TestObject,
  Gustavus\Concert\Test\TestBase,
  Gustavus\Concert\Controllers\SiteNavController,
  Gustavus\Concert\FileConfiguration,
  Gustavus\Concert\Config,
  Gustavus\Doctrine\DBAL,
  Gustavus\Concert\FileManager,
  Gustavus\Utility\PageUtil,
  Gustavus\Concourse\RoutingUtil,
  Gustavus\Extensibility\Filters;

/**
 * Tests for SiteNavController
 *
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class SiteNavControllerTest extends TestBase
{
  /**
   * SiteNavController
   *
   * @var SiteNavController
   */
  private $controller;

  /**
   * Token for overrides
   *
   * @var array
   */
  private static $overrideToken;

  /**
   * sets up the object for each test
   * @return void
   */
  public function setUp()
  {
    $_SERVER['REQUEST_URI'] = 'testing';
    $_SERVER['HTTP_REFERER'] = 'https://beta.gac.edu/billy/concert/newPage.php?concert=edit';
    TestBase::$pdo = $this->getDBH();


    if (!is_dir(self::$testFileDir . 'drafts')) {
      mkdir(self::$testFileDir . 'drafts');
    }
    if (!is_dir(self::$testFileDir . 'staged')) {
      mkdir(self::$testFileDir . 'staged');
    }
    if (!is_dir(self::$testFileDir . 'editableDrafts')) {
      mkdir(self::$testFileDir . 'editableDrafts');
    }
    parent::setUp();
  }

  /**
   * destructs the object after each test
   * @return void
   */
  public function tearDown()
  {
    if (isset($this->controller)) {
      $this->controller->setConcertMessage('');
    }
    unset($this->controller);
    self::removeFiles(self::$testFileDir);
    parent::tearDown();
    $_POST = [];
    $_GET = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
  }

  /**
   * Sets up environment for every test class
   *
   * @return void
   */
  public static function setUpBeforeClass()
  {
    self::$overrideToken = [];
    self::$overrideToken['loadAndEvaluate'] = override_method('\Gustavus\Utility\File', 'loadAndEvaluate', function() {
          // just return the contents of the file and don't evaluate it.
          return file_get_contents($this->value);
        }
    );
    self::$overrideToken['renderPageNotFound'] = override_method('\Gustavus\Utility\PageUtil', 'renderPageNotFound', function() {
          // just return the contents of the file and don't evaluate it.
          return 'renderPageNotFound';
        }
    );
    self::$overrideToken['addDocRootToPath'] = $token = override_method('\Gustavus\Concert\Config', 'addDocRootToPath', function($filePath) use (&$token) {
          if (strpos($filePath, '/cis/lib/') === 0) {
            return $filePath;
          }
          return call_overridden_func($token, null, $filePath);
        }
    );
    parent::setUpBeforeClass();
  }

  /**
   * Tears down the environment after each test class is done with tests
   * @return void
   */
  public static function tearDownAfterClass()
  {
    self::$overrideToken = [];
    parent::tearDownAfterClass();
  }

  /**
   * Sets up the controller and injects our test DB connection
   * @return  void
   */
  private function setUpController()
  {
    $this->controller = new TestObject(new SiteNavControllerTestController);

    $this->controller->dbal = DBAL::getDBAL('testDB', $this->getDBH());
  }

  /**
   * @test
   */
  public function edit()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, 'test contents');
    file_put_contents(self::$testFileDir . 'site_nav.php', 'siteNav test contents');

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');
    $this->setUpController();
    $_GET['concert'] = 'edit';
    $_GET['concertAction'] = 'siteNav';

    $result = $this->controller->handleSiteNavActions(['filePath' => $filePath]);

    $scripts = '';
    $scripts = Filters::apply('scripts', $scripts);

    $this->assertContains(Config::WEB_DIR . '/js/concert.js', $scripts);
    $this->assertSame(['action', 'value'], array_keys($result));
    $this->assertContains('siteNav test contents', $result['value']['localNavigation']);
    $this->assertContains($this->wrappedEditableIdentifier, $result['value']['localNavigation']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editParentNav()
  {
    $filePath = self::$testFileDir . 'concert/index.php';
    if (!is_dir(self::$testFileDir . 'concert/')) {
      mkdir(self::$testFileDir . 'concert/');
    }
    file_put_contents($filePath, 'test contents');
    file_put_contents(self::$testFileDir . 'site_nav.php', 'parent siteNav test contents');

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir . 'concert/', 'test']);

    $origSiteNavAccessLevels = Config::$siteNavAccessLevels;
    Config::$siteNavAccessLevels[] = 'test';

    $this->authenticate('testUser');
    $this->setUpController();
    $_GET['concert'] = 'edit';
    $_GET['concertAction'] = 'siteNav';

    $result = $this->controller->handleSiteNavActions(['filePath' => $filePath]);

    $scripts = '';
    $scripts = Filters::apply('scripts', $scripts);

    $this->assertContains(Config::WEB_DIR . '/js/concert.js', $scripts);
    $this->assertSame(['action', 'value'], array_keys($result));
    $this->assertContains('parent siteNav test contents', $result['value']['localNavigation']);
    $this->assertContains($this->wrappedEditableIdentifier, $result['value']['localNavigation']);

    Config::$siteNavAccessLevels = $origSiteNavAccessLevels;
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editParentNavNoPermission()
  {
    $filePath = self::$testFileDir . 'concert/index.php';
    if (!is_dir(self::$testFileDir . 'concert/')) {
      mkdir(self::$testFileDir . 'concert/');
    }
    file_put_contents($filePath, 'test contents');
    file_put_contents(self::$testFileDir . 'site_nav.php', 'parent siteNav test contents');

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser2', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir . 'concert/', 'test']);

    $this->authenticate('testUser');
    $this->setUpController();
    $_GET['concert'] = 'edit';
    $_GET['concertAction'] = 'siteNav';

    $result = $this->controller->handleSiteNavActions(['filePath' => $filePath]);

    $scripts = '';
    $scripts = Filters::apply('scripts', $scripts);

    $this->assertContains(Config::WEB_DIR . '/js/concert.js', $scripts);
    $this->assertSame(['action', 'value'], array_keys($result));
    // they weren't able to edit the parent site nav, so they should be creating a new one from the starter nav
    $this->assertContains(file_get_contents(Config::SITE_NAV_TEMPLATE), $result['value']['localNavigation']);
    $this->assertContains($this->wrappedEditableIdentifier, $result['value']['localNavigation']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function createOrEditNoNavsUpToGlobal()
  {
    $filePath = '/cis/www/billy/arstarst/arstarst/index.php';

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', '/billy/arstarst/arstarst/', 'test']);

    $this->authenticate('testUser');
    $this->setUpController();
    $_GET['concert'] = 'edit';
    $_GET['concertAction'] = 'siteNav';

    $siteNav = $this->call('Controllers\SiteNavController', 'getSiteNavForFile', [$filePath]);

    $this->assertTrue($this->call('Controllers\SiteNavController', 'isGlobalNav', [$siteNav]));

    $result = $this->controller->handleSiteNavActions(['filePath' => $filePath]);

    $scripts = '';
    $scripts = Filters::apply('scripts', $scripts);

    $this->assertContains(Config::WEB_DIR . '/js/concert.js', $scripts);
    $this->assertSame(['action', 'value'], array_keys($result));
    // they weren't able to edit the parent site nav, so they should be creating a new one from the starter nav
    $this->assertContains(file_get_contents(Config::SITE_NAV_TEMPLATE), $result['value']['localNavigation']);
    $this->assertContains($this->wrappedEditableIdentifier, $result['value']['localNavigation']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function createOrEditCreate()
  {
    $filePath = self::$testFileDir . '/concert/index.php';
    file_put_contents(self::$testFileDir . 'site_nav.php', 'siteNav test contents');

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');
    $this->setUpController();
    $_GET['concert'] = 'createSiteNav';
    $_GET['concertAction'] = 'siteNav';

    $result = $this->controller->handleSiteNavActions(['filePath' => $filePath]);

    $scripts = '';
    $scripts = Filters::apply('scripts', $scripts);

    $this->assertContains(Config::WEB_DIR . '/js/concert.js', $scripts);
    $this->assertSame(['action', 'value'], array_keys($result));
    // they weren't able to edit the parent site nav, so they should be creating a new one from the starter nav
    $this->assertContains(file_get_contents(Config::SITE_NAV_TEMPLATE), $result['value']['localNavigation']);
    $this->assertContains($this->wrappedEditableIdentifier, $result['value']['localNavigation']);

    $this->unauthenticate();
    $this->destructDB();
  }

  // /**
  //  * @test
  //  */
  // public function draftNoneFound()
  // {
  //   $filePath = self::$testFileDir . '/concert/index.php';
  //   file_put_contents(self::$testFileDir . 'site_nav.php', 'siteNav test contents');

  //   $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
  //   $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

  //   $this->authenticate('testUser');
  //   $this->setUpController();
  //   $_GET['concert'] = 'viewDraft';
  //   $_GET['concertAction'] = 'siteNav';

  //   $result = $this->controller->handleSiteNavActions(['filePath' => $filePath]);

  //   $scripts = '';
  //   $scripts = Filters::apply('scripts', $scripts);

  //   $this->assertContains(Config::WEB_DIR . '/js/concert.js', $scripts);
  //   $this->assertSame(['action', 'value'], array_keys($result));
  //   // they weren't able to edit the parent site nav, so they should be creating a new one from the starter nav
  //   $this->assertContains('aren\'t any drafts to show', $result['value']['localNavigation']['content']);

  //   $this->unauthenticate();
  //   $this->destructDB();
  // }
}