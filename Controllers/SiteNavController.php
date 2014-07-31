<?php
/**
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 */
namespace Gustavus\Concert\Controllers;

use Gustavus\Concert\Config,
  Gustavus\Concert\FileManager,
  Gustavus\Utility\PageUtil,
  Gustavus\Utility\File,
  Gustavus\Concert\PermissionsManager,
  Gustavus\FormBuilderMk2\ElementRenderers\TwigElementRenderer,
  Gustavus\FormBuilderMk2\FormElement,
  Gustavus\Resources\Resource;

/**
 * Handles actions for editing the site nav
 *
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 *
 * @todo  write tests
 */
class SiteNavController extends SharedController
{
  /**
   * Handles editing the site nav. Forwards onto MainController's mosh
   *
   * @param  string $filePath FilePath to the file to get the site nav for.
   * @return string
   */
  private function edit($filePath)
  {
    $this->setTitle('Edit Local Navigation');
    $siteNav = self::getSiteNavForFile($filePath);

    $siteBase = PermissionsManager::findUsersSiteForFile($this->getLoggedInUsername(), Config::removeDocRootFromPath($filePath));

    if (strpos(Config::removeDocRootFromPath($siteNav), $siteBase) !== 0) {
      // the found site nav exists above our current directory.
      $siteNav = Config::addDocRootToPath(dirname($filePath) . DIRECTORY_SEPARATOR . 'site_nav.php');
      $addNoSiteNavMessage = true;
    } else {
      $addNoSiteNavMessage = false;
    }

    // set temporary variables for forwarding
    $_GET['concert'] = 'edit';
    $_GET['forwardedFrom'] = 'siteNav';
    // we want the barebone version of the template
    $barebonesSet = isset($_GET['barebones']);
    if (!$barebonesSet) {
      $_GET['barebones'] = 'true';
    }
    $_GET['concertMoshed'] = 'false';

    $moshResult = $this->forward('mosh', [$siteNav]);

    if (isset($moshResult['action']) && $moshResult['action'] === 'return') {
      $this->setLocalNavigation($moshResult['value']);
    }
    // we don't want a barebone version of the template to be rendered at this point
    if (!$barebonesSet) {
      unset($_GET['barebones']);
    }
    // we don't want this temporary flag set anymore
    unset($_GET['forwardedFrom']);

    if ($addNoSiteNavMessage) {
      // check to see if the user has a draft of the site nav.
      $fm = new FileManager($this->getLoggedInUsername(), $siteNav, null, $this->getDB());
      $draft = $fm->getDraftForUser($this->getLoggedInUsername());
      if (empty($draft)) {
        $this->addSessionMessage('There was no site nav found in the current site. We created a blank one for you to edit.');
      } else {
        $this->addSessionMessage('There was no site nav found for the current site, but we did find a draft for you to edit.');
      }
    }

    return $this->renderPage();
  }

  /**
   * Handles drafts for the site nav. Forwards onto MainController's mosh
   *
   * @param  string $filePath FilePath to the file for drafts
   * @return string|array
   */
  private function draft($filePath)
  {
    $this->setTitle('Local Navigation Drafts');
    $siteNav = self::getSiteNavForFile($filePath);

    // set temporary variables for forwarding
    $_GET['forwardedFrom'] = 'siteNav';
    // we want the barebone version of the template but we need to check to see if it has already been set for later
    $barebonesSet = isset($_GET['barebones']);
    if (!$barebonesSet) {
      $_GET['barebones'] = 'true';
    }
    $_GET['concertMoshed'] = 'false';

    $moshResult = $this->forward('mosh', [$siteNav]);

    // we don't want this temporary flag set anymore
    unset($_GET['forwardedFrom']);
    if (!$barebonesSet) {
      // add the current file
      // @todo should this happen?
      //$this->setContent((new File(Config::addDocRootToPath($filePath)))->loadAndEvaluate());
      // we don't want a barebone version of the template to be rendered if one wasn't requested before coming here
      unset($_GET['barebones']);
      // we need to set the local nav to be the draft
      if (isset($moshResult['action']) && $moshResult['action'] === 'return' && !empty($moshResult['value'])) {
        $this->addSessionMessage(Config::SITE_NAV_DRAFT_NOTE, false);
        $this->setLocalNavigation($moshResult['value']);
      } else if (is_string($moshResult)) {
        $this->addSessionMessage(Config::SITE_NAV_DRAFT_NOTE, false);
        $this->setLocalNavigation($moshResult);
      }
    } else {
      return $moshResult;
    }
    if ($this->getMethod() === 'POST') {
      $value = true;
    } else {
      $value = $this->renderPage();
    }
    return ['action' => 'return', 'value' => $value];
  }

  /**
   * Handles deleting the site nav. Forwards onto MainController's mosh
   *
   * @param  string $filePath FilePath to the file we want to delete the site nav for
   * @return string|array
   */
  private function delete($filePath)
  {
    $this->setTitle('Delete Local Navigation');
    $siteNav = self::getSiteNavForFile($filePath);

    // set temporary variables for forwarding
    $_GET['forwardedFrom'] = 'siteNav';
    // we want the barebone version of the template but we need to check to see if it has already been set for later
    $barebonesSet = isset($_GET['barebones']);
    if (!$barebonesSet) {
      $_GET['barebones'] = 'true';
    }
    $_GET['concertMoshed'] = 'false';

    $moshResult = $this->forward('mosh', [$siteNav]);

    // we don't want this temporary flag set anymore
    unset($_GET['forwardedFrom']);
    if (!$barebonesSet) {
      // add the current file
      $this->setContent((new File(Config::addDocRootToPath($filePath)))->loadAndEvaluate());
      // we don't want a barebone version of the template to be rendered if one wasn't requested before coming here
      unset($_GET['barebones']);
    } else {
      return $moshResult;
    }
    return ['action' => 'return', 'value' => $this->renderPage()];
  }

  /**
   * Stops editing the site nav. Forwards back to mosh.
   *
   * @param  string $filePath FilePath to get the site nav we want to stop editing
   * @return array
   */
  private function stopEditing($filePath)
  {
    $siteNav = self::getSiteNavForFile($filePath);
    $_GET['forwardedFrom'] = 'siteNav';
    $_GET['concertMoshed'] = 'false';
    $moshResult = $this->forward('mosh', [$siteNav]);
    unset($_GET['forwardedFrom']);
    return $moshResult;
  }

  /**
   * Handles site nave actions
   *
   * @param  array $params Params from Router
   * @return array|string
   */
  public function handleSiteNavActions($params)
  {
    if (self::userIsEditing()) {
      return ['action' => 'return', 'value' => $this->edit($params['filePath'])];
    } else if (self::isDraftRequest()) {
      return $this->draft($params['filePath']);
    } else if (self::userIsDeleting()) {
      return $this->delete($params['filePath']);
    } else if (self::userIsDoneEditing()) {
      return $this->stopEditing($params['filePath']);
    }
  }
}