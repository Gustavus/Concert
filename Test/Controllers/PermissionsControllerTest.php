<?php
/**
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test\Controllers;

use Gustavus\Test\TestObject,
  Gustavus\Concert\Test\TestBase,
  Gustavus\Concert\Controllers\PermissionsController,
  Gustavus\Concert\PermissionsManager,
  Gustavus\Concert\Config,
  Gustavus\Concert\FileManager,
  Gustavus\Doctrine\DBAL,
  DateTime;

/**
 * Test controller for PermissionsController
 *
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class PermissionsControllerTestController extends PermissionsController
{
  /**
   * @var PDO connection
   */
  protected $pdo;

  /**
   * Class constructor
   */
  public function __construct()
  {
    // use our test db connection
    $this->pdo = TestBase::$pdo;
  }

  /**
   * Gets Doctrine's DBAL connection for Concert
   *
   * @return \Doctrine\DBAL\Connection
   */
  protected function getDB()
  {
    return $this->getDBAL(Config::DB, $this->pdo);
  }

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
   * overloads redirect so it doesn't try to redirect when called
   * @param  string $path
   * @param  integer $statusCode Redirection status code
   * @return void
   */
  protected function redirectWithMessage($path = '/', $message = '', $statusCode = 303)
  {
    $_POST = [];
    return ['redirect' => $path, 'message' => $message];
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
}

/**
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class PermissionsControllerTest extends TestBase
{
  /**
   * PermissionsController
   *
   * @var PermissionsController
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
    self::$overrideToken['renderAccessDenied'] = override_method('\Gustavus\Utility\PageUtil', 'renderAccessDenied', function() {
          // just return the contents of the file and don't evaluate it.
          return 'renderAccessDenied';
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
    $this->controller = new TestObject(new PermissionsControllerTestController);

    $this->controller->dbal = DBAL::getDBAL('testDB', $this->getDBH());
  }

  /**
   * @test
   */
  public function showSitesNoPermissions()
  {
    $this->constructDB(['Sites', 'Permissions']);

    $this->setUpController();
    $actual = $this->controller->showSites();

    $this->assertContains('renderAccessDenied', $actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function showSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    PermissionsManager::saveUserPermissions('bvisto', '/billy', Config::SUPER_USER);

    $this->authenticate('bvisto');
    $this->setUpController();
    $actual = $this->controller->showSites();

    $this->assertContains('<table', $actual['content']);
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function createSiteNoPermissions()
  {
    $this->constructDB(['Sites', 'Permissions']);

    $this->setUpController();
    $actual = $this->controller->createSite();

    $this->assertContains('renderAccessDenied', $actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function createSite()
  {
    $this->constructDB(['Sites', 'Permissions']);
    PermissionsManager::saveUserPermissions('bvisto', '/billy', Config::SUPER_USER);

    $this->authenticate('bvisto');

    $this->setUpController();
    $actual = $this->controller->createSite();

    $this->assertContains('<form', $actual['content']);
    $this->assertContains('xcluded', $actual['content']);

    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function createSitePostSiteExists()
  {
    $this->constructDB(['Sites', 'Permissions']);
    PermissionsManager::saveUserPermissions('bvisto', '/billy', Config::SUPER_USER);

    $this->authenticate('bvisto');
    $_POST = [
      'editsite' => [
        'siteinfo' => [
          'siteroot' => 'billy'
        ],
        'excludedfilessection' => [
          'excludedfile' => [
            [
              'file' => 'test.php'
            ],
          ],
        ],
        'peoplesection' => [
          'personpermissions' => [
            [
              'username' => 'jerry',
              'accesslevel' => [
                'siteAdmin',
                'editor',
              ],
              'includedfiles'  => 'include.php',
              'excludedfiles'  => 'private.php',
              'expirationdate' => (new DateTime('+1 day'))->format('Y-m-d'),
            ],
          ],
        ],
      ],
    ];

    $_SERVER['REQUEST_METHOD'] = 'POST';

    $this->setUpController();
    $actual = $this->controller->createSite();

    $this->assertContains('already exists', $actual['content']);

    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function createSitePost()
  {
    $this->constructDB(['Sites', 'Permissions']);
    PermissionsManager::saveUserPermissions('bvisto', '/billy', Config::SUPER_USER);

    $this->authenticate('bvisto');
    $_POST = [
      'editsite' => [
        'siteinfo' => [
          'siteroot' => 'arstarst'
        ],
        'excludedfilessection' => [
          'excludedfile' => [
            [
              'file' => 'test.php'
            ],
          ],
        ],
        'peoplesection' => [
          'personpermissions' => [
            [
              'username' => 'jerry',
              'accesslevel' => [
                'siteAdmin',
                'editor',
              ],
              'includedfiles'  => 'include.php',
              'excludedfiles'  => 'private.php',
              'expirationdate' => (new DateTime('+1 day'))->format('Y-m-d'),
            ],
          ],
        ],
      ],
    ];

    $_SERVER['REQUEST_METHOD'] = 'POST';

    $this->setUpController();
    $actual = $this->controller->createSite();

    $this->assertSame(['redirect'], array_keys($actual));

    $perms = PermissionsManager::getAllPermissionsForUser('jerry');

    $this->assertTrue(isset($perms['/arstarst']));
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function editSiteNoPermissions()
  {
    $this->constructDB(['Sites', 'Permissions']);

    $this->setUpController();
    $actual = $this->controller->editSite(['site' => 1]);

    $this->assertContains('renderAccessDenied', $actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function editSite()
  {
    $this->constructDB(['Sites', 'Permissions']);
    PermissionsManager::saveUserPermissions('bvisto', '/billy', Config::SUPER_USER);

    $this->authenticate('bvisto');

    $this->setUpController();
    $actual = $this->controller->editSite(['site' => 1]);

    $this->assertContains('<form', $actual['content']);
    $this->assertContains('xcluded', $actual['content']);

    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function editSitePost()
  {
    $this->constructDB(['Sites', 'Permissions']);
    PermissionsManager::saveUserPermissions('bvisto', '/billy', Config::SUPER_USER);

    $this->authenticate('bvisto');
    $_POST = [
      'editsite' => [
        'excludedfilessection' => [
          'excludedfile' => [
            [
              'file' => 'test.php'
            ],
          ],
        ],
        'peoplesection' => [
          'personpermissions' => [
            [
              'username' => 'jerry',
              'accesslevel' => [
                'siteAdmin',
                'editor',
              ],
              'includedfiles'  => 'include.php',
              'excludedfiles'  => 'private.php',
              'expirationdate' => (new DateTime('+1 day'))->format('Y-m-d'),
            ],
          ],
        ],
      ],
    ];

    $_SERVER['REQUEST_METHOD'] = 'POST';

    $this->setUpController();
    $actual = $this->controller->editSite(['site' => 1]);

    $this->assertSame(['redirect'], array_keys($actual));

    $perms = PermissionsManager::getAllPermissionsForUser('jerry');

    $this->assertTrue(isset($perms['/billy']));
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function editSitePostExpired()
  {
    $this->constructDB(['Sites', 'Permissions']);
    PermissionsManager::saveUserPermissions('bvisto', '/billy', Config::SUPER_USER);

    $this->authenticate('bvisto');
    $_POST = [
      'editsite' => [
        'excludedfilessection' => [
          'excludedfile' => [
            [
              'file' => 'test.php'
            ],
          ],
        ],
        'peoplesection' => [
          'personpermissions' => [
            [
              'username' => 'jerry',
              'accesslevel' => [
                'siteAdmin',
                'editor',
              ],
              'includedfiles'  => 'include.php',
              'excludedfiles'  => 'private.php',
              'expirationdate' => (new DateTime('-1 day'))->format('Y-m-d'),
            ],
          ],
        ],
      ],
    ];

    $_SERVER['REQUEST_METHOD'] = 'POST';

    $this->setUpController();
    $actual = $this->controller->editSite(['site' => 1]);

    $this->assertSame(['redirect'], array_keys($actual));

    $perms = PermissionsManager::getAllPermissionsForUser('jerry');

    // these permissions should be expired.
    $this->assertFalse(isset($perms['/billy']));
    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function editSitePostSwappingUser()
  {
    $this->constructDB(['Sites', 'Permissions']);
    PermissionsManager::saveUserPermissions('bvisto', '/billy', Config::SUPER_USER);
    PermissionsManager::saveUserPermissions('jerry', '/billy', Config::SITE_EDITOR_ACCESS_LEVEL);

    $this->authenticate('bvisto');
    $_POST = [
      'editsite' => [
        'excludedfilessection' => [
          'excludedfile' => [
            [
              'file' => 'test.php'
            ],
          ],
        ],
        'peoplesection' => [
          'personpermissions' => [
            [
              'username' => 'jholcom2',
              'accesslevel' => [
                'siteAdmin',
                'editor',
              ],
              'includedfiles'  => 'include.php',
              'excludedfiles'  => 'private.php',
              'expirationdate' => (new DateTime('+1 day'))->format('Y-m-d'),
            ],
          ],
        ],
      ],
    ];

    $_SERVER['REQUEST_METHOD'] = 'POST';

    $this->setUpController();
    $actual = $this->controller->editSite(['site' => 1]);

    $this->assertSame(['redirect'], array_keys($actual));

    $perms = PermissionsManager::getAllPermissionsForUser('jerry');
    $this->assertFalse(isset($perms['/billy']));

    $perms = PermissionsManager::getAllPermissionsForUser('jholcom2');
    $this->assertTrue(isset($perms['/billy']));

    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function deleteSiteNoPermissions()
  {
    $this->constructDB(['Sites', 'Permissions']);

    $this->setUpController();
    $actual = $this->controller->deleteSite(['site' => 1]);

    $this->assertContains('renderAccessDenied', $actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function deleteSite()
  {
    $this->constructDB(['Sites', 'Permissions']);
    PermissionsManager::saveUserPermissions('bvisto', '/billy', Config::SUPER_USER);

    $this->authenticate('bvisto');

    $this->setUpController();
    $actual = $this->controller->deleteSite(['site' => 1]);

    $this->assertContains('<form', $actual['content']);
    $this->assertContains('delete the site', $actual['content']);

    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function deleteSitePost()
  {
    $this->constructDB(['Sites', 'Permissions']);
    PermissionsManager::saveUserPermissions('bvisto', '/billy', Config::SUPER_USER);

    $this->authenticate('bvisto');
    $_POST = [
      'siteId' => '1',
      'deleteAction' => 'confirmDelete',
    ];

    $_SERVER['REQUEST_METHOD'] = 'POST';

    $this->setUpController();
    $actual = $this->controller->deleteSite(['site' => 1]);

    $this->assertSame(['redirect'], array_keys($actual));

    $perms = PermissionsManager::getAllPermissionsForUser('bvisto');

    $this->assertFalse(isset($perms['/billy']));

    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function userSearchNoPermissions()
  {
    $this->constructDB(['Sites', 'Permissions']);

    $this->setUpController();
    $actual = $this->controller->userSearch(['site' => 1]);

    $this->assertContains('renderAccessDenied', $actual);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userSearch()
  {
    $this->constructDB(['Sites', 'Permissions']);
    PermissionsManager::saveUserPermissions('bvisto', '/billy', Config::SUPER_USER);

    $this->authenticate('bvisto');

    $this->setUpController();
    $actual = $this->controller->userSearch(['site' => 1]);

    $this->assertContains('<form', $actual['content']);
    $this->assertContains('username', $actual['content']);

    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function userSearchPostNoSites()
  {
    $this->constructDB(['Sites', 'Permissions']);
    PermissionsManager::saveUserPermissions('bvisto', '/billy', Config::SUPER_USER);

    $this->authenticate('bvisto');
    $_POST = [
      'usersearch' => [
        'username' => 'jerry'
      ],
    ];

    $_SERVER['REQUEST_METHOD'] = 'POST';

    $this->setUpController();
    $actual = $this->controller->userSearch(['site' => 1]);

    $this->assertContains('<form', $actual['content']);
    $this->assertContains('username', $actual['content']);

    $this->assertContains('doesn\'t belong to any sites', $actual['content']);

    $this->destructDB();
    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function userSearchPost()
  {
    $this->constructDB(['Sites', 'Permissions']);
    PermissionsManager::saveUserPermissions('bvisto', '/billy', Config::SUPER_USER);
    PermissionsManager::saveUserPermissions('jerry', '/billy', Config::SUPER_USER);

    $this->authenticate('bvisto');
    $_POST = [
      'usersearch' => [
        'username' => 'jerry'
      ],
    ];

    $_SERVER['REQUEST_METHOD'] = 'POST';

    $this->setUpController();
    $actual = $this->controller->userSearch(['site' => 1]);

    $this->assertContains('<form', $actual['content']);
    $this->assertContains('username', $actual['content']);

    $this->assertContains('/billy', $actual['content']);

    $this->destructDB();
    $this->unauthenticate();
  }
}