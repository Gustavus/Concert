<?php
/**
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test\Controllers;

use Gustavus\Test\TestObject,
  Gustavus\Concert\Test\TestBase,
  Gustavus\Concert\Controllers\EmailController,
  Gustavus\Concert\Controllers\SharedController,
  Gustavus\Concert\Config,
  Gustavus\Concert\Utility,
  Gustavus\Utility\GACString,
  Gustavus\Utility\Set,
  Campus\Pull\People as CampusPeople;

/**
 * Tests for EmailController
 *
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class EmailControllerTest extends TestBase
{
  /**
   * EmailController
   *
   * @var EmailController
   */
  private $controller;

  /**
   * sets up the object for each test
   * @return void
   */
  public function setUp()
  {
    $_SERVER['REQUEST_URI'] = 'testing';
    $_SERVER['HTTP_REFERER'] = 'https://beta.gac.edu/billy/concert/newPage.php?concert=edit';
    TestBase::$pdo = $this->getDBH();
    parent::setUp();
  }

  /**
   * destructs the object after each test
   * @return void
   */
  public function tearDown()
  {
    unset($this->controller);
    parent::tearDown();
  }

  /**
   * Sets up the controller and injects our test DB connection
   * @return  void
   */
  private function setUpController()
  {
    $this->controller = new TestObject(new EmailController);
  }

  /**
   * @test
   */
  public function notifyUsersOfSharedDraft()
  {
    $this->setUpController();

    $draft = [
      'destFilepath'    => '/cis/www/billy/concert/',
      'draftFilename'   => md5('/cis/www/billy/concert/'),
      'type'            => Config::PUBLIC_DRAFT,
      'username'        => $this->findEmployeeUsername(),
      'additionalUsers' => null,
    ];

    $usernames = [$this->findEmployeeUsername(true), $this->findEmployeeUsername(true)];

    $result = $this->controller->notifyUsersOfSharedDraft(['draft' => $draft, 'usernames' => $usernames]);

    $expectedBcc = [];
    foreach (Config::$devEmails as $devEmail) {
      $expectedBcc[$devEmail] = null;
    }
    foreach ($usernames as $username) {
      $expectedBcc[$username . '@gustavus.edu'] = null;
    }

    $this->checkSentEmailContents(['bcc' => $expectedBcc], 'has shared a draft with you', 'The draft can be viewed or edited at: ' . $this->controller->buildUrl('drafts', ['draftName' => $draft['draftFilename']], '', true), true);
  }

  /**
   * @test
   */
  public function notifyUsersOfSharedDraftFakePerson()
  {
    $this->setUpController();

    $draft = [
      'destFilepath'    => '/cis/www/billy/concert/',
      'draftFilename'   => md5('/cis/www/billy/concert/'),
      'type'            => Config::PUBLIC_DRAFT,
      'username'        => 'fakeUser',
      'additionalUsers' => null,
    ];

    $usernames = [$this->findEmployeeUsername(), $this->findEmployeeUsername(true)];

    $result = $this->controller->notifyUsersOfSharedDraft(['draft' => $draft, 'usernames' => $usernames]);

    $expectedBcc = [];
    foreach (Config::$devEmails as $devEmail) {
      $expectedBcc[$devEmail] = null;
    }
    foreach ($usernames as $username) {
      $expectedBcc[$username . '@gustavus.edu'] = null;
    }

    $this->checkSentEmailContents(['bcc' => $expectedBcc], 'fakeUser has shared a draft with you', 'The draft can be viewed or edited at: ' . $this->controller->buildUrl('drafts', ['draftName' => $draft['draftFilename']], '', true), true);
  }

  /**
   * @test
   */
  public function notifyUsersOfSharedDraftFakeAdditionalUsers()
  {
    $this->setUpController();

    $draft = [
      'destFilepath'    => '/cis/www/billy/concert/',
      'draftFilename'   => md5('/cis/www/billy/concert/'),
      'type'            => Config::PUBLIC_DRAFT,
      'username'        => 'fakeUser',
      'additionalUsers' => null,
    ];

    $usernames = ['testuser'];

    $result = $this->controller->notifyUsersOfSharedDraft(['draft' => $draft, 'usernames' => $usernames]);

    $expectedTo = [];
    foreach (Config::$adminEmails as $adminEmail) {
      $expectedTo[$adminEmail] = null;
    }

    $this->checkSentEmailContents(['to' => $expectedTo], 'Unable to email users about shared draft', 'A draft has been shared with users, but they don\'t exist in the campusAPI', true);
  }

  /**
   * @test
   */
  public function notifyOwnerOfDraftEdit()
  {
    $this->setUpController();

    $draft = [
      'destFilepath'    => '/cis/www/billy/concert/',
      'draftFilename'   => md5('/cis/www/billy/concert/'),
      'type'            => Config::PUBLIC_DRAFT,
      'username'        => $this->findEmployeeUsername(),
      'additionalUsers' => null,
    ];

    $this->authenticate('testuser');
    $result = $this->controller->notifyOwnerOfDraftEdit(['draft' => $draft]);

    $expectedBcc = [];
    foreach (Config::$devEmails as $devEmail) {
      $expectedBcc[$devEmail] = null;
    }
    $expectedBcc[$draft['username'] . '@gustavus.edu'] = null;

    $this->checkSentEmailContents(['bcc' => $expectedBcc], 'has edited a draft', 'has edited the draft you shared', true);

    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function notifyOwnerOfDraftEditMale()
  {
    $this->setUpController();

    $draft = [
      'destFilepath'    => '/cis/www/billy/concert/',
      'draftFilename'   => md5('/cis/www/billy/concert/'),
      'type'            => Config::PUBLIC_DRAFT,
      'username'        => $this->findEmployeeUsername(),
      'additionalUsers' => null,
    ];

    $peoplePuller = new CampusPeople((new TestObject(new SharedController))->getApiKey());
    $peoplePuller->setGender('m');

    $username = $peoplePuller->current()->getUsername();
    $this->authenticate($username);
    $result = $this->controller->notifyOwnerOfDraftEdit(['draft' => $draft]);

    $expectedBcc = [];
    foreach (Config::$devEmails as $devEmail) {
      $expectedBcc[$devEmail] = null;
    }
    $expectedBcc[$draft['username'] . '@gustavus.edu'] = null;

    $this->checkSentEmailContents(['bcc' => $expectedBcc], 'has edited a draft', 'has edited the draft you shared with him', true);

    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function notifyOwnerOfDraftEditFemale()
  {
    $this->setUpController();

    $draft = [
      'destFilepath'    => '/cis/www/billy/concert/',
      'draftFilename'   => md5('/cis/www/billy/concert/'),
      'type'            => Config::PUBLIC_DRAFT,
      'username'        => $this->findEmployeeUsername(),
      'additionalUsers' => null,
    ];

    $peoplePuller = new CampusPeople((new TestObject(new SharedController))->getApiKey());
    $peoplePuller->setGender('f');

    while (empty($peoplePuller->current()->getUsername())) {
      $peoplePuller->next();
    }
    $username = $peoplePuller->current()->getUsername();
    $this->authenticate($username);
    $result = $this->controller->notifyOwnerOfDraftEdit(['draft' => $draft]);

    $expectedBcc = [];
    foreach (Config::$devEmails as $devEmail) {
      $expectedBcc[$devEmail] = null;
    }
    $expectedBcc[$draft['username'] . '@gustavus.edu'] = null;

    $this->checkSentEmailContents(['bcc' => $expectedBcc], 'has edited a draft', 'has edited the draft you shared with her', true);

    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function notifyOwnerOfDraftEditFakePerson()
  {
    $this->setUpController();

    $draft = [
      'destFilepath'    => '/cis/www/billy/concert/',
      'draftFilename'   => md5('/cis/www/billy/concert/'),
      'type'            => Config::PUBLIC_DRAFT,
      'username'        => 'fakeUser',
      'additionalUsers' => null,
    ];

    $username = $this->findEmployeeUsername();
    $this->authenticate($username);
    $result = $this->controller->notifyOwnerOfDraftEdit(['draft' => $draft]);

    $expectedTo = [];
    foreach (Config::$adminEmails as $adminEmail) {
      $expectedTo[$adminEmail] = null;
    }

    $this->checkSentEmailContents(['to' => $expectedTo], 'Unable to notify', 'draft has been edited, but the owner', true);

    $this->unauthenticate();
  }

  /**
   * @test
   */
  public function notifyPublishersOfPendingDraftNoPublishers()
  {
    $this->setUpController();

    $draft = [
      'destFilepath'    => '/cis/www/billy/concert/',
      'draftFilename'   => md5('/cis/www/billy/concert/'),
      'type'            => Config::PENDING_PUBLISH_DRAFT,
      'username'        => $this->findEmployeeUsername(),
      'additionalUsers' => null,
    ];

    $publishers = null;

    $result = $this->controller->notifyPublishersOfPendingDraft(['draft' => $draft, 'publishers' => $publishers]);

    $expectedTo = [];
    foreach (Config::$adminEmails as $adminEmail) {
      $expectedTo[$adminEmail] = null;
    }

    $this->checkSentEmailContents(
        ['to' => $expectedTo],
        'No publishers were found for ' . Utility::removeDocRootFromPath($draft['destFilepath']),
        $this->peoplePuller->current()->getFullName() . ' submitted a draft pending review',
        true
    );
  }

  /**
   * @test
   */
  public function notifyPublishersOfPendingDraft()
  {
    $this->setUpController();

    $draft = [
      'destFilepath'    => '/cis/www/billy/concert/',
      'draftFilename'   => md5('/cis/www/billy/concert/'),
      'type'            => Config::PENDING_PUBLISH_DRAFT,
      'username'        => 'fakeUser',
      'additionalUsers' => null,
    ];

    $publishers = [$this->findEmployeeUsername()];

    $result = $this->controller->notifyPublishersOfPendingDraft(['draft' => $draft, 'publishers' => $publishers]);

    $expectedBcc = [];
    foreach (Config::$devEmails as $devEmail) {
      $expectedBcc[$devEmail] = null;
    }
    foreach ($publishers as $publisher) {
      $expectedBcc[$publisher . '@gustavus.edu'] = null;
    }

    $draftPath = Utility::removeDocRootFromPath($draft['destFilepath']);
    $this->checkSentEmailContents(
        ['bcc' => $expectedBcc],
        $draft['username'] . ' has submitted a draft awaiting',
        'The draft can be reviewed at: ' . (new GACString($draftPath))->addQueryString(['concert' => 'viewDraft', 'concertDraft' => $draft['draftFilename']])->buildUrl()->getValue(),
        true
    );
  }

  /**
   * @test
   */
  public function notifyPublishersOfPendingDraftMultiplePublishers()
  {
    $this->setUpController();

    $draft = [
      'destFilepath'    => '/cis/www/billy/concert/',
      'draftFilename'   => md5('/cis/www/billy/concert/'),
      'type'            => Config::PENDING_PUBLISH_DRAFT,
      'username'        => 'fakeUser',
      'additionalUsers' => null,
    ];

    $publishers = [$this->findEmployeeUsername()];
    $publisherNames = [$this->peoplePuller->current()->getFullName()];

    $publishers[] = $this->findEmployeeUsername(true);
    $publisherNames[] = $this->peoplePuller->current()->getFullName();

    $result = $this->controller->notifyPublishersOfPendingDraft(['draft' => $draft, 'publishers' => $publishers]);

    $expectedBcc = [];
    foreach (Config::$devEmails as $devEmail) {
      $expectedBcc[$devEmail] = null;
    }
    foreach ($publishers as $publisher) {
      $expectedBcc[$publisher . '@gustavus.edu'] = null;
    }

    $draftPath = Utility::removeDocRootFromPath($draft['destFilepath']);
    $this->checkSentEmailContents(
        ['bcc' => $expectedBcc],
        $draft['username'] . ' has submitted a draft awaiting',
        sprintf("The draft can be reviewed at: %s\n\r%s",
            (new GACString($draftPath))->addQueryString(['concert' => 'viewDraft', 'concertDraft' => $draft['draftFilename']])->buildUrl()->getValue(),
            (new Set($publisherNames))->toSentence()->getValue()
        ),
        true
    );
  }

  /**
   * @test
   */
  public function notifyPublishersOfPendingDraftFakePublishers()
  {
    $this->setUpController();

    $draft = [
      'destFilepath'    => '/cis/www/billy/concert/',
      'draftFilename'   => md5('/cis/www/billy/concert/'),
      'type'            => Config::PENDING_PUBLISH_DRAFT,
      'username'        => $this->findEmployeeUsername(),
      'additionalUsers' => null,
    ];

    $publishers = ['fakeUser'];

    $result = $this->controller->notifyPublishersOfPendingDraft(['draft' => $draft, 'publishers' => $publishers]);

    $expectedTo = [];
    foreach (Config::$adminEmails as $adminEmail) {
      $expectedTo[$adminEmail] = null;
    }

    $this->checkSentEmailContents(
        ['to' => $expectedTo],
        'Unable to email publishers for: ' . Utility::removeDocRootFromPath($draft['destFilepath']),
        'A publisher was found',
        true
    );
  }
}