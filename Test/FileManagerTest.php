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
  Gustavus\Concert\Config,
  Gustavus\Doctrine\DBAL,
  Gustavus\Extensibility\Filters;

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
  private function buildFileManager($file, $user)
  {
    $this->fileManager = new TestObject(new FileManager($file, $user));
    $this->fileManager->dbal = DBAL::getDBAL('testDB', $this->getDBH());
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
    $this->assertInstanceOf('\Gustavus\Concert\FileConfiguration', $configuration);
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
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager(self::$testFileDir . 'index.php', 'testUser');

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
  public function editFile()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);

    $configuration = new FileConfiguration(self::$indexConfigArray);

    $edits = ['1' => '<p>This is some edited html content</p>'];

    $this->buildFileManager(self::$testFileDir . 'index.php', 'testUser');
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
  public function save()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
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
    $this->destructDB();
  }

  /**
   * @test
   */
  public function removeEditablePieces()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->buildFileManager(self::$testFileDir . 'index.php', 'testUser');

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
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->call('PermissionsManager', 'saveUserPermissions', ['testUser', self::$testFileDir, 'test']);
    file_put_contents(self::$testFileDir . 'index5.php', self::$indexFiveContents);

    $this->buildFileManager(self::$testFileDir . 'index5.php', 'testUser');

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

    $this->buildFileManager('/billy/files/private.php', 'bvisto');
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

    $this->buildFileManager('/billy/files/private.php', 'bvisto');
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
    $this->buildFileManager('/billy/files/private.php', 'bvisto');
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
    $this->buildFileManager('/billy/files/private.php', 'jerry');
    $this->assertFalse($this->fileManager->userCanEditFile());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function createLock()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->buildFileManager('/billy/files/private.php', 'bvisto');
    $this->assertTrue($this->fileManager->createLock());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getLockFromDB()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->buildFileManager('/billy/files/private.php', 'bvisto');
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

    $this->buildFileManager('/billy/files/private.php', 'bvisto');
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

    $this->buildFileManager('/billy/files/private.php', 'bvisto');
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

    $this->buildFileManager('/billy/files/private.php', 'bvisto');
    $this->assertTrue($this->fileManager->createLock());
    $this->fileManager->stopEditing();
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

    $this->buildFileManager('/billy/files/private.php', 'bvisto');
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

    $this->buildFileManager('/billy/files/private.php', 'jerry');
    $this->assertTrue($this->fileManager->createLock());
    $this->buildFileManager('/billy/files/private.php', 'bvisto');
    $this->assertFalse($this->fileManager->acquireLock());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function acquireLockNew()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->buildFileManager('/billy/files/private.php', 'bvisto');
    $this->assertTrue($this->fileManager->acquireLock());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function acquireLockExpired()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->buildFileManager('/billy/files/private.php', 'bvisto');
    $this->assertTrue($this->fileManager->acquireLock());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getLockDuration()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks']);

    $this->buildFileManager('/billy/files/private.php', 'bvisto');
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

    $this->buildFileManager(self::$testFileDir . 'index.php', 'bvisto');

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

    $this->buildFileManager(self::$testFileDir . 'index.php', 'jerry');

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

    $this->buildFileManager(self::$testFileDir . 'index.php', 'bvisto');

    $result = $this->fileManager->stageFile();

    $this->buildFileManager($result, 'bvisto');
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

    $this->buildFileManager(self::$testFileDir . 'index.php', 'bvisto');

    $result = $this->fileManager->stageFile();
    $this->assertContains(self::$testFileDir, $result);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager($result, 'bvisto');
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
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, ['admin', 'test']]);

    $this->buildFileManager(self::$testFileDir . 'index.php', 'bvisto');

    $this->assertTrue($this->fileManager->acquireLock());
    $result = $this->fileManager->stageFile();
    $this->assertContains(self::$testFileDir, $result);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager($result, 'root');

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

    $this->buildFileManager(self::$testFileDir . 'index.php', 'bvisto');

    $this->assertTrue($this->fileManager->acquireLock());
    $result = $this->fileManager->stageFile();
    $this->assertContains(self::$testFileDir, $result);

    $this->assertTrue($this->fileManager->destroyLock());

    $this->buildFileManager(self::$testFileDir . 'index.php', 'jerry');
    $result = $this->fileManager->stageFile();
    $this->assertContains(self::$testFileDir, $result);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager($result, 'root');

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
   * test
   */
  public function publishFileMultipleStagedEntriesSameUser()
  {
    $this->constructDB(['Sites', 'Permissions', 'Locks', 'StagedFiles']);

    file_put_contents(self::$testFileDir . 'index.php', self::$indexContents);

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, ['admin', 'test']]);

    $this->buildFileManager(self::$testFileDir . 'index.php', 'bvisto');

    $this->assertTrue($this->fileManager->acquireLock());
    $result = $this->fileManager->stageFile();
    $this->assertContains(self::$testFileDir, $result);

    $result = $this->fileManager->stageFile();
    $this->assertContains(self::$testFileDir, $result);

    // file is staged, now we can publish it.
    // re-create our fileManager with the staged file
    $this->buildFileManager($result, 'root');

    $this->assertTrue(file_exists($result));

    $this->assertTrue($this->fileManager->publishFile());

    $this->assertFalse(file_exists($result));
    $this->assertTrue(file_exists(self::$testFileDir . 'index.php'));
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

    $this->buildFileManager($filename, 'root');

    $this->assertSame('www', $this->fileManager->getGroupForFile($filename));
  }

  /**
   * @test
   */
  public function getGroupForFileFileNotExistingYet()
  {
    $filename = self::$testFileDir . 'arst.php';
    chgrp(self::$testFileDir, 'www');

    $this->buildFileManager($filename, 'root');

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

    $this->buildFileManager($dir, 'root');

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

    $this->buildFileManager($dir, 'bvisto');

    $this->fileManager->ensureDirectoryExists($dir, $owner, 'www');
    $this->assertTrue(is_dir($dir));
  }
}