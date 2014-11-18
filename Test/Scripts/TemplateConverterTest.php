<?php
/**
 * @package  Concert
 * @subpackage Test\Scripts
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test;

use Gustavus\Concert\Scripts\TemplateConverter,
  Gustavus\Test\TestObject;

/**
 * Class to test TemplateConverter script
 *
 * @package  Concert
 * @subpackage Test\Scripts
 * @author  Billy Visto
 */
class TemplateConverterTest extends TestBase
{
  /**
   * Path to an old template file
   */
  const TEMPLATE_FILE_DIR = '/cis/lib/Gustavus/Concert/Test/Scripts/pages/';

  /**
   * TemplateConverter object to test on
   *
   * @var TemplateConverter
   */
  private $templateConverter;

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
    unset($this->templateConverter);
    parent::tearDown();
  }

  /**
   * Sets up the converter for testing
   *
   * @param string $filePath Path to the file to test
   */
  private function setUpConverter($filePath)
  {
    $this->templateConverter = new TestObject(new TemplateConverter(self::TEMPLATE_FILE_DIR . $filePath));
  }

  /**
   * @test
   */
  public function getFirstPHPBlock()
  {
    $this->setUpConverter('oldTemplate.php');
    $prefs = $this->templateConverter->getFirstPHPBlock();

    $expected = '<?php
$templatePreferences  = array(
  \'localNavigation\'   => TRUE,
  \'auxBox\'        => false,
  \'templateRevision\'    => 1,
//  \'focusBoxColumns\'   => 10,
  \'bannerDirectory\'   => \'alumni\',
//  \'view\'          => \'template/views/general.html\',
);

require_once \'template/request.class.php\';
require_once \'rssgrabber/rssgrabber.class.php\';
require_once \'/cis/www/calendar/classes/puller.class.php\';

use Gustavus\SocialMedia\SocialMedia,
    Gustavus\Resources\Resource,
    Gustavus\TwigFactory\TwigFactory,
    Gustavus\Utility\String as GACString;
?>';

    $this->assertSame($expected, $prefs);
  }

  /**
   * @test
   */
  public function isPageTemplated()
  {
    $this->setUpConverter('oldTemplate.php');
    $this->assertTrue($this->templateConverter->isPageTemplated());
  }

  /**
   * @test
   */
  public function isPageTemplatedFalse()
  {
    $this->setUpConverter('expectedTemplate.php');
    $this->assertFalse($this->templateConverter->isPageTemplated());
  }

  /**
   * @test
   */
  public function extractSection()
  {
    $this->setUpConverter('oldTemplate.php');
    $actual = $this->templateConverter->extractSection('Head');
    $expected = '<link rel="stylesheet" href="<?php echo Resource::renderCSS(array(\'path\' => \'/alumni/css/homepage.css\', \'version\' => time()), false, true); ?>" />';
    $this->assertSame($expected, $actual);
  }

  /**
   * @test
   */
  public function convertUseStatementBuilderExists()
  {
    $this->setUpConverter('oldTemplate.php');

    $useStatement = '
use Gustavus\SocialMedia\SocialMedia,
    Gustavus\Resources\Resource,
    Gustavus\TwigFactory\TwigFactory,
    Gustavus\Utility\String as GACString,
    Gustavus\TemplateBuilder\Builder;
';

    $expected = '
use Gustavus\SocialMedia\SocialMedia,
    Gustavus\Resources\Resource,
    Gustavus\TwigFactory\TwigFactory,
    Gustavus\Utility\String as GACString,
    Gustavus\TemplateBuilder\Builder;

Builder::init();
';

    $actual = $this->templateConverter->convertUseStatement($useStatement);

    $this->assertSame($expected, $actual);
  }

  /**
   * @test
   */
  public function convertUseStatementBuilderExistsAsAlias()
  {
    $this->setUpConverter('oldTemplate.php');

    $useStatement = '
use Gustavus\SocialMedia\SocialMedia,
    Gustavus\Resources\Resource,
    Gustavus\TwigFactory\TwigFactory,
    Gustavus\Utility\String as GACString,
    Gustavus\TemplateBuilder\Builder as GACBuilder;
';

    $expected = '
use Gustavus\SocialMedia\SocialMedia,
    Gustavus\Resources\Resource,
    Gustavus\TwigFactory\TwigFactory,
    Gustavus\Utility\String as GACString,
    Gustavus\TemplateBuilder\Builder as GACBuilder;

GACBuilder::init();
';

    $actual = $this->templateConverter->convertUseStatement($useStatement);
    $this->assertSame($expected, $actual);
  }

  /**
   * @test
   */
  public function convertUseStatement()
  {
    $this->setUpConverter('oldTemplate.php');

    $useStatement = '
use Gustavus\SocialMedia\SocialMedia,
    Gustavus\Resources\Resource,
    Gustavus\TwigFactory\TwigFactory,
    Gustavus\Utility\String as GACString;
';

    $expected = '
use Gustavus\SocialMedia\SocialMedia,
    Gustavus\Resources\Resource,
    Gustavus\TwigFactory\TwigFactory,
    Gustavus\Utility\String as GACString,
    Gustavus\TemplateBuilder\Builder;

Builder::init();
';

    $actual = $this->templateConverter->convertUseStatement($useStatement);

    $this->assertSame($expected, $actual);
  }

  /**
   * @test
   */
  public function convertFirstPHPBlock()
  {
    $this->setUpConverter('oldTemplate.php');
    $actual = $this->templateConverter->convertFirstPHPBlock();

    $expected = '<?php
$templatePreferences  = array(
  \'localNavigation\'   => TRUE,
  \'auxBox\'        => false,
  \'templateRevision\'    => 1,
//  \'focusBoxColumns\'   => 10,
  \'bannerDirectory\'   => \'alumni\',
//  \'view\'          => \'template/views/general.html\',
);

require_once \'rssgrabber/rssgrabber.class.php\';
require_once \'/cis/www/calendar/classes/puller.class.php\';

use Gustavus\SocialMedia\SocialMedia,
    Gustavus\Resources\Resource,
    Gustavus\TwigFactory\TwigFactory,
    Gustavus\Utility\String as GACString,
    Gustavus\TemplateBuilder\Builder;

Builder::init();

$templateBuilderProperties = [];
ob_start();
?>';

    $this->assertSame($expected, $actual);
  }

  /**
   * @test
   */
  public function convertFirstPHPBlockNoUseStament()
  {
    $this->setUpConverter('oldTemplateNoUseStatement.php');
    $actual = $this->templateConverter->convertFirstPHPBlock();

    $expected = '<?php
$templatePreferences  = array(
  \'localNavigation\'   => TRUE,
  \'auxBox\'        => false,
  \'templateRevision\'    => 1,
//  \'focusBoxColumns\'   => 10,
  \'bannerDirectory\'   => \'alumni\',
//  \'view\'          => \'template/views/general.html\',
);

require_once \'rssgrabber/rssgrabber.class.php\';
require_once \'/cis/www/calendar/classes/puller.class.php\';

use Gustavus\TemplateBuilder\Builder;

Builder::init();

$templateBuilderPropertiesArray = [];
ob_start();
?>';

    $this->assertSame($expected, $actual);
  }

  /**
   * @test
   */
  public function convert()
  {
    $this->setUpConverter('oldTemplate.php');
    $actual = $this->templateConverter->convert();

    $this->assertSame(file_get_contents(self::TEMPLATE_FILE_DIR . 'expectedTemplate.php'), $actual);
  }

  /**
   * @test
   */
  public function convertWithLocalNavAndFocusBox()
  {
    $this->setUpConverter('indexWithLocalNav.php');
    $actual = $this->templateConverter->convert();

    $this->assertSame(file_get_contents(self::TEMPLATE_FILE_DIR . 'expectedIndexWithLocalNav.php'), $actual);
  }
}