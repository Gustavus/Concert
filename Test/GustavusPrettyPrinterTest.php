<?php
/**
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test;

require_once '/cis/lib/Gustavus/Concert/Assets/Composer/vendor/autoload.php';

use PhpParser\Parser,
  PhpParser\NodeTraverser,
  PhpParser\Lexer,
  Gustavus\Concert\GustavusPrettyPrinter,
  Gustavus\Concert\Config;

/**
 * Class to test FileConfigurationPart
 *
 * @package  Concert
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
  public function editValueChangingMultipleValues()
  {
    $content = '/**
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
    }
  }
}';

    $parser = new Parser(new Lexer);
    $nodes = $parser->parse(sprintf('<?php %s ?>', $content));

    $traverser = new NodeTraverser;
    $phpNodes = $traverser->traverse($nodes);

    $prettyPrinter = new GustavusPrettyPrinter;

    $actual = str_replace('    ', '  ', $prettyPrinter->prettyPrint($phpNodes));


    $expected = $content;

    $this->assertSame($expected, $actual);
  }

  /**
   * @test
   */
  public function editValueChangingMultipleValuesWithWhitespace()
  {
    $this->markTestSkipped('PHPParser doesn\'t like comments not attached to nodes.');
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

    $parser = new Parser(new Lexer);
    $nodes = $parser->parse(sprintf('<?php %s ?>', $content));

    $traverser = new NodeTraverser;
    $phpNodes = $traverser->traverse($nodes);

    $prettyPrinter = new GustavusPrettyPrinter;

    $actual = str_replace('    ', '  ', $prettyPrinter->prettyPrint($phpNodes));


    $expected = $content;

    $this->assertSame($expected, $actual);
  }

  /**
   * @test
   */
  public function editValueChangingMultipleValuesComplex()
  {
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

    $parser = new Parser(new Lexer);
    $nodes = $parser->parse(sprintf('<?php %s ?>', $content));

    $traverser = new NodeTraverser;
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

    $this->assertSame($expected, preg_replace('`\h+\v`', "\n", $actual));
  }

  /**
   * @test
   */
  public function editValueChangingMultipleValuesComplexMaintainingWhitespace()
  {
    $this->markTestSkipped('PHPParser doesn\'t keep track of whitespace');

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

    $parser = new Parser(new Lexer);
    $nodes = $parser->parse(sprintf('<?php %s ?>', $content));

    $traverser = new NodeTraverser;
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

    $this->assertSame($expected, $actual);
  }
}