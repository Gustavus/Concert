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
  Gustavus\Doctrine\DBAL,
  Gustavus\FormBuilderMK2\ElementRenderers\ElementRenderer,
  Gustavus\Concourse\Test\RouterTestUtil,
  Gustavus\Concert\FileManager,
  Gustavus\Utility\PageUtil,
  Gustavus\Concourse\RoutingUtil;

/**
 * Test controller for MainController
 *
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class MainControllerTestController extends MainController
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
    $_POST = null;
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
          'Gustavus\Concert\Controllers\MenuController' => 'Gustavus\Concert\Test\Controllers\MenuControllerTestController']);
  }
}

/**
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
    $this->controller = new TestObject(new MainControllerTestController);

    $this->controller->dbal = DBAL::getDBAL('testDB', $this->getDBH());
  }

  /**
   * @test
   */
  public function editNoPermission()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);

    $this->authenticate('testUser');

    $this->setUpController();

    $this->assertFalse($this->controller->edit('billy/concert/index.php'));

    $this->assertContains('don\'t have access', PageUtil::getSessionMessage($_SERVER['REQUEST_URI']));
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editNoLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', 'billy/concert/', 'test']);

    $this->buildFileManager('bvisto', 'billy/concert/index.php');
    $this->assertTrue($this->fileManager->acquireLock());

    $this->authenticate('testUser');

    $this->setUpController();

    $this->assertFalse($this->controller->edit('billy/concert/index.php'));

    $this->assertContains('unable to create a lock', PageUtil::getSessionMessage($_SERVER['REQUEST_URI']));
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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->buildFileManager('testUser', $filePath);
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController();

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $this->controller->edit($filePath));

    $this->assertEmpty(PageUtil::getSessionMessage($_SERVER['REQUEST_URI']));

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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', $filePath);
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);
    $this->fileManager->stopEditing();

    $this->authenticate('testUser');

    $this->setUpController();

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $this->controller->edit($filePath));

    $this->assertContains('draft open for this page', PageUtil::getSessionMessage($_SERVER['REQUEST_URI']));

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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');

    $this->setUpController();

    $this->assertContains(trim(self::$indexConfigArray['content'][1]), $this->controller->edit($filePath));

    $this->assertEmpty(PageUtil::getSessionMessage($_SERVER['REQUEST_URI']));

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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');

    $this->setUpController();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $this->assertTrue($this->controller->edit($filePath));

    $this->buildFileManager('testUser', $filePath);

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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'nonPub']);

    $this->authenticate('testUser');

    $this->setUpController();

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $this->assertTrue($this->controller->edit($filePath));

    $this->buildFileManager('testUser', $filePath);

    $modifiedFile = file_get_contents(Config::$draftDir . $this->fileManager->getDraftFileName('testUser'));

    $this->assertContains('This is some edited html content', $modifiedFile);

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

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');

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

    $this->authenticate('testUser');

    $this->setUpController();

    $this->assertFalse($this->controller->createNewPage($filePath));

    $this->assertContains('don\'t have access', PageUtil::getSessionMessage($_SERVER['REQUEST_URI']));
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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', $filePath);
    // create a lock on the file
    $this->assertTrue($this->fileManager->acquireLock());

    $this->authenticate('testUser');

    $this->setUpController();

    $this->assertFalse($this->controller->createNewPage($filePath));

    $this->assertContains('unable to create a lock', PageUtil::getSessionMessage($_SERVER['REQUEST_URI']));
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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->buildFileManager('testUser', $filePath, Config::TEMPLATE_PAGE);
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');

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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');

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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $this->setUpController();

    $this->assertTrue($this->controller->createNewPage($filePath));

    $this->buildFileManager('testUser', $filePath);

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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->authenticate('testUser');

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['1' => '<p>This is some edited html content</p>'];

    $this->setUpController();

    $this->assertTrue($this->controller->createNewPage($filePath, $fromFilePath));

    $this->buildFileManager('testUser', $filePath);

    $modifiedFile = file_get_contents(Config::$stagingDir . $this->fileManager->getFilePathHash());

    $this->assertContains($_POST['1'], $modifiedFile);

    $this->unauthenticate();
    $this->destructDB();
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

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->buildFileManager('testUser', $filePath, Config::TEMPLATE_PAGE);
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']);

    $this->authenticate('testUser');
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

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->buildFileManager('testUser', $filePath, Config::TEMPLATE_PAGE);
    //$this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']);
    $this->fileManager->acquireLock();

    $this->authenticate('testUser');
    $this->setUpController();

    $this->assertSame(['action' => 'none', 'value' => true], $this->controller->stopEditing($filePath));

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

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->buildFileManager('testUser', $filePath, Config::TEMPLATE_PAGE);
    $draftName = basename($this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']));

    $this->authenticate('testUser');
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


    $this->buildFileManager('bvisto', $filePath, Config::TEMPLATE_PAGE);
    $draftName = basename($this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']));

    $this->authenticate('bvisto');
    $_GET['concert'] = 'stopEditing';
    $_SERVER['REQUEST_URI'] = '/billy/concert/arst/arst/index.php';
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
  public function handleNewPageRequest()
  {
    $origNonCreationAccessLevels = Config::$nonCreationAccessLevels;
    Config::$nonCreationAccessLevels = ['noCreate'];

    $filePath = self::$testFileDir . 'index.php';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'noCreate']);

    $this->buildFileManager('bvisto', $filePath, Config::TEMPLATE_PAGE);
    $draftName = basename($this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']));

    $this->authenticate('bvisto');
    $_GET['concert'] = 'edit';
    $this->setUpController();

    $actual = $this->controller->handleNewPageRequest($filePath);

    $this->assertSame(['action', 'value'], array_keys($actual));
    $this->assertContains('Sub-Title', $actual['value']);

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
  }

  /**
   * @test
   */
  public function moshStopEditingFile()
  {
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $_SERVER['DOCUMENT_ROOT'] = '/cis/lib/';
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', str_replace('/cis/lib/', '', self::$testFileDir), 'test']);

    $this->buildFileManager('bvisto', $filePath, Config::TEMPLATE_PAGE);
    $draftName = basename($this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']));

    $this->authenticate('bvisto');
    $_GET['concert'] = 'stopEditing';
    $this->setUpController();

    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action' => 'none', 'value' => true], $actual);

    $this->unauthenticate();
    $this->destructDB();
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
  }

  /**
   * @test
   */
  public function moshNothingWithLock()
  {
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $_SERVER['DOCUMENT_ROOT'] = '/cis/lib/';
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', str_replace('/cis/lib/', '', self::$testFileDir), 'test']);

    $this->buildFileManager('bvisto', $filePath, Config::TEMPLATE_PAGE);
    $this->assertTrue($this->fileManager->acquireLock());

    $this->authenticate('bvisto');
    $_GET['concert'] = '';
    $this->setUpController();

    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action' => 'none'], $actual);

    $this->assertContains('process of editing this page but left', PageUtil::getSessionMessage($_SERVER['REQUEST_URI']));

    $this->unauthenticate();
    $this->destructDB();
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
  }

  /**
   * @test
   */
  public function moshNothing()
  {
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $_SERVER['DOCUMENT_ROOT'] = '/cis/lib/';
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', str_replace('/cis/lib/', '', self::$testFileDir), 'test']);

    $this->authenticate('bvisto');
    $_GET['concert'] = '';
    $this->setUpController();

    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action' => 'none'], $actual);

    $this->assertEmpty(PageUtil::getSessionMessage($_SERVER['REQUEST_URI']));

    $this->unauthenticate();
    $this->destructDB();
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
  }

  /**
   * @test
   */
  public function moshViewPublicDraft()
  {
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $_SERVER['DOCUMENT_ROOT'] = '/cis/lib/';
    $filePath = self::$testFileDir . 'index.php';
    file_put_contents($filePath, self::$indexContents);

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', str_replace('/cis/lib/', '', self::$testFileDir), 'test']);

    $this->buildFileManager('bvisto', $filePath, Config::TEMPLATE_PAGE);
    $draftName = basename($this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']));

    $_GET['concert'] = 'viewDraft';
    $_GET['concertDraft'] = $draftName;
    $this->setUpController();

    $actual = $this->controller->mosh($filePath);

    $this->assertSame(['action', 'value'], array_keys($actual));
    $this->assertContains('Sub-Title', $actual['value']);

    $this->destructDB();
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
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

    $this->assertFalse($this->controller->handleMoshRequest());
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
   * test
   * @todo  add more tests for userIsEditingPublicDraft. Also for viewing public draft
   */
  public function userIsEditingPublicDraftExtraQueryParams()
  {
    $this->setUpController();
    $requestURI = $this->controller->buildUrl('editDraft', ['draftName' => 'testFile']) . '?concert=test';

    $this->assertTrue($this->controller->userIsEditingPublicDraft($requestURI));
  }

}