<?php
/**
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test;

use Gustavus\Concert\Utility,
  Gustavus\Doctrine\DBAL,
  Gustavus\Concert\Config;

/**
 * Class to test Utility class
 *
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class UtilityTest extends TestBase
{
  /**
   * Token for overrides
   *
   * @var array
   */
  private static $overrideToken;

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
   * Sets up environment for every test class
   *
   * @return void
   */
  public static function setUpBeforeClass()
  {
    self::$overrideToken = [];
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
   * @test
   */
  public function removeDocRootFromPath()
  {
    $this->assertNotEmpty($_SERVER['DOCUMENT_ROOT']);
    $filePath = $_SERVER['DOCUMENT_ROOT'] . 'index.php';

    $this->assertSame('/index.php', Utility::removeDocRootFromPath($filePath));
  }

  /**
   * @test
   */
  public function removeDocRootFromPathNothingToDo()
  {
    $this->assertNotEmpty($_SERVER['DOCUMENT_ROOT']);
    $this->assertNotSame('/cis/lib/', $_SERVER['DOCUMENT_ROOT']);
    $filePath = '/cis/lib/index.php';

    $this->assertSame('/cis/lib/index.php', Utility::removeDocRootFromPath($filePath));
  }

  /**
   * @test
   */
  public function addDocRootToPath()
  {
    $filePath = 'index.php';

    $this->assertNotEmpty($_SERVER['DOCUMENT_ROOT']);
    $this->assertSame($_SERVER['DOCUMENT_ROOT'] . 'index.php', Utility::addDocRootToPath($filePath));
  }

  /**
   * @test
   */
  public function addDocRootToPathAlreadyThere()
  {
    $this->assertNotEmpty($_SERVER['DOCUMENT_ROOT']);
    $filePath = $_SERVER['DOCUMENT_ROOT'] . 'index.php';
    $this->assertSame($_SERVER['DOCUMENT_ROOT'] . 'index.php', Utility::addDocRootToPath($filePath));
  }

  /**
   * @test
   */
  public function buildSharedSiteNavNote()
  {
    $note = Utility::buildSharedSiteNavNote('/billy/concert/', false);
    $this->assertContains(Config::EDITING_SHARED_SITE_NAV_NOTE_START, $note);
    $this->assertContains('/billy/concert/', $note);
  }

  /**
   * @test
   */
  public function buildSharedSiteNavNoteCreation()
  {
    $note = Utility::buildSharedSiteNavNote('/billy/concert/', true);
    $this->assertContains(Config::CREATE_SHARED_SITE_NAV_NOTE_START, $note);
    $this->assertContains('/billy/concert/', $note);
  }

  /**
   * @test
   */
  public function getRevisionsAPI()
  {
    $dbal = DBAL::getDBAL('testDB', $this->getDBH());
    $api = Utility::getRevisionsAPI('/cis/www/billy/concert/index.php', $dbal);
    $this->assertInstanceOf('\Gustavus\Revisions\API', $api);
  }

  /**
   * @test
   */
  public function getUploadLocationNull()
  {
    $this->assertNull(Utility::getUploadLocation());
  }

  /**
   * @test
   */
  public function getUploadLocation()
  {
    $this->buildDB();
    $baseSite = self::$testFileDir . 'billy/';
    $_SESSION['concertCMS']['currentParentSiteBase'] = $baseSite;

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', $baseSite, 'admin']);
    $this->authenticate('bvisto');

    $this->assertSame($baseSite . 'files/', Utility::getUploadLocation());

    // make sure media directory was created
    $this->buildFileManager('bvisto', $baseSite . 'files/media/');
    $this->fileManager->filePath = Config::$stagingDir . $this->fileManager->getFilepathHash();

    $expected = [[
      'destFilepath' => $baseSite . 'files/media/',
      'username'     => 'bvisto',
      'action'       => Config::CREATE_HTTPD_DIRECTORY_STAGE,
    ]];
    $this->assertSame($expected, $this->fileManager->getStagedFileEntry());

    // now for thumbs dir
    $this->buildFileManager('bvisto', $baseSite . 'files/thumbs/');
    $this->fileManager->filePath = Config::$stagingDir . $this->fileManager->getFilepathHash();

    $expected = [[
      'destFilepath' => $baseSite . 'files/thumbs/',
      'username'     => 'bvisto',
      'action'       => Config::CREATE_HTTPD_DIRECTORY_STAGE,
    ]];
    $this->assertSame($expected, $this->fileManager->getStagedFileEntry());

    // now make sure .htaccess file was inserted
    $this->buildFileManager('bvisto', $baseSite . 'files/.htaccess');
    $this->fileManager->filePath = Config::$stagingDir . $this->fileManager->getFilepathHash();

    $expected = [[
      'destFilepath' => $baseSite . 'files/.htaccess',
      'username'     => 'bvisto',
      'action'       => Config::PUBLISH_STAGE,
    ]];
    $this->assertSame($expected, $this->fileManager->getStagedFileEntry());

    $this->unauthenticate();
    $this->destructDB();
  }
}