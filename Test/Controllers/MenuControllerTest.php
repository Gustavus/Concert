<?php
/**
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test\Controllers;

use Gustavus\Test\TestObject,
  Gustavus\Concert\Test\TestBase,
  Gustavus\Concert\Controllers\MenuController,
  Gustavus\Concert\Test\Controllers\MenuControllerTestController,
  Gustavus\Concert\FileConfiguration,
  Gustavus\Concert\Config,
  Gustavus\Concert\FileManager;

/**
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 *
 * @todo remove skipping tests
 */
class MenuControllerTest extends TestBase
{
  /**
   * MenuController
   *
   * @var MenuController
   */
  private $controller;

  /**
   * File path to test building a menu for
   *
   * @var string
   */
  private $filePath = '/cis/www/billy/concert/newPage.php';

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
  }

  /**
   * Sets up the controller and injects a test FileManager
   * @param FileManager $fileManager
   * @return  void
   */
  private function setUpController($fileManager = null)
  {
    if ($fileManager === null) {
      $this->buildFileManager('testUser', $this->filePath);
      $fileManager = $this->fileManager;
    }
    $this->controller = new TestObject(new MenuControllerTestController);

    $this->controller->pdo = $this->getDBH();

    $this->controller->fileManager = $fileManager;

    // @todo remove this if not needed
    // $this->controller->em  = $this->getEM();
    // $this->controller->pdo = $this->getDBH();
    // $this->controller->setCurrentEM($this->controller->em);
  }

  /**
   * @test
   */
  public function renderMenu()
  {
  }

  /**
   * @test
   */
  public function analyzeReferer()
  {
    $this->setUpController();
    $this->controller->analyzeReferer();

    $expectedQuery = ['concert' => 'edit'];
    $this->assertSame($expectedQuery, $this->controller->queryParams);
    $this->assertSame($this->filePath, $this->controller->filePath);
  }

  /**
   * @test
   */
  public function addMenuItem()
  {
    $item = ['id' => 'arst', 'url' => 'test'];

    $item2 = ['id' => 'test', 'url' => 'test2'];

    $this->setUpController();
    $this->controller->addMenuItem($item2, 'menu', 5);
    $this->controller->addMenuItem($item2, 'menu', 0);
    $this->controller->addMenuItem($item, 'concert', 20);
    $this->controller->addMenuItem($item2, 'actionButtons', 0);

    $expected = [
      'menu' => [
        'weight' => 2,
        'items' => [
          [
            'id' => 'test',
            'url' => 'test2',
            'weight' => 5,
          ],
          [
            'id' => 'test',
            'url' => 'test2',
            'weight' => 0,
          ],
        ],
        'type' => 'menu',
      ],
      'concert' => [
        'weight' => 0,
        'items' => [
          [
            'id' => 'arst',
            'url' => 'test',
            'weight' => 20,
          ],
        ],
        'type' => 'menu',
      ],
      'actionButtons' => [
        'weight' => 0,
        'items' => [
          [
            'id' => 'test',
            'url' => 'test2',
            'weight' => 0,
          ],
        ],
        'type' => 'buttons',
      ],
    ];

    $this->assertSame($expected, $this->controller->menu);
  }

  /**
   * @test
   */
  public function sortMenu()
  {
    $item = ['id' => 'arst', 'url' => 'test'];

    $item2 = ['id' => 'test', 'url' => 'test2'];

    $this->setUpController();
    $this->controller->addMenuItem($item2, 'menu', 5);
    $this->controller->addMenuItem($item2, 'menu', 0);
    $this->controller->addMenuItem($item, 'concert', 20);
    $this->controller->addMenuItem($item2, 'actionButtons', 0);

    $preSortedMenu = $this->controller->menu;
    $this->controller->sortMenu();
    $sortedMenu = $this->controller->menu;
    $this->assertNotSame($preSortedMenu, $sortedMenu);

    $expected = [
      'actionButtons' => [
        'weight' => 0,
        'items' => [
          [
            'id' => 'test',
            'url' => 'test2',
            'weight' => 0,
          ],
        ],
        'type' => 'buttons',
      ],
      'concert' => [
        'weight' => 0,
        'items' => [
          [
            'id' => 'arst',
            'url' => 'test',
            'weight' => 20,
          ],
        ],
        'type' => 'menu',
      ],
      'menu' => [
        'weight' => 2,
        'items' => [
          [
            'id' => 'test',
            'url' => 'test2',
            'weight' => 0,
          ],
          [
            'id' => 'test',
            'url' => 'test2',
            'weight' => 5,
          ],
        ],
        'type' => 'menu',
      ],
    ];
    $this->assertSame($expected, $sortedMenu);
  }

  /**
   * @test
   */
  public function addDraftButtons()
  {
    $this->markTestSkipped('We need to finish menus.');
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController($this->fileManager);

    $this->controller->addDraftButtons($this->fileManager);

    $expected = [
      [
        'id'   => 'addUsersToDraft',
        'text' => 'Add users to your draft',
        'url'  => 'https://bart.gac.edu/usr/bin/?concert=addUsers&concertDraft=f0338789c9134c0969da1d4e19e95e9b',
        'classes' => 'green',

      ],
      [
        'id'   => 'viewDrafts',
        'text' => 'View all drafts',
        'url'  => 'https://bart.gac.edu/usr/bin/?concert=viewDraft',
      ],
    ];

    $this->assertSame($expected, $this->controller->menu);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function addEditButtons()
  {
    $this->markTestSkipped('We need to finish menus.');
    $_SERVER['SCRIPT_NAME'] = '/concert/testing.php';
    $_SERVER['HTTP_REFERER'] = 'https://beta.gac.edu/billy/concert/newPage.php?concert';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController($this->fileManager);

    $this->controller->analyzeReferer();
    $this->controller->addRefererParamsToGet();
    $this->controller->addEditButtons($this->fileManager);

    $expected = [
      [
        'id'       => 'deletePage',
        'text'     => 'Delete Page',
        'url'      => 'https://bart.gac.edu/billy/concert/newPage.php?concert=delete',
        'thickbox' => true,
        'classes'  => 'red',
      ],
      [
        'id'       => 'startEditing',
        'text'     => 'Edit Page',
        'url'      => 'https://' . $_SERVER['HOSTNAME'] . '/billy/concert/newPage.php?concert=edit',
        'thickbox' => false,
        'classes'  => 'blue',
      ],
    ];

    $this->assertSame($expected, $this->controller->menu);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function addEditButtonsNotAllowedToEdit()
  {
    $this->markTestSkipped('We need to finish menus.');
    $_SERVER['SCRIPT_NAME'] = '/concert/testing.php';
    $_SERVER['HTTP_REFERER'] = 'https://beta.gac.edu/billy/concert/newPage.php?concert';
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', 'billy/arst/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController($this->fileManager);

    $this->controller->analyzeReferer();
    $this->controller->addRefererParamsToGet();
    $this->controller->addEditButtons($this->fileManager);

    $this->assertNull($this->controller->menu);
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function addEditButtonsStopEditing()
  {
    $this->markTestSkipped('We need to finish menus.');
    $_SERVER['SCRIPT_NAME'] = '/concert/testing.php';
    $_SERVER['HTTP_REFERER'] = 'https://beta.gac.edu/billy/concert/newPage.php?concert=edit';

    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', '/billy/concert/', 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->authenticate('testUser');

    $this->setUpController($this->fileManager);

    $this->controller->analyzeReferer();
    $this->controller->addRefererParamsToGet();
    $this->controller->addEditButtons($this->fileManager);

    $expected = [
      [
        'id'       => 'deletePage',
        'text'     => 'Delete Page',
        'url'      => 'https://bart.gac.edu/billy/concert/newPage.php?concert=delete',
        'thickbox' => true,
        'classes'  => 'red',
      ],
      [
        'id'       => 'stopEditing',
        'text'     => 'Stop Editing',
        'url'      => 'https://' . $_SERVER['HOSTNAME'] . '/billy/concert/newPage.php?concert=stopEditing',
        'thickbox' => false,
        'classes'  => 'red',
      ],
    ];

    $this->assertSame($expected, $this->controller->menu);
    $this->unauthenticate();
    $this->destructDB();
  }
}