<?php
/**
 * @package  ConcertCMS
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\ConcertCMS\Test;

use PHPParser_Node_Expr_Array,
  PHPParser_Parser,
  PHPParser_NodeTraverser,
  PHPParser_Lexer,
  Gustavus\ConcertCMS\GustavusPrettyPrinter,
  Gustavus\ConcertCMS\Config;

/**
 * Class to test FileConfigurationPart
 *
 * @package  ConcertCMS
 * @subpackage Test
 * @author  Billy Visto
 */
class GustavusPrettyPrinterTest extends TestBase
{
  public function setUp()
  {
  }

  public function tearDown()
  {
  }

  /**
   * @test
   */
  public function editValueChangingMultipleValues1()
  {
    if (!Config::ALLOW_PHP_EDITS) {
      $this->markTestSkipped('PHP is not editable');
    }
    $content = '
/**
 * [executeSomeContent description]
 * @return [type] [description]
 */
function executeSomeContent()
{
  return \'This is some executed content.\';
  if (true) {
    // true
    echo \'hello\';

    if (false) {
      // @todo
      // false
    }
  }
}
';

    $parser = new PHPParser_Parser(new PHPParser_Lexer);
    $nodes = $parser->parse(sprintf('<?php %s ?>', $content));

    $traverser = new PHPParser_NodeTraverser;
    $phpNodes = $traverser->traverse($nodes);

    $prettyPrinter = new GustavusPrettyPrinter;

    $actual = str_replace('    ', '  ', $prettyPrinter->prettyPrint($phpNodes));


file_put_contents(self::$testFileDir . 'index.php', $content);
    file_put_contents(self::$testFileDir . 'actual.php', $actual);
    exit;
    $this->assertSame($expected, $actual);
  }

  /**
   * @test
   */
  public function editValueChangingMultipleValues()
  {
    if (!Config::ALLOW_PHP_EDITS) {
      $this->markTestSkipped('PHP is not editable');
    }
    $content = '// use template getter...
// must use $config[\'templatepreference\']
$test = \'arst\';
$config = [
  \'title\' => \'Some Title\',
  \'subTitle\' => \'Some Sub Title\',
  \'content\' => \'This is some content.\',
  \'content\' => "$test",
  \'localNav\' => [
    \'arst\',
    \'arst\'
  ],
];

$config[\'content\'] .= executeSomeContent();

/**
 * [executeSomeContent description]
 * @return [type] [description]
 */
function executeSomeContent()
{
  return \'This is some executed content.\';
  if (true) {
    echo \'hello\';

    if (false) {
      /*test*/
      // false
      // test
    }
  }
}

/**
 * Test class
 */
class test
{
  /**
   * [$val description]
   * @var [type]
   */
  private $val;

  /**
   * [arst description]
   * @param  [type] $value [description]
   * @return [type]        [description]
   */
  public function arst($value)
  {
    $value = $value;
    foreach ($value as $val => $key) {
      $val = (int) $key;
    }

    switch($value) {
      case \'arst\':
        /*arst*/
        $arst = \'arst\';
          break;
      default:
        $art = \'arst\';
    }
  }
}

ob_start();';

    $parser = new PHPParser_Parser(new PHPParser_Lexer);
    $nodes = $parser->parse(sprintf('<?php %s ?>', $content));

    $traverser = new PHPParser_NodeTraverser;
    $phpNodes = $traverser->traverse($nodes);

    $prettyPrinter = new GustavusPrettyPrinter;

    $actual = str_replace('    ', '  ', $prettyPrinter->prettyPrint($phpNodes));

    $expected = '// use template getter...
// must use $config[\'templatepreference\']
$test = \'arst\';
$config = [
  \'title\' => \'Some Title\',
  \'subTitle\' => \'Some Sub Title\',
  \'content\' => \'This is some content.\',
  \'content\' => "{$test}",
  \'localNav\' => [
    \'arst\',
    \'arst\',
  ],
];
$config[\'content\'] .= executeSomeContent();

/**
 * [executeSomeContent description]
 * @return [type] [description]
 */
function executeSomeContent()
{
  return \'This is some executed content.\';
  if (true) {
    echo \'hello\';
    if (false) {
      // false
    }
  }
}

/**
 * Test class
 */
class test
{

  /**
   * [$val description]
   * @var [type]
   */
  private $val;

  /**
   * [arst description]
   * @param  [type] $value [description]
   * @return [type]    [description]
   */
  public function arst($value)
  {
    $value = $value;
    foreach ($value as $val => $key) {
      $val = (int) $key;
    }
    switch ($value) {
      case \'arst\':
        /*arst*/
        $arst = \'arst\';
        break;
      default:
        $art = \'arst\';
    }
  }
}
ob_start();';
file_put_contents(self::$testFileDir . 'index.php', $content);
    file_put_contents(self::$testFileDir . 'actual.php', $actual);
    exit;
    $this->assertSame($expected, $actual);

  }
}