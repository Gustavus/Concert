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
    $this->assertContains('<div class="editable" data-index="0-0"></div', str_replace($editablePiece, '', $actual));
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
  public function editValueNotSafeContent()
  {
    $this->buildFileConfigurationPart();
    $actual = $this->part->editValue('0', '<?php brand new content');

    $this->assertTrue($actual);
    $this->assertSame('', $this->part->getContent());
    $this->assertSame($this->partParams['content'], $this->part->getValueBeforeEdit());
  }

  /**
   * @test
   */
  public function editValueHTMLUnMatchedTags()
  {
    $this->partParams['content'] = '</div><div class="grid_36 alpha omega">

  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

<p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.</li>
  <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
  <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
  <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
  <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
</ul>
</div>
<div>';
    $this->buildFileConfigurationPart();
    $actual = $this->part->editValue('0', 'brand new content');

    $this->assertTrue($actual);
    $this->assertSame('</div>brand new content<div>', $this->part->getContent());
    $this->assertSame($this->partParams['content'], $this->part->getValueBeforeEdit());
  }

  /**
   * @test
   */
  public function editValueHTMLUnMatchedStartTag()
  {
    $this->partParams['content'] = '<div class="grid_36 alpha omega">

  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

<p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.</li>
  <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
  <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
  <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
  <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
</ul>
</div>
<div>';
    $this->buildFileConfigurationPart();
    $actual = $this->part->editValue('0', 'brand new content');

    $this->assertTrue($actual);
    $this->assertSame('brand new content<div>', $this->part->getContent());
    $this->assertSame($this->partParams['content'], $this->part->getValueBeforeEdit());
  }

  /**
   * @test
   */
  public function editValueHTMLUnMatchedEndTag()
  {
    $this->partParams['content'] = '<div class="grid_36 alpha omega">

  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

<p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.</li>
  <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
  <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
  <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
  <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
</ul>
</div>
</div>
';

    $expected = '<div class="grid_36 alpha omega">

  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

  <p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
  <ul>
    <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.</li>
    <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
    <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
    <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
    <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
  </ul>
</div>
</div>
';
    $this->buildFileConfigurationPart();
    $actual = $this->part->editValue('0', 'brand new content');

    $this->assertTrue($actual);
    $this->assertSame(rtrim($expected) . 'brand new content', $this->part->getContent());
    $this->assertSame($this->partParams['content'], $this->part->getValueBeforeEdit());
  }

  /**
   * @test
   */
  public function editValueMulipleUnMatchedTags()
  {
    $this->partParams['content'] = '<div class="grid_36 alpha omega">
      <ul>
        <li></li>
        </li>
      </ul>

  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

<p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.
  <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
  <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
  <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
  <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
</ul>
</div>';

    $expected = sprintf('<div class="grid_36 alpha omega">
  <ul>
    <li></li>
  </li>
</ul>brand new content<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.
    <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
    <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
    <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
    <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
  </ul>
</div>');


    $this->buildFileConfigurationPart();
    $actual = $this->part->editValue('0', 'brand new content');

    $this->assertTrue($actual);
    $this->assertSame($expected, $this->part->getContent());
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

  /**
   * @test
   */
  public function sanitize()
  {
    $content = '<?php echo "Haha!"?> <script>test</script> hello';

    $result = $this->call('FileConfigurationPart', 'sanitize', [$content]);

    $this->assertSame(' hello', $result);
  }

  /**
   * @test
   */
  public function sanitizeNothingSafe()
  {
    $content = '<?php echo "Haha!"?> <script>test</script>';

    $result = $this->call('FileConfigurationPart', 'sanitize', [$content]);

    $this->assertSame('', $result);
  }

  /**
   * @test
   */
  public function sanitizeAllPHP()
  {
    $content = '<?php echo "Haha!" <script>test</script>';

    $result = $this->call('FileConfigurationPart', 'sanitize', [$content]);

    $this->assertSame('', $result);
  }

  /**
   * @test
   */
  public function sanitizeAllScript()
  {
    $content = '<script>test</script>';

    $result = $this->call('FileConfigurationPart', 'sanitize', [$content]);

    $this->assertSame('', $result);
  }

  /**
   * @test
   */
  public function sanitizeAllSafe()
  {
    $content = '<p>test</p>';

    $result = $this->call('FileConfigurationPart', 'sanitize', [$content]);

    $this->assertSame($content, $result);
  }

  /**
   * @test
   */
  public function wrapEditableContentClosingDivs()
  {
    $content = '<div class="grid_36 alpha omega">
<img src="arst/arst/asrt" />
  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

<p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.</li>
  <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
  <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
  <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
  <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
</ul>
</div>

<div class="grid_36 alpha omega">';

    $expected = sprintf('<div class="editable" data-index="0"><div class="grid_36 alpha omega">
<img src="arst/arst/asrt" />
  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

<p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.</li>
  <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
  <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
  <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
  <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
</ul>
</div>

</div>%s<div class="grid_36 alpha omega">', Config::EDITABLE_DIV_CLOSING_IDENTIFIER);

    $this->buildFileConfigurationPart();
    $result = $this->part->wrapEditableContent($content);
    $this->assertSame($expected, $result);
  }

  /**
   * @test
   */
  public function wrapEditableContentOpeningDivs()
  {
    $content = '<div class="grid_36 alpha omega">

  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

<p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.</li>
  <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
  <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
  <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
  <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
</ul>
</div>
</div><!-- this div doesn\'t have an opening tag -->
';

    $expected = sprintf('<div class="grid_36 alpha omega">

  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

<p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.</li>
  <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
  <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
  <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
  <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
</ul>
</div>
</div><div class="editable" data-index="0"><!-- this div doesn\'t have an opening tag -->
</div>%s', Config::EDITABLE_DIV_CLOSING_IDENTIFIER);

    $this->buildFileConfigurationPart();
    $result = $this->part->wrapEditableContent($content);
    $this->assertSame($expected, $result);
  }

  /**
   * @test
   */
  public function wrapEditableContentUnMatchedOpeningAndClosingDivs()
  {
    $content = '</div><div class="grid_36 alpha omega">

  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

<p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.</li>
  <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
  <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
  <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
  <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
</ul>
</div>
<div>
';

    $expected = sprintf('</div><div class="editable" data-index="0"><div class="grid_36 alpha omega">

  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

<p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.</li>
  <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
  <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
  <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
  <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
</ul>
</div>
</div>%s<div>
', Config::EDITABLE_DIV_CLOSING_IDENTIFIER);

    $this->buildFileConfigurationPart();
    $result = $this->part->wrapEditableContent($content);
    $this->assertSame($expected, $result);
  }

  /**
   * @test
   */
  public function wrapEditableContentUnMatchedDivsForceMoreUnMatched()
  {
    $content = '<div class="grid_36 alpha omega">
      <ul>
        <li></li>
        </li>
      </ul>

  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

<p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.
  <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
  <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
  <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
  <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
</ul>
</div>';

    $expected = sprintf('<div class="grid_36 alpha omega">
      <ul>
        <li></li>
        </li>
      </ul><div class="editable" data-index="0">

  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

<p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
</div>%s<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.
  <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
  <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
  <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
  <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
</ul>
</div>', Config::EDITABLE_DIV_CLOSING_IDENTIFIER);

    $this->buildFileConfigurationPart();
    $result = $this->part->wrapEditableContent($content);
    $this->assertSame($expected, $result);
  }

  /**
   * @test
   */
  public function findUnMatchedOpeningTagsSimple()
  {
    $opening = [
      0 => '<div class="grid_36 alpha omega">',
      1 => '<div>',
      2 => '<p>',
    ];

    $closing = [
      3 => '</p>',
      4 => '</div>',
    ];

    $this->buildFileConfigurationPart();
    $result = $this->part->findUnMatchedOpeningTags($opening, $closing);
    $this->assertSame([0 => $opening[0]], $result);
  }

  /**
   * @test
   */
  public function findUnMatchedOpeningTagsSimpleEnd()
  {
    $opening = [
      0 => '<div class="grid_36 alpha omega">',
      1 => '<div>',
      2 => '<p>',
      6 => '<div>',
    ];

    $closing = [
      3 => '</p>',
      4 => '</div>',
      5 => '</div>',
    ];

    $this->buildFileConfigurationPart();
    $result = $this->part->findUnMatchedOpeningTags($opening, $closing);
    $this->assertSame([6 => $opening[6]], $result);
  }

  /**
   * @test
   */
  public function findUnMatchedOpeningTagsSimpleMiddleAndEnd()
  {
    $opening = [
      0 => '<div class="grid_36 alpha omega">',
      1 => '<div>',
      2 => '<span>',
      3 => '<p>',
      7 => '<div>',
    ];

    $closing = [
      4 => '</p>',
      5 => '</div>',
      6 => '</div>',
    ];

    $this->buildFileConfigurationPart();
    $result = $this->part->findUnMatchedOpeningTags($opening, $closing);
    $this->assertSame([2 => $opening[2], 7 => $opening[7]], $result);
  }

  /**
   * @test
   */
  public function findUnMatchedOpeningTags()
  {
    $opening = [
      0 => '<div class="grid_36 alpha omega">',
      2 => '<p>',
      3 => '<a href="/alumni/gather/reunions/spring.php">',
      5 => '<a href="\n  /alumni/gather/networking.php">',
      7 => '<a href="/alumni/gather/chapters/events.php">',
      9 => '<a href="/alumni/gather/homecoming.php">',
      11 => '<a href="/alumni/gather/reunions/fall.php">',
      14 => '<p>',
      16 => '<ul>',
      17 => '<li>',
      18 => '<strong>',
      20 => '<a href="/alumni/events/gustiebreakfast/index.php">',
      23 => '<li>',
      24 => '<strong>',
      26 => '<a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">',
      29 => '<li>',
      30 => '<strong>',
      32 => '<a href="/calendar/gustavus-networking-event-3/40314">',
      35 => '<li>',
      36 => '<strong>',
      39 => '<li>',
      40 => '<strong>',
      42 => '<a href="/athletics/halloffame/">',
      47 => '<div class="grid_36 alpha omega">',
    ];

    $closing = [
      4 => '</a>',
      6 => '</a>',
      8 => '</a>',
      10 => '</a>',
      12 => '</a>',
      13 => '</p>',
      15 => '</p>',
      19 => '</strong>',
      21 => '</a>',
      22 => '</li>',
      25 => '</strong>',
      27 => '</a>',
      28 => '</li>',
      31 => '</strong>',
      33 => '</a>',
      34 => '</li>',
      37 => '</strong>',
      38 => '</li>',
      41 => '</strong>',
      43 => '</a>',
      44 => '</li>',
      45 => '</ul>',
      46 => '</div>',
    ];

    $this->buildFileConfigurationPart();
    $result = $this->part->findUnMatchedOpeningTags($opening, $closing);
    $this->assertSame([47 => $opening[47]], $result);
  }

  /**
   * @test
   */
  public function findUnMatchedClosingTagsSimple()
  {
    $opening = [
      0 => '<div class="grid_36 alpha omega">',
      1 => '<div>',
      2 => '<p>',
    ];

    $closing = [
      3 => '</p>',
      4 => '</div>',
      5 => '</div>',
      6 => '</div>',
    ];

    $this->buildFileConfigurationPart();
    $result = $this->part->findUnMatchedClosingTags($opening, $closing);
    $this->assertSame([6 => $closing[6]], $result);
  }

  /**
   * @test
   */
  public function findUnMatchedClosingTagsSimpleStart()
  {
    $opening = [
      1 => '<div class="grid_36 alpha omega">',
      2 => '<div>',
      3 => '<p>',
    ];

    $closing = [
      0 => '</div>',
      4 => '</p>',
      5 => '</div>',
      6 => '</div>',
    ];

    $this->buildFileConfigurationPart();
    $result = $this->part->findUnMatchedClosingTags($opening, $closing);
    $this->assertSame([0 => $closing[0]], $result);
  }

  /**
   * @test
   */
  public function findUnMatchedClosingTags()
  {
    $opening = [
      0 => '<div class="grid_36 alpha omega">',
      1 => '<p>',
      2 => '<a href="/alumni/gather/reunions/spring.php">',
      4 => '<a href="\n  /alumni/gather/networking.php">',
      6 => '<a href="/alumni/gather/chapters/events.php">',
      8 => '<a href="/alumni/gather/homecoming.php">',
      10 => '<a href="/alumni/gather/reunions/fall.php">',
      13 => '<p>',
      15 => '<ul>',
      16 => '<li>',
      17 => '<strong>',
      19 => '<a href="/alumni/events/gustiebreakfast/index.php">',
      22 => '<li>',
      23 => '<strong>',
      25 => '<a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">',
      28 => '<li>',
      29 => '<strong>',
      31 => '<a href="/calendar/gustavus-networking-event-3/40314">',
      34 => '<li>',
      35 => '<strong>',
      38 => '<li>',
      39 => '<strong>',
      41 => '<a href="/athletics/halloffame/">',
    ];

    $closing = [
      3 =>'</a>',
      5 =>'</a>',
      7 =>'</a>',
      9 =>'</a>',
      11 =>'</a>',
      12 =>'</p>',
      14 =>'</p>',
      18 =>'</strong>',
      20 =>'</a>',
      21 =>'</li>',
      24 =>'</strong>',
      26 =>'</a>',
      27 =>'</li>',
      30 =>'</strong>',
      32 =>'</a>',
      33 =>'</li>',
      36 =>'</strong>',
      37 =>'</li>',
      40 =>'</strong>',
      42 =>'</a>',
      43 =>'</li>',
      44 =>'</ul>',
      45 =>'</div>',
      46 =>'</div>',
    ];

    $this->buildFileConfigurationPart();
    $result = $this->part->findUnMatchedClosingTags($opening, $closing);
    $this->assertSame([46 => $closing[46]], $result);
  }

  /**
   * @test
   */
  public function getUnMatchedOffsets()
  {
    $content = '<div class="grid_36 alpha omega">
<ul class="icon-list block-4">
<li><a href="#classes"><i class="alumni-icon-classes"></i><br>Classes</a></li>
<li><a href="#gribly"><i class="alumni-icon-gribly"></i><br>Alumni Gribly</a></li>
<li><a href="#publications"><i class="alumni-icon-publications"></i><br>Publications</a></li>
<li><a href="#social-stream"><i class="alumni-icon-social-stream"></i><br>Social Stream</a></li>
</ul>
</div>
<div id="classes" class="grid_36 alpha omega">
<div class="grid_17 suffix_1 alpha left"><a href="/alumni/connect/classes"><img class="fancy" src="/slir/w330-c16x10/alumni/images/connect/classes.jpg" alt="Classes"></a></div>
<div class="grid_18 omega right">
<h2>Classes</h2>
<p>Your class, your news. Get news and information specific to your graduating class from the class web page. From class letters to reunion details, class pages are the “go to” source for all things related to your year.</p>
<p><a href="/alumni/connect/classes">Find Your Class</a></p>
</div>
<hr class="grid_36 alpha omega"></div>
<div id="gribly" class="grid_36 alpha omega">
<div class="grid_17 prefix_1 omega right"><a href="/search"><img class="fancy" src="/slir/w330-c16x10/alumni/images/connect/gribly.jpg" alt="Alumni Gribly"></a></div>
<div class="grid_18 alpha left">';

    $expected = [
      'opening' => [
        [
          'offset' => 1042,
          'length' => 45,
        ],
        [
          'offset' => 1255,
          'length' => 32,
        ]
      ],
      'closing' => [],
    ];

    $result = $this->call('FileConfigurationPart', 'getUnMatchedOffsets', [$content]);
    $this->assertSame($expected, $result);
  }

  /**
   * @test
   */
  public function indentHTML()
  {
    $content = '<div class="grid_36 alpha omega">
<ul class="icon-list block-4">
<li><a href="#classes"><i class="alumni-icon-classes"></i><br>Classes</a></li>
<li><a href="#gribly"><i class="alumni-icon-gribly"></i><br>Alumni Gribly</a></li>
<li><a href="#publications"><i class="alumni-icon-publications"></i><br>Publications</a></li>
<li><a href="#social-stream"><i class="alumni-icon-social-stream"></i><br>Social Stream</a></li>
</ul>
</div>';

    $expected = '<div class="grid_36 alpha omega">
  <ul class="icon-list block-4">
    <li><a href="#classes"><i class="alumni-icon-classes"></i><br>Classes</a></li>
    <li><a href="#gribly"><i class="alumni-icon-gribly"></i><br>Alumni Gribly</a></li>
    <li><a href="#publications"><i class="alumni-icon-publications"></i><br>Publications</a></li>
    <li><a href="#social-stream"><i class="alumni-icon-social-stream"></i><br>Social Stream</a></li>
  </ul>
</div>';

    $result = $this->call('FileConfigurationPart', 'indentHTML', [$content]);
    $this->assertSame($expected, $result);
  }

  /**
   * @test
   */
  public function indentHTMLComplex()
  {
    $content = '<div class="grid_36 alpha omega">
<ul class="icon-list block-4">
<li><a href="#classes"><i class="alumni-icon-classes"></i><br>Classes</a></li>
<li><a href="#gribly"><i class="alumni-icon-gribly"></i><br>Alumni Gribly</a></li>
<li><a href="#publications"><i class="alumni-icon-publications"></i><br>Publications</a></li>
<li><a href="#social-stream"><i class="alumni-icon-social-stream"></i><br>Social Stream</a></li>
</ul>
</div>
<div id="classes" class="grid_36 alpha omega">
<div class="grid_17 suffix_1 alpha left"><a href="/alumni/connect/classes"><img class="fancy" src="/slir/w330-c16x10/alumni/images/connect/classes.jpg" alt="Classes"></a></div>
<div class="grid_18 omega right">
<h2>Classes</h2>
<p>Your class, your news. Get news and information specific to your graduating class from the class web page. From class letters to reunion details, class pages are the “go to” source for all things related to your year.</p>
<p><a href="/alumni/connect/classes">Find Your Class</a></p>
</div>
<hr class="grid_36 alpha omega"></div>
<div id="gribly" class="grid_36 alpha omega">
<div class="grid_17 prefix_1 omega right"><a href="/search"><img class="fancy" src="/slir/w330-c16x10/alumni/images/connect/gribly.jpg" alt="Alumni Gribly"></a></div></div>
<div class="grid_18 alpha left">';

    $expected = '<div class="grid_36 alpha omega">
  <ul class="icon-list block-4">
    <li><a href="#classes"><i class="alumni-icon-classes"></i><br>Classes</a></li>
    <li><a href="#gribly"><i class="alumni-icon-gribly"></i><br>Alumni Gribly</a></li>
    <li><a href="#publications"><i class="alumni-icon-publications"></i><br>Publications</a></li>
    <li><a href="#social-stream"><i class="alumni-icon-social-stream"></i><br>Social Stream</a></li>
  </ul>
</div>
<div id="classes" class="grid_36 alpha omega">
  <div class="grid_17 suffix_1 alpha left"><a href="/alumni/connect/classes"><img class="fancy" src="/slir/w330-c16x10/alumni/images/connect/classes.jpg" alt="Classes"></a></div>
  <div class="grid_18 omega right">
    <h2>Classes</h2>
    <p>Your class, your news. Get news and information specific to your graduating class from the class web page. From class letters to reunion details, class pages are the “go to” source for all things related to your year.</p>
    <p><a href="/alumni/connect/classes">Find Your Class</a></p>
  </div>
  <hr class="grid_36 alpha omega"></div>
<div id="gribly" class="grid_36 alpha omega">
  <div class="grid_17 prefix_1 omega right"><a href="/search"><img class="fancy" src="/slir/w330-c16x10/alumni/images/connect/gribly.jpg" alt="Alumni Gribly"></a></div></div>
<div class="grid_18 alpha left">';

    $result = $this->call('FileConfigurationPart', 'indentHTML', [$content]);
    $this->assertSame($expected, $result);
  }

  /**
   * @test
   */
  public function indentHTMLComplexEndingWithIndent()
  {
    $content = '<div class="grid_36 alpha omega">
<ul class="icon-list block-4">
<li><a href="#classes"><i class="alumni-icon-classes"></i><br>Classes</a></li>
<li><a href="#gribly"><i class="alumni-icon-gribly"></i><br>Alumni Gribly</a></li>
<li><a href="#publications"><i class="alumni-icon-publications"></i><br>Publications</a></li>
<li><a href="#social-stream"><i class="alumni-icon-social-stream"></i><br>Social Stream</a></li>
</ul>
</div>
<div id="classes" class="grid_36 alpha omega">
<div class="grid_17 suffix_1 alpha left"><a href="/alumni/connect/classes"><img class="fancy" src="/slir/w330-c16x10/alumni/images/connect/classes.jpg" alt="Classes"></a></div>
<div class="grid_18 omega right">
<h2>Classes</h2>
<p>Your class, your news. Get news and information specific to your graduating class from the class web page. From class letters to reunion details, class pages are the “go to” source for all things related to your year.</p>
<p><a href="/alumni/connect/classes">Find Your Class</a></p>
</div>
<hr class="grid_36 alpha omega"></div>
<div id="gribly" class="grid_36 alpha omega">
<div class="grid_17 prefix_1 omega right"><a href="/search"><img class="fancy" src="/slir/w330-c16x10/alumni/images/connect/gribly.jpg" alt="Alumni Gribly"></a></div>
<div class="grid_18 alpha left">';

    $expected = '<div class="grid_36 alpha omega">
  <ul class="icon-list block-4">
    <li><a href="#classes"><i class="alumni-icon-classes"></i><br>Classes</a></li>
    <li><a href="#gribly"><i class="alumni-icon-gribly"></i><br>Alumni Gribly</a></li>
    <li><a href="#publications"><i class="alumni-icon-publications"></i><br>Publications</a></li>
    <li><a href="#social-stream"><i class="alumni-icon-social-stream"></i><br>Social Stream</a></li>
  </ul>
</div>
<div id="classes" class="grid_36 alpha omega">
  <div class="grid_17 suffix_1 alpha left"><a href="/alumni/connect/classes"><img class="fancy" src="/slir/w330-c16x10/alumni/images/connect/classes.jpg" alt="Classes"></a></div>
  <div class="grid_18 omega right">
    <h2>Classes</h2>
    <p>Your class, your news. Get news and information specific to your graduating class from the class web page. From class letters to reunion details, class pages are the “go to” source for all things related to your year.</p>
    <p><a href="/alumni/connect/classes">Find Your Class</a></p>
  </div>
  <hr class="grid_36 alpha omega"></div>
<div id="gribly" class="grid_36 alpha omega">
  <div class="grid_17 prefix_1 omega right"><a href="/search"><img class="fancy" src="/slir/w330-c16x10/alumni/images/connect/gribly.jpg" alt="Alumni Gribly"></a></div>
  <div class="grid_18 alpha left">';

    $result = $this->call('FileConfigurationPart', 'indentHTML', [$content]);
    $this->assertSame($expected, $result);
  }

  /**
   * @test
   */
  public function indentHTMLComplexWithList()
  {

    $content = '<div class="grid_36 alpha omega">

  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

<p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.</li>
  <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
  <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
  <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
  <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
</ul>
</div>
</div>
';

    $expected = '<div class="grid_36 alpha omega">

  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

  <p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
  <ul>
    <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.</li>
    <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
    <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
    <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
    <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
  </ul>
</div>
</div>
';

    $result = $this->call('FileConfigurationPart', 'indentHTML', [$content]);
    $this->assertSame($expected, $result);
  }

  /**
   * @test
   */
  public function getAllTagsByType()
  {
    $content = '<p><span style="display: inline !important;"></span>
      </p>';

    $result = $this->call('FileConfigurationPart', 'getAllTagsByType', [$content]);

    $expected = [
      'opening' => [
        'result' => [
          0 => [
            0 => '<p>',
            1 => 0,
          ],
          1 => [
            0 => '<span style="display: inline !important;">',
            1 => 3,
          ],
        ],
        'flattened' => [
          0 => '<p>',
          1 => '<span style="display: inline !important;">',
        ],
      ],
      'closing' => [
        'result' => [
          2 => [
            0 => '</span>',
            1 => 45,
          ],
          3 => [
            0 => '</p>',
            1 => 59,
          ],
        ],
        'flattened' => [
          2 => '</span>',
          3 => '</p>',
        ],
      ],
      'selfClosing' => [
        'result' => [],
        'flattened' => [],
      ],
    ];

    $this->assertSame($expected, $result);
  }
}