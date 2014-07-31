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
  public function edit($filePath)
  {
    $this->setTitle('Edit Local Navigation');
    $siteNav = self::getSiteNavForFile($filePath);

    // set temporary variables for forwarding
    $_GET['concert'] = 'edit';
    $_GET['forwardedFrom'] = 'siteNav';
    // we want the barebone version of the template
    $_GET['barebones'] = 'true';
    $_GET['concertMoshed'] = 'false';

    $moshResult = $this->forward('mosh', [$siteNav]);

    if (isset($moshResult['action']) && $moshResult['action'] === 'return') {
      $this->setLocalNavigation($moshResult['value']);
    }
    // we don't want a barebone version of the template to be rendered at this point
    // we don't want this temporary flag set anymore
    unset($_GET['barebones'], $_GET['forwardedFrom']);

    return $this->renderPage();
  }

  /**
   * Handles drafts for the site nav. Forwards onto MainController's mosh
   *
   * @param  string $filePath FilePath to the file for drafts
   * @return string
   */
  public function draft($filePath)
  {
    $this->setTitle('Edit Local Navigation');
    $siteNav = self::getSiteNavForFile($filePath);

    // set temporary variables for forwarding
    $_GET['forwardedFrom'] = 'siteNav';
    // we want the barebone version of the template but we need to check to see if it has already been set for later
    $barebonesSet = isset($_GET['barebones']);
    $_GET['barebones'] = 'true';
    $_GET['concertMoshed'] = 'false';

    $moshResult = $this->forward('mosh', [$siteNav]);

    // we don't want this temporary flag set anymore
    unset($_GET['forwardedFrom']);
    if (!$barebonesSet) {
      // add the current file
      $this->setContent((new File(Config::addDocRootToPath($filePath)))->loadAndEvaluate());
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
    return ['action' => 'return', 'value' => $this->renderPage()];
  }

  public function handleSiteNavActions($params)
  {
    // var_dump('handle site nav actions', self::userIsViewingSiteNavDraft());
    // exit;
    if (self::userIsEditingSiteNav()) {
      return ['action' => 'return', 'value' => $this->edit($params['filePath'])];
    } else if (self::userIsViewingSiteNavDraft()) {
      return $this->draft($params['filePath']);
    }
  }
}