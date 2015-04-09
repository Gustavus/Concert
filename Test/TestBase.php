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
   * Path to an old template file
   */
  const TEMPLATE_FILE_DIR = '/cis/lib/Gustavus/Concert/Test/Scripts/pages/';

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
   * Token for overrides
   *
   * @var array
   */
  private static $overrideToken;

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
    self::$overrideToken = [];
    $iniSetToken = override_function('ini_set',
        function($varname, $newvalue) use (&$iniSetToken) {
          if ($varname === 'memory_limit') {
            return;
          }
          call_overridden_func($iniSetToken, null, $varname, $newvalue);
        }
    );
    self::$overrideToken['ini_set'] = $iniSetToken;

    self::$testFileDir = sprintf('%s/files/', __DIR__);
  }

  /**
   * Sets up environment for every test if parent::setUp() is called.
   *
   * @return void
   */
  public function setUp()
  {
    $_SERVER['HTTP_HOST'] = 'beta.gac.edu';
    Config::$stagingDir       = self::$testFileDir . '/staging/';
    Config::$draftDir         = self::$testFileDir . '/drafts/';
    Config::$editableDraftDir = self::$testFileDir . '/editableDrafts/';

    Config::$allowableDraftTypes = [
      Config::PUBLIC_DRAFT,
      Config::PRIVATE_DRAFT,
      Config::PENDING_PUBLISH_DRAFT,
    ];

    $dbal = DBAL::getDBAL('testDB', $this->getDBH());
    $this->set('PermissionsManager', 'dbal', $dbal);
    $this->set('FileManager', 'cachedDrafts', []);
    $this->set('FileManager', 'cachedDraftsByName', []);
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
    $this->set('Controllers\SharedController', 'messages', []);
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
    self::$overrideToken = null;
    parent::tearDownAfterClass();
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
   * Checks to see if a message exists in our message array.
   *
   * @param  string $searchMessage Message we want to find in our messages
   * @param  array  $messages      Array of arrays with keys of type and message
   * @return void
   */
  protected function assertMessageInMessages($searchMessage, $messages)
  {
    $found = false;
    foreach ($messages as $message) {
      if (strpos($message['message'], $searchMessage) !== false) {
        $found = true;
        break;
      }
    }
    $this->assertTrue($found, sprintf('We were unable to find the message: "%s" in %s', $searchMessage, print_r($messages, true)));
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
      'scriptcontent' => [],
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
      'scriptcontent' => [],
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
      'scriptcontent' => [],
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
      'scriptcontent' => [],
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
        1 => '=$test;',
        3 => '//arst',
      ],
      'scriptcontent' => [],
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
   * File 6 contents to test (Contains javascript)
   * @var string
   */
  protected static $indexSixContents = '
<p>This is some html content</p>

<?=$test;?>

more html
<script type="text/javascript">javascript</script>
arst
<script>more scripts</script>
<?php //arst';

  /**
   * File 6 configuration array
   * @var array
   */
  protected static $indexSixConfigArray = [
      'phpcontent' => [
        1 => '=$test;',
        7 => ' //arst',
      ],
      'scriptcontent' => [
        3 => '<script type="text/javascript">javascript</script>',
        5 => '<script>more scripts</script>',
      ],
      'content' => [
        0 => '
<p>This is some html content</p>

',
        2 => '

more html
',
        4 => '
arst
',
        6 => '
',
      ],
    ];

  /**
   * File 7 contents to test
   * @var string
   */
  protected static $indexSevenContents = '<?php
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

echo $config["content"];
?><?php
$config["focusBox"] = ob_get_contents();
?>';

  /**
   * File 7 configuration array
   * @var array
   */
  protected static $indexSevenConfigArray = [
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

echo $config["content"];
',
        3 => '
$config["focusBox"] = ob_get_contents();
',
      ],
      'scriptcontent' => [],
      'content' => [
        1 => '

<p>This is some html content</p>

'
      ],
    ];

  /**
   * File 8 contents to test
   * @var string
   */
  protected static $indexEightContents = '<?php
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

ob_start();?><script>hello</script><script>arstarst</script><p>This is some html content</p><?php

$config["content"] .= ob_get_contents();

echo $config["content"];
?><?php
$config["focusBox"] = ob_get_contents();
?>';

  /**
   * File 8 configuration array
   * @var array
   */
  protected static $indexEightConfigArray = [
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

ob_start();',
        4 => '

$config["content"] .= ob_get_contents();

echo $config["content"];
',
        5 => '
$config["focusBox"] = ob_get_contents();
',
      ],
      'scriptcontent' => [
        1 => '<script>hello</script>',
        2 => '<script>arstarst</script>',
      ],
      'content' => [
        3 => '<p>This is some html content</p>'
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