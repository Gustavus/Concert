<?php
/**
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test\Controllers;

use Gustavus\Test\TestObject,
  Gustavus\Concert\Test\TestBase,
  Gustavus\Concert\Controllers\SharedController,
  Gustavus\Concert\FileConfiguration,
  Gustavus\Concert\Config,
  Gustavus\Doctrine\DBAL,
  Gustavus\FormBuilderMK2\ElementRenderers\ElementRenderer,
  Gustavus\Concourse\Test\RouterTestUtil,
  Gustavus\Concert\FileManager,
  Gustavus\Utility\PageUtil,
  Gustavus\Concourse\RoutingUtil,
  Gustavus\Extensibility\Filters;

/**
 * Test controller for SharedController
 *
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class SharedControllerTestController extends SharedController
{
  /**
   * overloads renderPage so it doesn't try to start outbut buffers
   *
   * @return array
   */
  protected function renderPage()
  {
    $this->addSessionMessages();
    return [
      'title'           => $this->getTitle(),
      'subtitle'        => $this->getSubtitle(),
      'content'         => $this->getContent(),
      'localNavigation' => $this->getLocalNavigation(),
      'focusBox'        => $this->getFocusBox(),
      'stylesheets'     => $this->getStylesheets(),
      'javascripts'     => $this->getJavascripts(),
    ];
  }

  /**
   * overloads redirect so it doesn't try to redirect when called
   * @param  string $path
   * @param  integer $statusCode Redirection status code
   * @return void
   */
  protected function redirect($path = '/', $statusCode = 303)
  {
    $_POST = [];
    return ['redirect' => $path];
  }

  /**
   * {@inheritdoc}
   */
  protected function addFormBuilderResources(ElementRenderer $renderer, array $extraCSSResources = null, array $extraJSResources = null)
  {
    $this->setStylesheets('');
    $this->setJavascripts('');
  }

  /**
   * Adds css and js needed for filtering
   *
   * @return  void
   */
  protected function addFilteringJsAndCss()
  {
    $this->setStylesheets('');
    $this->setJavascripts('');
  }

  /**
   * Forwards specifying the test controller to forward to
   *
   * {@inheritdoc}
   */
  protected function forward($alias, array $parameters = array())
  {
    return RouterTestUtil::forward($this->getRoutingConfiguration(), $alias, $parameters, [
          'Gustavus\Concert\Controllers\DraftController' => 'Gustavus\Concert\Test\Controllers\DraftControllerTestController',
          'Gustavus\Concert\Controllers\MenuController' => 'Gustavus\Concert\Test\Controllers\MenuControllerTestController',
          'Gustavus\Concert\Controllers\SiteNavController' => 'Gustavus\Concert\Test\Controllers\SiteNavControllerTestController']);
  }
}

/**
 * Tests for SharedController
 *
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class SharedControllerTest extends TestBase
{
  /**
   * SharedController
   *
   * @var SharedController
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
    //self::removeFiles(self::$testFileDir);
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
    $this->controller = new TestObject(new SharedControllerTestController);

    $this->controller->dbal = DBAL::getDBAL('testDB', $this->getDBH());
  }

  /**
   * @test
   */
  public function userIsEditingPublicDraft()
  {
    $this->setUpController();
    $requestURI = $this->controller->buildUrl('editDraft', ['draftName' => 'testFile']);

    $this->assertTrue($this->controller->userIsEditingPublicDraft($requestURI));
  }

  /**
   * @test
   */
  public function userIsEditingPublicDraftFalse()
  {
    $_SERVER['REQUEST_URI'] = 'nothing';
    $this->setUpController();

    $this->assertFalse($this->controller->userIsEditingPublicDraft('/billy/files/testFile'));
  }

  /**
   * @test
   */
  public function userIsEditingPublicDraftFromFilePath()
  {
    $_SERVER['REQUEST_URI'] = 'nothing';
    $this->setUpController();
    $filePath = $this->controller->buildUrl('editDraft', ['draftName' => 'testFile']);

    $this->assertTrue($this->controller->userIsEditingPublicDraft($filePath));
  }

  /**
   * @test
   */
  public function userIsEditingPublicDraftFromFilePathFalse()
  {
    $_SERVER['REQUEST_URI'] = 'nothing';
    $filePath = '/cis/www/billy/testFile';
    $this->setUpController();

    $this->assertFalse($this->controller->userIsEditingPublicDraft($filePath));
  }

  /**
   * @test
   */
  public function userIsEditingPublicDraftExtraQueryParams()
  {
    $this->setUpController();
    $requestURI = $this->controller->buildUrl('editDraft', ['draftName' => 'testFile']) . '?concert=test';

    $this->assertTrue($this->controller->userIsEditingPublicDraft($requestURI));
  }

  /**
   * @test
   */
  public function userIsEditingPublicDraftNoDraft()
  {
    $this->setUpController();
    $_GET['concert'] = 'editDraft';
    $filePath = '/cis/www/billy/testFile';

    $this->assertFalse($this->controller->userIsEditingPublicDraft($filePath));
  }

  /**
   * @test
   */
  public function userIsEditingPublicDraftEditing()
  {
    $this->setUpController();
    $requestURI = '/billy/concert/index.php?concert=editDraft&concertDraft=12345678901234567890123456789012';

    $this->assertFalse($this->controller->userIsEditingPublicDraft($requestURI));
  }

  /**
   * @test
   */
  public function userIsEditingPublicDraftSavingPublicDraft()
  {
    $this->setUpController();
    $requestURI = '/billy/concert/index.php?concert=editDraft&concertDraft=12345678901234567890123456789012';
    $filePath = $this->controller->buildUrl('editDraft', ['draftName' => 'testFile']) . '?concert=test';
    $_POST['filePath']   = $filePath;
    $_POST['saveAction'] = 'savePublicDraft';

    $this->assertTrue($this->controller->userIsEditingPublicDraft($requestURI));
  }

  /**
   * @test
   */
  public function userIsViewingPublicDraft()
  {
    $this->setUpController();
    $requestURI = $this->controller->buildUrl('drafts', ['draftName' => 'testFile']);

    $this->assertTrue($this->controller->userIsViewingPublicDraft($requestURI));
  }

  /**
   * @test
   */
  public function userIsViewingPublicDraftFalse()
  {
    $_SERVER['REQUEST_URI'] = 'nothing';
    $this->setUpController();

    $this->assertFalse($this->controller->userIsViewingPublicDraft('/billy/files/testFile'));
  }

  /**
   * @test
   */
  public function userIsViewingPublicDraftFromFilePath()
  {
    $_SERVER['REQUEST_URI'] = 'nothing';
    $this->setUpController();
    $filePath = $this->controller->buildUrl('drafts', ['draftName' => 'testFile']);

    $this->assertTrue($this->controller->userIsViewingPublicDraft($filePath));
  }

  /**
   * @test
   */
  public function userIsViewingPublicDraftFromFilePathFalse()
  {
    $_SERVER['REQUEST_URI'] = 'nothing';
    $filePath = '/cis/www/billy/testFile';
    $this->setUpController();

    $this->assertFalse($this->controller->userIsViewingPublicDraft($filePath));
  }

  /**
   * @test
   */
  public function userIsViewingPublicDraftExtraQueryParams()
  {
    $this->setUpController();
    $requestURI = $this->controller->buildUrl('drafts', ['draftName' => 'testFile']) . '?concert=test';

    $this->assertTrue($this->controller->userIsViewingPublicDraft($requestURI));
  }

  /**
   * @test
   */
  public function userIsViewingPublicDraftNoDraft()
  {
    $this->setUpController();
    $_GET['concert'] = 'viewDraft';
    $filePath = '/cis/www/billy/testFile';

    $this->assertFalse($this->controller->userIsViewingPublicDraft($filePath));
  }

  /**
   * @test
   */
  public function renderLockNotAcquiredMessageNoPermission()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->setUpController();

    $result = $this->controller->renderLockNotAcquiredMessage('/cis/www/billy/testFile');

    $this->assertNotSame(Config::LOCK_NOT_ACQUIRED_MESSAGE, $result);
    $this->assertContains(Config::LOCK_NOT_ACQUIRED_MESSAGE, $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function renderLockNotAcquiredMessageAllowedToEdit()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->setUpController();

    $this->call('PermissionsManager', 'saveUserPermissions', ['billy', '/billy/', 'test']);
    $this->authenticate('billy');

    $fm = new FileManager('billy', '/cis/www/billy/testFile', null, $this->controller->getDB());

    $result = $this->controller->renderLockNotAcquiredMessage($fm);

    $this->assertSame(Config::LOCK_NOT_ACQUIRED_MESSAGE, $result);
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function renderLockNotAcquiredMessageWithOwner()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->setUpController();

    $this->call('PermissionsManager', 'saveUserPermissions', ['billy', '/billy/', 'test']);
    $this->authenticate('billy');

    $fm = new FileManager('billy', '/cis/www/billy/testFile', null, $this->controller->getDB());

    $this->assertTrue($fm->acquireLock());
    $this->authenticate('arst');

    $result = $this->controller->renderLockNotAcquiredMessage($fm);

    $this->assertNotSame(Config::LOCK_NOT_ACQUIRED_MESSAGE, $result);
    $this->assertContains(Config::LOCK_NOT_ACQUIRED_MESSAGE, $result);
    $this->assertContains('billy', $result);
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function renderLockNotAcquiredMessageWithOwnerInCampusAPI()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->setUpController();

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/', 'test']);
    $this->authenticate('bvisto');

    $fm = new FileManager('bvisto', '/cis/www/billy/testFile', null, $this->controller->getDB());

    $this->assertTrue($fm->acquireLock());
    $this->authenticate('arst');

    $result = $this->controller->renderLockNotAcquiredMessage($fm);

    $this->assertNotSame(Config::LOCK_NOT_ACQUIRED_MESSAGE, $result);
    $this->assertContains(Config::LOCK_NOT_ACQUIRED_MESSAGE, $result);
    $this->assertContains('Billy Visto', $result);
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function renderOpenDraftMessageCurrentOwner()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->setUpController();

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->authenticate('bvisto');

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $fm = new FileManager('bvisto', self::$testFileDir . 'index.php', null, $this->controller->getDB());

    $this->assertContains(self::$testFileDir, $fm->saveDraft(Config::PRIVATE_DRAFT));

    $result = $this->controller->renderOpenDraftMessage($fm);

    $this->assertEmpty($result);
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function renderOpenDraftMessage()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->setUpController();

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->authenticate('bvisto');

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $fm = new FileManager('bvisto', self::$testFileDir . 'index.php', null, $this->controller->getDB());

    $this->assertContains(self::$testFileDir, $fm->saveDraft(Config::PRIVATE_DRAFT));
    $this->authenticate('arst');

    $result = $this->controller->renderOpenDraftMessage(self::$testFileDir . 'index.php');

    $this->assertContains('a draft open for this page', $result);
    $this->assertContains('Billy Visto', $result);
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function renderOpenDraftMessageManyUsers()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->setUpController();

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser1', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser2', self::$testFileDir, 'test']);
    $this->authenticate('bvisto');

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $fm = new FileManager('bvisto', self::$testFileDir . 'index.php', null, $this->controller->getDB());
    $this->assertContains(self::$testFileDir, $fm->saveDraft(Config::PRIVATE_DRAFT));
    $fm->stopEditing();

    $fmTwo = new FileManager('testuser1', self::$testFileDir . 'index.php', null, $this->controller->getDB());
    $this->assertContains(self::$testFileDir, $fmTwo->saveDraft(Config::PRIVATE_DRAFT));
    $fmTwo->stopEditing();

    $fmThree = new FileManager('testuser2', self::$testFileDir . 'index.php', null, $this->controller->getDB());
    $this->assertContains(self::$testFileDir, $fmThree->saveDraft(Config::PRIVATE_DRAFT));
    $fmThree->stopEditing();


    $this->authenticate('bvisto');

    $result = $this->controller->renderOpenDraftMessage($fm);

    $this->assertContains('a draft open for this page', $result);
    $this->assertContains('You, testuser1, and testuser2 have', $result);
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function renderOpenDraftMessageManyUsersDifferentOrder()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->setUpController();

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser1', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser2', self::$testFileDir, 'test']);
    $this->authenticate('bvisto');

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $fm = new FileManager('testuser1', self::$testFileDir . 'index.php', null, $this->controller->getDB());
    $this->assertContains(self::$testFileDir, $fm->saveDraft(Config::PRIVATE_DRAFT));
    $fm->stopEditing();

    $fmTwo = new FileManager('bvisto', self::$testFileDir . 'index.php', null, $this->controller->getDB());
    $this->assertContains(self::$testFileDir, $fmTwo->saveDraft(Config::PRIVATE_DRAFT));
    $fmTwo->stopEditing();

    $fmThree = new FileManager('testuser2', self::$testFileDir . 'index.php', null, $this->controller->getDB());
    $this->assertContains(self::$testFileDir, $fmThree->saveDraft(Config::PRIVATE_DRAFT));
    $fmThree->stopEditing();


    $this->authenticate('bvisto');

    $result = $this->controller->renderOpenDraftMessage($fm);

    $this->assertContains('a draft open for this page', $result);
    $this->assertContains('testuser1, you, and testuser2 have', $result);
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function addMoshMenu()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->setUpController();

    $this->set(new SharedController, 'moshMenuAdded', false);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', '/billy/concert/', 'test']);
    $_SERVER['REQUEST_URI'] = '/billy/concert/index.php';
    $this->authenticate('testuser');

    $this->controller->addMoshMenu();

    $content = '';
    $utilBarExtras = Filters::apply('utilBarExtras', $content);
    $this->assertContains('concert', $utilBarExtras);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function setConcertMessage()
  {
    $this->setUpController();

    $this->controller->setConcertMessage('arst');
    $this->assertMessageInMessages('arst', SharedController::getConcertMessages());
  }

  /**
   * @test
   */
  public function setGet()
  {
    $origGET = $_GET;
    $this->call('Controllers\SharedController', 'setGET', [['arst' => 'arst']]);

    $this->assertNotSame($_GET, $origGET);
    $_GET = $origGET;
  }

  /**
   * @test
   */
  public function setGetMoshed()
  {
    $origGET = $_GET;
    $this->call('Controllers\SharedController', 'markMoshed');
    $this->call('Controllers\SharedController', 'setGET', [['arst' => 'arst']]);

    $this->assertNotSame($_GET, $origGET);
    $_GET = $origGET;
  }

  /**
   * @test
   */
  public function getSiteNavForFile()
  {
    file_put_contents(self::$testFileDir . 'site_nav.php', 'siteNav!');
    $filePath = self::$testFileDir . 'index.php';
    $this->assertSame(self::$testFileDir . 'site_nav.php', $this->call('Controllers\SharedController', 'getSiteNavForFile', [$filePath]));
  }

  /**
   * @test
   */
  public function getSiteNavForFileAlreadySiteNav()
  {
    $filePath = '/cis/www/billy/concert/site_nav.php';
    $this->assertSame($filePath, $this->call('Controllers\SharedController', 'getSiteNavForFile', [$filePath]));
  }

  /**
   * @test
   */
  public function isSiteNavShared()
  {
    $dir = self::$testFileDir . 'arst/';
    mkdir($dir);
    file_put_contents($dir . 'site_nav.php', 'siteNav test contents');

    $this->assertFalse($this->call('Controllers\SharedController', 'isSiteNavShared', [$dir . 'site_nav.php']));
    self::removeFiles(self::$testFileDir);
  }

  /**
   * @test
   */
  public function isSiteNavSharedTrue()
  {
    $dir = self::$testFileDir . 'arst/';
    mkdir($dir);
    file_put_contents($dir . 'site_nav.php', 'siteNav test contents');
    mkdir($dir . 'arst/');

    $this->assertTrue($this->call('Controllers\SharedController', 'isSiteNavShared', [$dir . 'site_nav.php']));
    self::removeFiles(self::$testFileDir);
  }

  /**
   * @test
   */
  public function addOutdatedDraftMessageIfNeededNotNeeded()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->setUpController();

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->authenticate('bvisto');

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $fm = new FileManager('bvisto', self::$testFileDir . 'index.php', null, $this->controller->getDB());

    $this->assertContains(self::$testFileDir, $fm->saveDraft(Config::PRIVATE_DRAFT));

    $draft = $fm->getDraft();

    $this->controller->addOutDatedDraftMessageIfNeeded($draft);

    $result = SharedController::getConcertMessages();

    $this->assertEmpty($result);
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function addOutdatedDraftMessageIfNeeded()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->setUpController();

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->authenticate('bvisto');

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $fm = new FileManager('bvisto', self::$testFileDir . 'index.php', null, $this->controller->getDB());

    $this->assertContains(self::$testFileDir, $fm->saveDraft(Config::PRIVATE_DRAFT));

    $draft = $fm->getDraft();

    // force the draft date to be older
    $draft['date'] = (new \DateTime('-1 days'))->format('m/d/Y g:i:s');

    $this->controller->addOutDatedDraftMessageIfNeeded($draft);

    $result = SharedController::getConcertMessages();

    $this->assertNotEmpty($result);
    $this->assertMessageInMessages(Config::OUTDATED_DRAFT_MESSAGE, $result);
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function insertEditingResourcesPublicDraft()
  {
    $this->buildDB();
    $this->setUpController();

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->authenticate('bvisto');

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');
    $this->assertContains(self::$testFileDir, $this->fileManager->saveDraft(Config::PUBLIC_DRAFT));

    $draft = $this->fileManager->getDraft();

    $filePath = $this->controller->buildUrl('editDraft', ['draftName' => $draft['draftFilename']]);

    $_GET['concert']      = 'editDraft';
    $_GET['concertDraft'] = $draft['draftFilename'];
    $this->controller->insertEditingResources($filePath);

    $this->assertContains(self::$testFileDir, $_SESSION['concertCMS']['siteAccessKeys']);

    $scripts = '';
    $scripts = Filters::apply('scripts', $scripts);

    // make sure filePath is the path for editing public drafts and not the path to the file being edited
    $this->assertContains(sprintf('Gustavus.Concert.filePath = "%s"', $filePath), $scripts);
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function insertEditingResourcesNotAllowed()
  {
    $this->buildDB();
    $this->setUpController();

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->authenticate('bvisto');

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');
    $this->assertContains(self::$testFileDir, $this->fileManager->saveDraft(Config::PUBLIC_DRAFT));

    $draft = $this->fileManager->getDraft();

    $filePath = $this->controller->buildUrl('editDraft', ['draftName' => $draft['draftFilename']]);

    $this->controller->insertEditingResources($filePath);

    $this->assertFalse(isset($_SESSION['concertCMS']));
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function insertEditingResources()
  {
    $this->buildDB();
    $this->setUpController();

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->authenticate('bvisto');

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->controller->insertEditingResources(self::$testFileDir . 'index.php');

    $this->assertContains(self::$testFileDir, $_SESSION['concertCMS']['siteAccessKeys']);
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function guessDraftName()
  {
    $_GET['concertDraft'] = 'arstarst';
    $this->assertSame($_GET['concertDraft'], $this->call('Controllers\SharedController', 'guessDraftName'));

    unset($_GET['concertDraft']);

    $this->assertSame('123457809', $this->call('Controllers\SharedController', 'guessDraftName', ['gustavus.edu/concert/drafts/edit/123457809']));

    $this->assertSame('123457809', $this->call('Controllers\SharedController', 'guessDraftName', ['gustavus.edu/concert/drafts/edit/123457809/index.php']));

    $_SERVER['REQUEST_URI'] = 'gustavus.edu/concert/drafts/edit/123457809';
    $this->assertSame('123457809', $this->call('Controllers\SharedController', 'guessDraftName'));

    $_SERVER['REQUEST_URI'] = 'gustavus.edu/concert/drafts/edit/123457809/index.php';
    $this->assertSame('123457809', $this->call('Controllers\SharedController', 'guessDraftName'));
  }
}