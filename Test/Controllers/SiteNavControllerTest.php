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
  Gustavus\Concert\Utility,
  Gustavus\Doctrine\DBAL,
  Gustavus\Concert\FileManager,
  Gustavus\Utility\PageUtil,
  Gustavus\Utility\String,
  Gustavus\Concourse\RoutingUtil,
  Gustavus\Extensibility\Filters,
  Gustavus\Revisions\API as RevisionsAPI;

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
    Filters::clear(RevisionsAPI::RENDER_REVISION_FILTER);
    Filters::clear(RevisionsAPI::RESTORE_HOOK);
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
    self::$overrideToken['addDocRootToPath'] = $token = override_method('\Gustavus\Concert\Utility', 'addDocRootToPath', function($filePath) use (&$token) {
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

  /**
   * @test
   */
  public function stopEditing()
  {
    $filePath = self::$testFileDir . 'index.php';
    $siteNavPath = self::$testFileDir . 'site_nav.php';
    file_put_contents($siteNavPath, 'site nav');
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');
    $this->setUpController();

    $fm = new FileManager('testUser', $siteNavPath, null, $this->controller->getDB());
    $this->assertTrue($fm->acquireLock());


    $_GET['concert'] = 'stopEditing';

    $this->assertSame(['action' => 'none', 'value' => true], $this->controller->handleSiteNavActions(['filePath' => $filePath]));

    $this->assertFalse($fm->userHasLock());

    $this->unauthenticate();
    $this->destructDB();
  }



  /**
   * @test
   */
  public function handleRevisionsCantView()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->buildDB();

    $this->authenticate('bvisto');
    $this->setUpController();

    $_GET['concert'] = 'revisions';

    $actual = $this->controller->handleSiteNavActions(['filePath' => $filePath]);

    $this->assertFalse($actual);
    $this->assertMessageInMessages(Config::NOT_ALLOWED_TO_VIEW_REVISIONS, $this->controller->getConcertMessages());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handleRevisionsNoRevisions()
  {
    $filePath = self::$testFileDir . 'index.php';
    $siteNav = self::$testFileDir . 'site_nav.php';
    file_put_contents($siteNav, 'siteNav contents');
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->authenticate('bvisto');

    $this->setUpController();

    $_GET['concert'] = 'revisions';

    $actual = $this->controller->handleSiteNavActions(['filePath' => $filePath]);

    $this->assertContains('aren\'t any revisions', $actual['value']['content']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handleRevisions()
  {
    $filePath = self::$testFileDir . 'index.php';
    $siteNav = self::$testFileDir . 'site_nav.php';
    file_put_contents($siteNav, 'siteNav contents');

    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $fileContents = '<?php ?>test contents<?php arst;?>more';
    file_put_contents($siteNav, $fileContents);
    $this->buildFileManager('bvisto', $siteNav);
    $this->assertTrue($this->fileManager->saveRevision());

    $this->authenticate('bvisto');

    $this->setUpController();

    $revisionsAPI = Utility::getRevisionsAPI($siteNav, $this->controller->getDB());
    $this->assertSame(1, $revisionsAPI->getRevisionCount());

    $_GET['concert'] = 'revisions';

    $actual = $this->controller->handleSiteNavActions(['filePath' => $filePath]);

    $this->assertNotContains('There doesn\'t seem to be any data associated with the information provided.', $actual['value']['content']);
    $this->assertContains('revisionsForm', $actual['value']['content']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handleRevisionsViewSpecific()
  {
    $filePath = self::$testFileDir . 'index.php';
    $siteNav = self::$testFileDir . 'site_nav.php';
    file_put_contents($siteNav, 'siteNav contents');

    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $fileContents = '<?php ?>test contents<?php arst;?>more';
    file_put_contents($siteNav, $fileContents);
    $this->buildFileManager('bvisto', $siteNav);
    $this->assertTrue($this->fileManager->saveRevision());

    $this->authenticate('bvisto');

    $this->setUpController();

    $revisionsAPI = Utility::getRevisionsAPI($siteNav, $this->controller->getDB());
    $this->assertSame(1, $revisionsAPI->getRevisionCount());

    $_GET['revisionNumber'] = 1;
    $_GET['concert'] = 'revisions';

    $actual = $this->controller->handleSiteNavActions(['filePath' => $filePath]);

    $this->assertNotContains('There doesn\'t seem to be any data associated with the information provided.', $actual['value']['content']);
    $this->assertContains("test contents\n\rmore", $actual['value']['content']);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handleRevisionsRestore()
  {
    $origManageRevAccessLevels = Config::$manageRevisionsAccessLevels;
    Config::$manageRevisionsAccessLevels = ['test'];
    $filePath = self::$testFileDir . 'index.php';
    $siteNav = self::$testFileDir . 'site_nav.php';
    file_put_contents($siteNav, 'siteNav contents');

    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $fileContents = '<?php ?>test contents<?php arst;?>';
    file_put_contents($siteNav, $fileContents);
    $this->buildFileManager('bvisto', $siteNav);
    $this->assertTrue($this->fileManager->saveRevision());

    $fileContents = '<?php ?>test contents<?php arst;?>more';
    file_put_contents($siteNav, $fileContents);
    $this->buildFileManager('bvisto', $siteNav);
    $this->assertTrue($this->fileManager->saveRevision());

    $this->authenticate('bvisto');

    $this->setUpController();

    $revisionsAPI = Utility::getRevisionsAPI($siteNav, $this->controller->getDB());
    $this->assertSame(2, $revisionsAPI->getRevisionCount());

    $_POST['revisionNumber'] = 1;
    $_POST['restore'] = 1;
    $_GET['concert'] = 'revisions';

    $actual = $this->controller->handleSiteNavActions(['filePath' => $filePath]);

    $this->assertNull($actual['value']['content']);

    $url = (new String($filePath))->addQueryString(['concert' => 'revisions', 'revisionsAction' => 'thankYou'])->buildUrl()->getValue();

    $this->assertSame(Config::RESTORED_MESSAGE, PageUtil::getSessionMessage($url));

    $filePathHash = $this->fileManager->getFilePathHash();
    $this->buildFileManager('root', Config::$stagingDir . $filePathHash);
    // publish the file to trigger a revision
    $this->fileManager->publishFile();

    $revisionsAPI = Utility::getRevisionsAPI($siteNav, $this->controller->getDB());
    $this->assertSame(3, $revisionsAPI->getRevisionCount());

    $this->unauthenticate();
    $this->destructDB();
    Config::$manageRevisionsAccessLevels = $origManageRevAccessLevels;
  }

  /**
   * @test
   */
  public function handleRevisionsRestoreUndo()
  {
    $origManageRevAccessLevels = Config::$manageRevisionsAccessLevels;
    Config::$manageRevisionsAccessLevels = ['test'];
    $filePath = self::$testFileDir . 'index.php';
    $siteNav = self::$testFileDir . 'site_nav.php';
    file_put_contents($siteNav, 'siteNav contents');

    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $fileContents = '<?php ?>test contents<?php arst;?>';
    file_put_contents($siteNav, $fileContents);
    $this->buildFileManager('bvisto', $siteNav);
    $this->assertTrue($this->fileManager->saveRevision());

    $fileContents = '<?php ?>test contents<?php arst;?>more';
    file_put_contents($siteNav, $fileContents);
    $this->buildFileManager('bvisto', $siteNav);
    $this->assertTrue($this->fileManager->saveRevision());

    $this->authenticate('bvisto');

    $this->setUpController();

    $revisionsAPI = Utility::getRevisionsAPI($siteNav, $this->controller->getDB());
    $this->assertSame(2, $revisionsAPI->getRevisionCount());

    $_POST['revisionNumber'] = 1;
    $_POST['restore'] = 1;
    $_GET['concert'] = 'revisions';

    $actual = $this->controller->handleSiteNavActions(['filePath' => $filePath]);

    $this->assertNull($actual['value']['content']);

    $url = (new String($filePath))->addQueryString(['concert' => 'revisions', 'revisionsAction' => 'thankYou'])->buildUrl()->getValue();

    $this->assertSame(Config::RESTORED_MESSAGE, PageUtil::getSessionMessage($url));

    $filePathHash = $this->fileManager->getFilePathHash();
    $this->buildFileManager('root', Config::$stagingDir . $filePathHash);
    // publish the file to trigger a revision
    $this->fileManager->publishFile();


    $_POST['revisionsAction'] = 'undo';

    $actual = $this->controller->handleSiteNavActions(['filePath' => $filePath]);

    $this->assertNull($actual['value']['content']);

    $url = (new String($filePath))->addQueryString(['concert' => 'revisions'])->buildUrl()->getValue();

    $this->assertSame(Config::UNDO_RESTORE_MESSAGE, PageUtil::getSessionMessage($url));

    $this->buildFileManager('root', Config::$stagingDir . $filePathHash);
    $this->fileManager->publishFile();

    $revisionsAPI = Utility::getRevisionsAPI($siteNav, $this->controller->getDB());

    $this->assertSame(4, $revisionsAPI->getRevisionCount());

    $revThree = $revisionsAPI->getRevision(3);
    $this->assertSame('File restored', $revThree->getRevisionMessage());

    $revFour = $revisionsAPI->getRevision(4);
    $this->assertSame('File restoration undone', $revFour->getRevisionMessage());

    $this->unauthenticate();
    $this->destructDB();
    Config::$manageRevisionsAccessLevels = $origManageRevAccessLevels;
  }
}