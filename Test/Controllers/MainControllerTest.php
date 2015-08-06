<?php
/**
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test\Controllers;

use Gustavus\Test\TestObject,
  Gustavus\Concert\Test\TestBase,
  Gustavus\Concert\Controllers\MainController,
  Gustavus\Concert\FileConfiguration,
  Gustavus\Concert\Config,
  Gustavus\Concert\Utility,
  Gustavus\Doctrine\DBAL,
  Gustavus\FormBuilderMK2\ElementRenderers\ElementRenderer,
  Gustavus\Concourse\Test\RouterTestUtil,
  Gustavus\Concert\FileManager,
  Gustavus\Utility\PageUtil,
  Gustavus\Utility\String,
  Gustavus\Extensibility\Filters,
  Gustavus\Revisions\API as RevisionsAPI,
  Gustavus\Concourse\RoutingUtil;

/**
 * Tests for MainController
 *
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class MainControllerTest extends TestBase
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
    self::$overrideToken['isPageEditable'] = override_method('\Gustavus\Concert\Utility', 'isPageEditable', function() {
          // just return true;
          return true;
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
    $this->controller = new TestObject(new MainControllerTestController);

    $this->controller->dbal = DBAL::getDBAL('testDB', $this->getDBH());
  }

  /**
   * @test
   */
  public function editNoPermission()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertFalse($this->controller->edit('billy/concert/index.php'));

    $this->assertMessageInMessages('don\'t have access', MainController::getConcertMessages());
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editLink()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $filePath = self::$testFileDir . 'indexLink.php';
    symlink($filePath, self::$testFileDir . 'indexLink.php');

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertFalse($this->controller->edit($filePath));

    $this->assertMessageInMessages(Config::SPECIAL_FILE_MESSAGE, MainController::getConcertMessages());
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editNoLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', 'billy/concert/', 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['arst', 'billy/concert/', 'test']);

    $this->buildFileManager('arst', 'billy/concert/index.php');
    $this->assertTrue($this->fileManager->acquireLock());

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertFalse($this->controller->edit('billy/concert/index.php'));

    $this->assertMessageInMessages('arst currently holds the lock', MainController::getConcertMessages());
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editExcluded()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', 'billy/concert/', 'test', null, 'concourse']);

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertFalse($this->controller->edit('billy/concert/concourse/index.php'));

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editIncluded()
  {
    mkdir(self::$testFileDir . 'billy/concert/concourse/user/', 0777, true);
    file_put_contents(self::$testFileDir . 'billy/concert/concourse/user/index.php', 'arst');
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir . 'billy/concert/', 'test', 'concourse/user', 'concourse']);

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertContains('arst', $this->controller->edit(self::$testFileDir . 'billy/concert/concourse/user/index.php'));

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editSiteExcluded()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveNewSiteIfNeeded', ['billy/concert/', 'concourse2/*']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', 'billy/concert/', 'test', null, '']);

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertFalse($this->controller->edit('billy/concert/concourse2/index.php'));

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editOpenDraft()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->buildFileManager('testuser', $filePath);
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $this->controller->edit($filePath));

    $this->assertEmpty(MainController::getConcertMessages());

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editOpenDraftOtherUser()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', $filePath);
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);
    $this->fileManager->stopEditing();

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $this->controller->edit($filePath));

    $this->assertMessageInMessages('draft open for this page', MainController::getConcertMessages());

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function edit()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $this->controller->edit($filePath));

    $this->assertEmpty(MainController::getConcertMessages());

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editSubmission()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->authenticate('testuser');

    $this->setUpController();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $this->assertTrue($this->controller->edit($filePath));

    $this->buildFileManager('testuser', $filePath);

    $modifiedFile = file_get_contents(Config::$stagingDir . $this->fileManager->getFilePathHash());

    $this->assertContains('This is some edited html content', $modifiedFile);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editSubmissionCantPublish()
  {
    $origNonPublishingAccessLevels = Config::$nonPublishingAccessLevels;
    Config::$nonPublishingAccessLevels = ['nonPub'];

    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'nonPub']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['publisherUser', self::$testFileDir, Config::SITE_PUBLISHER_ACCESS_LEVEL]);

    $this->authenticate('testuser');

    $this->setUpController();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $this->assertTrue($this->controller->edit($filePath));

    $this->buildFileManager('testuser', $filePath);

    $modifiedFile = file_get_contents(Config::$draftDir . $this->fileManager->getDraftFileName('testuser'));

    $this->assertContains('This is some edited html content', $modifiedFile);

    $expectedTo = [];
    foreach (Config::$adminEmails as $adminEmail) {
      $expectedTo[$adminEmail] = null;
    }

    $this->checkSentEmailContents(
        ['to' => $expectedTo],
        'Unable to email publishers for: ' . $filePath,
        'A publisher was found',
        true
    );

    $this->unauthenticate();
    $this->destructDB();
    Config::$nonPublishingAccessLevels = $origNonPublishingAccessLevels;
  }

  /**
   * @test
   */
  public function editSubmissionCantPublishNoPublishers()
  {
    $origNonPublishingAccessLevels = Config::$nonPublishingAccessLevels;
    Config::$nonPublishingAccessLevels = ['nonPub'];

    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'nonPub']);

    $this->authenticate('testuser');

    $this->setUpController();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $this->assertTrue($this->controller->edit($filePath));

    $this->buildFileManager('testuser', $filePath);

    $modifiedFile = file_get_contents(Config::$draftDir . $this->fileManager->getDraftFileName('testuser'));

    $this->assertContains('This is some edited html content', $modifiedFile);

    $expectedTo = [];
    foreach (Config::$adminEmails as $adminEmail) {
      $expectedTo[$adminEmail] = null;
    }

    $this->checkSentEmailContents(
        ['to' => $expectedTo],
        'No publishers were found for ' . $filePath,
        'testuser submitted a draft pending review',
        true
    );

    $this->unauthenticate();
    $this->destructDB();
    Config::$nonPublishingAccessLevels = $origNonPublishingAccessLevels;
  }

  /**
   * @test
   */
  public function savePendingDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');

    $this->setUpController();
    $this->assertFalse($this->controller->savePendingDraft($this->fileManager));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function createNewPageNoPermission()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertFalse($this->controller->createNewPage($filePath));

    $this->assertMessageInMessages('don\'t have access', MainController::getConcertMessages());
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function createNewPageNoLock()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['arst', self::$testFileDir, 'test']);

    $this->buildFileManager('arst', $filePath);
    // create a lock on the file
    $this->assertTrue($this->fileManager->acquireLock());

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertFalse($this->controller->createNewPage($filePath));

    $this->assertMessageInMessages('arst currently', MainController::getConcertMessages());
    $this->unauthenticate();
    $this->destructDB();
    self::removeFiles(self::$testFileDir);
  }

  /**
   * @test
   */
  public function createNewPageOpenDraft()
  {
    $filePath = self::$testFileDir . 'index.php';

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->buildFileManager('testuser', $filePath, Config::DEFAULT_TEMPLATE_PAGE);
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertContains('Sub-Title', $this->controller->createNewPage($filePath));

    $this->unauthenticate();
    $this->destructDB();
    self::removeFiles(self::$testFileDir);
  }

  /**
   * @test
   */
  public function createNewPage()
  {
    $filePath = self::$testFileDir . 'index.php';

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertContains('Sub-Title', $this->controller->createNewPage($filePath));

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function createNewPageFromPage()
  {
    $filePath = self::$testFileDir . 'index.php';
    $fromFilePath = self::$testFileDir . 'indexarst.php';
    file_put_contents($fromFilePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $this->controller->createNewPage($filePath, $fromFilePath));

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function createNewPageFromPageSubmission()
  {
    $filePath = self::$testFileDir . 'index.php';

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->authenticate('testuser');

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $this->setUpController();

    $this->assertTrue($this->controller->createNewPage($filePath));

    $this->buildFileManager('testuser', $filePath);

    $modifiedFile = file_get_contents(Config::$stagingDir . $this->fileManager->getFilePathHash());

    $this->assertContains($_POST['1'], $modifiedFile);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function createNewPageFromPageSubmissionCopy()
  {
    $filePath = self::$testFileDir . 'index.php';
    $fromFilePath = self::$testFileDir . 'indexarst.php';
    file_put_contents($fromFilePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->authenticate('testuser');

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $this->setUpController();

    $this->assertTrue($this->controller->createNewPage($filePath, $fromFilePath));

    $this->buildFileManager('testuser', $filePath);

    $modifiedFile = file_get_contents(Config::$stagingDir . $this->fileManager->getFilePathHash());

    $this->assertContains($_POST['1'], $modifiedFile);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function deletePageNonExistent()
  {
    $filePath = self::$testFileDir . 'index.php';

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->authenticate('testuser');

    $this->setUpController();

    $this->assertSame(['redirect' => dirname($filePath)], $this->controller->deletePage($filePath));

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function deletePageLink()
  {
    file_put_contents(self::$testFileDir . 'index.php', 'arst');
    $filePath = self::$testFileDir . 'indexLink.php';
    symlink(self::$testFileDir . 'index.php', $filePath);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);

    $this->authenticate('testuser');
    $this->setUpController();

    $this->assertFalse($this->controller->deletePage($filePath));

    $this->assertMessageInMessages(Config::SPECIAL_FILE_MESSAGE, MainController::getConcertMessages());

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function deletePageNotAllowedToDelete()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, 'arst');

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);

    $this->authenticate('testuser');
    $this->setUpController();

    $this->assertFalse($this->controller->deletePage($filePath));

    $this->assertMessageInMessages(Config::NOT_ALLOWED_TO_DELETE_MESSAGE, MainController::getConcertMessages());

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function deletePageNoLock()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, 'arst');

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser1', self::$testFileDir, 'test']);

    $this->buildFileManager('testuser1', $filePath);
    $this->assertTrue($this->fileManager->acquireLock());

    $this->authenticate('testuser');
    $this->setUpController();

    $this->assertFalse($this->controller->deletePage($filePath));

    $this->assertMessageInMessages(Config::LOCK_NOT_ACQUIRED_MESSAGE, MainController::getConcertMessages());

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function deletePageWithDraft()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, 'arst');

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser1', self::$testFileDir, 'test']);

    $this->buildFileManager('testuser1', $filePath);
    $this->assertTrue($this->fileManager->saveDraft(Config::PUBLIC_DRAFT) !== false);
    $this->fileManager->stopEditing();

    $this->authenticate('testuser');
    $this->setUpController();

    $result = $this->controller->deletePage($filePath);

    $this->assertSame(['action', 'value'], array_keys($result));
    $this->assertContains('<form', $result['value']['content']);

    $this->assertContains('testuser1', $result['value']['content']);
    $this->assertContains('draft open', $result['value']['content']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function deletePageBarebones()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, 'arst');

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->authenticate('testuser');
    $this->setUpController();
    $origGet = $_GET;
    $_GET['barebones'] = true;

    $result = $this->controller->deletePage($filePath);

    $this->assertSame(['action', 'value'], array_keys($result));
    $this->assertContains('<script', $result['value']['content']);
    $this->assertContains('<form', $result['value']['content']);

    $this->unauthenticate();
    $this->destructDB();
    $_GET = $origGet;
  }

  /**
   * @test
   */
  public function deletePageSubmissionCancel()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, 'arst');

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->authenticate('testuser');
    $this->setUpController();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
      'filePath'      => $filePath,
      'deleteAction'  => 'cancelDelete',
      'concertAction' => 'delete',
    ];

    $result = $this->controller->deletePage($filePath);

    $this->assertSame(['redirect' => $filePath], $result);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function deletePageSubmissionBarebonesCancel()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, 'arst');

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->authenticate('testuser');
    $this->setUpController();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
      'filePath'     => $filePath,
      'deleteAction' => 'cancelDelete',
      'concertAction' => 'delete',
    ];
    $origGet = $_GET;
    $_GET['barebones'] = true;

    $result = $this->controller->deletePage($filePath);

    $this->assertSame(['action' => 'return', 'value' => true], $result);

    $this->unauthenticate();
    $this->destructDB();
    $_GET = $origGet;
  }

  /**
   * @test
   */
  public function deletePageSubmission()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, 'arst');

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->authenticate('testuser');
    $this->setUpController();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
      'filePath'      => $filePath,
      'deleteAction'  => 'confirmDelete',
      'concertAction' => 'delete',
    ];

    $result = $this->controller->deletePage($filePath);

    $this->buildFileManager('testuser', $filePath);
    $this->buildFileManager('testuser', self::$testFileDir . $this->fileManager->getFilePathHash());
    $stagedFile = $this->fileManager->getStagedFileEntry();

    $this->assertSame('renderPageNotFound', $result);
    $this->assertSame('delete', $stagedFile[0]['action']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function deletePageSubmissionBarebones()
  {
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, 'arst');

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->authenticate('testuser');
    $this->setUpController();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
      'filePath'      => $filePath,
      'deleteAction'  => 'confirmDelete',
      'concertAction' => 'delete',
    ];
    $origGet = $_GET;
    $_GET['barebones'] = true;

    $result = $this->controller->deletePage($filePath);

    $this->buildFileManager('testuser', $filePath);
    $this->buildFileManager('testuser', self::$testFileDir . $this->fileManager->getFilePathHash());
    $stagedFile = $this->fileManager->getStagedFileEntry();

    $this->assertSame(['action' => 'return', 'value' => json_encode(['redirectUrl' => dirname($filePath)])], $result);
    $this->assertSame('delete', $stagedFile[0]['action']);

    $this->unauthenticate();
    $this->destructDB();
    $_GET = $origGet;
  }

  /**
   * @test
   */
  public function handleQueryRequestNotFound()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $_POST['concertAction'] = 'query';
    $_POST['query']         = 'arst';
    $this->setUpController();

    $this->assertFalse($this->controller->handleQueryRequest($filePath));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handleQueryRequestSharedDraft()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $_POST['concertAction'] = 'query';
    $_POST['query']         = 'hasSharedDraft';
    $this->setUpController();

    $this->assertFalse($this->controller->handleQueryRequest($filePath));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handleQueryRequestSharedDraftTrue()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);
    $_POST['concertAction'] = 'query';
    $_POST['query']         = 'hasSharedDraft';

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->buildFileManager('testuser', $filePath, Config::DEFAULT_TEMPLATE_PAGE);
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']);

    $this->authenticate('testuser');
    $this->setUpController();

    $this->assertTrue($this->controller->handleQueryRequest($filePath));

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function stopEditing()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->buildFileManager('testuser', $filePath, Config::DEFAULT_TEMPLATE_PAGE);
    //$this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']);
    $this->fileManager->acquireLock();

    $this->authenticate('testuser');
    $this->setUpController();

    $this->assertSame(['action' => 'none', 'value' => true], $this->controller->stopEditing($filePath));

    $this->assertFalse($this->fileManager->userHasLock());

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function stopEditingDraft()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->buildFileManager('testuser', $filePath, Config::DEFAULT_TEMPLATE_PAGE);
    $draftName = basename($this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']));

    $this->authenticate('testuser');
    $this->setUpController();

    $_GET['concert'] = 'editDraft';
    $_GET['concertDraft'] = $draftName;

    $this->assertSame(['action' => 'return', 'value' => true], $this->controller->stopEditing($filePath));

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function autocompleteUser()
  {
    $args = ['value' => 'bvisto'];

    $this->setUpController();
    $result = $this->controller->autocompleteUser($args);

    $this->assertContains('Billy Visto', $result);
  }

  /**
   * @test
   */
  public function moshNotLoggedIn()
  {
    $expected = ['action' => 'none'];

    $this->setUpController();
    $this->assertSame($expected, $this->controller->mosh('arst.php'));
  }

  /**
   * @test
   */
  public function moshLoggedInAlreadyMoshed()
  {
    $expected = ['action' => 'none'];

    $this->authenticate('bvisto');

    $this->setUpController();
    $this->controller->markMoshed();
    $this->assertSame($expected, $this->controller->mosh('arst.php'));

    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function moshQueryRequestSharedDraft()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->authenticate('bvisto');
    $_POST['concertAction'] = 'query';
    $_POST['query']         = 'hasSharedDraft';
    $this->setUpController();

    $this->assertSame(['action' => 'return', 'value' => false], $this->controller->mosh($filePath));
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function moshStopEditing()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->authenticate('bvisto');
    $_POST['concertAction'] = 'stopEditing';
    $this->setUpController();

    $this->assertSame(['action' => 'none', 'value' => true], $this->controller->mosh($filePath));
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function moshViewDrafts()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->authenticate('bvisto');
    $_GET['concert'] = 'viewDraft';
    $this->setUpController();

    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action', 'value'], array_keys($actual));
    $this->assertContains('aren\'t any drafts to show', $actual['value']['content']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function moshCreateFile()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->authenticate('bvisto');
    $_GET['concert'] = 'edit';
    $this->setUpController();

    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action', 'value'], array_keys($actual));
    $this->assertContains('Sub-Title', $actual['value']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function moshStopEditingNewFile()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->authenticate('bvisto');
    $_GET['concert'] = 'stopEditing';
    $this->setUpController();

    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action' => 'none'], $actual);

    $this->unauthenticate();
    $this->destructDB();
    self::removeFiles(self::$testFileDir);
  }

  /**
   * @test
   */
  public function moshStopEditingNewFileWithOpenDraft()
  {
    $filePath = '/cis/www/billy/concert/arst/arst/index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', 'test']);


    $this->buildFileManager('bvisto', $filePath, Config::DEFAULT_TEMPLATE_PAGE);
    $draftName = basename($this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']));

    $this->authenticate('bvisto');
    $_GET['concert'] = 'stopEditing';
    $_SERVER['REQUEST_URI'] = '/billy/concert/arst/arst/index.php';
    $this->setUpController();


    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action'], array_keys($actual));
    $this->assertSame('none', $actual['action']);

    // make sure our lock has been released
    $this->assertFalse($this->fileManager->getLockFromDB());

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handleNewPageRequest()
  {
    $origNonCreationAccessLevels = Config::$nonCreationAccessLevels;
    Config::$nonCreationAccessLevels = ['noCreate'];

    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'noCreate']);

    $this->buildFileManager('bvisto', $filePath, Config::DEFAULT_TEMPLATE_PAGE);
    $draftName = basename($this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']));

    $this->authenticate('bvisto');
    $_GET['concert'] = 'edit';
    $this->setUpController();

    $actual = $this->controller->handleNewPageRequest($filePath);

    $this->assertSame(['action'], array_keys($actual));
    $this->assertSame('none', $actual['action']);

    $this->assertMessageInMessages(Config::NOT_ALLOWED_TO_CREATE_MESSAGE, MainController::getConcertMessages());

    $this->unauthenticate();
    $this->destructDB();
    Config::$nonCreationAccessLevels = $origNonCreationAccessLevels;
  }

  /**
   * @test
   */
  public function moshEditFile()
  {
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $_SERVER['DOCUMENT_ROOT'] = '/cis/lib/';
    $this->set('Config', 'requiredDocRoot', '/cis/lib');
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', str_replace('/cis/lib/', '', self::$testFileDir), 'test']);

    $this->authenticate('bvisto');
    $_GET['concert'] = 'edit';
    $this->setUpController();

    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action', 'value'], array_keys($actual));
    $this->assertContains($this->wrappedEditableIdentifier, $actual['value']);

    $this->unauthenticate();
    $this->destructDB();
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    $this->set('Config', 'requiredDocRoot', $docRoot);
  }

  /**
   * @test
   */
  public function moshEditFileNoIndex()
  {
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $_SERVER['DOCUMENT_ROOT'] = '/cis/lib/';
    $this->set('Config', 'requiredDocRoot', '/cis/lib');
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', str_replace('/cis/lib/', '', self::$testFileDir), 'test']);

    $this->authenticate('bvisto');
    $_GET['concert'] = 'edit';
    $this->setUpController();

    $actual = $this->controller->mosh(self::$testFileDir);
    $this->assertSame(['action', 'value'], array_keys($actual));
    $messages = MainController::getConcertMessages();
    $this->assertEmpty($messages);
    $scripts = '';
    $scripts = Filters::apply('scripts', $scripts);
    // make sure our full file path including index.php is used
    $this->assertContains($filePath, $scripts);
    $this->assertContains($this->wrappedEditableIdentifier, $actual['value']);

    $this->unauthenticate();
    $this->destructDB();
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    $this->set('Config', 'requiredDocRoot', $docRoot);
  }

  /**
   * @test
   */
  public function moshEditFileNonExistentNoCreation()
  {
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $_SERVER['DOCUMENT_ROOT'] = '/cis/lib/';
    $this->set('Config', 'requiredDocRoot', '/cis/lib');
    $filePath = self::$testFileDir . 'index.php';
    if (file_exists($filePath)) {
      unlink($filePath);
    }

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', str_replace('/cis/lib/', '', self::$testFileDir), 'noCreate']);

    $this->authenticate('bvisto');
    $_GET['concert'] = 'edit';
    $this->setUpController();

    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action' => 'none'], $actual);
    $this->assertMessageInMessages(Config::NOT_ALLOWED_TO_CREATE_MESSAGE, MainController::getConcertMessages());

    $this->unauthenticate();
    $this->destructDB();
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    $this->set('Config', 'requiredDocRoot', $docRoot);
  }

  /**
   * @test
   */
  public function moshStopEditingFile()
  {
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $_SERVER['DOCUMENT_ROOT'] = '/cis/lib/';
    $this->set('Config', 'requiredDocRoot', '/cis/lib');
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', str_replace('/cis/lib/', '', self::$testFileDir), 'test']);

    $this->buildFileManager('bvisto', $filePath, Config::DEFAULT_TEMPLATE_PAGE);
    $draftName = basename($this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']));

    $this->authenticate('bvisto');
    $_GET['concert'] = 'stopEditing';
    $this->setUpController();

    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action' => 'none', 'value' => true], $actual);

    $this->unauthenticate();
    $this->destructDB();
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    $this->set('Config', 'requiredDocRoot', $docRoot);
  }

  /**
   * @test
   */
  public function moshNothingWithLock()
  {
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $_SERVER['DOCUMENT_ROOT'] = '/cis/lib/';
    $this->set('Config', 'requiredDocRoot', '/cis/lib');
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', str_replace('/cis/lib/', '', self::$testFileDir), 'test']);

    $this->buildFileManager('bvisto', $filePath, Config::DEFAULT_TEMPLATE_PAGE);
    $this->assertTrue($this->fileManager->acquireLock());

    $this->authenticate('bvisto');
    $_GET['concert'] = '';
    $this->setUpController();

    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action' => 'none'], $actual);

    $this->assertMessageInMessages(Config::CONTINUE_EDITING_MESSAGE, MainController::getConcertMessages());

    $this->unauthenticate();
    $this->destructDB();
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    $this->set('Config', 'requiredDocRoot', $docRoot);
  }

  /**
   * @test
   */
  public function moshNothing()
  {
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $_SERVER['DOCUMENT_ROOT'] = '/cis/lib/';
    $this->set('Config', 'requiredDocRoot', '/cis/lib');
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', str_replace('/cis/lib/', '', self::$testFileDir), 'test']);

    $this->authenticate('bvisto');
    $_GET['concert'] = '';
    $this->setUpController();

    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action' => 'none'], $actual);

    $this->assertEmpty(MainController::getConcertMessages());

    $this->unauthenticate();
    $this->destructDB();
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    $this->set('Config', 'requiredDocRoot', $docRoot);
  }

  /**
   * @test
   */
  public function moshViewPublicDraft()
  {
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $_SERVER['DOCUMENT_ROOT'] = '/cis/lib/';
    $this->set('Config', 'requiredDocRoot', '/cis/lib');
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', str_replace('/cis/lib/', '', self::$testFileDir), 'test']);

    $this->buildFileManager('bvisto', $filePath, Config::DEFAULT_TEMPLATE_PAGE);
    $draftName = basename($this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']));

    $_GET['concert'] = 'viewDraft';
    $_GET['concertDraft'] = $draftName;
    $this->setUpController();

    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action', 'value'], array_keys($actual));
    $this->assertContains('Sub-Title', $actual['value']);

    $this->destructDB();
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    $this->set('Config', 'requiredDocRoot', $docRoot);
  }

  /**
   * @test
   */
  public function handleMoshActionsQuery()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->authenticate('bvisto');
    $_POST['concertAction'] = 'query';
    $_POST['query']         = 'hasSharedDraft';
    $_POST['filePath']      = $filePath;
    $this->setUpController();

    $this->assertSame('false', $this->controller->handleMoshRequest());
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   * @expectedException InvalidArgumentException
   */
  public function handleMoshActionsQueryException()
  {
    $_POST['concertAction'] = 'query';
    $_POST['query']         = 'hasSharedDraft';
    $this->setUpController();

    $actual = $this->controller->handleMoshRequest();
    $this->assertFalse(true);
  }

  /**
   * @test
   */
  public function handleRevisionsCantView()
  {
    $filePath = self::$testFileDir . 'index.php';
    $this->buildDB();

    $this->setUpController();

    $actual = $this->controller->handleRevisions($filePath);

    $this->assertFalse($actual);
    $this->assertMessageInMessages(Config::NOT_ALLOWED_TO_VIEW_REVISIONS, MainController::getConcertMessages());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function handleRevisionsNoRevisions()
  {
    $origManageRevAccessLevels = Config::$manageRevisionsAccessLevels;
    Config::$manageRevisionsAccessLevels = ['test'];
    $filePath = self::$testFileDir . 'index.php';
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->authenticate('bvisto');

    $this->setUpController();

    $actual = $this->controller->handleRevisions($filePath);

    $this->assertContains('aren\'t any revisions', $actual['value']['content']);

    $this->unauthenticate();
    $this->destructDB();
    Config::$manageRevisionsAccessLevels = $origManageRevAccessLevels;
  }

  /**
   * @test
   */
  public function handleRevisions()
  {
    $origManageRevAccessLevels = Config::$manageRevisionsAccessLevels;
    Config::$manageRevisionsAccessLevels = ['test'];
    $filePath = self::$testFileDir . 'index.php';
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $fileContents = '<?php ?>test contents<?php arst;?>more';
    file_put_contents($filePath, $fileContents);
    $this->buildFileManager('bvisto', $filePath);
    $this->assertTrue($this->fileManager->saveRevision());

    $this->authenticate('bvisto');

    $this->setUpController();

    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->controller->getDB());
    $this->assertSame(1, $revisionsAPI->getRevisionCount());

    $actual = $this->controller->handleRevisions($filePath);

    $this->assertNotContains('There doesn\'t seem to be any data associated with the information provided.', $actual['value']['content']);
    $this->assertContains('revisionsForm', $actual['value']['content']);

    $this->unauthenticate();
    $this->destructDB();
    Config::$manageRevisionsAccessLevels = $origManageRevAccessLevels;
  }

  /**
   * @test
   */
  public function handleRevisionsViewSpecific()
  {
    $origManageRevAccessLevels = Config::$manageRevisionsAccessLevels;
    Config::$manageRevisionsAccessLevels = ['test'];
    $filePath = self::$testFileDir . 'index.php';
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $fileContents = '<?php ?>test contents<?php arst;?>more';
    file_put_contents($filePath, $fileContents);
    $this->buildFileManager('bvisto', $filePath);
    $this->assertTrue($this->fileManager->saveRevision());

    $this->authenticate('bvisto');

    $this->setUpController();

    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->controller->getDB());
    $this->assertSame(1, $revisionsAPI->getRevisionCount());

    $_GET['revisionNumber'] = 0;

    $actual = $this->controller->handleRevisions($filePath);

    $this->assertNotContains('There doesn\'t seem to be any data associated with the information provided.', $actual['value']['content']);
    $this->assertNotContains("test contents\n\rmore", $actual['value']['content']);

    $_GET['revisionNumber'] = 1;

    $actual = $this->controller->handleRevisions($filePath);

    $this->assertNotContains('There doesn\'t seem to be any data associated with the information provided.', $actual['value']['content']);
    $this->assertContains("test contents\n\rmore", $actual['value']['content']);
    $this->destructDB();
    Config::$manageRevisionsAccessLevels = $origManageRevAccessLevels;
  }

  /**
   * @test
   */
  public function handleRevisionsRestoreNotAllowed()
  {
    $origManageRevAccessLevels = Config::$manageRevisionsAccessLevels;
    Config::$manageRevisionsAccessLevels = ['admin'];
    $filePath = self::$testFileDir . 'index.php';
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $fileContents = '<?php ?>test contents<?php arst;?>';
    file_put_contents($filePath, $fileContents);
    $this->buildFileManager('bvisto', $filePath);
    $this->assertTrue($this->fileManager->saveRevision());

    $fileContents = '<?php ?>test contents<?php arst;?>more';
    file_put_contents($filePath, $fileContents);
    $this->buildFileManager('bvisto', $filePath);
    $this->assertTrue($this->fileManager->saveRevision());

    $this->authenticate('bvisto');

    $this->setUpController();

    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->controller->getDB());
    $this->assertSame(2, $revisionsAPI->getRevisionCount());

    $_POST['revisionNumber'] = 1;
    $_POST['restore'] = 1;

    $actual = $this->controller->handleRevisions($filePath);

    $filePathHash = $this->fileManager->getFilePathHash();
    $this->buildFileManager('root', Config::$stagingDir . $filePathHash);
    // publish the file to trigger a revision
    $this->fileManager->publishFile();

    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->controller->getDB());
    $this->assertSame(2, $revisionsAPI->getRevisionCount());

    $this->unauthenticate();
    $this->destructDB();
    Config::$manageRevisionsAccessLevels = $origManageRevAccessLevels;
  }

  /**
   * @test
   */
  public function handleRevisionsRestoreNoLock()
  {
    $origManageRevAccessLevels = Config::$manageRevisionsAccessLevels;
    Config::$manageRevisionsAccessLevels = ['test'];
    $filePath = self::$testFileDir . 'index.php';
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', self::$testFileDir, 'test']);

    $fileContents = '<?php ?>test contents<?php arst;?>';
    file_put_contents($filePath, $fileContents);
    $this->buildFileManager('bvisto', $filePath);
    $this->assertTrue($this->fileManager->saveRevision());

    $fileContents = '<?php ?>test contents<?php arst;?>more';
    file_put_contents($filePath, $fileContents);
    $this->buildFileManager('bvisto', $filePath);
    $this->assertTrue($this->fileManager->saveRevision());

    $this->buildFileManager('jerry', $filePath);
    $this->assertTrue($this->fileManager->acquireLock());

    $this->authenticate('bvisto');

    $this->setUpController();

    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->controller->getDB());
    $this->assertSame(2, $revisionsAPI->getRevisionCount());

    $_POST['revisionNumber'] = 1;
    $_POST['restore'] = 1;

    $actual = $this->controller->handleRevisions($filePath);

    $this->assertContains($this->controller->renderLockNotAcquiredMessage($filePath), $actual['value']['content']);

    $filePathHash = $this->fileManager->getFilePathHash();
    $this->buildFileManager('root', Config::$stagingDir . $filePathHash);
    // publish the file to trigger a revision
    $this->fileManager->publishFile();

    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->controller->getDB());
    $this->assertSame(2, $revisionsAPI->getRevisionCount());

    $this->unauthenticate();
    $this->destructDB();
    Config::$manageRevisionsAccessLevels = $origManageRevAccessLevels;
  }

  /**
   * @test
   */
  public function handleRevisionsRestore()
  {
    $origManageRevAccessLevels = Config::$manageRevisionsAccessLevels;
    Config::$manageRevisionsAccessLevels = ['test'];
    $filePath = self::$testFileDir . 'index.php';
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $fileContents = '<?php ?>test contents<?php arst;?>';
    file_put_contents($filePath, $fileContents);
    $this->buildFileManager('bvisto', $filePath);
    $this->assertTrue($this->fileManager->saveRevision());

    $fileContents = '<?php ?>test contents<?php arst;?>more';
    file_put_contents($filePath, $fileContents);
    $this->buildFileManager('bvisto', $filePath);
    $this->assertTrue($this->fileManager->saveRevision());

    $this->authenticate('bvisto');

    $this->setUpController();

    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->controller->getDB());
    $this->assertSame(2, $revisionsAPI->getRevisionCount());

    $_POST['revisionNumber'] = 1;
    $_POST['restore'] = 1;

    $actual = $this->controller->handleRevisions($filePath);

    $this->assertNull($actual['value']['content']);

    $url = (new String($filePath))->addQueryString(['concert' => 'revisions', 'revisionsAction' => 'thankYou'])->buildUrl()->getValue();

    $this->assertSame(Config::RESTORED_MESSAGE, PageUtil::getSessionMessage($url));

    $filePathHash = $this->fileManager->getFilePathHash();
    $this->buildFileManager('root', Config::$stagingDir . $filePathHash);
    // publish the file to trigger a revision
    $this->fileManager->publishFile();

    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->controller->getDB());
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
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $fileContents = '<?php ?>test contents<?php arst;?>';
    file_put_contents($filePath, $fileContents);
    $this->buildFileManager('bvisto', $filePath);
    $this->assertTrue($this->fileManager->saveRevision());

    $fileContents = '<?php ?>test contents<?php arst;?>more';
    file_put_contents($filePath, $fileContents);
    $this->buildFileManager('bvisto', $filePath);
    $this->assertTrue($this->fileManager->saveRevision());

    $this->authenticate('bvisto');

    $this->setUpController();

    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->controller->getDB());
    $this->assertSame(2, $revisionsAPI->getRevisionCount());

    $_POST['revisionNumber'] = 1;
    $_POST['restore'] = 1;

    $actual = $this->controller->handleRevisions($filePath);

    $this->assertNull($actual['value']['content']);

    $url = (new String($filePath))->addQueryString(['concert' => 'revisions', 'revisionsAction' => 'thankYou'])->buildUrl()->getValue();

    $this->assertSame(Config::RESTORED_MESSAGE, PageUtil::getSessionMessage($url));

    $filePathHash = $this->fileManager->getFilePathHash();
    $this->buildFileManager('root', Config::$stagingDir . $filePathHash);
    // publish the file to trigger a revision
    $this->fileManager->publishFile();


    $_POST['revisionsAction'] = 'undo';

    $actual = $this->controller->handleRevisions($filePath);

    $this->assertNull($actual['value']['content']);

    $url = (new String($filePath))->addQueryString(['concert' => 'revisions'])->buildUrl()->getValue();

    $this->assertSame(Config::UNDO_RESTORE_MESSAGE, PageUtil::getSessionMessage($url));

    $this->buildFileManager('root', Config::$stagingDir . $filePathHash);
    $this->fileManager->publishFile();

    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->controller->getDB());

    $this->assertSame(4, $revisionsAPI->getRevisionCount());

    $revThree = $revisionsAPI->getRevision(3);
    $this->assertSame('File restored', $revThree->getRevisionMessage());

    $revFour = $revisionsAPI->getRevision(4);
    $this->assertSame('File restoration undone', $revFour->getRevisionMessage());


    $this->unauthenticate();
    $this->destructDB();
    Config::$manageRevisionsAccessLevels = $origManageRevAccessLevels;
  }

  /**
   * @test
   */
  public function viewRecentActivityNothing()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->authenticate('bvisto');
    $_GET['concert'] = 'edit';
    $this->setUpController();

    $actual = $this->controller->viewRecentActivity();

    $this->assertContains('don\'t have any recent activity', $actual['content']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function viewRecentActivity()
  {
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $_SERVER['DOCUMENT_ROOT'] = '/cis/lib/';
    $this->set('Config', 'requiredDocRoot', '/cis/lib');
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', str_replace('/cis/lib/', '', self::$testFileDir), 'test']);

    $this->authenticate('bvisto');
    $_POST['saveAction'] = 'savePublicDraft';
    $this->setUpController();

    // save a draft
    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action', 'value'], array_keys($actual));

    // now view our recent activity
    $actual = $this->controller->viewRecentActivity();

    $this->assertNotContains('don\'t have any recent activity', $actual['content']);
    $this->assertContains('<table', $actual['content']);

    $this->unauthenticate();
    $this->destructDB();
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    $this->set('Config', 'requiredDocRoot', $docRoot);
  }

  /**
   * @test
   */
  public function dashboardNoSites()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->authenticate('bvisto');
    $this->setUpController();

    $actual = $this->controller->dashboard();

    $this->assertContains('don\'t have any recent activity', $actual['content']);
    $this->assertContains('Sites', $actual['content']);
    $this->assertContains('don\'t have access to any sites', $actual['content']);

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function dashboard()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', str_replace('/cis/lib/', '', self::$testFileDir), 'test']);

    $this->authenticate('bvisto');
    $this->setUpController();

    $actual = $this->controller->dashboard();

    $this->assertContains('don\'t have any recent activity', $actual['content']);
    $this->assertContains('Sites', $actual['content']);
    $this->assertNotContains('don\'t have access to any sites', $actual['content']);
    $this->assertContains('<table', $actual['content']);

    $this->unauthenticate();
    $this->destructDB();
  }
}