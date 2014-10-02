<?php
/**
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test\Controllers;

use Gustavus\Test\TestObject,
  Gustavus\Concert\Test\TestBase,
  Gustavus\Concert\Controllers\DraftController,
  Gustavus\Concert\Test\Controllers\DraftControllerTestController,
  Gustavus\Concert\FileConfiguration,
  Gustavus\Concert\Config,
  Gustavus\Doctrine\DBAL,
  Gustavus\Concert\FileManager,
  Gustavus\Utility\PageUtil,
  Gustavus\Concourse\RoutingUtil,
  Gustavus\Extensibility\Filters;

/**
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class DraftControllerTest extends TestBase
{
  /**
   * DraftController
   *
   * @var DraftController
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
    $_SERVER['REQUEST_URI']  = 'testing';
    $_SERVER['HTTP_REFERER'] = 'https://beta.gac.edu/billy/concert/newPage.php?concert=edit';


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
    unset($this->controller);

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
    $this->controller = new TestObject(new DraftControllerTestController);

    $this->controller->dbal = DBAL::getDBAL('testDB', $this->getDBH());
  }

  /**
   * @test
   */
  public function showDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController();

    $this->assertContains('Please select a draft', $this->controller->showDraft(['filePath' => '/billy/concert/index.php'])['content']);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function showDraftMultipleOptions()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);
    $this->fileManager->stopEditing();

    $this->buildFileManager('jerry', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController();

    $actual = $this->controller->showDraft(['filePath' => '/billy/concert/index.php']);

    $this->assertContains('Please select a draft', $actual['content']);
    $this->assertContains('testUser', $actual['content']);
    $this->assertContains('Jerry', $actual['content']);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function showDraftNotAllowed()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('jerry');

    $this->setUpController();

    $actual = $this->controller->showDraft(['filePath' => '/billy/concert/index.php']);
    $this->assertSame(['redirect'], array_keys($actual));
    $this->assertSame('/billy/concert/index.php', $actual['redirect']);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function showDraftNoDrafts()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $this->authenticate('testUser');

    $this->setUpController();

    $this->assertContains('aren\'t any drafts to show', $this->controller->showDraft(['filePath' => '/billy/concert/index.php'])['content']);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function showDraftSpecific()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController();

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $this->controller->showDraft(['filePath' => '/billy/concert/index.php', 'draft' => basename($draftName)]));
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function showDraftFromConcertRoot()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    // make it look like the request is coming from the concert root
    $_SERVER['REQUEST_URI'] = Config::WEB_DIR . 'index.php';

    $this->setUpController();

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $this->controller->showDraft(['filePath' => '/billy/concert/index.php', 'draft' => basename($draftName)]));
    $this->assertMessageInMessages('will live at', $this->controller->getConcertMessages());
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handleDraftActionsShowSpecificDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController();

    $_GET['concert']      = 'viewDraft';
    $_GET['concertDraft'] = basename($draftName);

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $this->controller->handleDraftActions(['filePath' => '/billy/concert/index.php'])['value']);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handleDraftActionsNotSpecified()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController();

    $_GET['concert']      = 'viewDraft';

    $this->assertContains('Please select a draft', $this->controller->handleDraftActions(['filePath' => '/billy/concert/index.php'])['value']['content']);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function showDraftSpecificNotFound()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    self::removeFiles(Config::$draftDir);

    $this->authenticate('testUser');

    $this->setUpController();

    $actual = $this->controller->showDraft(['filePath' => '/billy/concert/index.php', 'draft' => basename($draftName)]);
    $this->assertContains('could not be found', $actual['content']);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function renderPublicDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->setUpController();

    $actual = $this->controller->renderPublicDraft(['draftName' => basename($draftName)]);

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $actual);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function renderPublicDraftEditable()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController();

    $actual = $this->controller->renderPublicDraft(['draftName' => basename($draftName)]);

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $actual);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function renderPublicDraftEditableFromConcertRoot()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController();
    // make it look like it is coming from the concert root
    $_SERVER['REQUEST_URI'] = Config::WEB_DIR . 'index.php';

    $actual = $this->controller->renderPublicDraft(['draftName' => basename($draftName)]);

    // we are forcing people to view public drafts from the location that they will live at
    $this->assertSame(['redirect' => 'https://beta.gac.edu/billy/concert/index.php?concert=viewDraft&concertDraft=edba98d222792b8363ebcdc9c56c67b8'], $actual);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function renderPublicDraftNotPublic()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController();

    $actual = $this->controller->renderPublicDraft(['draftName' => basename($draftName)]);

    // PageUtil::renderPageNotFound has been overridden to return renderPageNotFound
    $this->assertSame('renderPageNotFound', $actual);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editPublicDraftPrivate()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->authenticate('jerry');

    $this->setUpController();

    $actual = $this->controller->editPublicDraft(['draftName' => basename($draftName)]);

    $this->assertContains('specified draft doesn\'t exist', $actual['content']);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editPublicDraftNotAllowed()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['bvisto']);

    $this->authenticate('jerry');

    $this->setUpController();

    $actual = $this->controller->editPublicDraft(['draftName' => basename($draftName)]);

    $this->assertContains('you don\'t have access', $actual['content']);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editPublicDraftCantLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['bvisto']);

    $this->authenticate('bvisto');

    $this->setUpController();

    $actual = $this->controller->editPublicDraft(['draftName' => basename($draftName)]);

    $this->assertContains(Config::LOCK_NOT_ACQUIRED_MESSAGE, $actual['content']);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editPublicDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['bvisto']);
    $this->fileManager->stopEditing();

    $this->authenticate('bvisto');

    $this->setUpController();

    $actual = $this->controller->editPublicDraft(['draftName' => basename($draftName)]);

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $actual);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editPublicDraftSubmission()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['bvisto']);
    $this->fileManager->stopEditing();

    $this->authenticate('bvisto');

    $this->setUpController();
    $_SERVER['REQUEST_METHOD'] = 'POST';

    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $actual = $this->controller->editPublicDraft(['draftName' => basename($draftName)]);

    $this->assertContains($_POST['1'], file_get_contents($draftName));
    $this->assertSame('return', $actual['action']);
    $this->assertTrue($actual['value']);
    $this->assertNotEmpty($actual['redirectUrl']);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handleDraftActionsEditPublicDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['bvisto']);
    $this->fileManager->stopEditing();

    $this->authenticate('bvisto');

    $this->setUpController();

    $_SERVER['REQUEST_URI'] = $this->controller->buildUrl('editDraft', ['draftName' => basename($draftName)]);

    $actual = $this->controller->handleDraftActions(['filePath' => $draftName]);

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $actual['value']);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handleDraftActionsEditPublicDraftSubmission()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['bvisto']);
    $this->fileManager->stopEditing();

    $this->authenticate('bvisto');

    $this->setUpController();

    $_SERVER['REQUEST_URI'] = $this->controller->buildUrl('editDraft', ['draftName' => basename($draftName)]);
    $_SERVER['REQUEST_METHOD'] = 'POST';

    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $actual = $this->controller->handleDraftActions(['filePath' => $draftName]);

    $this->assertContains($_POST['1'], file_get_contents($draftName));
    $this->assertSame('return', $actual['action']);
    $this->assertTrue($actual['value']);
    $this->assertNotEmpty($actual['redirectUrl']);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveDraftNotAllowed()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->authenticate('jerry');

    $this->setUpController();

    $actual = json_decode($this->controller->saveDraft($filePath), true);

    $this->assertSame(['error', 'reason'], array_keys($actual));

    $this->assertContains(Config::NOT_ALLOWED_TO_EDIT_MESSAGE, $actual['reason']);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveDraftCantLock()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', $filePath);
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->acquireLock();

    $this->authenticate('jerry');

    $this->setUpController();

    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $actual = $this->controller->saveDraft($filePath);

    $this->assertContains(Config::LOCK_NOT_ACQUIRED_MESSAGE, $actual['content']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveDraft()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->authenticate('testUser');

    $this->setUpController();

    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $actual = $this->controller->saveDraft($filePath);

    $this->assertTrue($actual);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveDraftNewFileNotAllowed()
  {
    $this->removeFiles(self::$testFileDir);
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('jerry');

    $this->setUpController();

    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $actual = json_decode($this->controller->saveDraft($filePath), true);

    $this->assertSame(['error', 'reason'], array_keys($actual));

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handleDraftActions()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');

    $this->setUpController();

    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $_POST['saveAction'] = 'savePublicDraft';

    $actual = $this->controller->handleDraftActions(['filePath' => $filePath]);

    $this->assertSame(['action', 'value'], array_keys($actual));
    $this->assertSame('return', $actual['action']);
    $this->assertTrue($actual['value']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveDraftForNewFileCantLock()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', $filePath);
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->acquireLock();

    $this->authenticate('jerry');

    $this->setUpController();

    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $actual = json_decode($this->controller->saveDraftForNewFile($filePath, Config::DEFAULT_TEMPLATE_PAGE), true);

    $this->assertContains(Config::LOCK_NOT_ACQUIRED_MESSAGE, $actual['reason']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveDraftForNewFile()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');

    $this->setUpController();

    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $actual = $this->controller->saveDraftForNewFile($filePath, Config::DEFAULT_TEMPLATE_PAGE);

    $this->assertTrue($actual);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveDraftForNewFileEditingDraft()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');

    $this->setUpController();

    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $actual = $this->controller->saveDraftForNewFile($filePath, Config::DEFAULT_TEMPLATE_PAGE);

    $this->assertTrue($actual);


    $_POST = ['1' => '<p>This is some more edited html content</p>'];

    $actual = $this->controller->saveDraftForNewFile($filePath, Config::DEFAULT_TEMPLATE_PAGE);
    $this->assertTrue($actual);

    $this->buildFileManager('testUser', $filePath);
    $draft = $this->fileManager->getDraftForUser('testUser');
    $draftContents = file_get_contents(Config::$draftDir . $draft['draftFilename']);
    $this->assertContains($_POST['1'], $draftContents);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function deleteDraft()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');

    $this->setUpController();

    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $actual = $this->controller->saveDraft($filePath);
    $this->assertTrue($actual);

    $this->assertTrue($this->controller->deleteDraft($filePath));

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function deleteDraftNoDrafts()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');

    $this->setUpController();

    $this->assertTrue($this->controller->deleteDraft($filePath));

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function deleteDraftNoDraftsNotAllowed()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('jerry');

    $this->setUpController();

    $actual = json_decode($this->controller->deleteDraft($filePath), true);

    $this->assertSame(['error', 'reason'], array_keys($actual));
    $this->assertSame(Config::NOT_ALLOWED_TO_EDIT_MESSAGE, $actual['reason']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function deleteDraftHandleDaftActions()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');

    $this->setUpController();

    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $actual = $this->controller->saveDraft($filePath);
    $this->assertTrue($actual);

    $_POST['saveAction'] = 'discardDraft';

    $this->assertTrue($this->controller->handleDraftActions(['filePath' => $filePath])['value']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function stopEditingPublicDraft()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');

    $this->setUpController();

    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $actual = $this->controller->saveDraft($filePath);
    $this->assertTrue($actual);

    $this->assertTrue($this->controller->stopEditingPublicDraft(['filePath' => $filePath])['value']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function addUsersToDraftNotOwned()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', $filePath);
    $this->fileManager->fileConfiguration = $configuration;

    $draftFileName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('jerry');

    $this->setUpController();

    $actual = $this->controller->addUsersToDraft(['draftName' => basename($draftFileName)]);

    $this->assertContains('you don\'t own this draft', $actual['content']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function addUsersToDraftNotExistent()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->authenticate('jerry');

    $this->setUpController();

    $actual = $this->controller->addUsersToDraft(['draftName' => basename($filePath)]);

    $this->assertContains('draft doesn\'t exist', $actual['content']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function addUsersToDraft()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', $filePath);
    $this->fileManager->fileConfiguration = $configuration;

    $draftFileName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController();

    $actual = $this->controller->addUsersToDraft(['draftName' => basename($draftFileName)]);

    $this->assertContains('<form', $actual['content']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function addUsersToDraftBarebones()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', $filePath);
    $this->fileManager->fileConfiguration = $configuration;

    $draftFileName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $_GET['barebones'] = true;

    $this->setUpController();

    $actual = $this->controller->addUsersToDraft(['draftName' => basename($draftFileName)]);

    $this->assertContains('<form', $actual);
    $this->assertContains('<script', $actual);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function addUsersToDraftSubmission()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', $filePath);
    $this->fileManager->fileConfiguration = $configuration;

    $draftFileName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
      'addusers' => [
        'adduserssection' => [
          'person' => [
            ['username' => 'bvisto'],
            ['username' => 'jerry'],
          ],
        ],
      ],
    ];

    $actual = $this->controller->addUsersToDraft(['draftName' => basename($draftFileName)]);

    $this->assertSame(['redirect'], array_keys($actual));

    $draft = $this->fileManager->getDraft(basename($draftFileName));

    $this->assertSame(['bvisto', 'jerry'], $draft['additionalUsers']);

    $expectedBcc = [];
    foreach (Config::$devEmails as $devEmail) {
      $expectedBcc[$devEmail] = null;
    }
    $expectedBcc['bvisto@gustavus.edu'] = null;
    $expectedBcc['jerry@gustavus.edu'] = null;

    $this->checkSentEmailContents(['bcc' => $expectedBcc], 'testUser has shared a draft with you', 'The draft can be viewed or edited at: ' . $this->controller->buildUrl('drafts', ['draftName' => $draft['draftFilename']], '', true), true);


    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function addUsersToDraftSubmissionAlreadyShared()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', $filePath);
    $this->fileManager->fileConfiguration = $configuration;

    $draftFileName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['bvisto']);

    $this->authenticate('testUser');

    $this->setUpController();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
      'addusers' => [
        'adduserssection' => [
          'person' => [
            ['username' => 'bvisto'],
            ['username' => 'jerry'],
          ],
        ],
      ],
    ];

    $actual = $this->controller->addUsersToDraft(['draftName' => basename($draftFileName)]);

    $this->assertSame(['redirect'], array_keys($actual));

    $draft = $this->fileManager->getDraft(basename($draftFileName));

    $this->assertSame(['bvisto', 'jerry'], $draft['additionalUsers']);

    $expectedBcc = [];
    foreach (Config::$devEmails as $devEmail) {
      $expectedBcc[$devEmail] = null;
    }
    $expectedBcc['jerry@gustavus.edu'] = null;

    $this->checkSentEmailContents(['bcc' => $expectedBcc], 'testUser has shared a draft with you', 'The draft can be viewed or edited at: ' . $this->controller->buildUrl('drafts', ['draftName' => $draft['draftFilename']], '', true), true);


    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function addUsersToDraftSubmissionAlreadySharedWithAll()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', $filePath);
    $this->fileManager->fileConfiguration = $configuration;

    $draftFileName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['bvisto', 'jerry']);

    $this->authenticate('testUser');

    $this->setUpController();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
      'addusers' => [
        'adduserssection' => [
          'person' => [
            ['username' => 'bvisto'],
            ['username' => 'jerry'],
          ],
        ],
      ],
    ];

    $actual = $this->controller->addUsersToDraft(['draftName' => basename($draftFileName)]);

    $this->assertSame(['redirect'], array_keys($actual));

    $draft = $this->fileManager->getDraft(basename($draftFileName));

    $this->assertSame(['bvisto', 'jerry'], $draft['additionalUsers']);

    $message = $this->mockMailer->popMessage();

    $this->assertNull($message);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function addUsersToDraftSubmissionBarebones()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', $filePath);
    $this->fileManager->fileConfiguration = $configuration;

    $draftFileName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController();

    $_GET['barebones'] = true;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
      'addusers' => [
        'adduserssection' => [
          'person' => [
            ['username' => 'bvisto'],
            ['username' => 'jerry'],
          ],
        ],
      ],
    ];

    $actual = $this->controller->addUsersToDraft(['draftName' => basename($draftFileName)]);

    $this->assertTrue($actual);

    $draft = $this->fileManager->getDraft(basename($draftFileName));

    $this->assertSame(['bvisto', 'jerry'], $draft['additionalUsers']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function addUsersToDraftSubmissionThroughHandleDraftActions()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', $filePath);
    $this->fileManager->fileConfiguration = $configuration;

    $draftFileName = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController();

    $_GET['concert'] = 'addUsers';

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
      'addusers' => [
        'adduserssection' => [
          'person' => [
            ['username' => 'bvisto'],
            ['username' => 'jerry'],
          ],
        ],
      ],
    ];

    $actual = $this->controller->handleDraftActions(['filePath' => $draftFileName]);

    $this->assertSame(['action', 'value'], array_keys($actual));
    $this->assertSame(['redirect'], array_keys($actual['value']));

    $draft = $this->fileManager->getDraft(basename($draftFileName));

    $this->assertSame(['bvisto', 'jerry'], $draft['additionalUsers']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function getFilePathToCopy()
  {
    $this->setUpController();

    $_SERVER['HTTP_REFERER'] = 'arst?srcFilePath=' . urlencode('/billy/concert/index.php');

    $result = $this->controller->getFilePathToCopy();

    $this->assertSame($_SERVER['DOCUMENT_ROOT'] . 'billy/concert/index.php', $result);
  }

  /**
   * @test
   */
  public function getFilePathToCopyDefault()
  {
    $this->setUpController();

    $result = $this->controller->getFilePathToCopy();

    $this->assertSame(Config::DEFAULT_TEMPLATE_PAGE, $result);
  }

  /**
   * @test
   */
  public function getFilePathToCopyFromGetNotForwardedInternally()
  {
    $this->setUpController();

    $origGet = $_GET;
    $_GET['srcFilePath'] = Config::SITE_NAV_TEMPLATE;
    $result = $this->controller->getFilePathToCopy();

    $this->assertSame(Config::DEFAULT_TEMPLATE_PAGE, $result);
    $_GET = $origGet;
  }

  /**
   * @test
   */
  public function getFilePathToCopyFromGet()
  {
    $this->setUpController();

    $origGet = $_GET;
    $_GET['srcFilePath'] = Config::SITE_NAV_TEMPLATE;
    // mark that we are forwarded from site nav controller
    $_GET['forwardedFrom'] = 'siteNav';
    $result = $this->controller->getFilePathToCopy();

    $this->assertSame(Config::SITE_NAV_TEMPLATE, $result);
    $_GET = $origGet;
  }

  /**
   * @test
   */
  public function handlePendingDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);

    $draft = $this->fileManager->getDraft(basename($draftName));

    $this->authenticate('testUser');

    $this->setUpController();

    $actual = $this->controller->handlePendingDraft($draft, $this->fileManager);

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $actual);

    $this->assertMessageInMessages('confirmPublish=true', $this->controller->getConcertMessages());
    $this->assertMessageInMessages('confirmReject=true', $this->controller->getConcertMessages());
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handlePendingDraftConfirmReject()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);

    $draft = $this->fileManager->getDraft(basename($draftName));

    $this->authenticate('testUser');

    $this->setUpController();
    $_GET['confirmReject'] = 'true';

    $actual = $this->controller->handlePendingDraft($draft, $this->fileManager);

    $this->assertContains('value="reject"', $actual);
    $this->assertContains('name="action"', $actual);
    $this->assertContains('name="message"', $actual);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handlePendingDraftConfirmPublish()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);

    $draft = $this->fileManager->getDraft(basename($draftName));

    $this->authenticate('testUser');

    $this->setUpController();
    $_GET['confirmPublish'] = 'true';

    $actual = $this->controller->handlePendingDraft($draft, $this->fileManager);

    $this->assertContains('value="publish"', $actual);
    $this->assertContains('name="action"', $actual);
    $this->assertContains('name="message"', $actual);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handlePendingDraftPublishNoLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser2', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser2', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->assertTrue($this->fileManager->acquireLock());
    $draftName = $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);

    $draft = $this->fileManager->getDraft(basename($draftName));

    $this->authenticate('testUser');

    $this->setUpController();
    $_POST['action'] = 'publish';

    $fm = new FileManager('testUser', '/billy/concert/index.php', null, $this->controller->getDB());

    $actual = $this->controller->handlePendingDraft($draft, $fm);

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $actual);

    $this->assertMessageInMessages('couldn\'t acquire a lock', $this->controller->getConcertMessages());
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handlePendingDraftPublish()
  {
    $this->buildDB();
    $draftOwner = $this->findEmployeeUsername(true);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', [$draftOwner, 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);


    $this->buildFileManager($draftOwner, '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);

    $draft = $this->fileManager->getDraft(basename($draftName));
    $this->fileManager->stopEditing();

    $this->authenticate('testUser');

    $this->setUpController();
    $_POST['action']  = 'publish';
    $_POST['message'] = 'Looks good';

    $fm = new FileManager('testUser', '/billy/concert/index.php', null, $this->controller->getDB());

    $actual = $this->controller->handlePendingDraft($draft, $fm);
    $this->assertSame(['redirect' => '/billy/concert/index.php'], $actual);

    $filePath = Config::$stagingDir . $fm->getDraftFileName($draftOwner);

    $expectedBcc = [];
    foreach (Config::$devEmails as $devEmail) {
      $expectedBcc[$devEmail] = null;
    }

    $this->checkSentEmailContents(
        [
          'bcc' => $expectedBcc,
          'to'  => [$draftOwner . '@gustavus.edu' => null],
          'replyTo' => ['no-reply@gustavus.edu' => null],
        ],
        'pending draft has been published',
        'Looks good',
        true
    );

    $this->buildFileManager('root', $filePath);
    $expected = [[
      'destFilepath' => '/billy/concert/index.php',
      'username'     => 'testUser',
      'action'       => Config::PUBLISH_PENDING_STAGE,
    ]];

    $this->assertSame($expected, $this->fileManager->getStagedFileEntry());

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handlePendingDraftPublishFakeUser()
  {
    $this->buildDB();

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser2', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);


    $this->buildFileManager('testUser2', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);

    $draft = $this->fileManager->getDraft(basename($draftName));
    $this->fileManager->stopEditing();

    $this->authenticate('testUser');

    $this->setUpController();
    $_POST['action']  = 'publish';
    $_POST['message'] = 'Looks good';

    $fm = new FileManager('testUser', '/billy/concert/index.php', null, $this->controller->getDB());

    $actual = $this->controller->handlePendingDraft($draft, $fm);

    $this->assertSame(['redirect', 'message'], array_keys($actual));
    $this->assertSame('/billy/concert/index.php', $actual['redirect']);
    $this->assertContains('couldn\'t be notified', $actual['message']);

    $filePath = Config::$stagingDir . $fm->getDraftFileName('testUser2');

    $message = $this->mockMailer->popMessage();

    $this->assertNull($message);

    $this->buildFileManager('root', $filePath);
    $expected = [[
      'destFilepath' => '/billy/concert/index.php',
      'username'     => 'testUser',
      'action'       => Config::PUBLISH_PENDING_STAGE,
    ]];

    $this->assertSame($expected, $this->fileManager->getStagedFileEntry());

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handlePendingDraftReject()
  {
    $this->buildDB();
    $draftOwner = $this->findEmployeeUsername(true);
    $loggedInUser = $this->findEmployeeUsername(true);

    $this->call('PermissionsManager', 'saveUserPermissions', [$loggedInUser, 'billy/concert/', 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', [$draftOwner, 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);


    $this->buildFileManager($draftOwner, '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);

    $draft = $this->fileManager->getDraft(basename($draftName));
    $this->fileManager->stopEditing();

    $this->authenticate($loggedInUser);

    $this->setUpController();
    $_POST['action']  = 'reject';
    $_POST['message'] = 'Looks horrible';

    $fm = new FileManager($loggedInUser, '/billy/concert/index.php', null, $this->controller->getDB());

    $actual = $this->controller->handlePendingDraft($draft, $fm);
    $this->assertSame(['redirect' => '/billy/concert/index.php'], $actual);

    $filePath = Config::$stagingDir . $fm->getDraftFileName($draftOwner);

    $expectedBcc = [];
    foreach (Config::$devEmails as $devEmail) {
      $expectedBcc[$devEmail] = null;
    }

    $this->checkSentEmailContents(
        [
          'bcc' => $expectedBcc,
          'to'  => [$draftOwner . '@gustavus.edu' => null],
          'replyTo' => [$loggedInUser . '@gustavus.edu' => null],
        ],
        'pending draft has been rejected',
        'Looks horrible',
        true
    );

    $this->buildFileManager('root', $filePath);

    $this->assertSame([], $this->fileManager->getStagedFileEntry());

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handlePendingDraftRejectFakeUser()
  {
    $this->buildDB();

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser2', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);


    $this->buildFileManager('testUser2', '/billy/concert/index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);

    $draft = $this->fileManager->getDraft(basename($draftName));
    $this->fileManager->stopEditing();

    $this->authenticate('testUser');

    $this->setUpController();
    $_POST['action']  = 'reject';
    $_POST['message'] = 'Looks good';

    $fm = new FileManager('testUser', '/billy/concert/index.php', null, $this->controller->getDB());

    $actual = $this->controller->handlePendingDraft($draft, $fm);

    $this->assertSame(['redirect', 'message'], array_keys($actual));
    $this->assertSame('/billy/concert/index.php', $actual['redirect']);
    $this->assertContains('couldn\'t be notified', $actual['message']);

    $filePath = Config::$stagingDir . $fm->getDraftFileName('testUser2');

    $message = $this->mockMailer->popMessage();

    $this->assertNull($message);

    $this->buildFileManager('root', $filePath);

    $this->assertSame([], $this->fileManager->getStagedFileEntry());

    $this->unauthenticate();
    $this->destructDB();
  }
}