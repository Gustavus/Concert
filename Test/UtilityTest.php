<?php
/**
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test;

use Gustavus\Concert\Utility,
  Gustavus\Doctrine\DBAL,
  Gustavus\Concert\Config,
  Gustavus\Concert\FileConfiguration,
  DateTime;

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
    $filePath = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'] . '/index.php');

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
    $this->assertSame(str_replace('//', '/', $_SERVER['DOCUMENT_ROOT'] . '/index.php'), Utility::addDocRootToPath($filePath));
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
    $this->buildFileManager('root', $baseSite . 'files/thumbs/');
    $this->fileManager->filePath = Config::$stagingDir . $this->fileManager->getFilepathHash();

    $expected = [[
      'destFilepath' => $baseSite . 'files/thumbs/',
      'username'     => 'bvisto',
      'action'       => Config::CREATE_HTTPD_DIRECTORY_STAGE,
    ]];
    $this->assertSame($expected, $this->fileManager->getStagedFileEntry());
    $this->assertTrue($this->fileManager->publishFile());
    $this->assertTrue(is_dir($baseSite . 'files/thumbs/'));

    // now make sure .htaccess file was inserted
    $this->buildFileManager('root', $baseSite . 'files/.htaccess');
    $this->fileManager->filePath = Config::$stagingDir . $this->fileManager->getFilepathHash();

    $expected = [[
      'destFilepath' => $baseSite . 'files/.htaccess',
      'username'     => 'bvisto',
      'action'       => Config::CREATE_HTTPD_DIR_HTACCESS_STAGE,
    ]];
    $this->assertSame($expected, $this->fileManager->getStagedFileEntry());
    $this->assertTrue($this->fileManager->publishFile());
    $this->assertTrue(file_exists($baseSite . 'files/.htaccess'));
    $this->assertTrue(is_link($baseSite . 'files/.htaccess'));
    $this->assertSame(Config::MEDIA_DIR_HTACCESS_TEMPLATE, readlink($baseSite . 'files/.htaccess'));

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function sharedDraftHasBeenEditedByCollaborator()
  {
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy/concert/', 'admin']);
    $this->authenticate('bvisto');

    $this->buildFileManager('bvisto', '/billy/concert/index.php');

    $configuration = new FileConfiguration(self::$indexConfigArray);
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);
    $draft = $this->fileManager->getDraft();

    $this->assertFalse(Utility::sharedDraftHasBeenEditedByCollaborator($draft));

    $draft['date'] = (new DateTime($draft['date']))->modify('-30 minutes')->format('y-m-d g:i:s');
    $this->assertTrue(Utility::sharedDraftHasBeenEditedByCollaborator($draft));
    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function getGroupForFile()
  {
    $filename = self::$testFileDir . 'index.php';
    file_put_contents($filename, self::$indexContents);
    chgrp($filename, 'www');

    $this->assertSame('www', Utility::getGroupForFile($filename));
  }

  /**
   * @test
   */
  public function getGroupForFileNonExistent()
  {
    $fileName = self::$testFileDir . 'test/index.php';

    $this->assertSame('www', Utility::getGroupForFile($fileName));
  }

  /**
   * @test
   */
  public function getGroupForFileFileNotExistingYet()
  {
    $filename = self::$testFileDir . 'arst.php';
    chgrp(self::$testFileDir, 'www');

    $this->assertSame('www', Utility::getGroupForFile($filename));
  }
}