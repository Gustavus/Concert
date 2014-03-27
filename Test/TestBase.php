<?php
/**
 * @package  CMS
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\CMS\Test;

use Gustavus\Test\Test;

/**
 * Base class for testing
 *
 * @package  CMS
 * @subpackage Test
 * @author  Billy Visto
 */
class TestBase extends Test
{
  /**
   * Location for test files to live
   * @var string
   */
  protected static $testFileDir;

  /**
   * Identifier of wrapped editable content
   * @var string
   */
  protected $wrappedEditableIdentifier = 'div class="editable"';

  /**
   * Class constructor
   */
  public static function setUpBeforeClass()
  {
    self::$testFileDir = sprintf('%s/files/', __DIR__);
  }

  /**
   * Tears down the environment after each test class is done with tests
   * @return void
   */
  public static function tearDownAfterClass()
  {
    $files = scandir(self::$testFileDir);
    foreach ($files as $file) {
      if ($file === '.' || $file === '..') {
        continue;
      }
      unlink(self::$testFileDir . $file);
    }
  }

  /**
   * File 1 contents to test
   * @var string
   */
  protected static $indexContents = '<?php
// use template getter...
// must use $config["templatepreference"]
$config = [
  "title" => "Some Title",
  "subTitle" => "Some Sub Title",
  "content" => "This is some content.",
];

$config["content"] .= executeSomeContent();

function executeSomeContent()
{
  return "This is some executed content.";
}

ob_start();
?>
<p>This is some html content</p>
<?php

$config["content"] .= ob_get_contents();

echo $config["content"];';

  /**
   * File 1 configuration array
   * @var array
   */
  protected static $indexConfigArray = [
      'phpcontent' => [
        0 => '
// use template getter...
// must use $config["templatepreference"]
$config = [
  "title" => "Some Title",
  "subTitle" => "Some Sub Title",
  "content" => "This is some content.",
];

$config["content"] .= executeSomeContent();

function executeSomeContent()
{
  return "This is some executed content.";
}

ob_start();
',
        2 => '

$config["content"] .= ob_get_contents();

echo $config["content"];'
      ],
      'content' => [
        1 => '
<p>This is some html content</p>
'
      ],
    ];

  /**
   * File 2 contents to test
   * @var string
   */
  protected static $indexTwoContents = '<?php

$config["content"] .= ob_get_contents();

echo $config["content"];';

  /**
   * File 2 configuration array
   * @var array
   */
  protected static $indexTwoConfigArray = [
      'phpcontent' => [
        0 => '

$config["content"] .= ob_get_contents();

echo $config["content"];'
      ],
      'content' => [],
    ];

  /**
   * File 3 contents to test
   * @var string
   */
  protected static $indexThreeContents = '
<p>This is some html content</p>

this does not contain any php';

  /**
   * File 3 configuration array
   * @var array
   */
  protected static $indexThreeConfigArray = [
      'phpcontent' => [],
      'content' => [
        0 => '
<p>This is some html content</p>

this does not contain any php'
      ],
    ];

  /**
   * File 4 contents to test
   * @var string
   */
  protected static $indexFourContents = '
<p>This is some html content</p>

<?=$test;';

  /**
   * File 4 configuration array
   * @var array
   */
  protected static $indexFourConfigArray = [
      'phpcontent' => [
        1 => '$test;',
      ],
      'content' => [
        0 => '
<p>This is some html content</p>

',
      ],
    ];

  /**
   * File 5 contents to test
   * @var string
   */
  protected static $indexFiveContents = '
<p>This is some html content</p>

<?=$test;?>

more html

<?php //arst';

  /**
   * File 5 configuration array
   * @var array
   */
  protected static $indexFiveConfigArray = [
      'phpcontent' => [
        1 => '$test;',
        3 => '//arst',
      ],
      'content' => [
        0 => '
<p>This is some html content</p>

',
        2 => '
more html
'
      ],
    ];



}