<?php
/**
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 */
namespace Gustavus\Concert\Controllers;

use Gustavus\Concert\Config,
  Gustavus\Concert\Utility,
  Gustavus\Concert\PermissionsManager,
  Gustavus\FormBuilderMk2\ElementRenderers\TwigElementRenderer,
  Gustavus\FormBuilderMk2\FormElement,
  Gustavus\Resources\Resource,
  Gustavus\Utility\PageUtil,
  Gustavus\Utility\Set,
  Gustavus\Utility\String,
  Campus\Pull\People as CampusPeople,
  DateTime;

/**
 * Handles draft actions
 *
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 */
class PermissionsController extends SharedController
{
  /**
   * Shows all the sites managed by Concert
   *
   * @return string
   */
  public function showSites()
  {
    if (!PermissionsManager::isUserSuperUser($this->getLoggedInUsername()) && !PermissionsManager::isUserAdmin($this->getLoggedInUsername())) {
      return PageUtil::renderAccessDenied();
    }

    $this->setSubTitle('Sites');
    return $this->renderTemplate('permissions/renderSites.html.twig', ['sites' => PermissionsManager::getSitesFromBase('/', true, true), 'isSuperUser' => PermissionsManager::isUserSuperUser($this->getLoggedInUsername())]);
  }

  /**
   * Creates a new site
   *
   * @return string
   */
  public function createSite()
  {
    if (!PermissionsManager::isUserSuperUser($this->getLoggedInUsername()) && !PermissionsManager::isUserAdmin($this->getLoggedInUsername())) {
      return PageUtil::renderAccessDenied();
    }

    // we don't want this form to be persisted
    $this->flushForm('concertCreateSite');

    $form = $this->buildForm('concertCreateSite', ['\Gustavus\Concert\Forms\Site', 'getConfig'], [null, $this->getLoggedInUsername()]);

    if ($this->getMethod() === 'POST' && $form->validate()) {
      $siteRoot = $form->getChildElement('siteinfo.siteroot')->getValue();

      $excludedFiles = [];
      foreach ($form->getChildElement('excludedfilessection')->setIteratorSource(FormElement::ISOURCE_CHILDREN) as $child) {
        if ($child->getName() === 'excludedfile') {
          $excludedFiles[] = $child->getChildElement('file')->getValue();
        }
      }
      $excludedFiles = array_filter(array_unique($excludedFiles));
      $siteRoot = preg_replace('`/+`', '/', sprintf('/%s/', trim($siteRoot)));

      $searchSiteId = PermissionsManager::getSiteId($siteRoot);
      if (!empty($searchSiteId)) {
        $this->addMessage(sprintf('Oops! The site you wanted to create already exists. Did you mean to <a href="%s">edit that site</a>?', $this->buildUrl('editSite', ['site' => $searchSiteId])));
      } else {
        $siteId = PermissionsManager::saveNewSiteIfNeeded($siteRoot, $excludedFiles);

        foreach ($form->getChildElement('peoplesection')->setIteratorSource(FormElement::ISOURCE_CHILDREN) as $child) {
          if ($child->getName() === 'personpermissions') {
            $accessLevel = $child->getChildElement('accesslevel')->getValue();
            if ($accessLevel === Config::SUPER_USER && !PermissionsManager::isUserSuperUser($this->getLoggedInUsername())) {
              // @todo add a message here if it becomes necessary
              continue;
            }

            $expirationDate = $child->getChildElement('expirationdate')->getValue();
            if (!empty($expirationDate)) {
              $expirationDate = new DateTime($expirationDate);
            } else {
              $expirationDate = null;
            }
            $childUsername = $child->getChildElement('username')->getValue();
            if (!empty($childUsername)) {
              PermissionsManager::saveUserPermissions(
                  $childUsername,
                  $siteId,
                  $accessLevel,
                  $child->getChildElement('includedfiles')->getValue(),
                  $child->getChildElement('excludedfiles')->getValue(),
                  $expirationDate,
                  true
              );
            }
          }
        }
        $this->flushForm('concertCreateSite');

        return $this->redirect($this->buildUrl('sites'));
      }
    }

    $additionalScripts = [['path' => Config::WEB_DIR . 'js/autocompleteUser.js', 'version' => Config::AUTOCOMPLETE_JS_VERSION]];

    $this->addLongSelectResources();
    $renderer = new TwigElementRenderer();
    $this->addFormResources($renderer, null, $additionalScripts);

    $this->setSubTitle('Create Site');
    $this->addContent($renderer->render($form));
    return $this->renderPage();
  }

  /**
   * Edits a specific site
   *
   * @param  array $params Params from Router
   * @return string
   */
  public function editSite(array $params = [])
  {
    if (!PermissionsManager::isUserSuperUser($this->getLoggedInUsername()) && !PermissionsManager::isUserAdmin($this->getLoggedInUsername())) {
      return PageUtil::renderAccessDenied();
    }

    assert('isset($params[\'site\'])');
    $siteId = $params['site'];

    $dbal = $this->getDB();
    $qb   = $dbal->createQueryBuilder();
    $qb->select('siteRoot')
      ->addSelect('excludedFiles')
      ->from('sites', 's')
      ->where('id = :id');

    $site = $dbal->fetchAssoc($qb->getSQL(), [':id' => $siteId]);

    if ($site['siteRoot'] === '/' && !PermissionsManager::isUserSuperUser($this->getLoggedInUsername())) {
      return PageUtil::renderAccessDenied();
    }


    $qb   = $dbal->createQueryBuilder();
    $qb->select('username')
      ->addSelect('accessLevel')
      ->addSelect('includedFiles')
      ->addSelect('excludedFiles')
      ->addSelect('expirationDate')
      ->from('permissions', 'p')
      ->where('site_id = :siteId');

    $people = $dbal->fetchAll($qb->getSQL(), [':siteId' => $siteId]);

    $currPeople = [];
    foreach ($people as &$person) {
      $person['expirationDate'] = ($person['expirationDate']) ? (new DateTime($person['expirationDate']))->format('Y-m-d') : null;
      $person['accessLevel'] = array_filter(explode(',', $person['accessLevel']));
      $currPeople[] = $person['username'];
    }

    $site['people'] = $people;

    // we don't want this form to be persisted
    $formKey = 'concertEditSite_'. $siteId;
    $this->flushForm($formKey);

    $form = $this->buildForm($formKey, ['\Gustavus\Concert\Forms\Site', 'getConfig'], [$site, $this->getLoggedInUsername()]);

    if ($this->getMethod() === 'POST' && $form->validate()) {
      $excludedFiles = [];
      foreach ($form->getChildElement('excludedfilessection')->setIteratorSource(FormElement::ISOURCE_CHILDREN) as $child) {
        if ($child->getName() === 'excludedfile') {
          $excludedFiles[] = $child->getChildElement('file')->getValue();
        }
      }
      $excludedFiles = array_filter(array_unique($excludedFiles));

      PermissionsManager::updateSite($siteId, $excludedFiles);

      foreach ($form->getChildElement('peoplesection')->setIteratorSource(FormElement::ISOURCE_CHILDREN) as $child) {
        if ($child->getName() === 'personpermissions') {
          $accessLevel = $child->getChildElement('accesslevel')->getValue();
          if ($accessLevel === Config::SUPER_USER && !PermissionsManager::isUserSuperUser($this->getLoggedInUsername())) {
            // @todo add a message here if it becomes necessary
            continue;
          }
          $username = $child->getChildElement('username')->getValue();
          if (empty($username)) {
            // no one to add.
            continue;
          }
          $expirationDate = $child->getChildElement('expirationdate')->getValue();
          if (!empty($expirationDate)) {
            $expirationDate = new DateTime($expirationDate);
          } else {
            $expirationDate = null;
          }
          PermissionsManager::saveUserPermissions(
              $username,
              $siteId,
              $accessLevel,
              $child->getChildElement('includedfiles')->getValue(),
              $child->getChildElement('excludedfiles')->getValue(),
              $expirationDate,
              true
          );

          if (($foundIndex = array_search($username, $currPeople)) !== false) {
            // this person already existed.
            // unset them from our currPeople array.
            unset($currPeople[$foundIndex]);
          }
        }
      }
      // any items in $currPeople weren't in post and need to be removed.
      foreach ($currPeople as $removal) {
        PermissionsManager::deleteUserFromSite($removal, $siteId, true);
      }

      $this->flushForm($formKey);
      return $this->redirect($this->buildUrl('sites'));
    }

    $additionalScripts = [['path' => Config::WEB_DIR . 'js/autocompleteUser.js', 'version' => Config::AUTOCOMPLETE_JS_VERSION]];

    $this->addLongSelectResources();
    $renderer = new TwigElementRenderer();
    $this->addFormResources($renderer, null, $additionalScripts);

    $this->setSubTitle('Edit Site');
    $this->setContent($renderer->render($form));
    return $this->renderPage();
  }

  /**
   * Deletes a specific site
   *
   * @param  array $params Params from Router
   * @return string
   */
  public function deleteSite(array $params = [])
  {
    if (!PermissionsManager::isUserSuperUser($this->getLoggedInUsername()) && !PermissionsManager::isUserAdmin($this->getLoggedInUsername())) {
      return PageUtil::renderAccessDenied();
    }

    assert('isset($params[\'site\'])');
    $siteId = $params['site'];

    $dbal = $this->getDB();
    $qb   = $dbal->createQueryBuilder();
    $qb->select('siteRoot')
      ->addSelect('excludedFiles')
      ->from('sites', 's')
      ->where('id = :id');

    $site = $dbal->fetchAssoc($qb->getSQL(), [':id' => $siteId]);

    if ($this->getMethod() === 'POST' && isset($_POST['siteId'], $_POST['deleteAction'])) {
      if ($_POST['deleteAction'] === 'confirmDelete') {
        PermissionsManager::deleteSite($siteId);
      }
      return $this->redirect($this->buildUrl('sites'));
    }

    $cssResource = Resource::renderCSS(['path' => Config::WEB_DIR . '/css/concert.css', 'version' => Config::CSS_VERSION]);
    if (!self::isResourceAdded($cssResource, 'css')) {
      $this->addStylesheets(sprintf(
          '<link rel="stylesheet" type="text/css" href="%s" />',
          $cssResource
      ));

      self::markResourcesAdded([$cssResource], 'css');
    }

    $this->setSubTitle('Delete Site');
    return $this->renderTemplate('permissions/confirmDeleteSite.html.twig', ['actionUrl' => $_SERVER['REQUEST_URI'], 'site' => $site['siteRoot'], 'siteId' => $siteId]);
  }

  /**
   * Searches for a user
   *
   * @return string
   */
  public function userSearch()
  {
    if (!PermissionsManager::isUserSuperUser($this->getLoggedInUsername()) && !PermissionsManager::isUserAdmin($this->getLoggedInUsername())) {
      return PageUtil::renderAccessDenied();
    }

    $form = $this->buildForm('userSearch', ['\Gustavus\Concert\Forms\UserSearch', 'getConfig']);

    $autocompletePath = $this->buildUrl('autocompleteUser', ['value' => '{value}']);

    if ($this->getMethod() === 'POST' && $form->validate()) {
      $username = $form->getChildElement('username')->getValue();

      $userSites = PermissionsManager::getSitesForUser($username, true, true);
      if (empty($userSites)) {
        $this->addMessage('It appears that the user doesn\'t belong to any sites');
      } else {


        $peoplePuller = new CampusPeople($this->getApiKey());
        $person   = $peoplePuller->setUsername($username)->current();

        if (is_object($person)) {
          $name    = $person->getFullName(false);
        } else {
          $name    = $username;
        }

        $this->setSubTitle(sprintf('%s Sites', (new String($name))->possessive()));
        $this->addContent($this->renderView('permissions/renderSites.html.twig', ['sites' => $userSites]));
      }
    }

    $additionalScripts = [['path' => Config::WEB_DIR . 'js/autocompleteUser.js', 'version' => Config::AUTOCOMPLETE_JS_VERSION]];

    $renderer = new TwigElementRenderer();
    $this->addFormResources($renderer, null, $additionalScripts);

    $this->addContent($renderer->render($form));

    $this->setSubTitle('User Search');
    return $this->renderPage('permissions/userSearch.html.twig', ['action' => $_SERVER['REQUEST_URI'], 'autoCompletePath' => $autocompletePath]);
  }

  /**
   * Adds resources for select2
   *
   * @return  void
   */
  private function addLongSelectResources()
  {
    $this->addStylesheets(sprintf('<link rel="stylesheet" href="%s" type="text/css" />',
        Resource::renderResource('select2-css')));
    $this->addJavascripts(sprintf('<script type="text/javascript">
        Modernizr.load({
          load: "%s",
          callback: function() {
            var selectArgs = {placeholder: "Select an option", allowClear: true};
            Extend.add("selectDuplicated", function() {
              // set up action for when select2 elements are duplicated
              var $this = $(this);
              // at this point, select2 elements were cloned to the new element.
              // we need to remove them so we can initialize select2
              $this.find(".select2-container").remove();

              $this.find(".longSelect")
                .removeClass("select2-offscreen")
                .removeAttr("tabindex")
                .select2(selectArgs);
            })
            $(function() {
              // set up select2 elements
              $(".longSelect").select2(selectArgs);
            });
          }
        });
        </script>', Resource::renderResource('select2')
    ));
  }
}