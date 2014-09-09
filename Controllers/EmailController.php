<?php
/**
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 */
namespace Gustavus\Concert\Controllers;

use Gustavus\Concert\Config,
  Gustavus\Concert\Utility,
  Gustavus\GACMailer\EmailMessage,
  Gustavus\Utility\String,
  Gustavus\Utility\Set,
  Campus\Pull\People as CampusPeople;

/**
 * Handles main Concert actions
 *
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 *
 * @todo  write tests
 */
class EmailController extends SharedController
{
  /**
   * Notifies users that a draft has been shared with them
   *
   * @param  array  $params Params with keys of draft, and username
   * @return string
   */
  public function notifyUsersOfSharedDraft(array $params)
  {
    assert('isset($params[\'draft\'], $params[\'usernames\'])');

    $draft     = $params['draft'];
    $usernames = $params['usernames'];
    $peoplePuller = new CampusPeople($this->getApiKey());
    $draftOwner   = $peoplePuller->setUsername($draft['username'])->current();

    if (is_object($draftOwner)) {
      $name    = $draftOwner->getFullName();
      $replyTo = $draftOwner->getGustavusEmailAddress();
    } else {
      $name    = $draft['username'];
      $replyTo = 'no-reply@gustavus.edu';
    }
    $subject = sprintf('%s has shared a draft with you', $name);

    $body = sprintf("%s has shared a draft with you for the page: %s\n\rThe draft can be viewed or edited at: %s",
        $name,
        Utility::removeDocRootFromPath($draft['destFilepath']),
        $this->buildUrl('drafts', ['draftName' => $draft['draftFilename']], '', true)
    );

    $bcc = [];
    foreach ($usernames as $username) {
      $peoplePuller = new CampusPeople($this->getApiKey());
      $person = $peoplePuller->setUsername($username)->current();
      if (is_object($person)) {
        $bcc[] = $person->getGustavusEmailAddress();
      }
    }

    if (empty($bcc)) {
      $body = sprintf("A draft has been shared with users, but they don't exist in the campusAPI\n\rUsers: %s", (new Set($usernames))->toSentence()->getValue());
      $message = (new EmailMessage)
        ->setSubject('Unable to email users about shared draft for: ' . Utility::removeDocRootFromPath($draft['destFilepath']))
        ->setFrom('concert@gustavus.edu')
        ->setReplyTo('no-reply@gustavus.edu')
        ->setTo(Config::$adminEmails)
        ->setBody($body)
        ->setDebuggingRecipients(Config::$devEmails);

      return $message->send();
    }

    $bcc = array_merge(Config::$devEmails, $bcc);

    $message = (new EmailMessage)
      ->setSubject($subject)
      ->setFrom('concert@gustavus.edu')
      ->setReplyTo($replyTo)
      ->setBcc($bcc)
      ->setBody($body)
      ->setDebuggingRecipients(Config::$devEmails);

    return $message->send();
  }

  /**
   * Notify publishers that a draft has been submitted pending their approval.
   *   If no publishers exist, a notification will be sent to the admins alerting them of this.
   *
   * @param  array  $params Params from router
   * @return boolean
   */
  public function notifyPublishersOfPendingDraft(array $params)
  {
    assert('isset($params[\'draft\']) && isset($params[\'publishers\']) || $params[\'publishers\'] === null');

    $draft      = $params['draft'];
    $publishers = $params['publishers'];
    $draftPath  = Utility::removeDocRootFromPath($draft['destFilepath']);

    $peoplePuller = new CampusPeople($this->getApiKey());
    $draftOwner   = $peoplePuller->setUsername($draft['username'])->current();

    if (is_object($draftOwner)) {
      $name    = $draftOwner->getFullName();
      $replyTo = $draftOwner->getGustavusEmailAddress();
    } else {
      $name    = $draft['username'];
      $replyTo = 'no-reply@gustavus.edu';
    }

    if (empty($publishers)) {
      $body = sprintf("No publishers were found for %s\n\r%s submitted a draft pending review to be published.", $draftPath, $name);
      $message = (new EmailMessage)
        ->setSubject('No publishers were found for ' . $draftPath)
        ->setFrom('concert@gustavus.edu')
        ->setReplyTo('no-reply@gustavus.edu')
        ->setTo(Config::$adminEmails)
        ->setBody($body)
        ->setDebuggingRecipients(Config::$devEmails);

      return $message->send();
    }

    $subject = sprintf('%s has submitted a draft awaiting your approval', $name);

    $bcc = Config::$devEmails;
    $publisherNames = [];
    foreach ($publishers as $publisher) {
      $peoplePuller = new CampusPeople($this->getApiKey());
      $person = $peoplePuller->setUsername($publisher)->current();
      if (is_object($person)) {
        $bcc[] = $person->getGustavusEmailAddress();
        $publisherNames[] = $person->getFullName();
      }
    }

    if (empty($publisherNames)) {
      $body = sprintf("A publisher was found, but they don't exist in the campusAPI\n\rFound publishers: %s", (new Set($publishers))->toSentence()->getValue());
      $message = (new EmailMessage)
        ->setSubject('Unable to email publishers for: ' . $draftPath)
        ->setFrom('concert@gustavus.edu')
        ->setReplyTo('no-reply@gustavus.edu')
        ->setTo(Config::$adminEmails)
        ->setBody($body)
        ->setDebuggingRecipients(Config::$devEmails);

      return $message->send();
    }

    if (count($publisherNames) > 1) {
      // @todo make this add the publishers and alert them that others also received this. If 1 publisher, we still need to add them to bcc
      $othersNotifiedMessage = sprintf("\n\r%s have also been notified.", (new Set($publisherNames))->toSentence()->getValue());
    } else {
      $othersNotifiedMessage = '';
    }

    $body = sprintf("%s has submitted a draft to be published for: %s\n\rThe draft can be reviewed at: %s%s",
        $name,
        $draftPath,
        (new String($draftPath))->addQueryString(['concert' => 'viewDraft', 'concertDraft' => $draft['draftFilename']])->buildUrl()->getValue(),
        $othersNotifiedMessage
    );

    $message = (new EmailMessage)
      ->setSubject($subject)
      ->setFrom('concert@gustavus.edu')
      ->setReplyTo($replyTo)
      ->setBcc($bcc)
      ->setBody($body)
      ->setDebuggingRecipients(Config::$devEmails);

    return $message->send();
  }

  /**
   * Sends an email to the owner of the draft that their draft has been published.
   *
   * @param  array  $params Array with keys of draft and message
   * @return boolean False if the mail couldn't be sent. True otherwise.
   */
  public function notifyPendingDraftOwnerOfPublish(array $params)
  {
    assert('isset($params[\'draft\'])');
    $draft = $params['draft'];

    $bcc = Config::$devEmails;
    $peoplePuller = new CampusPeople($this->getApiKey());
    $person = $peoplePuller->setUsername($draft['username'])->current();
    if (is_object($person)) {
      $to = $person->getGustavusEmailAddress();
    } else {
      return false;
    }

    $loggedInPerson = $this->getLoggedInPerson();

    if ($loggedInPerson !== null && !$loggedInPerson->isFake()) {
      $replyTo   = $loggedInPerson->getGustavusEmailAddress();
      $publisher = $loggedInPerson->getFullName();
    } else {
      $replyTo = 'no-reply@gustavus.edu';
      $publisher = $this->getLoggedInUsername();
    }

    $body = sprintf('%s published your draft of %s', $publisher, (new String(Utility::removeDocRootFromPath($draft['destFilepath'])))->buildUrl()->getValue());

    if (!empty($params['message'])) {
      $body .= sprintf(" with the following comment:\n\r\"%s\"", $params['message']);
    }

    $message = (new EmailMessage)
      ->setSubject('Your pending draft has been published.')
      ->setFrom('concert@gustavus.edu')
      ->setReplyTo($replyTo)
      ->setBcc($bcc)
      ->setTo($to)
      ->setBody($body)
      ->setDebuggingRecipients(Config::$devEmails);

    return ($message->send() > 0);
  }

  /**
   * Sends an email to the owner of the draft that their draft has been rejected.
   *
   * @param  array  $params Array with keys of draft and message
   * @return boolean False if the mail couldn't be sent. True otherwise.
   */
  public function notifyPendingDraftOwnerOfRejection(array $params)
  {
    assert('isset($params[\'draft\'])');
    $draft = $params['draft'];

    $bcc = Config::$devEmails;
    $peoplePuller = new CampusPeople($this->getApiKey());
    $person = $peoplePuller->setUsername($draft['username'])->current();
    if (is_object($person)) {
      $to = $person->getGustavusEmailAddress();
    } else {
      return false;
    }

    $loggedInPerson = $this->getLoggedInPerson();

    if ($loggedInPerson !== null && !$loggedInPerson->isFake()) {
      $replyTo   = $loggedInPerson->getGustavusEmailAddress();
      $publisher = $loggedInPerson->getFullName();
    } else {
      $replyTo = 'no-reply@gustavus.edu';
      $publisher = $this->getLoggedInUsername();
    }

    $body = sprintf('%s rejected your draft of %s', $publisher, (new String(Utility::removeDocRootFromPath($draft['destFilepath'])))->buildUrl()->getValue());

    if (!empty($params['message'])) {
      $body .= sprintf(" with the following comment:\n\r\"%s\"", $params['message']);
    }

    $message = (new EmailMessage)
      ->setSubject('Your pending draft has been rejected.')
      ->setFrom('concert@gustavus.edu')
      ->setReplyTo($replyTo)
      ->setBcc($bcc)
      ->setTo($to)
      ->setBody($body)
      ->setDebuggingRecipients(Config::$devEmails);

    return ($message->send() > 0);
  }
}