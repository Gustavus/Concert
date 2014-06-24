<?php
/**
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test;

use Gustavus\Concert\Config,
  Gustavus\Concert\FileConfigurationPart,
  Gustavus\Test\TestObject;

/**
 * Class to test FileConfigurationPart
 *
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class FileConfigurationPartTest extends TestBase
{
  /**
   * Part to use for testing
   *
   * @var FileConfigurationPart
   */
  private $part;

  /**
   * Basic params for building a FileContentPart representing normal content
   * @var array
   */
  private $partParams = [
    'contentType' => Config::OTHER_CONTENT_TYPE,
    'content' => 'test content',
    'key'   => 0,
  ];

  /**
   * Basic params for buiulding a FileContentPart representing php content
   * @var array
   */
  private $PHPPartParams = [
    'contentType' => Config::PHP_CONTENT_TYPE,
    'content' => '$config[\'content\'] = \'some content\';',
    'key'   => 0,
  ];

  public function setUp()
  {
  }

  public function tearDown()
  {
  }

  /**
   * Builds a basic FileConfigurationPart from partParams
   * @return void
   */
  private function buildFileConfigurationPart()
  {
    $this->part = new TestObject(new FileConfigurationPart($this->partParams));
  }

  /**
   * Builds a basic FileConfigurationPart from PHPPartParams
   * @return void
   */
  private function buildPHPFileConfigurationPart()
  {
    $this->part = new TestObject(new FileConfigurationPart($this->PHPPartParams));
  }

  /**
   * @test
   */
  public function testConstructor()
  {
    $this->buildFileConfigurationPart();

    $this->assertSame($this->partParams['contentType'], $this->part->contentType);
    $this->assertSame($this->partParams['content'], $this->part->content);
    $this->assertSame($this->partParams['key'], $this->part->getKey());
  }

  /**
   * @test
   * @expectedException \InvalidArgumentException
   */
  public function testName()
  {
    $part = new FileConfigurationPart;
  }

  /**
   * @test
   */
  public function isPHPContent()
  {
    $this->buildPHPFileConfigurationPart();

    $this->assertTrue($this->part->isPHPContent());
  }

  /**
   * @test
   */
  public function isPHPContentFalse()
  {
    $this->buildFileConfigurationPart();

    $this->assertFalse($this->part->isPHPContent());
  }

  /**
   * @test
   */
  public function getContent()
  {
    $this->buildFileConfigurationPart();

    $this->assertSame($this->partParams['content'], $this->part->getContent());
  }

  /**
   * @test
   */
  public function getContentPHP()
  {
    $this->buildPHPFileConfigurationPart();

    $this->assertSame($this->PHPPartParams['content'], $this->part->getContent());
  }

  /**
   * @test
   */
  public function getContentEditable()
  {
    $this->buildFileConfigurationPart();

    $actual = $this->part->getContent(true);
    $this->assertContains($this->partParams['content'], $actual);
    $this->assertContains($this->wrappedEditableIdentifier, $actual);
  }

  /**
   * @test
   */
  public function getContentPHPEditable()
  {
    if (!Config::ALLOW_PHP_EDITS) {
      $this->markTestSkipped('PHP is not editable');
    }
    $editablePiece = 'some content';
    $content = sprintf('$config[\'content\'] = \'%s\';', $editablePiece);
    $params = [
      'contentType' => Config::PHP_CONTENT_TYPE,
      'content' => $content,
      'key'   => 0,
    ];
    $part = new FileConfigurationPart($params);

    $actual = $part->getContent(true);
    $this->assertContains($editablePiece, $actual);
    $this->assertContains($this->wrappedEditableIdentifier, $actual);
    $this->assertContains('></span', str_replace($editablePiece, '', $actual));
  }

  /**
   * @test
   */
  public function getContentNotEditable()
  {
    $params = [
      'contentType' => Config::PHP_CONTENT_TYPE,
      'content' => '$config[\'content\'] = someFunctionCall();',
      'key'   => 0,
    ];
    $part = new FileConfigurationPart($params);

    $actual = $part->getContent(true);
    $this->assertNotContains($this->wrappedEditableIdentifier, $actual);
  }

  /**
   * @test
   */
  public function parseContentNotPHP()
  {
    $this->buildFileConfigurationPart();

    $this->assertFalse($this->part->parseContent());
  }

  /**
   * @test
   */
  public function partContent()
  {
    $content = '$config = [\'content\' => \'some content\'];';
    $params = [
      'contentType' => Config::PHP_CONTENT_TYPE,
      'content' => $content,
      'key'   => 0,
    ];
    $part = new TestObject(new FileConfigurationPart($params));

    $result = $part->parseContent();
    $this->assertTrue(is_array($result));
  }

  /**
   * @test
   */
  public function buildPHPNodesNotPHP()
  {
    $this->buildFileConfigurationPart();

    $this->assertNull($this->part->buildPHPNodes());
  }

  /**
   * @test
   */
  public function buildPHPNodes()
  {
    $content = '$config = [\'content\' => \'some content\'];';
    $params = [
      'contentType' => Config::PHP_CONTENT_TYPE,
      'content' => $content,
      'key'   => 0,
    ];
    $part = new TestObject(new FileConfigurationPart($params));

    $part->buildPHPNodes();
    $actual = $part->phpNodes;
    $this->assertNotNull($actual);
    $this->assertTrue(is_array($actual));
    $this->assertSame(1, count($actual));
  }

  /**
   * @test
   */
  public function getPHPNodesNotPHP()
  {
    $this->buildFileConfigurationPart();

    $this->assertNull($this->part->getPHPNodes());
  }

  /**
   * @test
   */
  public function getAndBuildPHPNodesFromTestIndex()
  {
    $params = [
      'contentType' => Config::PHP_CONTENT_TYPE,
      'content' => self::$indexConfigArray['phpcontent'][0],
      'key'   => 0,
    ];
    $part = new TestObject(new FileConfigurationPart($params));

    $this->assertEmpty($part->phpNodes);
    $part->buildPHPNodes();
    $actual = $part->phpNodes;
    $this->assertNotNull($actual);
    $this->assertTrue(is_array($actual));
    $this->assertSame(4, count($actual));
  }

  /**
   * @test
   * @dataProvider buildEditableIndexData
   */
  public function buildEditableIndex($expected, $key, $subKey = null, $subSubKey = null)
  {
    $this->partParams['key'] = $key;
    $this->buildFileConfigurationPart();
    $actual = $this->part->buildEditableIndex($subKey, $subSubKey);
    $this->assertSame($expected, $actual);
  }

  /**
   * DataProvider for buildEditableIndex
   *
   * @return array
   */
  public function buildEditableIndexData()
  {
    return [
      ['1', 1],
      ['2-0', 2, 0],
      ['1-1-1', 1, 1, 1],
    ];
  }

  /**
   * @test
   */
  public function buildEditablePHPNodes()
  {
    if (!Config::ALLOW_PHP_EDITS) {
      $this->markTestSkipped('PHP is not editable');
    }
    $params = [
      'contentType' => Config::PHP_CONTENT_TYPE,
      'content' => self::$indexConfigArray['phpcontent'][0],
      'key'   => 0,
    ];
    $part = new TestObject(new FileConfigurationPart($params));

    $this->assertEmpty($part->editablePHPNodeValues);

    $part->buildEditablePHPNodes();
    $this->assertSame(4, count($part->phpNodes));

    // There are 3 sub parts that are editable from the first node.
    $this->assertSame(3, count($part->editablePHPNodeValues));

    $expected = [
      '0-0-0' => 'Some Title',
      '0-0-1' => 'Some Sub Title',
      '0-0-2' => 'This is some content.',
    ];
    $this->assertSame($expected, $part->editablePHPNodeValues);
  }

  /**
   * @test
   */
  public function buildEditablePHPNodesAddingEditablePiece()
  {
    if (!Config::ALLOW_PHP_EDITS) {
      $this->markTestSkipped('PHP is not editable');
    }
    $params = [
      'contentType' => Config::PHP_CONTENT_TYPE,
      'content' => self::$indexConfigArray['phpcontent'][0] . '$title = \'Another title\';',
      'key'   => 0,
    ];
    $part = new TestObject(new FileConfigurationPart($params));

    $this->assertEmpty($part->editablePHPNodeValues);

    $part->buildEditablePHPNodes();
    $this->assertSame(5, count($part->phpNodes));

    // There are 3 sub parts that are editable from the first node, and 1 from the last.
    $this->assertSame(4, count($part->editablePHPNodeValues));

    $expected = [
      '0-0-0' => 'Some Title',
      '0-0-1' => 'Some Sub Title',
      '0-0-2' => 'This is some content.',
      '0-4'   => 'Another title',
    ];
    $this->assertSame($expected, $part->editablePHPNodeValues);
  }

  /**
   * @test
   */
  public function buildEditablePHPNodesWrapEditable()
  {
    if (!Config::ALLOW_PHP_EDITS) {
      $this->markTestSkipped('PHP is not editable');
    }
    $params = [
      'contentType' => Config::PHP_CONTENT_TYPE,
      'content' => self::$indexConfigArray['phpcontent'][0],
      'key'   => 0,
    ];
    $part = new TestObject(new FileConfigurationPart($params));

    $this->assertEmpty($part->editablePHPNodeValues);

    $part->buildEditablePHPNodes(true);
    $this->assertSame(4, count($part->phpNodes));

    // There are 3 sub parts that are editable from the first node.
    $this->assertSame(3, count($part->editablePHPNodeValues));

    $expected = [
      '0-0-0' => 'Some Title',
      '0-0-1' => 'Some Sub Title',
      '0-0-2' => 'This is some content.',
    ];

    $this->assertSame(array_keys($expected), array_keys($part->editablePHPNodeValues));

    foreach ($expected as $key => $value) {
      // make sure it has our wrapper
      $this->assertContains($this->wrappedEditableIdentifier, $part->editablePHPNodeValues[$key]);
      // make sure that wrapper includes the key
      $this->assertContains($key, $part->editablePHPNodeValues[$key]);
    }
  }

  /**
   * @test
   */
  public function editValuePHP()
  {
    if (!Config::ALLOW_PHP_EDITS) {
      $this->markTestSkipped('PHP is not editable');
    }
    $editablePiece = 'some content';
    $content = sprintf('$config[\'content\'] = \'%s\';', $editablePiece);
    $params = [
      'contentType' => Config::PHP_CONTENT_TYPE,
      'content' => $content,
      'key'   => 0,
    ];
    $part = new TestObject(new FileConfigurationPart($params));

    $part->buildEditablePHPNodes();
    $this->assertSame($editablePiece, $part->editablePHPNodeValues['0-0']);

    $edit = $editablePiece . ' additions';
    $this->assertTrue($part->editValue('0-0', $edit));
    $this->assertSame($part->editablePHPNodeValues['0-0'], $edit);

    $expected = sprintf('$config[\'content\'] = \'%s\';', $edit);
    $this->assertSame($expected, $part->getContent());
  }

  /**
   * @test
   */
  public function editValuePHPChangingMultipleValues()
  {
    if (!Config::ALLOW_PHP_EDITS) {
      $this->markTestSkipped('PHP is not editable');
    }
    $params = [
      'contentType' => Config::PHP_CONTENT_TYPE,
      'content' => self::$indexConfigArray['phpcontent'][0],
      'key'   => 0,
    ];
    $part = new TestObject(new FileConfigurationPart($params));

    $editablePieces = [
      '0-0-0' => 'Some Title',
      '0-0-1' => 'Some Sub Title',
      '0-0-2' => 'This is some content.',
    ];

    // @todo replace this when we are supporting the ability to edit php.
    $this->assertTrue($part->editValue('0-0-1', 'A new sub title'));
    $this->assertTrue($part->editValue('0-0-2', '<p>Test content.</p>'));

    $editedContent = $part->getContent();

    // file_put_contents(self::$testFileDir . 'index.php', $editedContent);

    // var_dump($editedContent);
  }

  /**
   * @test
   */
  public function editValueHTML()
  {
    $this->buildFileConfigurationPart();
    $actual = $this->part->editValue('0', 'brand new content');

    $this->assertTrue($actual);
    $this->assertSame('brand new content', $this->part->getContent());
    $this->assertSame($this->partParams['content'], $this->part->getValueBeforeEdit());
  }

  /**
   * @test
   */
  public function getValueBeforeEdit()
  {
    $this->buildFileConfigurationPart();
    $this->assertNull($this->part->getValueBeforeEdit());
  }
}