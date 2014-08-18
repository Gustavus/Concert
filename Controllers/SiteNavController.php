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
 * @todo  Add the ability to inherit or include a parent nav. (Potentially a bad idea as it might make for some crazy site navs. We don't want that. Maybe for some power users.)
 */
class SiteNavController extends SharedController
{
  /**
   * Handles editing the site nav. Forwards onto MainController's mosh
   *
   * @param  string $siteNav Path to the siteNav to edit.
   * @return string
   */
  private function edit($siteNav)
  {
    $this->setTitle('Edit Local Navigation');

    $origGet = $_GET;

    // set temporary variables for forwarding
    $_GET['concert']       = 'edit';
    $_GET['forwardedFrom'] = 'siteNav';
    // we want the barebone version of the template
    $_GET['barebones']     = 'true';
    $_GET['concertMoshed'] = 'false';

    if (self::isSiteNavShared($siteNav)) {
      $this->addConcertMessage(Config::buildSharedSiteNavNote(dirname($siteNav), false));
    }

    $moshResult = $this->forward('mosh', [$siteNav]);

    if (isset($moshResult['action']) && $moshResult['action'] === 'return') {
      $this->setLocalNavigation($moshResult['value']);
    }
    // we don't want our temporary GET parameters for moshing set anymore.
    self::setGET($origGet);

    return $this->displayPage();
  }

  /**
   * Handles editing the site nav. Forwards onto MainController's mosh
   *
   * @param  string $filePath FilePath to the file to get the site nav for.
   * @return string
   */
  private function createOrEditSiteNav($filePath)
  {
    // we only want users to be able to publish
    self::overrideVisibleEditingButtons(['publish']);
    // check to see if the site nav already exists.
    // if it is from a parent site, we want to give them the offer to copy their parent.
    // if it exists in a parent directory, we want to give them the option to create a new one for the current file's directory, or to edit the parent.
    //
    // Just kidding. For now, we want to either allow them to edit a current nav, create one in their current directory, delete one, or talk to us.
    $siteNav = self::getSiteNavForFile($filePath);

    $filePathFromDocRoot = Config::removeDocRootFromPath($filePath);
    $siteNavFromDocRoot  = Config::removeDocRootFromPath($siteNav);

    // Location of the current directory's site nav. Even if it doesn't exist.
    $currentDirNav = Config::addDocRootToPath(dirname($filePath) . DIRECTORY_SEPARATOR . 'site_nav.php');

    if (self::isGlobalNav($siteNav)) {
      // no site nav exists for this site up to the global nav.
      // we don't need to prompt the user for anything. Just create our site nav.
      return $this->create($currentDirNav);
    }

    $siteBase = PermissionsManager::findUsersSiteForFile($this->getLoggedInUsername(), $filePathFromDocRoot);

    if (strpos($siteNavFromDocRoot, $siteBase) === 0) {
      // the found site nav exists in the base of the site.
      // we need to ask them if they want to use this one, create a new one based off of this, or create a blank one.
      if (self::userIsEditing() || $currentDirNav === $siteNav && file_exists($siteNav)) {
        // the user is wanting to edit or
        // the base site nav exists in the current directory. We want to edit this one
        return $this->edit($siteNav);
      } else {
        // we need to build a site nav for the person in their current directory
        return $this->create($currentDirNav);
      }
    } else if (PermissionsManager::userCanEditSiteNav($this->getLoggedInUsername(), $siteNavFromDocRoot) && file_exists($siteNav)) {
      // the found site nav belongs to a parent site and the user can edit it.
      return $this->edit($siteNav);
    } else {
      // the user doesn't have access to edit the site nav that is being displayed. We need to create one in their current directory for them.
      return $this->create($currentDirNav);
    }
  }

  /**
   * Creates a new local navigation
   *
   * @param  string $navToCreate     Path to the location of the new nav
   * @param  string $navToCreateFrom Path of the nav to copy
   * @return string
   */
  private function create($navToCreate, $navToCreateFrom = null)
  {
    if ($navToCreateFrom === null) {
      $navToCreateFrom = self::getSiteNavToCreateFrom();
    }

    $this->setTitle('Create Local Navigation');

    if (self::isSiteNavShared($navToCreate)) {
      $this->addConcertMessage(Config::buildSharedSiteNavNote(dirname($navToCreate), true));
    }

    $origGet = $_GET;

    // set temporary variables for forwarding
    $_GET['srcFilePath']   = $navToCreateFrom;
    $_GET['concert']       = 'edit';
    $_GET['forwardedFrom'] = 'siteNav';
    // we want the barebone version of the template
    $_GET['barebones']     = 'true';
    $_GET['concertMoshed'] = 'false';

    // if ($this->getMethod() === 'POST') {
    //   $fm = new FileManager($this->getLoggedInUsername(), $navToCreate, $navToCreateFrom, $this->getDB());
    //   $fm->editFile($_POST);
    //   var_dump(preg_replace('`\s+`', '', file_get_contents(Config::SITE_NAV_TEMPLATE)), preg_replace('`\s+`', '', $fm->assembleFile()));
    //   exit;
    //   if (preg_replace('`\s+`', '', file_get_contents(Config::SITE_NAV_TEMPLATE)) === preg_replace('`\s+`', '', $fm->assembleFile())) {
    //     // the user tried to save a default template page. Not cool. They shouldn't be doing this.
    //     return json_encode(['error' => true, 'reason' => Config::DEFAULT_PAGE_SAVED_MESSAGE]);
    //   }
    // }

    // forward onto mosh to have it create the page for us
    $moshResult = $this->forward('mosh', [$navToCreate]);

    if (isset($moshResult['action']) && $moshResult['action'] === 'return') {
      $this->setLocalNavigation($moshResult['value']);
    }
    // we don't want our temporary GET parameters for moshing set anymore.
    self::setGET($origGet);

    return $this->renderPage();
  }

  /**
   * Handles drafts for the site nav. Forwards onto MainController's mosh
   *
   * @param  string $filePath Path to the file we are wanting to manage a draft for its site nav
   * @return string|array
   * @todo  Should we allow drafts for site navs?
   *   Currently, no.
   */
  // private function draft($filePath)
  // {
  //   $this->setTitle('Local Navigation Drafts');

  //   $filePathFromDocRoot = Config::removeDocRootFromPath($filePath);

  //   $siteNav             = self::getSiteNavForFile($filePath);
  //   $siteNavFromDocRoot  = Config::removeDocRootFromPath($siteNav);

  //   // Location of the current directory's site nav. Even if it doesn't exist.
  //   $currentDirNav = Config::addDocRootToPath(dirname($filePath) . DIRECTORY_SEPARATOR . 'site_nav.php');

  //   if (self::isGlobalNav($siteNav)) {
  //     // no site nav exists for this site up to the global nav.
  //     // we don't need to prompt the user for anything. Just create our site nav.
  //     $siteNav = $currentDirNav;
  //   } else {
  //     $siteBase = PermissionsManager::findUsersSiteForFile($this->getLoggedInUsername(), $filePathFromDocRoot);

  //     if (strpos($siteNavFromDocRoot, $siteBase) === 0) {
  //       // the found site nav exists in the base of the site.
  //       if (!file_exists($siteNav)) {
  //         // the user is wanting to edit or
  //         // the base site nav exists in the current directory. We want to edit this one
  //         $siteNav = $currentDirNav;
  //       }
  //     } else if (PermissionsManager::userCanEditSiteNav($this->getLoggedInUsername(), $siteNavFromDocRoot) && file_exists($siteNav)) {
  //       // the found site nav belongs to a parent site and the user can edit it.
  //       return $this->edit($siteNav);
  //     } else {
  //       // the user doesn't have access to edit the site nav that is being displayed. We need to create one in their current directory for them.
  //       return $this->create($currentDirNav);
  //     }

  //   }




  //   $siteBase = PermissionsManager::findUsersSiteForFile($this->getLoggedInUsername(), Config::removeDocRootFromPath($filePath));

  //   if (strpos($siteNavFromDocRoot, $siteBase) === 0) {
  //     // the found site nav lives in the current site.
  //     $fm = new FileManager($this->getLoggedInUsername(), $siteNav, null, $this->getDB());
  //   } else {
  //     // the site nav lives outside of our current site.
  //     $fm = new FileManager($this->getLoggedInUsername(), dirname($this->filePath) . DIRECTORY_SEPARATOR . 'site_nav.php', null, $this->getDB());
  //   }

  //   var_dump($filePath, $siteNav);

  //   $origGet = $_GET;
  //   // set temporary variables for forwarding
  //   $_GET['forwardedFrom'] = 'siteNav';
  //   // @todo why is this using filePath and not siteNav?
  //   if (!file_exists($filePath)) {
  //     // we want to specify what file to copy for creating a draft
  //     $_GET['srcFilePath'] = self::getSiteNavToCreateFrom();
  //   }
  //   // we want the barebone version of the template but we need to check to see if it has already been set for later
  //   $barebonesSet = isset($_GET['barebones']);
  //   $_GET['barebones']     = 'true';
  //   $_GET['concertMoshed'] = 'false';

  //   $moshResult = $this->forward('mosh', [$siteNav]);

  //   // we don't want our temporary variables anymore
  //   self::setGET($origGet);
  //   if (!$barebonesSet) {
  //     // we don't want a barebone version of the template to be rendered if one wasn't requested before coming here
  //     // we need to set the local nav to be the draft
  //     if (isset($moshResult['action']) && $moshResult['action'] === 'return' && !empty($moshResult['value'])) {
  //       $this->addConcertMessage(Config::SITE_NAV_DRAFT_NOTE);
  //       $this->setLocalNavigation($moshResult['value']);
  //     } else if (is_string($moshResult)) {
  //       $this->addConcertMessage(Config::SITE_NAV_DRAFT_NOTE);
  //       $this->setLocalNavigation($moshResult);
  //     }
  //   } else {
  //     return $moshResult;
  //   }
  //   if ($this->getMethod() === 'POST') {
  //     $value = true;
  //   } else {
  //     $value = $this->renderPage();
  //   }
  //   return ['action' => 'return', 'value' => $value];
  // }

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

    $origGet = $_GET;
    // set temporary variables for forwarding
    $_GET['forwardedFrom'] = 'siteNav';
    // we want the barebone version of the template but we need to check to see if it has already been set for later
    $barebonesSet = isset($_GET['barebones']);
    $_GET['barebones']     = 'true';
    $_GET['concertMoshed'] = 'false';

    $moshResult = $this->forward('mosh', [$siteNav]);

    // we don't want our temporary variables anymore
    self::setGET($origGet);
    if (!$barebonesSet) {
      // add the current file
      $this->setContent((new File(Config::addDocRootToPath($filePath)))->loadAndEvaluate());
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
    if (isset($_GET['concertAction']) && $_GET['concertAction'] === 'siteNav') {
      unset($_GET['concertAction']);
    }
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
    switch (true) {
      case (self::userIsEditing() || self::userIsCreatingSiteNav() || self::userIsSaving()):
          return ['action' => 'return', 'value' => $this->createOrEditSiteNav($params['filePath'])];

      // Disabled. We aren't allowing drafts of site_navs
      // case self::isDraftRequest():
      //     return $this->draft($params['filePath']);

      case self::userIsDeleting():
          return $this->delete($params['filePath']);

      case self::userIsDoneEditing():
          return $this->stopEditing($params['filePath']);
    }
  }

  /**
   * Gets the site nav to create one from
   *
   * @return string
   */
  private static function getSiteNavToCreateFrom()
  {
    return Config::SITE_NAV_TEMPLATE;
  }
}