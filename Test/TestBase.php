<?php
/**
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test;

use Gustavus\Test\TestEM,
  Gustavus\Test\TestObject,
  Gustavus\Concert\FileManager,
  Gustavus\Concert\Config,
  Gustavus\Concert\Controllers\SharedController,
  Gustavus\Doctrine\DBAL,
  Gustavus\GACCache\Workers\ArrayFactoryWorker,
  Gustavus\GACMailer\Test\MockMailer,
  Campus\Pull\People as CampusPeople;

/**
 * Base class for testing
 *
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class TestBase extends TestEM
{
  /**
   * Doctrine DBAL test instance to use in forwarded classes
   * @var Doctrine\DBAL\Connection
   */
  public static $pdo;

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
   * FileManager object to do our testing on
   * @var FileManager
   */
  protected $fileManager;

  /**
   * Array of the original get properties to restore
   * @var array
   */
  private $origGet;

  /**
   * MockMailer
   * @var MockMailer
   */
  protected $mockMailer;

  /**
   * PeoplePuller for testing
   *
   * @var \Campus\Pull\People
   */
  protected $peoplePuller;

  /**
   * Mapping of Generated Entities to their namespace for testing
   * @var array
   */
  private static $entityMappings = [
    'Sites'       => '\Gustavus\Concert\Setup\GeneratedEntities\Sites',
    'Permissions' => '\Gustavus\Concert\Setup\GeneratedEntities\Permissions',
    'Locks'       => '\Gustavus\Concert\Setup\GeneratedEntities\Locks',
    'StagedFiles' => '\Gustavus\Concert\Setup\GeneratedEntities\StagedFiles',
    'Drafts'      => '\Gustavus\Concert\Setup\GeneratedEntities\Drafts',
    'Revision'    => '\Gustavus\Revisions\Test\Entities\Revision',
    'RevisionData'=> '\Gustavus\Revisions\Test\Entities\RevisionData',
  ];

  /**
   * Class constructor
   */
  public static function setUpBeforeClass()
  {
    self::$testFileDir = sprintf('%s/files/', __DIR__);
  }

  /**
   * Sets up environment for every test if parent::setUp() is called.
   *
   * @return void
   */
  public function setUp()
  {
    Config::$stagingDir       = self::$testFileDir . '/staging/';
    Config::$draftDir         = self::$testFileDir . '/drafts/';
    Config::$editableDraftDir = self::$testFileDir . '/editableDrafts/';

    $dbal = DBAL::getDBAL('testDB', $this->getDBH());
    $this->set('PermissionsManager', 'dbal', $dbal);
    $this->set('Utility', 'dbal', $dbal);
    $this->setUpCaches();
    $this->origGet = $_GET;
    $this->mockMailer = new MockMailer();
  }

  /**
   * Sets up environment for every test if parent::tearDown() is called.
   *
   * @return void
   */
  public function tearDown()
  {
    $this->set('PermissionsManager', 'dbal', null);
    $cache = $this->call('PermissionsManager', 'getCache');
    if (is_object($cache)) {
      $cache->clearAllValues();
    }
    $this->set('PermissionsManager', 'cache', null);
    $_GET = $this->origGet;
    unset($this->mockMailer);
  }

  /**
   * Tears down the environment after each test class is done with tests
   * @return void
   */
  public static function tearDownAfterClass()
  {
    self::removeFiles(self::$testFileDir);
  }

  /**
   * Sets up caches to use for testing
   * @return  void
   */
  protected function setUpCaches()
  {
    $this->set('PermissionsManager', 'cache', (new ArrayFactoryWorker())->buildDataStore());
  }

  /**
   * Recursively removes files
   *
   * @param  string $dir Directory to remove files from
   * @return void
   */
  protected static function removeFiles($dir)
  {
    $files = scandir($dir);
    foreach ($files as $file) {
      if ($file === '.' || $file === '..') {
        continue;
      }
      $file = $dir . '/' . $file;
      if (is_dir($file)) {
        self::removeFiles($file);
        rmdir($file);
        continue;
      }
      unlink($file);
    }
  }

  /**
   * Builds the FileManager object to use for testing
   * @param  string $file Filename
   * @param  string $user username
   * @return void
   */
  protected function buildFileManager($user, $file, $srcFilePath = null)
  {
    $this->fileManager = new TestObject(new FileManager($user, $file, $srcFilePath, DBAL::getDBAL('testDB', $this->getDBH())));
  }

  /**
   * Checks to see if the contents of an email match the expected values
   *
   * @param  array $expectedRecipients Array of expected recipients keyed by recipient type. ie. 'bcc', 'cc', 'to'
   * @param  string $expectedSubject   Expected subject
   * @param  string $expectedBody      Expected Body
   * @return void
   */
  protected function checkSentEmailContents($expectedRecipients, $expectedSubject, $expectedBody, $checkContains = false)
  {
    // get the "sent" message
    $message = $this->mockMailer->popMessage();

    $recipientTypes = array_keys($expectedRecipients);
    foreach ($recipientTypes as $recipientType) {
      $getter = 'get' . ucfirst($recipientType);
      $this->assertSame($expectedRecipients[$recipientType], $message->{$getter}());
    }
    $assertion = $checkContains ? 'assertContains' : 'assertSame';
    $this->{$assertion}($expectedSubject, $message->getSubject());
    $this->{$assertion}($expectedBody, $message->getBody());
  }

  /**
   * Finds an employee username
   *
   * @param  boolean $increment Whether to get the current person from the people puller, or the next
   * @return string
   */
  protected function findEmployeeUsername($increment = false)
  {
    if (empty($this->peoplePuller)) {
      $this->peoplePuller = new CampusPeople((new TestObject(new SharedController))->getApiKey());
      $this->peoplePuller->setCampusDepartment('Gustavus Technology Services');
    }
    if ($increment) {
      $this->peoplePuller->next();
    }
    return $this->peoplePuller->current()->getUsername();
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

  /**
   * Sets up the DB using the entities and their namespace mapping
   *
   * @param  array  $entities Entity names
   * @return \Doctrine\ORM\EntityManager
   */
  protected function constructDB(array $entities)
  {
    foreach ($entities as &$entity) {
      $entity = self::$entityMappings[$entity];
    }
    return $this->setUpDB('/cis/lib/Gustavus/Concert/Setup/GeneratedEntities', $entities);
  }

  /**
   * Builds the DB with all the tables
   *
   * @return void
   */
  protected function buildDB()
  {
    $this->constructDB(['Locks', 'Permissions', 'Sites', 'StagedFiles', 'Drafts', 'Revision', 'RevisionData']);
  }

  /**
   * Destroy DB tables
   *
   * @return void
   */
  protected function destructDB()
  {
    $this->destroyDB('/cis/lib/Gustavus/Concert/Setup/GeneratedEntities');
  }

  /**
   * {@inheritdoc}
   */
  protected function set($class, $property, $value)
  {
    if (is_object($class)) {
      return parent::set($class, $property, $value);
    }
    parent::set('\Gustavus\Concert\\' . $class, $property, $value);
  }

  /**
   * {@inheritdoc}
   */
  protected function get($class, $property)
  {
    if (is_object($class)) {
      return parent::get($class, $property);
    }
    parent::get('\Gustavus\Concert\\' . $class, $property);
  }

  /**
   * {@inheritdoc}
   */
  protected function call($object, $method, array $arguments = array())
  {
    if (is_object($object)) {
      return parent::call($object, $method, $arguments);
    }
    return parent::call('\Gustavus\Concert\\' . $object, $method, $arguments);
  }
}