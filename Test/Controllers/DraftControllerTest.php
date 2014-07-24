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
  Gustavus\Concourse\RoutingUtil;

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
    $_SERVER['REQUEST_URI'] = 'testing';
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
    $this->assertContains('jerry', $actual['content']);
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

    $sessionMessage = PageUtil::getSessionMessage($_SERVER['REQUEST_URI']);
    $this->assertContains('Edit Draft', $sessionMessage);
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

    $this->assertContains(Config::LOCK_NOT_AQUIRED_MESSAGE, $actual['content']);
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

    $_SERVER['REQUEST_URI'] = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'editDraft', ['draftName' => basename($draftName)]);

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

    $_SERVER['REQUEST_URI'] = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'editDraft', ['draftName' => basename($draftName)]);
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

    $this->assertContains(Config::LOCK_NOT_AQUIRED_MESSAGE, $actual['content']);

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

    $actual = json_decode($this->controller->saveDraftForNewFile($filePath, Config::TEMPLATE_PAGE), true);

    $this->assertContains(Config::LOCK_NOT_AQUIRED_MESSAGE, $actual['reason']);

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

    $actual = $this->controller->saveDraftForNewFile($filePath, Config::TEMPLATE_PAGE);

    $this->assertTrue($actual);

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
        'person' => [
          ['username' => 'bvisto'],
          ['username' => 'jerry'],
        ],
      ],
    ];

    $actual = $this->controller->addUsersToDraft(['draftName' => basename($draftFileName)]);

    $this->assertSame(['redirect'], array_keys($actual));

    $draft = $this->fileManager->getDraft(basename($draftFileName));

    $this->assertSame(['bvisto', 'jerry'], $draft['additionalUsers']);

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
        'person' => [
          ['username' => 'bvisto'],
          ['username' => 'jerry'],
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
}