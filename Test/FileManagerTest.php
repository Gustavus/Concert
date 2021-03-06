<?php
/**
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test;

use Gustavus\Concert\FileManager,
  Gustavus\Concert\FileConfiguration,
  Gustavus\Concert\PermissionsManager,
  Gustavus\Concert\Config,
  Gustavus\Concert\Utility,
  Gustavus\Doctrine\DBAL,
  Gustavus\Extensibility\Filters,
  Gustavus\Concourse\RoutingUtil,
  Gustavus\Revisions\API as RevisionsAPI,
  Gustavus\Utility\File;

/**
 * Class to test FileManager class
 *
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class FileManagerTest extends TestBase
{
  /**
   * Sets up environment for every test
   *
   * @return void
   */
  public function setUp()
  {
    if (!is_dir(self::$testFileDir . 'drafts')) {
      mkdir(self::$testFileDir . 'drafts');
    }
    if (!is_dir(self::$testFileDir . 'staged')) {
      mkdir(self::$testFileDir . 'staged');
    }
    if (!is_dir(self::$testFileDir . 'editableDrafts')) {
      mkdir(self::$testFileDir . 'editableDrafts');
    }
    parent::setUp();
  }

  /**
   * Tears down the environment for every test
   *
   * @return void
   */
  public function tearDown()
  {
    unset($this->fileManager);
    parent::tearDown();
    self::removeFiles(self::$testFileDir);
    Filters::clear(RevisionsAPI::RENDER_REVISION_FILTER);
  }

  /**
   * Checks to see if drafts are the same or not
   *   Makes sure the date isn't empty, but doesn't check to make sure it is the correct date because that isn't that easy to do testing
   *
   * @param  array $expected Expected drafts. (Without dates)
   * @param  array $actual   Actual drafts.
   * @return void
   */
  private function assertDraftsAreSame($expected, $actual)
  {
    if (isset($actual[0]) && is_array($actual[0])) {
      foreach ($actual as &$draft) {
        if (isset($draft['date'])) {
          $this->assertNotEmpty($draft['date']);
          unset($draft['date']);
        }
      }
    } else {
      if (isset($actual['date'])) {
        $this->assertNotEmpty($actual['date']);
        unset($actual['date']);
      }
    }

    // now we don't have any dates we need to worry about. Now make sure they are the same.
    $this->assertSame($expected, $actual);
  }

  /**
   * @test
   */
  public function buildAndGetFileConfigurationArray()
  {
    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');

    $this->assertSame(self::$indexConfigArray, $this->fileManager->getFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildConfigAndAssembleLargeFile()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::TEMPLATE_FILE_DIR, 'test']);
    $this->buildFileManager('bvisto', self::TEMPLATE_FILE_DIR . 'largeFile.php');

    $configuration = $this->fileManager->getFileConfigurationArray();

    $file = $this->fileManager->assembleFile(true);

    $this->assertNotEmpty($configuration['content'][9]);
    $this->assertContains('the archivist for more information', $file);
  }

  /**
   * @test
   * @expectedException \RuntimeException
   */
  public function buildAndGetFileConfigurationArrayNotFound()
  {
    $this->buildFileManager('testuser', self::$testFileDir . 'indexarst.php');

    $this->assertSame(self::$indexConfigArray, $this->fileManager->getFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildAndGetFileConfigurationArrayFromOtherFile()
  {
    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'indexCpy.php', self::$testFileDir . 'index.php');

    $this->assertSame(self::$indexConfigArray, $this->fileManager->getFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildAndGetFileConfigurationArrayOnlyPHP()
  {
    file_put_contents(self::$testFileDir . 'index2.php', self::$indexTwoContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index2.php');

    $this->assertSame(self::$indexTwoConfigArray, $this->fileManager->getFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildFileConfigurationArrayOnlyHTML()
  {
    file_put_contents(self::$testFileDir . 'index3.php', self::$indexThreeContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index3.php');

    $this->assertSame(self::$indexThreeConfigArray, $this->fileManager->buildFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildFileConfigurationArrayHTMLFirst()
  {
    file_put_contents(self::$testFileDir . 'index4.php', self::$indexThreeContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index4.php');

    $this->assertSame(self::$indexThreeConfigArray, $this->fileManager->buildFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildFileConfigurationArrayHTMLFirstAlternatingPHP()
  {
    file_put_contents(self::$testFileDir . 'index5.php', self::$indexThreeContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index5.php');

    $this->assertSame(self::$indexThreeConfigArray, $this->fileManager->buildFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildFileConfigurationArrayWithScripts()
  {
    file_put_contents(self::$testFileDir . 'index6.php', self::$indexSixContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index6.php');

    $this->assertSame(self::$indexSixConfigArray, $this->fileManager->buildFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildFileConfigurationArrayWithPHPTagsRightNextToEachOther()
  {
    file_put_contents(self::$testFileDir . 'index7.php', self::$indexSevenContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index7.php');

    $this->assertSame(self::$indexSevenConfigArray, $this->fileManager->buildFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildFileConfigurationArrayWithContentTypesNextToEachOther()
  {
    file_put_contents(self::$testFileDir . 'index8.php', self::$indexEightContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index8.php');

    $this->assertSame(self::$indexEightConfigArray, $this->fileManager->buildFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildAndGetFileConfiguration()
  {
    file_put_contents(self::$testFileDir . 'index5.php', self::$indexThreeContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index5.php');

    $configuration = $this->fileManager->getFileConfiguration();
    $this->assertInstanceOf('\Gustavus\Concert\FileConfiguration', $configuration);
    $this->assertSame(1, count($configuration->getFileConfigurationParts()));
  }

  /**
   * @test
   */
  public function assembleFile()
  {
    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');

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

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');

    $file = $this->fileManager->assembleFile(true);

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
   */
  public function makeEditableDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');

    $filename = $this->fileManager->makeEditableDraft();
    $this->assertContains(self::$testFileDir, $filename);

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

    $draftFile = file_get_contents($filename);
    unlink($filename);

    $this->assertSame($expected, $draftFile);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function makeEditableDraftFromExisting()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles', 'Drafts']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');

    $edits = ['1' => '<p>This is some edited html content</p>'];

    $this->fileManager->editFile($edits);
    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $filename = $this->fileManager->makeEditableDraft(true);
    $this->assertContains(self::$testFileDir, $filename);

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

<p>This is some edited html content</p>

</div>%s

<?php

$config["content"] .= ob_get_contents();

echo $config["content"];', Config::EDITABLE_DIV_CLOSING_IDENTIFIER);

    $draftFile = file_get_contents($filename);
    unlink($filename);

    $this->assertSame($expected, $draftFile);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function makeEditableDraftNoLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles', 'Drafts']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testuser1', self::$testFileDir . 'index.php');

    $filename = $this->fileManager->makeEditableDraft();
    $this->assertFalse($filename);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function makeEditableDraftDraftDirNotExists()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');

    $filename = $this->fileManager->makeEditableDraft();


    $this->assertContains(self::$testFileDir, $filename);

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

    $draftFile = file_get_contents($filename);
    unlink($filename);

    $this->assertSame($expected, $draftFile);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function makeEditableDraftWithPHPInsideOfDiv()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles', 'Drafts']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', '/cis/lib/Gustavus/Concert/Test/Scripts/pages/', 'test']);

    $this->buildFileManager('testuser', '/cis/lib/Gustavus/Concert/Test/Scripts/pages/phpInsideOfDiv.php');

    $filename = $this->fileManager->makeEditableDraft(true);
    $this->assertContains(self::$testFileDir, $filename);


    //$edits = ['1' => '<p>This is some edited html content</p>'];

    // $this->fileManager->editFile($edits);
    // $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $expected = file_get_contents('/cis/lib/Gustavus/Concert/Test/Scripts/pages/expectedEditablePHPInsideOfDiv.php');

    $draftFile = file_get_contents($filename);
    unlink($filename);

    $this->assertSame($expected, $draftFile);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function makeEditableDraftWithClassesAndFunctions()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $content = '<?php
// use template getter...
// must use $config["templatepreference"]
$config = [
  "title" => "Some Title",
  "subTitle" => "Some Sub Title",
  "content" => "This is some content.",
];

$config["content"] .= executeSomeContentarstarst();

function executeSomeContentarstarst()
{
  return "This is some executed content.";
}

class testing
{
  public function test() {
    echo \'arst\';
  }
}

ob_start();
?>

<p>This is some html content</p>

<?php

$config["content"] .= ob_get_contents();
ob_end_clean();

echo $config["content"];';

    file_put_contents(self::$testFileDir . 'index.php', $content);
    // load the file.
    $file = (new File(self::$testFileDir . 'index.php'))->loadAndEvaluate();

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');

    $filename = $this->fileManager->makeEditableDraft();
    $this->assertContains(self::$testFileDir, $filename);

    $expected = sprintf('<?php
// use template getter...
// must use $config["templatepreference"]
$config = [
  \'title\' => \'Some Title\',
  \'subTitle\' => \'Some Sub Title\',
  \'content\' => \'This is some content.\',
];
$config[\'content\'] .= executeSomeContentarstarst();
ob_start();
?>

<div class="editable" data-index="1">

<p>This is some html content</p>

</div>%s

<?php

$config["content"] .= ob_get_contents();
ob_end_clean();

echo $config["content"];', Config::EDITABLE_DIV_CLOSING_IDENTIFIER);

    $draftFile = file_get_contents($filename);
    unlink($filename);

    $this->assertSame($expected, $draftFile);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function makeEditableDraftWithMagicDirConstant()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $content = '<?php
// use template getter...
// must use $config["templatepreference"]
$config = [
  "title" => "Some Title",
  "subTitle" => "Some Sub Title",
  "content" => "This is some content.",
];

require_once(\'/cis/www/calendar/classes/puller.class.php\');
$cp = new CalendarPuller(array(
  \'maxPastEvents\'     => 5,
  \'maxUpcomingEvents\'       => 10,
  \'sponsors\'        => array(\'GWIL\', \'Gustavus Women in Leadership\'),
  \'eventView\' => __DIR__ . \'/views/event.html\',
));

ob_start();
?>

<p>This is some html content</p>

<?php

$config["content"] .= ob_get_contents();

echo $config["content"];';

    file_put_contents(self::$testFileDir . 'index.php', $content);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');

    $filename = $this->fileManager->makeEditableDraft();
    $this->assertContains(self::$testFileDir, $filename);

    $expected = sprintf('<?php
// use template getter...
// must use $config["templatepreference"]
$config = [
  "title" => "Some Title",
  "subTitle" => "Some Sub Title",
  "content" => "This is some content.",
];

require_once(\'/cis/www/calendar/classes/puller.class.php\');
$cp = new CalendarPuller(array(
  \'maxPastEvents\'     => 5,
  \'maxUpcomingEvents\'       => 10,
  \'sponsors\'        => array(\'GWIL\', \'Gustavus Women in Leadership\'),
  \'eventView\' => \'%s\' . \'/views/event.html\',
));

ob_start();
?>

<div class="editable" data-index="1">

<p>This is some html content</p>

</div>%s

<?php

$config["content"] .= ob_get_contents();

echo $config["content"];', rtrim(self::$testFileDir, '/'), Config::EDITABLE_DIV_CLOSING_IDENTIFIER);

    $draftFile = file_get_contents($filename);
    unlink($filename);

    $this->assertSame($expected, $draftFile);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function makeTemporaryFile()
  {
    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');

    $filename = $this->fileManager->makeTemporaryFile();
    $this->assertContains(self::$testFileDir, $filename);

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
?>

<p>This is some html content</p>

<?php

$config["content"] .= ob_get_contents();

echo $config["content"];';

    $tmpFile = file_get_contents($filename);
    unlink($filename);

    $this->assertSame($expected, $tmpFile);
  }

  /**
   * @test
   */
  public function makeTemporaryFileAdjusted()
  {
    $contents = '<?php
// use template getter...
// must use $config["templatepreference"]
$config = [
  "title" => "Some Title",
  "subTitle" => "Some Sub Title",
  "content" => "This is some content.",
];

require(__DIR__ . "/arst.php");
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
    file_put_contents(self::$testFileDir . 'index.php', $contents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');

    $filename = $this->fileManager->makeTemporaryFile();
    $this->assertContains(self::$testFileDir, $filename);

    $expected = sprintf('<?php
// use template getter...
// must use $config["templatepreference"]
$config = [
  "title" => "Some Title",
  "subTitle" => "Some Sub Title",
  "content" => "This is some content.",
];

require(\'%s\' . "/arst.php");
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

echo $config["content"];', rtrim(self::$testFileDir, '/'));

    $tmpFile = file_get_contents($filename);
    unlink($filename);

    $this->assertSame($expected, $tmpFile);
  }

  /**
   * @test
   */
  public function getDraftFileName()
  {
    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $actual = $this->fileManager->getDraftFileName();
    $this->assertSame(md5(md5(self::$testFileDir . 'index.php') . '-testuser'), $actual);
  }

  /**
   * @test
   */
  public function saveAndGetDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->assertTrue(false !== $this->fileManager->saveDraft(Config::PRIVATE_DRAFT, ['jerry', 'testuser1']));

    $expected = [
      [
        'destFilepath'    => self::$testFileDir . 'index.php',
        'draftFilename'   => $this->fileManager->getDraftFileName(),
        'type'            => Config::PRIVATE_DRAFT,
        'username'        => 'testuser',
        'additionalUsers' => ['jerry', 'testuser1'],
      ],
    ];

    $this->assertDraftsAreSame($expected, $this->fileManager->getDrafts());

    $expected[0]['type'] = Config::PUBLIC_DRAFT;
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);
    $this->assertDraftsAreSame($expected, $this->fileManager->getDrafts());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function getDraftNotAllowed()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $fileName = $this->fileManager->saveDraft(Config::PRIVATE_DRAFT, ['jerry', 'testuser1']);

    $this->assertTrue(false !== $fileName);

    $this->buildFileManager('arst', self::$testFileDir . 'index.php');
    $this->assertFalse($this->fileManager->getDraft(basename($fileName)));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function addUsersToDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftFilePath = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry', 'testuser1']);

    $this->assertTrue($draftFilePath !== false);

    $this->assertTrue($this->fileManager->addUsersToDraft(basename($draftFilePath), ['bvisto']));

    $draft = $this->fileManager->getDraft(basename($draftFilePath));
    $this->assertSame(['bvisto'], $draft['additionalUsers']);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function addUsersToDraftNotOwned()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftFilePath = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry', 'testuser1']);

    $this->assertTrue($draftFilePath !== false);

    $this->buildFileManager('jerry', self::$testFileDir . 'index.php');

    $this->assertFalse($this->fileManager->addUsersToDraft(basename($draftFilePath), ['bvisto']));

    $draft = $this->fileManager->getDraft(basename($draftFilePath));
    $this->assertSame(['jerry', 'testuser1'], $draft['additionalUsers']);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function addUsersToPrivateDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftFilePath = $this->fileManager->saveDraft(Config::PRIVATE_DRAFT, ['jerry', 'testuser1']);

    $this->assertTrue($draftFilePath !== false);

    $this->assertFalse($this->fileManager->addUsersToDraft(basename($draftFilePath), ['bvisto']));

    $draft = $this->fileManager->getDraft(basename($draftFilePath));
    $this->assertSame(['jerry', 'testuser1'], $draft['additionalUsers']);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveDraftNotAllowable()
  {
    $origAllowableDrafts = Config::$allowableDraftTypes;
    Config::$allowableDraftTypes = [Config::PUBLIC_DRAFT];
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->assertTrue($this->fileManager->acquireLock());

    $this->assertFalse($this->fileManager->saveDraft(Config::PRIVATE_DRAFT));
    $this->destructDB();
    Config::$allowableDraftTypes = $origAllowableDrafts;
  }

  /**
   * @test
   */
  public function saveDraftNoLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser1', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->assertFalse($this->fileManager->saveDraft(Config::PRIVATE_DRAFT));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function destroyDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);
    $this->assertNotEmpty($this->fileManager->getDrafts());

    $this->fileManager->destroyDraft($this->fileManager->getDraftFileName());

    $this->assertEmpty($this->fileManager->getDrafts());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function getDraftsByType()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);
    $this->assertEmpty($this->fileManager->getDrafts(Config::PRIVATE_DRAFT));

    $this->assertNotEmpty($this->fileManager->getDrafts(Config::PENDING_PUBLISH_DRAFT));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function getDraftsByTypeArray()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser1', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser2', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);
    $this->fileManager->username = 'testuser1';
    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);
    $this->fileManager->username = 'testuser2';
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->assertSame(3, count($this->fileManager->getDrafts()));
    $this->assertSame(2, count($this->fileManager->getDrafts([Config::PRIVATE_DRAFT, Config::PUBLIC_DRAFT])));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function getDraftForUser()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);

    $this->assertNotEmpty($this->fileManager->getDraftForUser('testuser'));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findDraftsForCurrentUser()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser1', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->fileManager->username = 'testuser1';
    $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);

    $this->assertSame(2, count($this->fileManager->findDraftsForCurrentUser()));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findDraftsForCurrentUserAdmin()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['adminUser', self::$testFileDir, 'siteAdmin']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);

    // simulate new file manager for the admin user
    $this->fileManager->username = 'adminUser';
    // admin user now has a draft
    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->assertSame(2, count($this->fileManager->findDraftsForCurrentUser()));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findDraftsForCurrentUserMultiplePublicDrafts()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['adminUser', self::$testFileDir, 'admin']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    // simulate new file manager for the admin user
    $this->fileManager->username = 'adminUser';
    // admin user now has a draft
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->assertSame(2, count($this->fileManager->findDraftsForCurrentUser()));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findDraftsForCurrentUserAdminPrivateDrafts()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser1', self::$testFileDir, 'test']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['adminUser', self::$testFileDir, 'admin']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->fileManager->username = 'testuser1';
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    // simulate new file manager for the admin user
    $this->fileManager->username = 'adminUser';
    // admin user now has a draft
    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->assertSame(2, count($this->fileManager->findDraftsForCurrentUser()));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findDraftsForCurrentUserAdminSpecificDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser1', self::$testFileDir, 'test']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['adminUser', self::$testFileDir, 'admin']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->fileManager->username = 'testuser1';
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    // simulate new file manager for the admin user
    $this->fileManager->username = 'adminUser';
    // admin user now has a draft
    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $result = $this->fileManager->findDraftsForCurrentUser($this->fileManager->getDraftFileName('testuser1'));
    $this->assertSame(1, count($result));

    $expected = [[
      'destFilepath'    => self::$testFileDir . 'index.php',
      'draftFilename'   => $this->fileManager->getDraftFileName('testuser1'),
      'type'            => Config::PUBLIC_DRAFT,
      'username'        => 'testuser1',
      'additionalUsers' => null,
    ]];

    $this->assertDraftsAreSame($expected, $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findDraftsForCurrentUserSpecificDraftNotAllowed()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser1', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->fileManager->username = 'testuser1';
    $draftName = $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $result = $this->fileManager->findDraftsForCurrentUser($this->fileManager->getDraftFileName('testuser'));
    $this->assertNull($result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function getPublicDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser1', self::$testFileDir, 'test']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['adminUser', self::$testFileDir, 'admin']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->fileManager->username = 'testuser1';
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    // simulate new file manager for the admin user
    $this->fileManager->username = 'adminUser';
    // admin user now has a draft
    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $result = $this->fileManager->findDraftsForCurrentUser($this->fileManager->getDraftFileName('testuser1'));
    $this->assertSame(1, count($result));

    $expected = [[
      'destFilepath'    => self::$testFileDir . 'index.php',
      'draftFilename'   => $this->fileManager->getDraftFileName('testuser1'),
      'type'            => Config::PUBLIC_DRAFT,
      'username'        => 'testuser1',
      'additionalUsers' => null,
    ]];

    $this->assertDraftsAreSame($expected, $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function draftExists()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->assertFalse($this->fileManager->draftExists());
    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->assertTrue($this->fileManager->draftExists());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userHasOpenDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->assertFalse($this->fileManager->userHasOpenDraft());
    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->assertTrue($this->fileManager->userHasOpenDraft());
    $this->buildFileManager('testuser1', self::$testFileDir . 'index.php');
    $this->assertFalse($this->fileManager->userHasOpenDraft());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editFile()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $edits = ['1' => '<p>This is some edited html content</p>'];

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->assertTrue($this->fileManager->editFile($edits));
    $editedPart = $this->fileManager->getFileConfiguration()->getEditedFileConfigurationPart('1');
    $this->assertNotNull($editedPart);
    $this->assertSame("\n\n<p>This is some html content</p>\n\n", $editedPart->getValueBeforeEdit());
    $this->assertNotSame("\n\n<p>This is some html content</p>\n\n", $editedPart->getContent());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editFileNoLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $edits = ['1' => '<p>This is some edited html content</p>'];

    $this->buildFileManager('testuser1', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->assertFalse($this->fileManager->editFile($edits));
    $this->destructDB();
  }

  /**
   * @test
   * @expectedException RuntimeException
   */
  public function saveFileNotWritable()
  {
    $this->buildFileManager('testuser1', self::$testFileDir . 'index.php');
    $this->fileManager->saveFile(self::$testFileDir . 'arstarstarst/asrt', 'test');
  }

  /**
   * @test
   */
  public function removeEditablePieces()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);
    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');

    $filename = $this->fileManager->makeEditableDraft();

    $fileContents = file_get_contents($filename);
    //echo $fileContents;
    unlink($filename);

    $this->assertContains($this->wrappedEditableIdentifier, $fileContents);

    $nonEditableContent = $this->fileManager->removeEditablePieces($fileContents);
    $this->assertNotContains($this->wrappedEditableIdentifier, $nonEditableContent);

    $expectedFile = '<?php
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
    $this->assertSame($expectedFile, $nonEditableContent);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function removeEditablePiecesNewLines()
  {
    $this->buildFileManager('bvisto', '/billy');

    $content = '

<div class="editable" data-index="7"></div>' . Config::EDITABLE_DIV_CLOSING_IDENTIFIER . '
    ';
    $result = $this->fileManager->removeEditablePieces($content);

    $this->assertSame('', $result);
  }

  /**
   * @test
   */
  public function removeEditablePiecesAndAttemptToEditNonEditableKey()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);
    file_put_contents(self::$testFileDir . 'index5.php', self::$indexFiveContents);

    $this->buildFileManager('testuser', self::$testFileDir . 'index5.php');

    $filename = $this->fileManager->makeEditableDraft();

    $fileContents = file_get_contents($filename);
    unlink($filename);

    $this->assertContains($this->wrappedEditableIdentifier, $fileContents);

    $nonEditableContent = $this->fileManager->removeEditablePieces($fileContents);
    $this->assertNotContains($this->wrappedEditableIdentifier, $nonEditableContent);

    $expectedFile = '
<p>This is some html content</p>



<?php=$test;?>



more html



<?php //arst';

    $this->assertSame($expectedFile, $nonEditableContent);

    $this->fileManager->editFile(['0' => '<p>This is content</p>', '2' => 'arstarstarst']);

    // since we removed all editable pieces from this file, we shouldn't be able to edit.
    $this->assertEmpty($this->fileManager->getFileConfiguration()->getFileConfigurationPartsEdited());
    $this->assertContains('0', $_SESSION['concertCMS']['nonEditableKeys'][$this->fileManager->getFilePathHash()]);
    $this->assertContains('2', $_SESSION['concertCMS']['nonEditableKeys'][$this->fileManager->getFilePathHash()]);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function removeEditablePiecesConcertAdditions()
  {
    $content = sprintf('<div class="editable" data-index="0"><div class="grid_36 alpha omega">

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
</div></div>%s', Config::EDITABLE_DIV_CLOSING_IDENTIFIER);

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
</div>';
    $this->buildFileManager('testuser', self::$testFileDir . 'index5.php');

    $result = $this->fileManager->removeEditablePieces($content);
    $this->assertSame($expected, $result);
  }

  /**
   * @test
   */
  public function setUpCheckEditableFilter()
  {
    Filters::clear('concertCMSCheckEditable');
    $this->constructDB(['Sites', 'Permissions']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'admin', ['files/*', 'private/public/*'], ['private/*', 'protected/*']]);

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->fileManager->setUpCheckEditableFilter();

    $content = 'arst<' . $this->wrappedEditableIdentifier . ' data-index="1">editable</div>' . Config::EDITABLE_DIV_CLOSING_IDENTIFIER;

    $actual = Filters::apply('concertCMSCheckEditable', $content, 'title');

    $this->assertSame($content, $actual);
    $this->destructDB();
    Filters::clear('concertCMSCheckEditable');
  }

  /**
   * @test
   */
  public function setUpCheckEditableFilterNotEditable()
  {
    Filters::clear('concertCMSCheckEditable');
    $this->constructDB(['Sites', 'Permissions']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test', ['files/*', 'private/public/*'], ['private/*', 'protected/*']]);

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->fileManager->setUpCheckEditableFilter();

    $content = 'arst<' . $this->wrappedEditableIdentifier . ' data-index="1">editable</div>' . Config::EDITABLE_DIV_CLOSING_IDENTIFIER;

    $actual = Filters::apply('concertCMSCheckEditable', $content, 'auxBox');

    $this->assertNotSame($content, $actual);
    $this->assertSame('arsteditable', $actual);
    $this->destructDB();
    Filters::clear('concertCMSCheckEditable');
  }

  /**
   * @test
   */
  public function userCanEditFile()
  {
    $this->constructDB(['Sites', 'Permissions']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test', ['files/*', 'private/public/*'], ['private/*', 'protected/*']]);
    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->userCanEditFile());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFileFromDocRoot()
  {
    $this->constructDB(['Sites', 'Permissions']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test', ['files/*', 'private/public/*'], ['private/*', 'protected/*']]);
    $this->buildFileManager('bvisto', $_SERVER['DOCUMENT_ROOT'] . '/billy/files/private.php');
    $this->assertTrue($this->fileManager->userCanEditFile());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFileFalse()
  {
    $this->constructDB(['Sites', 'Permissions']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test', ['files/*', 'private/public/*'], ['private/*', 'protected/*']]);
    $this->buildFileManager('jerry', '/billy/files/private.php');
    $this->assertFalse($this->fileManager->userCanEditFile());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFilePublicDraft()
  {
    $this->constructDB(['Drafts', 'Permissions', 'Sites', 'Locks']);
    $_SERVER['REQUEST_URI'] = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'editDraft', ['draftName' => 'testFile']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', '/billy/files/testFile', 'test']);

    $this->buildFileManager('jerry', '/billy/files/testFile');

    $configuration = new FileConfiguration(self::$indexConfigArray);
    $this->fileManager->fileConfiguration = $configuration;

    $draftFilePath = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->assertTrue($draftFilePath !== false);

    $this->buildFileManager('jerry', $draftFilePath);


    $this->fileManager->setUserIsEditingPublicDraft();


    $draft = $this->fileManager->getDraft($this->fileManager->getDraftFileName());

    $this->assertTrue($this->fileManager->userCanEditFile());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFilePublicDraftFalse()
  {
    $this->constructDB(['Drafts', 'Permissions', 'Sites', 'Locks']);
    $_SERVER['REQUEST_URI'] = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'editDraft', ['draftName' => 'testFile']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', '/billy/files/testFile', 'test']);

    $this->buildFileManager('jerry', '/billy/files/testFile');

    $configuration = new FileConfiguration(self::$indexConfigArray);
    $this->fileManager->fileConfiguration = $configuration;

    $draftFilePath = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->assertTrue($draftFilePath !== false);

    $this->buildFileManager('bvisto', $draftFilePath);


    $this->fileManager->setUserIsEditingDraft();


    $draft = $this->fileManager->getDraft($this->fileManager->getDraftFileName());

    $this->assertFalse($this->fileManager->userCanEditFile());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFilePublicDraftShared()
  {
    $this->constructDB(['Drafts', 'Permissions', 'Sites', 'Locks']);
    $_SERVER['REQUEST_URI'] = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'editDraft', ['draftName' => 'testFile']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', '/billy/files/testFile', 'test']);

    $this->buildFileManager('jerry', '/billy/files/testFile');

    $configuration = new FileConfiguration(self::$indexConfigArray);
    $this->fileManager->fileConfiguration = $configuration;

    $draftFilePath = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['bvisto']);

    $this->assertTrue($draftFilePath !== false);

    $this->buildFileManager('bvisto', $draftFilePath);


    $this->fileManager->setUserIsEditingPublicDraft();


    $draft = $this->fileManager->getDraft($this->fileManager->getDraftFileName());

    $this->assertTrue($this->fileManager->userCanEditFile());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditPart()
  {
    $this->constructDB(['Sites', 'Permissions']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test', ['files/*', 'private/public/*'], ['private/*', 'protected/*']]);
    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->userCanEditPart('Title'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditPartFromDocRoot()
  {
    $this->constructDB(['Sites', 'Permissions']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test', ['files/*', 'private/public/*'], ['private/*', 'protected/*']]);
    $this->buildFileManager('bvisto', $_SERVER['DOCUMENT_ROOT'] . '/billy/files/private.php');
    $this->assertTrue($this->fileManager->userCanEditPart('Title'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function forceAccessLevel()
  {
    $_SERVER['REQUEST_URI'] = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'editDraft', ['draftName' => 'testFile']) . '?concert=test';

    $this->buildFileManager('jerry', '/billy/files/testFile');

    $this->fileManager->setUserIsEditingPublicDraft();

    $this->assertSame(Config::PUBLIC_ACCESS_LEVEL, $this->fileManager->forceAccessLevel());
  }

  /**
   * @test
   */
  public function forceAccessLevelNonPublicDraft()
  {
    $_SERVER['REQUEST_URI'] = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'editDraft', ['draftName' => 'testFile']) . '?concert=test';

    $this->buildFileManager('jerry', '/billy/files/testFile');

    $this->fileManager->setUserIsEditingDraft();

    $this->assertFalse($this->fileManager->forceAccessLevel());
  }

  /**
   * @test
   */
  public function forceAccessLevelFalse()
  {
    $_SERVER['REQUEST_URI'] = 'nothing';

    $this->buildFileManager('jerry', '/billy/files/testFile');
    $this->assertFalse($this->fileManager->forceAccessLevel());
  }

  /**
   * @test
   */
  public function userCanEditPartPublicAccessLevel()
  {
    $this->constructDB(['Sites', 'Permissions']);
    $_SERVER['REQUEST_URI'] = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'editDraft', ['draftName' => 'testFile']) . '?concert=test';

    $this->buildFileManager('bvisto', '/billy/files/testFile');
    $this->assertFalse($this->fileManager->userCanEditPart('Title'));
    $this->fileManager->setUserIsEditingPublicDraft();
    $this->assertTrue($this->fileManager->userCanEditPart('Title'));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function createLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->createLock());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getLockFromDB()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->createLock());
    $this->assertSame(['username', 'date'], array_keys($this->fileManager->getLockFromDB()));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getLockOwner()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->createLock());
    $this->assertSame('bvisto', $this->fileManager->getLockOwner());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getLockOwnerNoLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertFalse($this->fileManager->getLockOwner());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function updateLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->createLock());
    $lock1 = $this->fileManager->getLockFromDB();
    sleep(1);
    $this->assertTrue($this->fileManager->updateLock());
    $lock2 = $this->fileManager->getLockFromDB();

    $this->assertNotSame($lock1['date'], $lock2['date']);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function destroyLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->createLock());
    $this->assertTrue($this->fileManager->destroyLock());
    $this->assertTrue($this->fileManager->destroyLock());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function stopEditingAndUserHasLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->createLock());
    $this->fileManager->stopEditing();
    $this->assertFalse($this->fileManager->userHasLock());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userHasLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->createLock());
    $this->assertTrue($this->fileManager->userHasLock());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userHasLockExpired()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->createLock());
    $dbal = $this->fileManager->getDBAL();
    $dbal->update('locks', ['date' => new \DateTime('-1 year')], ['username' => 'bvisto', 'filepathHash' => $this->fileManager->getFilePathHash()], ['date' => 'datetime']);
    $this->assertFalse($this->fileManager->userHasLock());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function acquireLockAlreadyOwned()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test']);

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->createLock());
    $this->assertTrue($this->fileManager->acquireLock());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function acquireLockNotOwned()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->buildFileManager('jerry', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->createLock());
    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertFalse($this->fileManager->acquireLock());
    $this->assertFalse($this->fileManager->acquireLock());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function acquireLockNew()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test']);

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->acquireLock());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function acquireLockExpired()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', '/billy', 'test']);

    $this->buildFileManager('bvisto', '/billy/files/private.php');

    $this->assertTrue($this->fileManager->createLock());
    $dbal = $this->fileManager->getDBAL();
    $dbal->update('locks', ['date' => new \DateTime('-1 year')], ['username' => 'bvisto', 'filepathHash' => $this->fileManager->getFilePathHash()], ['date' => 'datetime']);

    $this->buildFileManager('testuser', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->acquireLock());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function acquireLockDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftFilePath = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry', 'testuser']);

    $this->assertTrue($draftFilePath !== false);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php', $draftFilePath);
    $this->fileManager->setUserIsEditingDraft();

    $draft = $this->fileManager->getDraft(basename($draftFilePath));

    $this->assertTrue($this->fileManager->acquireLock());

    $fileName = $draft['destFilepath'];

    $this->buildFileManager('testuser', $fileName);
    $this->assertNotSame($fileName, $draftFilePath);
    $lock = $this->fileManager->getLockFromDB();
    // make sure lock has been acquired for the file the draft represents
    $this->assertNotEmpty($lock);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function acquireLockSharedDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftFilePath = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']);

    $this->assertTrue($draftFilePath !== false);
    $this->fileManager->stopEditing();

    $this->buildFileManager('jerry', $draftFilePath);
    $this->fileManager->setUserIsEditingPublicDraft();
    $this->assertTrue($this->fileManager->userIsEditingPublicDraft);

    $this->assertTrue($this->fileManager->acquireLock());

    $draft = $this->fileManager->getDraft(basename($draftFilePath));

    $fileName = $draft['destFilepath'];

    // hang onto this file manager so we can test releasing the locks
    $fm1 = $this->fileManager;

    $this->buildFileManager('jerry', $fileName);
    $this->assertNotSame($fileName, $draftFilePath);
    $lock = $this->fileManager->getLockFromDB();
    // make sure lock has been acquired for the file the draft represents
    $this->assertNotEmpty($lock);

    $fm1->stopEditing();

    $lock = $fm1->getLockFromDB();
    $this->assertEmpty($lock);

    $lock = $this->fileManager->getLockFromDB();
    $this->assertEmpty($lock);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function acquireLockSharedDraftNotSettingIsEditingDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftFilePath = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']);

    $this->assertTrue($draftFilePath !== false);
    $this->fileManager->stopEditing();

    $this->buildFileManager('jerry', $draftFilePath);

    $draft = $this->fileManager->getDraft(basename($draftFilePath));

    // we didn't set that the user is editing a draft. This person shouldn't have access.
    $this->assertFalse($this->fileManager->acquireLock());

    $fileName = $draft['destFilepath'];

    $this->buildFileManager('jerry', $fileName);
    $this->assertNotSame($fileName, $draftFilePath);
    $lock = $this->fileManager->getLockFromDB();
    // make sure lock has not been acquired for the file the draft represents
    $this->assertEmpty($lock);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function acquireLockForPublicDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftFilePath = str_replace('//', '/', $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']));

    $this->assertTrue($draftFilePath !== false);
    $this->fileManager->stopEditing();

    $this->buildFileManager('jerry', $draftFilePath);
    $this->fileManager->setUserIsEditingPublicDraft();
    $this->assertTrue($this->fileManager->userIsEditingPublicDraft);

    $this->assertTrue($this->fileManager->acquireLock());

    $dbal = $this->fileManager->getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('username')
      ->addSelect('filepath')
      ->from('locks', 'l');

    $expectedLocks = [
      [
        'username' => 'jerry',
        'filepath' => $draftFilePath,
      ],
      [
        'username' => 'jerry',
        'filepath' => self::$testFileDir . 'index.php',
      ],
    ];
    $this->assertSame($expectedLocks, $dbal->fetchAll($qb->getSQL()));

    $this->fileManager->stopEditing();

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('username')
      ->addSelect('filepath')
      ->from('locks', 'l');

    $this->assertEmpty($dbal->fetchAll($qb->getSQL()));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function acquireLockBasedOnDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testuser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftFilePath = str_replace('//', '/', $this->fileManager->saveDraft(Config::PUBLIC_DRAFT, ['jerry']));

    $this->assertTrue($draftFilePath !== false);
    $this->fileManager->stopEditing();

    $dbal = $this->fileManager->getDBAL();

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('username')
      ->addSelect('filepath')
      ->from('locks', 'l');

    // make sure all locks are empty.
    $this->assertEmpty($dbal->fetchAll($qb->getSQL()));

    $this->buildFileManager('testuser', self::$testFileDir . 'index.php', $draftFilePath);
    $this->fileManager->setUserIsEditingDraft();

    $this->assertTrue($this->fileManager->acquireLock());

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('username')
      ->addSelect('filepath')
      ->from('locks', 'l');

    $expectedLocks = [
      [
        'username' => 'testuser',
        'filepath' => self::$testFileDir . 'index.php',
      ],
      [
        'username' => 'testuser',
        'filepath' => $draftFilePath,
      ],
    ];
    $this->assertSame($expectedLocks, $dbal->fetchAll($qb->getSQL()));

    $this->fileManager->stopEditing();

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('username')
      ->addSelect('filepath')
      ->from('locks', 'l');

    $this->assertEmpty($dbal->fetchAll($qb->getSQL()));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getLockDuration()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test']);

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->acquireLock());
    $lock = $this->fileManager->getLockFromDB();

    $this->assertLessThan(1, $this->fileManager->getLockDuration($lock['date']));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function stageFile()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());
    $this->assertTrue($this->fileManager->stageFile());

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
?>

<p>This is some html content</p>

<?php

$config["content"] .= ob_get_contents();

echo $config["content"];';

    $modifiedFile = file_get_contents(self::$testFileDir . 'index.php');

    $this->assertSame($expected, $modifiedFile);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function stageFileNoPermission()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('jerry', self::$testFileDir . 'index.php');

    $this->assertFalse($this->fileManager->stageFile());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function markStagedFileAsPublished()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->stageFile());

    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->buildFileManager('bvisto', $filePath);
    $stagedEntry = $this->fileManager->getStagedFileEntry();
    $this->assertNotEmpty($stagedEntry);

    $this->fileManager->markStagedFileAsPublished($filePath);

    $stagedEntry = $this->fileManager->getStagedFileEntry();
    $this->assertEmpty($stagedEntry);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function stageForDeletion()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());
    $this->assertTrue($this->fileManager->stageForDeletion());

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
?>

<p>This is some html content</p>

<?php

$config["content"] .= ob_get_contents();

echo $config["content"];';

    $modifiedFile = file_get_contents(self::$testFileDir . 'index.php');

    $this->assertSame($expected, $modifiedFile);

    $this->buildFileManager('bvisto', Config::$stagingDir . $this->fileManager->getFilePathHash());

    $stagedEntry = $this->fileManager->getStagedFileEntry();

    $this->assertSame([['destFilepath' => self::$testFileDir . 'index.php', 'username' => 'bvisto', 'action' => Config::DELETE_STAGE]], $stagedEntry);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function stagePublishPendingDraft()
  {
    $this->buildDB();

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', self::$testFileDir, Config::AUTHOR_ACCESS_LEVEL]);

    // save a draft for our non-publishing user
    $this->buildFileManager('jerry', self::$testFileDir . 'index.php');
    $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);
    $this->fileManager->stopEditing();

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());
    $this->assertTrue($this->fileManager->stagePublishPendingDraft('jerry'));

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
?>

<p>This is some html content</p>

<?php

$config["content"] .= ob_get_contents();

echo $config["content"];';

    $modifiedFile = file_get_contents(self::$testFileDir . 'index.php');

    $this->assertSame($expected, $modifiedFile);

    $this->buildFileManager('bvisto', Config::$stagingDir . $this->fileManager->getDraftFileName('jerry'));

    $stagedEntry = $this->fileManager->getStagedFileEntry();

    $this->assertSame([['destFilepath' => self::$testFileDir . 'index.php', 'username' => 'bvisto', 'action' => Config::PUBLISH_PENDING_STAGE]], $stagedEntry);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function stageAndPublishFileMediaDir()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->assertFalse(is_dir(self::$testFileDir . '/httpdDir'));


    $this->buildFileManager('bvisto', self::$testFileDir . '/httpdDir');

    $this->assertTrue($this->fileManager->stageFile(Config::CREATE_MEDIA_DIRECTORY_STAGE, ''));

    $this->buildFileManager('root', Config::$stagingDir . $this->fileManager->getFilePathHash());

    $stagedEntry = $this->fileManager->getStagedFileEntry();

    $this->assertSame([['destFilepath' => self::$testFileDir . 'httpdDir', 'username' => 'bvisto', 'action' => Config::CREATE_MEDIA_DIRECTORY_STAGE]], $stagedEntry);

    $this->fileManager->publishFile();

    $stagedEntry = $this->fileManager->getStagedFileEntry();

    $this->assertEmpty($stagedEntry);

    $this->assertTrue(is_dir(self::$testFileDir . '/httpdDir'));
    foreach (Config::$mediaSubFolders as $folder) {
      $this->assertTrue(is_dir(self::$testFileDir . '/httpdDir/' . $folder));
    }
    $this->assertTrue(file_exists(self::$testFileDir . '/httpdDir/.htaccess'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function stageAndPublishFileMediaDirOnlyThumbsAndHtaccess()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    mkdir(self::$testFileDir . '/httpdDir/media', 0775, true);
    $this->assertTrue(is_dir(self::$testFileDir . '/httpdDir'));
    $this->assertFalse(is_dir(self::$testFileDir . '/httpdDir/thumbs'));
    $this->assertFalse(file_exists(self::$testFileDir . '/httpdDir/.htaccess'));


    $this->buildFileManager('bvisto', self::$testFileDir . '/httpdDir');

    $this->assertTrue($this->fileManager->stageFile(Config::CREATE_MEDIA_DIRECTORY_STAGE, ''));

    $this->buildFileManager('root', Config::$stagingDir . $this->fileManager->getFilePathHash());

    $stagedEntry = $this->fileManager->getStagedFileEntry();

    $this->assertSame([['destFilepath' => self::$testFileDir . 'httpdDir', 'username' => 'bvisto', 'action' => Config::CREATE_MEDIA_DIRECTORY_STAGE]], $stagedEntry);

    $this->fileManager->publishFile();

    $stagedEntry = $this->fileManager->getStagedFileEntry();

    $this->assertEmpty($stagedEntry);

    $this->assertTrue(is_dir(self::$testFileDir . '/httpdDir'));
    foreach (Config::$mediaSubFolders as $folder) {
      $this->assertTrue(is_dir(self::$testFileDir . '/httpdDir/' . $folder));
    }
    $this->assertTrue(file_exists(self::$testFileDir . '/httpdDir/.htaccess'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function stageAndPublishFileMediaDirOnlyHtaccess()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    mkdir(self::$testFileDir . '/httpdDir/media', 0775, true);
    mkdir(self::$testFileDir . '/httpdDir/thumbs', 0775, true);
    $this->assertTrue(is_dir(self::$testFileDir . '/httpdDir'));
    $this->assertTrue(is_dir(self::$testFileDir . '/httpdDir/thumbs'));
    $this->assertFalse(file_exists(self::$testFileDir . '/httpdDir/.htaccess'));


    $this->buildFileManager('bvisto', self::$testFileDir . '/httpdDir');

    $this->assertTrue($this->fileManager->stageFile(Config::CREATE_MEDIA_DIRECTORY_STAGE, ''));

    $this->buildFileManager('root', Config::$stagingDir . $this->fileManager->getFilePathHash());

    $stagedEntry = $this->fileManager->getStagedFileEntry();

    $this->assertSame([['destFilepath' => self::$testFileDir . 'httpdDir', 'username' => 'bvisto', 'action' => Config::CREATE_MEDIA_DIRECTORY_STAGE]], $stagedEntry);

    $this->fileManager->publishFile();

    $stagedEntry = $this->fileManager->getStagedFileEntry();

    $this->assertEmpty($stagedEntry);

    $this->assertTrue(is_dir(self::$testFileDir . '/httpdDir'));
    foreach (Config::$mediaSubFolders as $folder) {
      $this->assertTrue(is_dir(self::$testFileDir . '/httpdDir/' . $folder));
    }
    $this->assertTrue(file_exists(self::$testFileDir . '/httpdDir/.htaccess'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function stageAndPublishFileMediaDirFromChildSite()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', str_replace('//', '/', self::$testFileDir . '/subSite'), 'test']);

    $this->assertFalse(is_dir(self::$testFileDir . '/httpdDir'));


    $this->buildFileManager('jerry', self::$testFileDir . '/httpdDir');

    $this->assertFalse($this->fileManager->stageFile(Config::CREATE_MEDIA_DIRECTORY_STAGE, ''));

    $this->buildFileManager('jerry', self::$testFileDir . '/httpdDir');

    $this->assertTrue($this->fileManager->stageFile(Config::CREATE_MEDIA_DIRECTORY_STAGE, '', null, true));

    $this->buildFileManager('root', Config::$stagingDir . $this->fileManager->getFilePathHash());

    $stagedEntry = $this->fileManager->getStagedFileEntry();

    $this->assertSame([['destFilepath' => self::$testFileDir . 'httpdDir', 'username' => 'jerry', 'action' => Config::CREATE_MEDIA_DIRECTORY_STAGE]], $stagedEntry);

    $this->fileManager->publishFile();

    $stagedEntry = $this->fileManager->getStagedFileEntry();
    $this->assertEmpty($stagedEntry);

    $this->assertTrue(is_dir(self::$testFileDir . '/httpdDir'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function publishFileNotRoot()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->stageFile());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('bvisto', $filePath);
    try {
      $this->fileManager->publishFile();
    } catch (\RuntimeException $e) {
      $this->destructDB();
      $this->assertInstanceOf('\RuntimeException', $e);
      return;
    }
    $this->assertTrue(false, 'Exception was supposed to be thrown');
    $this->destructDB();
    // We shouldn't get here. publishFile has to be run as root, so bvisto shouldn't have access to publish files
  }

  /**
   * @test
   */
  public function publishFile()
  {
    $this->buildDB();

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());
    $this->assertTrue($this->fileManager->stageFile());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $filePath);

    $this->assertTrue(file_exists($filePath));

    $this->assertTrue($this->fileManager->publishFile());

    $this->assertFalse(file_exists($filePath));
    $this->assertTrue(file_exists(self::$testFileDir . 'index.php'));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function publishFileOnlySiteNavPerms()
  {
    $this->buildDB();

    file_put_contents(self::$testFileDir . 'site_nav.php', 'siteNav here');

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, [Config::NO_EDIT_ACCESS_LEVEL, Config::SITE_NAV_ACCESS_LEVEL]]);

    $this->buildFileManager('bvisto', self::$testFileDir . 'site_nav.php');

    $this->fileManager->setUserIsEditingSiteNav();

    $this->assertTrue($this->fileManager->acquireLock());
    $this->assertTrue($this->fileManager->stageFile());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $filePath);

    $this->assertTrue(file_exists($filePath));

    $this->assertTrue($this->fileManager->publishFile());

    $this->assertFalse(file_exists($filePath));
    $this->assertTrue(file_exists(self::$testFileDir . 'site_nav.php'));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function publishFileMultipleStagedEntries()
  {
    $this->buildDB();

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());

    $this->assertTrue($this->fileManager->stageFile());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);

    $this->assertTrue($this->fileManager->destroyLock());

    $this->buildFileManager('jerry', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->stageFile());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $filePath);

    $this->assertTrue(file_exists($filePath));

    try {
      $this->fileManager->publishFile();
    } catch (\RuntimeException $e) {
      $this->destructDB();
      return;
    }
    $this->assertTrue(false, 'Exception was supposed to be thrown');
  }

  /**
   * @test
   */
  public function publishFileMultipleStagedEntriesSameUser()
  {
    $this->buildDB();

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());

    $this->assertTrue($this->fileManager->stageFile());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);

    $this->assertTrue($this->fileManager->stageFile());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $filePath);

    $this->assertTrue(file_exists($filePath));

    $this->assertTrue($this->fileManager->publishFile());

    $this->assertFalse(file_exists($filePath));
    $this->assertTrue(file_exists(self::$testFileDir . 'index.php'));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function publishFileNoStagedFiles()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('root', self::$testFileDir . 'stagedFiles/arst');
    $this->assertFalse($this->fileManager->publishFile());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function publishFileNoLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles', 'Drafts']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->stageFile());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);
    PermissionsManager::deleteUserFromSite('bvisto', self::$testFileDir);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $filePath);

    $this->assertFalse($this->fileManager->publishFile());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function publishFilePendingDraft()
  {
    $this->buildDB();

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', self::$testFileDir, Config::AUTHOR_ACCESS_LEVEL]);

    // author submits for approval
    $this->buildFileManager('jerry', self::$testFileDir . 'index.php');
    $edits = ['1' => '<p>This is some edited html content</p>'];
    $this->fileManager->editFile($edits);
    $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);
    $this->fileManager->stopEditing();

    // publisher approves
    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());
    $this->assertTrue($this->fileManager->stagePublishPendingDraft('jerry'));
    $filePath = Config::$stagingDir . $this->fileManager->getDraftFileName('jerry');

    $this->assertContains(self::$testFileDir, $filePath);
    $this->assertContains('This is some edited html content', file_get_contents($filePath));

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $filePath);

    $this->assertTrue(file_exists($filePath));

    $this->assertTrue($this->fileManager->publishFile());

    $this->assertFalse(file_exists($filePath));
    $this->assertTrue(file_exists(self::$testFileDir . 'index.php'));

    $revisionsAPI = Utility::getRevisionsAPI(self::$testFileDir . 'index.php', $this->fileManager->getDBAL());
    $this->assertSame(2, $revisionsAPI->getRevisionCount());
    $revision = $revisionsAPI->getRevision(1);

    $this->assertSame('bvisto', $revision->getCreatedBy());
    $this->assertContains('Initial', $revision->getRevisionMessage());
    $this->assertNotContains('jerry', $revision->getRevisionMessage());

    $revision = $revisionsAPI->getRevision(2);

    $this->assertSame('bvisto', $revision->getCreatedBy());
    $this->assertContains('jerry', $revision->getRevisionMessage());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function deleteFile()
  {
    $this->buildDB();

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());
    $this->assertTrue($this->fileManager->stageForDeletion());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $filePath);

    $this->assertTrue(file_exists($filePath));

    $this->assertTrue($this->fileManager->deleteFile());

    $this->assertFalse(file_exists(self::$testFileDir . 'index.php'));
    $this->destructDB();
  }

  /**
   * @test
   * @expectedException RuntimeException
   */
  public function deleteFileNotRoot()
  {
    $this->buildFileManager('arst', 'arst.php');

    $this->fileManager->deleteFile();
    $this->assertFalse(true);
  }

  /**
   * @test
   */
  public function deleteFileNothingStaged()
  {
    $this->buildDB();

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());
    //$this->assertTrue($this->fileManager->stageForDeletion());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $filePath);

    $this->assertFalse($this->fileManager->deleteFile());

    $this->assertTrue(file_exists(self::$testFileDir . 'index.php'));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function deleteFileMultipleStagedEntries()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles', 'Drafts']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());

    $this->assertTrue($this->fileManager->stageFile());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);

    $this->assertTrue($this->fileManager->destroyLock());

    $this->buildFileManager('jerry', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->stageFile());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $filePath);

    $this->assertTrue(file_exists($filePath));

    try {
      $this->fileManager->deleteFile();
    } catch (\RuntimeException $e) {
      $this->destructDB();
      return;
    }
    $this->assertTrue(false, 'Exception was supposed to be thrown');
  }

  /**
   * @test
   */
  public function deleteFileFromPublish()
  {
    $this->buildDB();

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());
    $this->assertTrue($this->fileManager->stageForDeletion());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $filePath);

    $this->assertTrue(file_exists($filePath));

    $this->assertTrue($this->fileManager->publishFile());

    $this->assertFalse(file_exists(self::$testFileDir . 'index.php'));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function deletePublishFileRevisions()
  {
    $this->buildDB();

    $file = self::$testFileDir . 'index.php';

    file_put_contents($file, self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->buildFileManager('bvisto', $file);

    $this->assertTrue($this->fileManager->acquireLock());
    $edits = ['1' => '<p>This is some edited html content</p>'];
    $this->fileManager->editFile($edits);
    $this->assertTrue($this->fileManager->stageFile());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $filePath);

    $this->assertTrue(file_exists($filePath));

    $this->assertTrue($this->fileManager->publishFile());

    $this->assertFalse(file_exists($filePath));
    $this->assertTrue(file_exists($file));

    // now for deletion

    $this->assertTrue($this->fileManager->acquireLock());
    $this->assertTrue($this->fileManager->stageForDeletion());
    $filePath = Config::$stagingDir . $this->fileManager->getFilePathHash();

    $this->assertContains(self::$testFileDir, $filePath);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $filePath);

    $this->assertTrue(file_exists($filePath));

    $this->assertTrue($this->fileManager->publishFile());

    $this->assertFalse(file_exists($file));


    $revisionsAPI = Utility::getRevisionsAPI($file, $this->fileManager->getDBAL());
    $this->assertSame(3, $revisionsAPI->getRevisionCount());

    $this->assertSame('Initial version', $revisionsAPI->getRevision(1)->getRevisionMessage());
    $this->assertSame(self::$indexContents, $revisionsAPI->getRevision(1)->getRevisionData('page')->getContent());
    $this->assertNotContains('This is some edited html content', $revisionsAPI->getRevision(1)->getRevisionData('page')->getContent());

    $this->assertSame('File published', $revisionsAPI->getRevision(2)->getRevisionMessage());
    $this->assertContains('This is some edited html content', $revisionsAPI->getRevision(2)->getRevisionData('page')->getContent());

    $this->assertSame('File deleted', $revisionsAPI->getRevision(3)->getRevisionMessage());
    $this->assertSame('', $revisionsAPI->getRevision(3)->getRevisionData('page')->getContent());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function ensureDirectoryExists()
  {
    $dir = self::$testFileDir . 'arst';
    $owner = fileowner(self::$testFileDir);
    $owner = posix_getpwuid($owner);
    $owner = $owner['name'];

    $this->buildFileManager('root', $dir);

    $this->assertTrue($this->fileManager->ensureDirectoryExists($dir, $owner, 'www'));
  }

  /**
   * @test
   */
  public function ensureDirectoryExistsRecursive()
  {
    $dir = self::$testFileDir . 'directory/arst/arst/asrt';
    $owner = fileowner(self::$testFileDir);
    $owner = posix_getpwuid($owner);
    $owner = $owner['name'];

    $this->buildFileManager('bvisto', $dir);

    $this->fileManager->ensureDirectoryExists($dir, $owner, 'www');
    $this->assertTrue(is_dir($dir));
  }

  /**
   * @test
   */
  public function removeFilesTest()
  {
    $dir = self::$testFileDir . 'directory/arst/arst/';

    mkdir($dir, 0777, true);
    file_put_contents($dir . 'test.php', 'arst');
    file_put_contents($dir . 'test2.php', 'arst2');
    $this->assertSame(4, count(scandir($dir)));

    $result = $this->call('FileManager', 'removeFiles', [$dir]);
    $this->assertTrue($result);

    $this->assertTrue(file_exists($dir));
    $this->assertSame(2, count(scandir($dir)));

  }

  /**
   * @test
   */
  public function removeFilesDirs()
  {
    $dir = self::$testFileDir . 'directory/arst/arst/';

    mkdir($dir, 0777, true);
    file_put_contents($dir . 'test.php', 'arst');
    file_put_contents($dir . 'test2.php', 'arst2');

    $subDir = $dir . '/directory/';
    mkdir($subDir);
    file_put_contents($subDir . 'test.php', 'arst');
    file_put_contents($subDir . 'test2.php', 'arst2');
    $this->assertSame(5, count(scandir($dir)));
    $this->assertSame(4, count(scandir($subDir)));

    $result = $this->call('FileManager', 'removeFiles', [$dir]);
    $this->assertTrue($result);

    $this->assertTrue(file_exists($dir));
    $this->assertSame(2, count(scandir($dir)));
    $this->assertFalse(file_exists($subDir));
  }

  /**
   * @test
   */
  public function removeEmptyParentDirectories()
  {
    $dir = self::$testFileDir . 'directory/arst/arst/';

    mkdir($dir, 0777, true);
    $file = $dir . 'test.php';

    $site = self::$testFileDir;

    $result = $this->call('FileManager', 'removeEmptyParentDirectories', [$file, $site]);

    $this->assertFalse(file_exists(self::$testFileDir . 'directory'));
    $this->assertTrue(file_exists(self::$testFileDir));
  }

  /**
   * @test
   */
  public function removeEmptyParentDirectoriesToSite()
  {
    $dir = self::$testFileDir . 'directory/arst/arst/';

    mkdir($dir, 0777, true);
    $file = $dir . 'test.php';

    $site = self::$testFileDir . 'directory/arst/';

    $result = $this->call('FileManager', 'removeEmptyParentDirectories', [$file, $site]);

    $this->assertFalse(file_exists($dir));
    $this->assertTrue(file_exists(self::$testFileDir . 'directory/arst/'));
    $this->assertTrue(file_exists(self::$testFileDir));
  }

  /**
   * @test
   */
  public function removeEmptyParentDirectoriesNotAllEmpty()
  {
    $dir = self::$testFileDir . 'directory/arst/arst/';

    mkdir($dir, 0777, true);
    $file = $dir . 'test.php';
    file_put_contents($file, 'arst');

    $subDir = $dir . '/directory/';
    mkdir($subDir);

    $site = self::$testFileDir;

    $this->assertTrue(file_exists($subDir));

    $result = $this->call('FileManager', 'removeEmptyParentDirectories', [$subDir, $site]);

    $this->assertFalse(file_exists($subDir));
    $this->assertTrue(file_exists($dir));
    $this->assertTrue(file_exists(self::$testFileDir));
  }

  /**
   * @test
   */
  public function removeFile()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles', 'Drafts']);

    $dir = self::$testFileDir . 'directory/arst/arst/';

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', $dir, 'test']);

    mkdir($dir, 0777, true);
    $file = $dir . 'test.php';
    file_put_contents($file, 'arst');

    $subDir = $dir . '/directory/';
    mkdir($subDir);

    $site = self::$testFileDir;

    $this->assertTrue(file_exists($subDir));

    $this->buildFileManager('bvisto', $file);

    $result = $this->fileManager->removeFile();

    $this->assertTrue($result);

    $this->assertFalse(file_exists($file));
    $this->assertTrue(file_exists($subDir));
    $this->assertTrue(file_exists($dir));
    $this->assertTrue(file_exists(self::$testFileDir));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function removeFileAdmin()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles', 'Drafts']);

    $dir = self::$testFileDir . 'directory/arst/arst/';

    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', self::$testFileDir . 'directory/arst/', 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '*', Config::SUPER_USER]);

    mkdir($dir, 0777, true);
    $file = $dir . 'test.php';
    file_put_contents($file, 'arst');

    $site = self::$testFileDir;

    $this->buildFileManager('bvisto', $file);

    $result = $this->fileManager->removeFile();

    $this->assertTrue($result);

    $this->assertFalse(file_exists($file));
    $this->assertFalse(file_exists($dir));
    $this->assertTrue(file_exists(self::$testFileDir . 'directory/arst/'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function removeFileNoSites()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles', 'Drafts']);

    $dir = self::$testFileDir . 'directory/arst/arst/';

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '*', Config::SUPER_USER]);

    mkdir($dir, 0777, true);
    $file = $dir . 'test.php';
    file_put_contents($file, 'arst');

    $site = self::$testFileDir;

    $this->buildFileManager('bvisto', $file);

    $result = $this->fileManager->removeFile();

    $this->assertFalse($result);

    $this->assertTrue(file_exists($file));
    $this->assertTrue(file_exists($dir));
    $this->assertTrue(file_exists(self::$testFileDir . 'directory/arst/'));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveRevision()
  {
    $this->buildDB();

    $file = self::$testFileDir . 'index.php';

    $this->buildFileManager('bvisto', $file);

    $result = $this->fileManager->saveRevision();

    $this->assertTrue($result);

    $revisionsAPI = Utility::getRevisionsAPI($file, $this->fileManager->getDBAL());
    $this->assertSame(0, $revisionsAPI->getRevisionCount());


    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveRevisionWithContent()
  {
    $this->buildDB();

    $file = self::$testFileDir . 'index.php';

    $fileContents = '<?php ?>test contents<?php arst;?>more';

    file_put_contents($file, $fileContents);

    $this->buildFileManager('bvisto', $file);

    $result = $this->fileManager->saveRevision();
    $this->assertTrue($result);

    $revisionsAPI = Utility::getRevisionsAPI($file, $this->fileManager->getDBAL());
    $this->assertSame(1, $revisionsAPI->getRevisionCount());

    Filters::add(RevisionsAPI::RENDER_REVISION_FILTER, function($content) {
      $contentArr = FileManager::separateContentByType($content);
      return implode("\n", $contentArr['content']);
    });

    $this->assertSame($fileContents, $revisionsAPI->getRevision(1)->getRevisionData('page')->getContent());

    $this->assertSame("test contents\nmore", $revisionsAPI->getRevision(1)->getRevisionData('page')->getContent(false, null, true));

    $this->destructDB();
  }

  /**
   * @test
   */
  public function isNonEditableFileFalse()
  {
    file_put_contents(self::$testFileDir . 'arst.php', 'arst');

    $this->buildFileManager('bvisto', self::$testFileDir . 'arst.php');

    $this->assertFalse($this->fileManager->isNonEditableFile());
  }

  /**
   * @test
   */
  public function isNonEditableFile()
  {
    file_put_contents(self::$testFileDir . 'arst.php', 'arst');
    symlink(self::$testFileDir . 'arst.php', self::$testFileDir . 'arstLink.php');

    $this->buildFileManager('bvisto', self::$testFileDir . 'arstLink.php');

    $this->assertTrue($this->fileManager->isNonEditableFile());
  }

  /**
   * @test
   */
  public function buildFileForEditingWithDefinedPHPFunction()
  {
    $fileContents = '<?php
      function testingArst() {

      }
      ?>
      this is text
    ';
    file_put_contents(self::$testFileDir . 'test.php', $fileContents);
    ob_start();
    require(self::$testFileDir . 'test.php');
    ob_end_clean();

    $this->buildFileManager('testuser', self::$testFileDir . 'test.php');

    $file = $this->fileManager->assembleFile(true);

    $this->assertNotContains('testingArst', $file);
  }

  /**
   * @test
   */
  public function buildFileForEditingWithDefinedPHPClass()
  {
    $fileContents = '<?php
      class testingArst
      {
        public function testingFunc() {

        }
      }
      ?>
      this is text
    ';
    file_put_contents(self::$testFileDir . 'test.php', $fileContents);
    ob_start();
    require(self::$testFileDir . 'test.php');
    ob_end_clean();

    $this->buildFileManager('testuser', self::$testFileDir . 'test.php');

    $file = $this->fileManager->assembleFile(true);

    $this->assertNotContains('testingArst', $file);
    $this->assertNotContains('testingFunc', $file);
  }

  /**
   * @test
   */
  public function buildFileForEditingWithDefinedPHPClassAndFunc()
  {
    $fileContents = '<?php
      class testingArst1
      {
        public function testingFunc() {

        }
      }
      function anotherFunc() {

      }
      ?>
      this is text
    ';
    file_put_contents(self::$testFileDir . 'test.php', $fileContents);
    ob_start();
    require(self::$testFileDir . 'test.php');
    ob_end_clean();

    $this->buildFileManager('testuser', self::$testFileDir . 'test.php');

    $file = $this->fileManager->assembleFile(true);

    $this->assertNotContains('testingArst', $file);
    $this->assertNotContains('testingFunc', $file);
    $this->assertNotContains('anotherFunc', $file);
  }

  /**
   * @test
   */
  public function buildFileForEditingWithRelativePathInclude()
  {
    $fileContents = '<?php
      include "arst.php";
      ?>
      this is text
    ';
    file_put_contents(self::$testFileDir . 'test.php', $fileContents);
    file_put_contents(self::$testFileDir . 'arst.php', 'hello');
    ob_start();
    require(self::$testFileDir . 'test.php');
    ob_end_clean();

    $this->buildFileManager('testuser', self::$testFileDir . 'test.php');

    $file = $this->fileManager->assembleFile(true);

    $this->assertContains('include \'' . self::$testFileDir, $file);
  }

  /**
   * @test
   */
  public function buildFileForEditingWithRelativePathIncludeInlinePHP()
  {
    $fileContents = '<?php include "arst.php"; ?>
      this is text
    ';
    file_put_contents(self::$testFileDir . 'test.php', $fileContents);
    file_put_contents(self::$testFileDir . 'arst.php', 'hello');
    ob_start();
    require(self::$testFileDir . 'test.php');
    ob_end_clean();

    $this->buildFileManager('testuser', self::$testFileDir . 'test.php');

    $file = $this->fileManager->assembleFile(true);

    $this->assertContains('<?php include \'' . self::$testFileDir, $file);
  }

  /**
   * @test
   */
  public function buildFileForEditingWithRelativePathRequire()
  {
    $fileContents = '<?php
      require "arst.php";
      ?>
      this is text
    ';
    file_put_contents(self::$testFileDir . 'test.php', $fileContents);
    file_put_contents(self::$testFileDir . 'arst.php', 'hello');
    ob_start();
    require(self::$testFileDir . 'test.php');
    ob_end_clean();

    $this->buildFileManager('testuser', self::$testFileDir . 'test.php');

    $file = $this->fileManager->assembleFile(true);

    $this->assertContains('require \'' . self::$testFileDir, $file);
  }

  /**
   * @test
   */
  public function buildFileForEditingWithRelativePathRequireOnce()
  {
    $fileContents = '<?php
      require_once "arst.php";
      ?>
      this is text
    ';
    file_put_contents(self::$testFileDir . 'test.php', $fileContents);
    file_put_contents(self::$testFileDir . 'arst.php', 'hello');
    ob_start();
    require(self::$testFileDir . 'test.php');
    ob_end_clean();

    $this->buildFileManager('testuser', self::$testFileDir . 'test.php');

    $file = $this->fileManager->assembleFile(true);

    $this->assertContains('require_once \'' . self::$testFileDir, $file);
  }

  /**
   * @test
   */
  public function buildFileForEditingWithRelativePathRequireOnceAbsolute()
  {
    $fileContents = sprintf('<?php
      require_once "%sarst.php";
      ?>
      this is text
    ', self::$testFileDir);
    file_put_contents(self::$testFileDir . 'test.php', $fileContents);
    file_put_contents(self::$testFileDir . 'arst.php', 'hello');
    ob_start();
    require(self::$testFileDir . 'test.php');
    ob_end_clean();

    $this->buildFileManager('testuser', self::$testFileDir . 'test.php');

    $file = $this->fileManager->assembleFile(true);

    $this->assertContains('require_once "' . self::$testFileDir . 'arst.php"', $file);
  }

  /**
   * @test
   */
  public function buildFileForEditingWithRelativePathInPath()
  {
    $fileContents = '<?php
      require_once "Gustavus/Concert/Test/files/arst.php";
      ?>
      this is text
    ';
    file_put_contents(self::$testFileDir . 'test.php', $fileContents);
    file_put_contents(self::$testFileDir . 'arst.php', 'hello');
    ob_start();
    require(self::$testFileDir . 'test.php');
    ob_end_clean();

    $this->buildFileManager('testuser', self::$testFileDir . 'test.php');

    $file = $this->fileManager->assembleFile(true);

    $this->assertContains('require_once "Gustavus/Concert/Test/files/arst.php"', $file);
  }
}