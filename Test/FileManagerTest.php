<?php
/**
 * @package  CMS
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\CMS\Test;

use Gustavus\Test\TestObject,
  Gustavus\CMS\FileManager,
  Gustavus\CMS\FileConfiguration;

/**
 * Class to test FileManager class
 *
 * @package  CMS
 * @subpackage Test
 * @author  Billy Visto
 */
class FileManagerTest extends TestBase
{
  /**
   * FileManager object to do our testing on
   * @var FileManager
   */
  private $fileManager;

  /**
   * Sets up environment for every test
   *
   * @return void
   */
  public function setUp()
  {

  }

  /**
   * Tears down the environment for every test
   *
   * @return void
   */
  public function tearDown()
  {
    unset($this->fileManager);
  }

  /**
   * Builds the FileManager object to use for testing
   * @param  string $file Filename
   * @param  string $user username
   * @return void
   */
  private function buildFileManager($file, $user)
  {
    $this->fileManager = new TestObject(new FileManager($file, $user));
  }

  /**
   * @test
   */
  public function buildAndGetFileConfigurationArray()
  {
    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager(self::$testFileDir . 'index.php', 'testUser');

    $this->assertSame(self::$indexConfigArray, $this->fileManager->getFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildAndGetFileConfigurationArrayOnlyPHP()
  {
    file_put_contents(self::$testFileDir . 'index2.php', self::$indexTwoContents);

    $this->buildFileManager(self::$testFileDir . 'index2.php', 'testUser');

    $this->assertSame(self::$indexTwoConfigArray, $this->fileManager->getFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildFileConfigurationArrayOnlyHTML()
  {
    file_put_contents(self::$testFileDir . 'index3.php', self::$indexThreeContents);

    $this->buildFileManager(self::$testFileDir . 'index3.php', 'testUser');

    $this->assertSame(self::$indexThreeConfigArray, $this->fileManager->buildFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildFileConfigurationArrayHTMLFirst()
  {
    file_put_contents(self::$testFileDir . 'index4.php', self::$indexThreeContents);

    $this->buildFileManager(self::$testFileDir . 'index4.php', 'testUser');

    $this->assertSame(self::$indexThreeConfigArray, $this->fileManager->buildFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildFileConfigurationArrayHTMLFirstAlternatingPHP()
  {
    file_put_contents(self::$testFileDir . 'index5.php', self::$indexThreeContents);

    $this->buildFileManager(self::$testFileDir . 'index5.php', 'testUser');

    $this->assertSame(self::$indexThreeConfigArray, $this->fileManager->buildFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildAndGetFileConfiguration()
  {
    file_put_contents(self::$testFileDir . 'index5.php', self::$indexThreeContents);

    $this->buildFileManager(self::$testFileDir . 'index5.php', 'testUser');

    $configuration = $this->fileManager->getFileConfiguration();
    $this->assertInstanceOf('\Gustavus\CMS\FileConfiguration', $configuration);
    $this->assertSame(1, count($configuration->getFileConfigurationParts()));
  }

  /**
   * @test
   */
  public function assembleFile()
  {
    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager(self::$testFileDir . 'index.php', 'testUser');

    $file = $this->fileManager->assembleFile();
    // $configuration = new FileConfiguration(self::$indexConfigArray);
    // $file = $configuration->buildFile();
    $this->assertSame(self::$indexContents, $file);
  }

  /**
   * @test
   */
  public function buildFileForEditing()
  {
    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager(self::$testFileDir . 'index.php', 'testUser');

    $file = $this->fileManager->assembleFile(true);

    $expected = '<?php
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
?><div class="editable" data-index="1"><p>This is some html content</p></div><?php

$config["content"] .= ob_get_contents();

echo $config["content"];';

    $this->assertSame($expected, $file);
  }

  /**
   * @test
   */
  public function makeDraft()
  {
    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager(self::$testFileDir . 'index.php', 'testUser');

    $filename = $this->fileManager->makeDraft();
    $this->assertContains('/cis/lib/', $filename);

    $expected = '<?php
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
?><div class="editable" data-index="1"><p>This is some html content</p></div><?php

$config["content"] .= ob_get_contents();

echo $config["content"];';

    $draftFile = file_get_contents($filename);
    unlink($filename);

    $this->assertSame($expected, $draftFile);
  }

  /**
   * @test
   */
  public function editFile()
  {
    $configuration = new FileConfiguration(self::$indexConfigArray);

    $edits = ['1' => '<p>This is some edited html content</p>'];

    $this->buildFileManager(self::$testFileDir . 'index.php', 'testUser');
    $this->fileManager->fileConfiguration = $configuration;

    $this->assertTrue($this->fileManager->editFile($edits));
    $editedPart = $this->fileManager->getFileConfiguration()->getEditedFileConfigurationPart('1');
    $this->assertNotNull($editedPart);
    $this->assertSame("\n<p>This is some html content</p>\n", $editedPart->getValueBeforeEdit());
    $this->assertNotSame("\n<p>This is some html content</p>\n", $editedPart->getContent());
  }

  /**
   * @test
   */
  public function save()
  {
    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager(self::$testFileDir . 'index.php', 'testUser');

    $this->fileManager->editFile(['1' => '<p>This is content</p>']);
    $this->fileManager->save();

    $expected = '<?php
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
?><p>This is content</p><?php

$config["content"] .= ob_get_contents();

echo $config["content"];';

    $modifiedFile = file_get_contents(self::$testFileDir . 'index.php');

    $this->assertSame($expected, $modifiedFile);
  }
}