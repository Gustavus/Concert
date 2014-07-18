<?php
/**
 * @package  Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test;

use Gustavus\Test\TestObject,
  Gustavus\Concert\FileManager,
  Gustavus\Concert\FileConfiguration,
  Gustavus\Concert\PermissionsManager,
  Gustavus\Concert\Config,
  Gustavus\Doctrine\DBAL,
  Gustavus\Extensibility\Filters,
  Gustavus\Concourse\RoutingUtil;

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
  }

  /**
   * Builds the FileManager object to use for testing
   * @param  string $file Filename
   * @param  string $user username
   * @return void
   */
  private function buildFileManager($user, $file, $srcFilePath = null)
  {
    $this->fileManager = new TestObject(new FileManager($user, $file, $srcFilePath));
    $this->fileManager->dbal = DBAL::getDBAL('testDB', $this->getDBH());
  }

  /**
   * @test
   */
  public function buildAndGetFileConfigurationArray()
  {
    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');

    $this->assertSame(self::$indexConfigArray, $this->fileManager->getFileConfigurationArray());
  }

  /**
   * @test
   * @expectedException \RuntimeException
   */
  public function buildAndGetFileConfigurationArrayNotFound()
  {
    $this->buildFileManager('testUser', self::$testFileDir . 'indexarst.php');

    $this->assertSame(self::$indexConfigArray, $this->fileManager->getFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildAndGetFileConfigurationArrayFromOtherFile()
  {
    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testUser', self::$testFileDir . 'indexCpy.php', self::$testFileDir . 'index.php');

    $this->assertSame(self::$indexConfigArray, $this->fileManager->getFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildAndGetFileConfigurationArrayOnlyPHP()
  {
    file_put_contents(self::$testFileDir . 'index2.php', self::$indexTwoContents);

    $this->buildFileManager('testUser', self::$testFileDir . 'index2.php');

    $this->assertSame(self::$indexTwoConfigArray, $this->fileManager->getFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildFileConfigurationArrayOnlyHTML()
  {
    file_put_contents(self::$testFileDir . 'index3.php', self::$indexThreeContents);

    $this->buildFileManager('testUser', self::$testFileDir . 'index3.php');

    $this->assertSame(self::$indexThreeConfigArray, $this->fileManager->buildFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildFileConfigurationArrayHTMLFirst()
  {
    file_put_contents(self::$testFileDir . 'index4.php', self::$indexThreeContents);

    $this->buildFileManager('testUser', self::$testFileDir . 'index4.php');

    $this->assertSame(self::$indexThreeConfigArray, $this->fileManager->buildFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildFileConfigurationArrayHTMLFirstAlternatingPHP()
  {
    file_put_contents(self::$testFileDir . 'index5.php', self::$indexThreeContents);

    $this->buildFileManager('testUser', self::$testFileDir . 'index5.php');

    $this->assertSame(self::$indexThreeConfigArray, $this->fileManager->buildFileConfigurationArray());
  }

  /**
   * @test
   */
  public function buildAndGetFileConfiguration()
  {
    file_put_contents(self::$testFileDir . 'index5.php', self::$indexThreeContents);

    $this->buildFileManager('testUser', self::$testFileDir . 'index5.php');

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

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');

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

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');

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
?><div class="editable" data-index="1"><p>This is some html content</p></div>%s<?php

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

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');

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
?><div class="editable" data-index="1"><p>This is some html content</p></div>%s<?php

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

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');

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
?><div class="editable" data-index="1"><p>This is some edited html content</p></div>%s<?php

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

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testUser1', self::$testFileDir . 'index.php');

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
    self::removeFiles(self::$testFileDir);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');

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
?><div class="editable" data-index="1"><p>This is some html content</p></div>%s<?php

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
  public function getDraftFileName()
  {
    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $actual = $this->fileManager->getDraftFileName();
    $this->assertSame(md5(md5(self::$testFileDir . 'index.php') . '-testUser'), $actual);
  }

  /**
   * @test
   */
  public function saveAndGetDraft()
  {
    self::removeFiles(self::$testFileDir);
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->assertTrue(false !== $this->fileManager->saveDraft(Config::PRIVATE_DRAFT, ['jerry', 'testUser1']));

    $expected = [
      [
        'destFilePath'    => self::$testFileDir . 'index.php',
        'draftFileName'   => $this->fileManager->getDraftFileName(),
        'type'            => Config::PRIVATE_DRAFT,
        'username'        => 'testUser',
        'additionalUsers' => ['jerry', 'testUser1'],
      ],
    ];

    $this->assertSame($expected, $this->fileManager->getDrafts());

    $expected[0]['type'] = Config::PUBLIC_DRAFT;
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);
    $this->assertSame($expected, $this->fileManager->getDrafts());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function saveDraftNoLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser1', self::$testFileDir . 'index.php');
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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser1', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser2', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);
    $this->fileManager->username = 'testUser1';
    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);
    $this->fileManager->username = 'testUser2';
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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT);

    $this->assertNotEmpty($this->fileManager->getDraftForUser('testUser'));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findDraftsForCurrentUser()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser1', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    $this->fileManager->username = 'testUser1';
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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['adminUser', self::$testFileDir, 'admin']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
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
  public function findDraftsForCurrentUserAdminPrivateDrafts()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser1', self::$testFileDir, 'test']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['adminUser', self::$testFileDir, 'admin']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->fileManager->username = 'testUser1';
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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser1', self::$testFileDir, 'test']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['adminUser', self::$testFileDir, 'admin']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->fileManager->username = 'testUser1';
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    // simulate new file manager for the admin user
    $this->fileManager->username = 'adminUser';
    // admin user now has a draft
    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $result = $this->fileManager->findDraftsForCurrentUser($this->fileManager->getDraftFileName('testUser1'));
    $this->assertSame(1, count($result));

    $expected = [[
      'destFilePath'    => self::$testFileDir . 'index.php',
      'draftFileName'   => $this->fileManager->getDraftFileName('testUser1'),
      'type'            => Config::PUBLIC_DRAFT,
      'username'        => 'testUser1',
      'additionalUsers' => null,
    ]];

    $this->assertSame($expected, $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function findDraftsForCurrentUserSpecificDraftNotAllowed()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser1', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $draftName = $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->fileManager->username = 'testUser1';
    $draftName = $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $result = $this->fileManager->findDraftsForCurrentUser($this->fileManager->getDraftFileName('testUser'));
    $this->assertNull($result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function getPublicDraft()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser1', self::$testFileDir, 'test']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['adminUser', self::$testFileDir, 'admin']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->fileManager->username = 'testUser1';
    $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);

    // simulate new file manager for the admin user
    $this->fileManager->username = 'adminUser';
    // admin user now has a draft
    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $result = $this->fileManager->findDraftsForCurrentUser($this->fileManager->getDraftFileName('testUser1'));
    $this->assertSame(1, count($result));

    $expected = [[
      'destFilePath'    => self::$testFileDir . 'index.php',
      'draftFileName'   => $this->fileManager->getDraftFileName('testUser1'),
      'type'            => Config::PUBLIC_DRAFT,
      'username'        => 'testUser1',
      'additionalUsers' => null,
    ]];

    $this->assertSame($expected, $result);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function draftExists()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
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
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->assertFalse($this->fileManager->userHasOpenDraft());
    $this->fileManager->saveDraft(Config::PRIVATE_DRAFT);

    $this->assertTrue($this->fileManager->userHasOpenDraft());
    $this->buildFileManager('testUser1', self::$testFileDir . 'index.php');
    $this->assertFalse($this->fileManager->userHasOpenDraft());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editFile()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $edits = ['1' => '<p>This is some edited html content</p>'];

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');
    $this->fileManager->fileConfiguration = $configuration;

    $this->assertTrue($this->fileManager->editFile($edits));
    $editedPart = $this->fileManager->getFileConfiguration()->getEditedFileConfigurationPart('1');
    $this->assertNotNull($editedPart);
    $this->assertSame("\n<p>This is some html content</p>\n", $editedPart->getValueBeforeEdit());
    $this->assertNotSame("\n<p>This is some html content</p>\n", $editedPart->getContent());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function editFileNoLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $edits = ['1' => '<p>This is some edited html content</p>'];

    $this->buildFileManager('testUser1', self::$testFileDir . 'index.php');
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
    $this->buildFileManager('testUser1', self::$testFileDir . 'index.php');
    $this->fileManager->saveFile(self::$testFileDir . 'arstarstarst/asrt', 'test');
  }

  /**
   * @test
   */
  public function removeEditablePieces()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager('testUser', self::$testFileDir . 'index.php');

    $filename = $this->fileManager->makeEditableDraft();

    $fileContents = file_get_contents($filename);
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
?><p>This is some html content</p><?php

$config["content"] .= ob_get_contents();

echo $config["content"];';
    $this->assertSame($expectedFile, $nonEditableContent);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function removeEditablePiecesAndAttemptToEditNonEditableKey()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    file_put_contents(self::$testFileDir . 'index5.php', self::$indexFiveContents);

    $this->buildFileManager('testUser', self::$testFileDir . 'index5.php');

    $filename = $this->fileManager->makeEditableDraft();

    $fileContents = file_get_contents($filename);
    unlink($filename);

    $this->assertContains($this->wrappedEditableIdentifier, $fileContents);

    $nonEditableContent = $this->fileManager->removeEditablePieces($fileContents);
    $this->assertNotContains($this->wrappedEditableIdentifier, $nonEditableContent);

    $expectedFile = '<p>This is some html content</p><?php=$test;?>more html<?php //arst';

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
  public function setUpCheckEditableFilter()
  {
    Filters::clear('concertCMSCheckEditable');
    $this->constructDB(['Sites', 'Permissions']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', ['admin', 'test'], ['files/*', 'private/public/*'], ['private/*', 'protected/*']]);

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

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', ['admin', 'test'], ['files/*', 'private/public/*'], ['private/*', 'protected/*']]);

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

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', ['admin', 'test'], ['files/*', 'private/public/*'], ['private/*', 'protected/*']]);
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

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', ['admin', 'test'], ['files/*', 'private/public/*'], ['private/*', 'protected/*']]);
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

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', ['admin', 'test'], ['files/*', 'private/public/*'], ['private/*', 'protected/*']]);
    $this->buildFileManager('jerry', '/billy/files/private.php');
    $this->assertFalse($this->fileManager->userCanEditFile());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function userCanEditFilePublicDraft()
  {
    $_SERVER['REQUEST_URI'] = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'editDraft', ['draftName' => 'testFile']);
    $this->buildFileManager('jerry', '/billy/files/testFile');
    if (Config::userIsEditingPublicDraft('/billy/files/testFile')) {
      $this->fileManager->setUserIsEditingPublicDraft();
    }
    $this->assertTrue($this->fileManager->userCanEditFile());
  }

  /**
   * @test
   */
  public function userIsEditingPublicDraft()
  {
    $_SERVER['REQUEST_URI'] = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'editDraft', ['draftName' => 'testFile']);

    $this->assertTrue(Config::userIsEditingPublicDraft('/billy/files/testFile'));
  }

  /**
   * @test
   */
  public function userIsEditingPublicDraftFalse()
  {
    $_SERVER['REQUEST_URI'] = 'nothing';

    $this->assertFalse(Config::userIsEditingPublicDraft('/billy/files/testFile'));
  }

  /**
   * @test
   */
  public function userIsEditingPublicDraftFromFilePath()
  {
    $_SERVER['REQUEST_URI'] = 'nothing';
    $filePath = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'editDraft', ['draftName' => 'testFile']);

    $this->assertTrue(Config::userIsEditingPublicDraft($filePath));
  }

  /**
   * @test
   */
  public function userIsEditingPublicDraftFromFilePathFalse()
  {
    $_SERVER['REQUEST_URI'] = 'nothing';
    $filePath = '/cis/www/billy/testFile';

    $this->assertFalse(Config::userIsEditingPublicDraft($filePath));
  }

  /**
   * @test
   */
  public function userIsEditingPublicDraftExtraQueryParams()
  {
    $_SERVER['REQUEST_URI'] = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'editDraft', ['draftName' => 'testFile']) . '?concert=test';

    $this->assertTrue(Config::userIsEditingPublicDraft('/billy/files/testFile'));
  }

  /**
   * @test
   */
  public function userCanEditPart()
  {
    $this->constructDB(['Sites', 'Permissions']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', ['admin', 'test'], ['files/*', 'private/public/*'], ['private/*', 'protected/*']]);
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

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', ['admin', 'test'], ['files/*', 'private/public/*'], ['private/*', 'protected/*']]);
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
    if (Config::userIsEditingPublicDraft('/billy/files/testFile')) {
      $this->fileManager->setUserIsEditingPublicDraft();
    }
    $this->assertSame(Config::PUBLIC_ACCESS_LEVEL, $this->fileManager->forceAccessLevel());
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
    $_SERVER['REQUEST_URI'] = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'editDraft', ['draftName' => 'testFile']) . '?concert=test';

    $this->buildFileManager('bvisto', '/billy/files/testFile');
    $this->assertTrue($this->fileManager->userCanEditPart('Title'));
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
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

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
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

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

    $this->buildFileManager('bvisto', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->acquireLock());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function acquireLockExpired()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', '/billy', 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', '/billy', 'test']);

    $this->buildFileManager('bvisto', '/billy/files/private.php');

    $this->assertTrue($this->fileManager->createLock());
    $dbal = $this->fileManager->getDBAL();
    $dbal->update('locks', ['date' => new \DateTime('-1 year')], ['username' => 'bvisto', 'filepathHash' => $this->fileManager->getFilePathHash()], ['date' => 'datetime']);

    $this->buildFileManager('testUser', '/billy/files/private.php');
    $this->assertTrue($this->fileManager->acquireLock());

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

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, ['admin', 'test']]);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());
    $this->assertContains(self::$testFileDir, $this->fileManager->stageFile());

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

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, ['admin', 'test']]);

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

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, ['admin', 'test']]);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $result = $this->fileManager->stageFile();

    $this->buildFileManager('bvisto', $result);
    $stagedEntry = $this->fileManager->getStagedFileEntry();
    $this->assertNotEmpty($stagedEntry);

    $this->fileManager->markStagedFileAsPublished($result);

    $stagedEntry = $this->fileManager->getStagedFileEntry();
    $this->assertEmpty($stagedEntry);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function publishFileNotRoot()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, ['admin', 'test']]);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $result = $this->fileManager->stageFile();
    $this->assertContains(self::$testFileDir, $result);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('bvisto', $result);
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
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles', 'Drafts']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, ['admin', 'test']]);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());
    $result = $this->fileManager->stageFile();
    $this->assertContains(self::$testFileDir, $result);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $result);

    $this->assertTrue(file_exists($result));

    $this->assertTrue($this->fileManager->publishFile());

    $this->assertFalse(file_exists($result));
    $this->assertTrue(file_exists(self::$testFileDir . 'index.php'));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function publishFileMultipleStagedEntries()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, ['admin', 'test']]);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', self::$testFileDir, ['admin', 'test']]);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());
    $result = $this->fileManager->stageFile();
    $this->assertContains(self::$testFileDir, $result);

    $this->assertTrue($this->fileManager->destroyLock());

    $this->buildFileManager('jerry', self::$testFileDir . 'index.php');
    $result = $this->fileManager->stageFile();
    $this->assertContains(self::$testFileDir, $result);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $result);

    $this->assertTrue(file_exists($result));

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
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles', 'Drafts']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, ['admin', 'test']]);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $this->assertTrue($this->fileManager->acquireLock());
    $result = $this->fileManager->stageFile();
    $this->assertContains(self::$testFileDir, $result);

    $result = $this->fileManager->stageFile();
    $this->assertContains(self::$testFileDir, $result);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $result);

    $this->assertTrue(file_exists($result));

    $this->assertTrue($this->fileManager->publishFile());

    $this->assertFalse(file_exists($result));
    $this->assertTrue(file_exists(self::$testFileDir . 'index.php'));
    $this->destructDB();
  }

  /**
   * @test
   */
  public function publishFileNoStagedFiles()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles', 'Drafts']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, ['admin', 'test']]);

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

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, ['admin', 'test']]);

    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');

    $result = $this->fileManager->stageFile();
    $this->assertContains(self::$testFileDir, $result);
    PermissionsManager::deleteUserFromSite('bvisto', self::$testFileDir);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager('root', $result);

    $this->assertFalse($this->fileManager->publishFile());
    $this->destructDB();
  }

  /**
   * @test
   */
  public function getGroupForFile()
  {
    $filename = self::$testFileDir . 'index.php';
    file_put_contents($filename, self::$indexContents);
    chgrp($filename, 'www');

    $this->buildFileManager('root', $filename);

    $this->assertSame('www', $this->fileManager->getGroupForFile($filename));
  }

  /**
   * @test
   */
  public function getGroupForFileNonExistent()
  {
    $fileName = self::$testFileDir . 'test/index.php';
    $this->buildFileManager('root', $fileName);

    $this->assertSame('www', $this->fileManager->getGroupForFile($fileName));
  }

  /**
   * @test
   */
  public function getGroupForFileFileNotExistingYet()
  {
    $filename = self::$testFileDir . 'arst.php';
    chgrp(self::$testFileDir, 'www');

    $this->buildFileManager('root', $filename);

    $this->assertSame('www', $this->fileManager->getGroupForFile($filename));
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
}