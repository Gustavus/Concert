<?php
/**
 * @package  Concert
 * @subpackage Test\Scripts\Mover
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test\Mover;

use Gustavus\Concert\Scripts\Mover\PageMover,
  Gustavus\Concert\Test\TestBase,
  Gustavus\Concert\Config,
  Gustavus\Concert\Utility,
  Gustavus\Concert\PermissionsManager,
  Gustavus\Test\TestObject;

/**
 * Class to test PageMover script
 *
 * @package  Concert
 * @subpackage Test\Scripts\Mover
 * @author  Billy Visto
 */
class PageMoverTest extends TestBase
{
  /**
   * pageMover object to test on
   *
   * @var pageMover
   */
  private $pageMover;

  /**
   * Sets up environment for every test
   *
   * @return void
   */
  public function setUp()
  {
    self::removeFiles(self::$testFileDir);
    parent::setUp();
  }

  /**
   * Tears down the environment for every test
   *
   * @return void
   */
  public function tearDown()
  {
    unset($this->pageMover);
    parent::tearDown();
  }

  /**
   * Sets up the converter for testing
   *
   * @param string $filePath Path to the file to test
   * @param string $destPath Path to the file to test
   * @param \Doctrine\DBAL\Connection $dbal DBAL connection to inject into our mover
   * @param  boolean $touchFilesystem Whether to touch the filesystem or not.
   * @return  void
   */
  private function setUpMover($filePath, $destPath, $dbal = null, $touchFilesystem = true)
  {
    $this->pageMover = new TestObject(new PageMover($filePath, $destPath, $touchFilesystem));
    $this->set('Scripts\Mover\PageMover', 'dbal', $dbal);
  }

  /**
   * @test
   */
  public function adjustRevisions()
  {
    $origManageRevAccessLevels = Config::$manageRevisionsAccessLevels;
    Config::$manageRevisionsAccessLevels = ['test'];
    $filePath = self::$testFileDir . 'arst.php';
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $fileContents = '<?php ?>test contents<?php arst;?>more';
    file_put_contents($filePath, $fileContents);
    $this->buildFileManager('bvisto', $filePath);
    $this->assertTrue($this->fileManager->saveRevision());

    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->fileManager->getDBAL());
    $this->assertSame(1, $revisionsAPI->getRevisionCount());

    $newFilePath = self::$testFileDir . 'arstNew.php';
    // get a revisions api for the new path
    $newRevisionsAPI = Utility::getRevisionsAPI($newFilePath, $this->fileManager->getDBAL());
    $this->assertSame(0, $newRevisionsAPI->getRevisionCount());

    $this->setUpMover($filePath, $newFilePath, $this->fileManager->getDBAL());

    $this->assertSame(2, $this->pageMover->adjustRevisions($filePath, $newFilePath));

    $this->assertSame(1, $newRevisionsAPI->getRevisionCount());

    // build a new revisionsAPI for the original path so we can verify that this one no longer has any revisions.
    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->fileManager->getDBAL());
    $this->assertSame(0, $revisionsAPI->getRevisionCount());

    $this->unauthenticate();
    $this->destructDB();
    Config::$manageRevisionsAccessLevels = $origManageRevAccessLevels;
  }

  /**
   * @test
   */
  public function adjustDrafts()
  {
    $filePath = self::$testFileDir . 'arst.php';
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', self::$testFileDir, 'test']);

    $fileContents = '<?php ?>test contents<?php arst;?>more';
    file_put_contents($filePath, $fileContents);

    // save a few drafts
    $this->buildFileManager('jerry', $filePath);
    $draftPath = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);
    $this->assertContains(self::$testFileDir, $draftPath);
    $this->assertTrue(file_exists($draftPath));
    $this->fileManager->stopEditing();

    $this->buildFileManager('bvisto', $filePath);
    $draftPath = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);
    $this->assertContains(self::$testFileDir, $draftPath);
    $this->assertTrue(file_exists($draftPath));

    // set up our mover and adjust drafts
    $newFilePath = self::$testFileDir . 'arstNew.php';
    $this->setUpMover($filePath, $newFilePath, $this->fileManager->getDBAL());

    $this->assertSame(2, $this->pageMover->adjustDrafts($filePath, $newFilePath));

    // test results
    $this->fileManager->cachedDraftsByName = [];
    $this->assertEmpty($this->fileManager->getDrafts());

    $this->buildFileManager('bvisto', $newFilePath);
    $newDrafts = $this->fileManager->getDrafts();

    foreach ($newDrafts as $newDraft) {
      $this->assertSame($newFilePath, $newDraft['destFilepath']);

      $this->assertTrue(file_exists(Config::$draftDir . $newDraft['draftFilename']));
    }

    $this->unauthenticate();
    $this->destructDB();
  }

  /**
   * @test
   */
  public function adjustSite()
  {
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);

    $this->setUpMover(self::$testFileDir, self::$testFileDir . '/arstarst', $this->get('Utility', 'dbal'));
    $this->assertTrue($this->pageMover->adjustSite(self::$testFileDir, self::$testFileDir . '/arstarst'));
    // already moved
    $this->assertFalse($this->pageMover->adjustSite(self::$testFileDir, self::$testFileDir . '/arstarst'));

    $sites = PermissionsManager::getSitesFromBase('/');
    $this->assertContains(self::$testFileDir . 'arstarst', $sites);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function moveFile()
  {
    $origManageRevAccessLevels = Config::$manageRevisionsAccessLevels;
    Config::$manageRevisionsAccessLevels = ['test'];
    $filePath = self::$testFileDir . 'arst.php';
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', self::$testFileDir, 'test']);

    $fileContents = '<?php ?>test contents<?php arst;?>more';
    file_put_contents($filePath, $fileContents);

    // save a few drafts
    $this->buildFileManager('jerry', $filePath);
    $draftPath = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);
    $this->assertContains(self::$testFileDir, $draftPath);
    $this->assertTrue(file_exists($draftPath));
    $this->fileManager->stopEditing();

    $this->buildFileManager('bvisto', $filePath);
    $draftPath = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);
    $this->assertContains(self::$testFileDir, $draftPath);
    $this->assertTrue(file_exists($draftPath));
    $this->assertTrue($this->fileManager->saveRevision());

    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->fileManager->getDBAL());
    $this->assertSame(1, $revisionsAPI->getRevisionCount());


    // set up our mover and move the file
    $newFilePath = self::$testFileDir . 'arstNew.php';
    $this->setUpMover($filePath, $newFilePath, $this->fileManager->getDBAL());

    $result = $this->pageMover->move();

    $expected = [
      'adjustedFiles' => [
        '/cis/lib/Gustavus/Concert/Test/files/arst.php' => [
          'newLocation'      => '/cis/lib/Gustavus/Concert/Test/files/arstNew.php',
          'revisionsUpdated' => 2,
          'draftsUpdated'    => 2,
        ],
      ],
      'adjustedSites' => [],
      'movedFiles'    => ['/cis/lib/Gustavus/Concert/Test/files/arst.php' =>
    '/cis/lib/Gustavus/Concert/Test/files/arstNew.php'],
      'errors'        => [],
    ];

    $this->assertSame($expected, $result);


    $newRevisionsAPI = Utility::getRevisionsAPI($newFilePath, $this->fileManager->getDBAL());
    $this->assertSame(1, $newRevisionsAPI->getRevisionCount());

    // test results
    $this->fileManager->cachedDraftsByName = [];
    $this->assertEmpty($this->fileManager->getDrafts());

    $this->buildFileManager('bvisto', $newFilePath);
    $newDrafts = $this->fileManager->getDrafts();

    foreach ($newDrafts as $newDraft) {
      $this->assertSame($newFilePath, $newDraft['destFilepath']);

      $this->assertTrue(file_exists(Config::$draftDir . $newDraft['draftFilename']));
    }

    $this->unauthenticate();
    $this->destructDB();
    Config::$manageRevisionsAccessLevels = $origManageRevAccessLevels;
  }

  /**
   * @test
   */
  public function moveNothing()
  {
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir . 'arstarst', 'test']);

    $this->setUpMover(self::$testFileDir, self::$testFileDir . '/new', $this->get('Utility', 'dbal'));

    $result = $this->pageMover->move();
    $expected = [
      'adjustedFiles' => [],
      'adjustedSites' => [
        '/cis/lib/Gustavus/Concert/Test/files/',
        '/cis/lib/Gustavus/Concert/Test/files/arstarst',
      ],
      'movedFiles'    => [],
      'errors'        => [],
    ];

    $this->assertSame($expected, $result);

    $sites = PermissionsManager::getSitesFromBase('/');

    $this->assertContains(self::$testFileDir . 'new/arstarst', $sites);
    $this->assertContains(self::$testFileDir . 'new/', $sites);
    $this->assertNotContains(self::$testFileDir . 'arstarst', $sites);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function move()
  {
    $this->buildDB();
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', self::$testFileDir . 'arstarst', 'test']);

    file_put_contents(self::$testFileDir . 'arst.php', 'test');
    mkdir(self::$testFileDir . 'arstarst');
    file_put_contents(self::$testFileDir . 'arstarst/test.php', 'test');

    $this->setUpMover(self::$testFileDir, self::$testFileDir . '/new', $this->get('Utility', 'dbal'));

    $result = $this->pageMover->move();

    $expected = [
      'adjustedFiles' => [
        '/cis/lib/Gustavus/Concert/Test/files/arst.php' => [
          'newLocation'      => '/cis/lib/Gustavus/Concert/Test/files/new/arst.php',
          'revisionsUpdated' => 0,
          'draftsUpdated'    => 0,
        ],
        '/cis/lib/Gustavus/Concert/Test/files/arstarst/test.php' => [
          'newLocation'      => '/cis/lib/Gustavus/Concert/Test/files/new/arstarst/test.php',
          'revisionsUpdated' => 0,
          'draftsUpdated'    => 0,
        ],
      ],
      'adjustedSites' => [
        '/cis/lib/Gustavus/Concert/Test/files/',
        '/cis/lib/Gustavus/Concert/Test/files/arstarst',
      ],
      'movedFiles' => [
        '/cis/lib/Gustavus/Concert/Test/files/arst.php' => '/cis/lib/Gustavus/Concert/Test/files/new/arst.php',
        '/cis/lib/Gustavus/Concert/Test/files/arstarst' => '/cis/lib/Gustavus/Concert/Test/files/new/arstarst',
      ],
      'errors' => [],
    ];

    $this->assertSame($expected, $result);

    $this->assertFalse(file_exists(self::$testFileDir . 'arst.php'));
    $this->assertFalse(file_exists(self::$testFileDir . 'arstarst/test.php'));
    $this->assertFalse(file_exists(self::$testFileDir . 'arstarst'));

    $sites = PermissionsManager::getSitesFromBase('/');
    $this->assertContains(self::$testFileDir . 'new/', $sites);
    $this->assertContains(self::$testFileDir . 'new/arstarst', $sites);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function moveDirWithDraftsAndRevisions()
  {
    $this->buildDB();

    $testDir = self::$testFileDir . 'testing/';

    $this->call('PermissionsManager', 'saveUserPermissions', ['bvisto', $testDir, 'test']);
    $this->call('PermissionsManager', 'saveUserPermissions', ['jerry', $testDir . 'arstarst', 'test']);

    mkdir($testDir);
    file_put_contents($testDir . 'arst.php', 'test');
    mkdir($testDir . 'arstarst');
    file_put_contents($testDir . 'arstarst/test.php', 'test');

    // save a few drafts
    $this->buildFileManager('jerry', $testDir . 'arstarst/test.php');
    $draftPath = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);
    $this->assertContains(self::$testFileDir, $draftPath);
    $this->assertTrue(file_exists($draftPath));
    $this->fileManager->stopEditing();

    $this->buildFileManager('bvisto', $testDir . 'arst.php');
    $draftPath = $this->fileManager->saveDraft(Config::PUBLIC_DRAFT);
    $this->assertContains(self::$testFileDir, $draftPath);
    $this->assertTrue(file_exists($draftPath));
    $this->assertTrue($this->fileManager->saveRevision());

    // make sure we can see our revision
    $revisionsAPI = Utility::getRevisionsAPI($testDir . 'arst.php', $this->fileManager->getDBAL());
    $this->assertSame(1, $revisionsAPI->getRevisionCount());

    $this->setUpMover($testDir, $testDir . '/new', $this->fileManager->getDBAL());

    $result = $this->pageMover->move();

    $expected = [
      'adjustedFiles' => [
        '/cis/lib/Gustavus/Concert/Test/files/testing/arst.php' => [
          'newLocation'      => '/cis/lib/Gustavus/Concert/Test/files/testing/new/arst.php',
          'revisionsUpdated' => 2,
          'draftsUpdated'    => 1,
        ],
        '/cis/lib/Gustavus/Concert/Test/files/testing/arstarst/test.php' => [
          'newLocation'      => '/cis/lib/Gustavus/Concert/Test/files/testing/new/arstarst/test.php',
          'revisionsUpdated' => 0,
          'draftsUpdated'    => 1,
        ],
      ],
      'adjustedSites' => [
        '/cis/lib/Gustavus/Concert/Test/files/testing/',
        '/cis/lib/Gustavus/Concert/Test/files/testing/arstarst',
      ],
      'movedFiles' => [
        '/cis/lib/Gustavus/Concert/Test/files/testing/arst.php' => '/cis/lib/Gustavus/Concert/Test/files/testing/new/arst.php',
        '/cis/lib/Gustavus/Concert/Test/files/testing/arstarst' => '/cis/lib/Gustavus/Concert/Test/files/testing/new/arstarst',
      ],
      'errors' => [],
    ];

    $this->assertSame($expected, $result);

    $this->assertFalse(file_exists($testDir . 'arst.php'));
    $this->assertFalse(file_exists($testDir . 'arstarst/test.php'));
    $this->assertFalse(file_exists($testDir . 'arstarst'));

    $sites = PermissionsManager::getSitesFromBase('/');
    $this->assertContains($testDir . 'new/', $sites);
    $this->assertContains($testDir . 'new/arstarst', $sites);

    // make sure our revision has been moved
    $newRevisionsAPI = Utility::getRevisionsAPI($testDir . 'new/arst.php', $this->fileManager->getDBAL());
    $this->assertSame(1, $newRevisionsAPI->getRevisionCount());

    // test results
    $this->fileManager->cachedDraftsByName = [];
    $this->assertEmpty($this->fileManager->getDrafts());

    $this->buildFileManager('bvisto', $testDir . 'new/arst.php');
    $newDrafts = $this->fileManager->getDrafts();
    $this->assertNotEmpty($newDrafts);

    foreach ($newDrafts as $newDraft) {
      $this->assertSame($testDir . 'new/arst.php', $newDraft['destFilepath']);

      $this->assertTrue(file_exists(Config::$draftDir . $newDraft['draftFilename']));
    }

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getDeletedFilesEmpty()
  {
    $this->buildDB();

    $this->setUpMover(self::$testFileDir, self::$testFileDir . '/newarst/');
    $this->assertEmpty($this->pageMover->getDeletedFiles());

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getDeletedFile()
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

    $this->setUpMover(self::$testFileDir . 'index.php', self::$testFileDir . '/newarst/index.php', $this->fileManager->getDBAL(), false);
    $result = $this->pageMover->getDeletedFiles();

    $expected = [
      [
        'destFilepath' => '/cis/lib/Gustavus/Concert/Test/files/index.php',
      ],
    ];

    $this->assertNotEmpty($result);
    $this->assertSame($expected, $result);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function getDeleted()
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

    $this->setUpMover(self::$testFileDir, self::$testFileDir . '/newarst/', $this->fileManager->getDBAL());

    mkdir(self::$testFileDir . '/newarst/');
    $result = $this->pageMover->getDeletedFiles();

    $expected = [
      [
        'destFilepath' => '/cis/lib/Gustavus/Concert/Test/files/index.php',
      ],
    ];

    $this->assertNotEmpty($result);
    $this->assertSame($expected, $result);

    $this->destructDB();
  }

  /**
   * @test
   */
  public function adjustStagedFiles()
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

    file_put_contents(self::$testFileDir . 'index.php', 'test');
    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');
    $this->fileManager->stageFile();

    mkdir(self::$testFileDir . 'hello');
    file_put_contents(self::$testFileDir . 'hello/index.php', 'test');
    $this->buildFileManager('bvisto', self::$testFileDir . 'hello/index.php');
    $this->fileManager->stageFile();

    mkdir(self::$testFileDir . 'newarst/');
    $this->setUpMover(self::$testFileDir, self::$testFileDir . 'newarst/', $this->fileManager->getDBAL(), false);

    $this->pageMover->adjustStagedFiles();

    $dbal = $this->pageMover->getDBAL();
    $qb = $dbal->createQueryBuilder();


    $qb->addSelect('destFilepath')
      ->addSelect('id')
      ->addSelect('srcFilename')
      ->from('stagedFiles', 'sf')
      ->where($qb->expr()->like('destFilepath', ':destFilepath'));
    $destFilepath = self::$testFileDir . '%';

    $stagedFiles = $dbal->fetchAll($qb->getSQL(), [':destFilepath' => $destFilepath]);

    $expected = [
      [
        'destFilepath' => '/cis/lib/Gustavus/Concert/Test/files/newarst/index.php',
        'id'           => '1',
        'srcFilename'  => 'e7da8300c506f495d4ce4a63f87c8ab6',
      ],
      [
        'destFilepath' => '/cis/lib/Gustavus/Concert/Test/files/newarst/index.php',
        'id'           => '2',
        'srcFilename'  => 'e7da8300c506f495d4ce4a63f87c8ab6',
      ],
      [
        'destFilepath' => '/cis/lib/Gustavus/Concert/Test/files/newarst/hello/index.php',
        'id'           => '3',
        'srcFilename'  => '72bb2335ad0a6fc34ddbfd51f021eab7',
      ],
    ];

    $this->assertSame($expected, $stagedFiles);
    $this->destructDB();
  }

  /**
   * @test
   */
  public function moveTestingStagedFiles()
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

    file_put_contents(self::$testFileDir . 'index.php', 'test');
    $this->buildFileManager('bvisto', self::$testFileDir . 'index.php');
    $this->fileManager->stageFile();

    mkdir(self::$testFileDir . 'hello');
    file_put_contents(self::$testFileDir . 'hello/index.php', 'test');
    $this->buildFileManager('bvisto', self::$testFileDir . 'hello/index.php');
    $this->fileManager->stageFile();

    mkdir(self::$testFileDir . 'newarst/');
    $this->setUpMover(self::$testFileDir, self::$testFileDir . 'newarst/', $this->fileManager->getDBAL(), false);

    $this->pageMover->move();

    $dbal = $this->pageMover->getDBAL();
    $qb = $dbal->createQueryBuilder();


    $qb->addSelect('destFilepath')
      ->addSelect('id')
      ->addSelect('srcFilename')
      ->from('stagedFiles', 'sf')
      ->where($qb->expr()->like('destFilepath', ':destFilepath'));
    $destFilepath = self::$testFileDir . '%';

    $stagedFiles = $dbal->fetchAll($qb->getSQL(), [':destFilepath' => $destFilepath]);

    $expected = [
      [
        'destFilepath' => '/cis/lib/Gustavus/Concert/Test/files/newarst/index.php',
        'id'           => '1',
        'srcFilename'  => 'e7da8300c506f495d4ce4a63f87c8ab6',
      ],
      [
        'destFilepath' => '/cis/lib/Gustavus/Concert/Test/files/newarst/index.php',
        'id'           => '2',
        'srcFilename'  => 'e7da8300c506f495d4ce4a63f87c8ab6',
      ],
      [
        'destFilepath' => '/cis/lib/Gustavus/Concert/Test/files/newarst/hello/index.php',
        'id'           => '3',
        'srcFilename'  => '72bb2335ad0a6fc34ddbfd51f021eab7',
      ],
    ];

    $this->assertSame($expected, $stagedFiles);
    $this->destructDB();
  }
}