<?php
/**
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test;

use Gustavus\Concert\FileConfiguration,
  Gustavus\Concert\FileConfigurationPart,
  Gustavus\Concert\Config,
  Gustavus\Test\TestObject;

/**
 * Class to test FileConfiguration
 *
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 * @todo  Add tests for editing php content
 */
class FileConfigurationTest extends TestBase
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
  public function buildAndGetFileConfigurationParts()
  {
    // called in constructor
    $configuration = new FileConfiguration(self::$indexConfigArray);
    $configurationParts = $configuration->getFileConfigurationParts();
    $this->assertSame(3, count($configurationParts));
    foreach ($configurationParts as $fileConfigurationPart) {
      $this->assertTrue($fileConfigurationPart instanceof FileConfigurationPart);
    }

    $this->assertTrue($this->call($configurationParts[0], 'isPHPContent'));
    $this->assertFalse($this->call($configurationParts[1], 'isPHPContent'));
    $this->assertTrue($this->call($configurationParts[2], 'isPHPContent'));
  }

  /**
   * @test
   */
  public function buildAndGetFileConfigurationPartsIndexThree()
  {
    // called in constructor
    $configArray = self::$indexThreeConfigArray;
    unset($configArray['phpcontent']);
    $configuration = new TestObject(new FileConfiguration($configArray));
    // test that getFileConfigurationParts builds them if they don't exist.
    $configuration->fileConfigurationParts = null;
    $configurationParts = $configuration->getFileConfigurationParts();
    $this->assertSame(1, count($configurationParts));
    foreach ($configurationParts as $fileConfigurationPart) {
      $this->assertTrue($fileConfigurationPart instanceof FileConfigurationPart);
    }

    $this->assertFalse($this->call($configurationParts[0], 'isPHPContent'));
  }

  /**
   * @test
   */
  public function buildAndGetFileConfigurationPartsIndexFive()
  {
    $configuration = new FileConfiguration(self::$indexFiveConfigArray);
    $configurationParts = $configuration->getFileConfigurationParts();
    $this->assertSame(4, count($configurationParts));
    foreach ($configurationParts as $fileConfigurationPart) {
      $this->assertTrue($fileConfigurationPart instanceof FileConfigurationPart);
    }

    $this->assertFalse($this->call($configurationParts[0], 'isPHPContent'));
    $this->assertTrue($this->call($configurationParts[1], 'isPHPContent'));
    $this->assertFalse($this->call($configurationParts[2], 'isPHPContent'));
    $this->assertTrue($this->call($configurationParts[3], 'isPHPContent'));
  }

  /**
   * @test
   * @expectedException \UnexpectedValueException
   */
  public function buildAndGetFileConfigurationPartsDuplicateIndex()
  {
    $configArray = [
      'phpcontent' => [
        0 => 'echo "here"',
      ],
      'content' => [
        0 => 'contents',
      ],
    ];
    // called in constructor
    $configuration = new FileConfiguration($configArray);
    $configurationParts = $configuration->getFileConfigurationParts();
  }

  /**
   * @test
   */
  public function buildFile()
  {
    $configuration = new FileConfiguration(self::$indexConfigArray);
    $file = $configuration->buildFile();
    $this->assertSame(self::$indexContents, $file);
  }

  /**
   * @test
   */
  public function buildFileForEditing()
  {
    $configuration = new FileConfiguration(self::$indexConfigArray);
    $file = $configuration->buildFile(true);

    $expected = sprintf('<?php
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

<div class="editable" data-index="1">

<p>This is some html content</p>

</div>%s

<?php

$config["content"] .= ob_get_contents();

echo $config["content"];', Config::EDITABLE_DIV_CLOSING_IDENTIFIER);

    $this->assertSame($expected, $file);
  }

  /**
   * @test
   * @dataProvider getFilePartKeyData
   */
  public function getFilePartKey($expected, $index)
  {
    $this->assertSame($expected, $this->call('FileConfiguration', 'getFilePartKey', [$index]));
  }

  /**
   * DataProvider for getFilePartKey
   * @return array
   */
  public function getFilePartKeyData()
  {
    return [
      ['1', '1'],
      ['2', '2-0'],
      ['1', '1-1-1'],
      ['511', '511-1-1'],
    ];
  }

  /**
   * @test
   */
  public function getFileConfigurationPartsEdited()
  {
    $configuration = new FileConfiguration(self::$indexConfigArray);
    $this->assertEmpty($configuration->getFileConfigurationPartsEdited());
  }

  /**
   * @test
   */
  public function editFileHTML()
  {
    $configuration = new FileConfiguration(self::$indexConfigArray);

    $edits = ['1' => '<p>This is some edited html content</p>'];

    $this->assertTrue($configuration->editFile($edits));
    $editedPart = $configuration->getEditedFileConfigurationPart('1');
    $this->assertNotNull($editedPart);
    // 2 shouldn't exist
    $this->assertNull($configuration->getEditedFileConfigurationPart('2'));
    $this->assertSame("\n\n<p>This is some html content</p>\n\n", $editedPart->getValueBeforeEdit());
    $this->assertNotSame("\n\n<p>This is some html content</p>\n\n", $editedPart->getContent());
  }

  /**
   * @test
   */
  public function editFileHTMLTwo()
  {
    $configuration = new FileConfiguration(self::$indexFiveConfigArray);

    $edits = ['0' => '<p>This is some edited html content</p>', '2' => 'some more html'];

    $this->assertTrue($configuration->editFile($edits));

    $editedPart = $configuration->getEditedFileConfigurationPart('0');
    $this->assertNotNull($editedPart);
    $this->assertSame("\n<p>This is some html content</p>\n\n", $editedPart->getValueBeforeEdit());
    $this->assertNotSame("\n<p>This is some html content</p>\n\n", $editedPart->getContent());

    $editedPart = $configuration->getEditedFileConfigurationPart('2');
    $this->assertNotNull($editedPart);
    $this->assertSame("\nmore html\n", $editedPart->getValueBeforeEdit());
    $this->assertSame('some more html', $editedPart->getContent());
  }
}