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
}