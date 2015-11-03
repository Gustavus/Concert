<?php
/**
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 */
namespace Gustavus\Concert\Controllers;

use Gustavus\Concert\Config,
  Gustavus\Concert\Utility,
  Gustavus\Concert\FileManager,
  Gustavus\Utility\File,
  Gustavus\Utility\String,
  Gustavus\Utility\PageUtil,
  Gustavus\Utility\Jsonizer,
  Gustavus\Concert\PermissionsManager,
  Campus\Utility\Autocomplete,
  Gustavus\Extensibility\Actions,
  Gustavus\Extensibility\Filters,
  Gustavus\Revisions\API as RevisionsAPI,
  Gustavus\Resources\Resource,
  Gustavus\Template\Template,
  Config as GACConfig,
  InvalidArgumentException;

/**
 * Handles main Concert actions
 *
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 */
class MainController extends SharedController
{
  /**
   * Handles file manager requests.
   *   All of fileManager's php requests get routed through here so we can figure out what site they are editing based off of the access key used
   *
   * @param  array $params Array of parameters containing the requested file
   * @return string
   */
  public function handleFileManagerRequest($params)
  {
    assert('isset($params[\'request\'], $_GET[\'accessKey\'])');

    // we don't want warnings to get triggered since filemanager does a lot of crazy stuff. Mostly with wanting relative paths when we are actually using absolute paths
    error_reporting(~E_WARNING);

    PageUtil::startSessionIfNeeded();
    if (isset($_SESSION['concertCMS']['siteAccessKeys'][$_GET['accessKey']])) {
      $siteBase = $_SESSION['concertCMS']['siteAccessKeys'][$_GET['accessKey']];
      $fileToCheckForPerms = Utility::removeDocRootFromPath(str_replace('//', '/', $siteBase . DIRECTORY_SEPARATOR . 'index.php'));

      $currentFile = PageUtil::getReferer();
      if (!empty($currentFile)) {
        $urlParts = parse_url($currentFile);
        if (isset($urlParts['path']) && strpos(trim($urlParts['path'], '/'), trim($siteBase, '/')) === 0) {
          $fileToCheckForPerms = $urlParts['path'];
        }
      }


      $parentSiteBase = PermissionsManager::findParentSiteForFile($fileToCheckForPerms);
      if (empty($parentSiteBase)) {
        return null;
      }
      // set our current parent's site base in the session
      $_SESSION['concertCMS']['currentParentSiteBase'] = Utility::addDocRootToPath($parentSiteBase);
      // set our current site's base in the session
      $_SESSION['concertCMS']['currentSiteBase'] = Utility::addDocRootToPath($siteBase);
      // set whether the user can upload in the session
      $_SESSION['concertCMS']['userCanUploadToCurrentSite'] = PermissionsManager::userCanUpload($this->getLoggedInUsername(), $fileToCheckForPerms);
    } else {
      // no access key. We won't know what to do.
      return null;
    }

    // now we need to fix things for filemanager
    if ($this->getMethod() === 'POST' && strpos($params['request'], 'upload.php') !== false) {
      if (isset($_POST['path'])) {
        $_POST['path'] = Utility::addDocRootToPath($_POST['path']);
      }

      if (isset($_POST['path_thumb'])) {
        $_POST['path_thumb'] = Utility::addDocRootToPath($_POST['path_thumb']);
      }
    }

    if ($this->getMethod() === 'POST' && strpos($params['request'], 'execute.php') !== false) {
      // we need to make sure the thumb path is full.
      if (isset($_POST['path_thumb'])) {
        $_POST['path_thumb'] = Utility::addDocRootToPath($_POST['path_thumb']);
      }
    }

    if (strpos($params['request'], 'ajax_calls.php') !== false && isset($_GET['sub_action'], $_GET['file']) && $_GET['sub_action'] === 'preview') {
      $_GET['file'] = Utility::addDocRootToPath($_GET['file']);
    }

    // set up our default language if it isn't already
    if (!isset($_SESSION['RF']['language'])) {
      $_SESSION['RF']['language'] = 'en_EN';
    }
    if (!isset($_SESSION['RF']['language_file'])) {
      $_SESSION['RF']['language_file'] = Config::FILE_MANAGER_LOCATION . '/lang/' . $_SESSION['RF']['language'] . '.php';
    }

    $file = (new File(Utility::addDocRootToPath($this->buildUrl('fileManager', ['request' => '']) . $params['request'])));
    if (strpos($params['request'], '.php') !== false) {
      $output = $file->loadAndEvaluate();
      return str_replace(rtrim($_SERVER['DOCUMENT_ROOT'], '/'), '', $output);
    } else {
      return $file->serve();
    }
  }

  /**
   * Handles editing a page
   *
   * @param  string $filePath Path to the file we are trying to edit
   * @return string|boolean String of the rendered edit page or boolean on save
   */
  private function edit($filePath)
  {
    if (!self::isForwardedFromSiteNav() && !Utility::isPageEditable($filePath)) {
      $this->addConcertMessage(Config::SPECIAL_FILE_MESSAGE, 'error');
      return false;
    }

    if (file_exists($filePath) && filesize($filePath) > Config::MAX_EDITABLE_FILE_SIZE) {
      return false;
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());

    if (self::isForwardedFromSiteNav()) {
      // user is trying to edit a site nav.
      $fm->setUserIsEditingSiteNav();
    }

    if (!$fm->userCanEditFile()) {
      $this->addConcertMessage(Config::NOT_ALLOWED_TO_EDIT_MESSAGE, 'error');
      return false;
    }

    if ($fm->isNonEditableFile()) {
      $this->addConcertMessage(Config::SPECIAL_FILE_MESSAGE, 'error');
      return false;
    }

    if (!$fm->acquireLock()) {
      $this->addConcertMessage($this->renderLockNotAcquiredMessage($fm), 'error');
      return false;
    }

    if ($fm->draftExists()) {
      $this->addConcertMessage($this->renderOpenDraftMessage($fm));
    }

    if ($fm->draftExists() && $fm->userHasOpenDraft()) {
      $draft = $fm->getDraft();
      // we want to re-create our FileManager to be based off of the current draft.
      $fm = new FileManager($this->getLoggedInUsername(), $filePath, Config::$draftDir . $draft['draftFilename'], $this->getDB(), $draft['destFilepath']);
      $fm->setUserIsEditingDraft();
      $editDraft = true;
      // add a message saying that the draft is older than the published date of the page and it might be out of sync.
      $this->addOutdatedDraftMessageIfNeeded($draft);
      if (Utility::sharedDraftHasBeenEditedByCollaborator($draft)) {
        // this draft has been modified since the owner last saved it.
        $this->addConcertMessage(Config::DRAFT_EDITED_BY_COLLABORATOR_MESSAGE);
      }
    } else {
      $editDraft = false;
    }

    if ($this->getMethod() === 'POST' && $fm->editFile($_POST)) {
      // trying to save an edit
      $userCanPublish = PermissionsManager::userCanPublishFile($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath));
      if ($userCanPublish && $fm->stageFile()) {
        $location = (new String(Utility::removeDocRootFromPath($filePath)))
          ->removeQueryStringParams(Config::$concertGETKeys)
          ->addQueryString(['concert' => 'stopEditing'])
          ->buildUrl()
          ->getValue();
        self::setConcertSessionMessage($this->buildPublishSuccessMessage($filePath), null, $location);
        return true;
      } else if (!$userCanPublish) {
        return $this->savePendingDraft($fm);
      }
    }

    $this->insertEditingResources($filePath, self::findRedirectPath());

    $draftFilename = $fm->makeEditableDraft($editDraft);

    if ($draftFilename === false) {
      return $this->renderErrorPage(Config::GENERIC_ERROR_MESSAGE);
    }

    $page = $this->displayPage($draftFilename, true);

    // remove our editable draft since it doesn't need to sit around anywhere anymore.
    unlink($draftFilename);
    return $page;
  }

  /**
   * Saves a draft waiting to be published
   *
   * @param  FileManager $fileManager FileManager instance to save the draft for
   * @return boolean
   */
  private function savePendingDraft($fileManager)
  {
    if ($fileManager->saveDraft(Config::PENDING_PUBLISH_DRAFT)) {
      $pendingDraft = $fileManager->getDraft();

      $publishers = PermissionsManager::findPublishersForFile(Utility::removeDocRootFromPath($pendingDraft['destFilepath']));

      $this->forward('emailPendingDraft', ['draft' => $pendingDraft, 'publishers' => $publishers]);

      return true;
    }
    return false;
  }

  /**
   * Creates a new page for the user
   *
   * @param  string $filePath       Absolute path to the file to create
   * @param  string $fromFilePath   Absolute path to a file to create the new file from
   * @return boolean
   */
  private function createNewPage($filePath, $fromFilePath = null)
  {
    if ($fromFilePath === null) {
      if (isset($_POST['fromFilePath'])) {
        // our from file path is specified here.
        $fromFilePath = $_POST['fromFilePath'];
      } else {
        // default to our default template.
        $fromFilePath = Config::DEFAULT_TEMPLATE;
      }
    }

    if (!self::isForwardedFromSiteNav() && !Utility::isPageEditable($fromFilePath)) {
      $this->addConcertMessage(Config::SPECIAL_FILE_COPY_MESSAGE, 'error');
      return false;
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, $fromFilePath, $this->getDB());

    if (self::isForwardedFromSiteNav() && PermissionsManager::userCanEditSiteNav($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath))) {
      // user is trying to create a site nav.
      $fm->setUserIsEditingSiteNav();
    } else if (!PermissionsManager::userCanCreatePage($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath))) {
      $this->addConcertMessage(Config::NOT_ALLOWED_TO_CREATE_MESSAGE, 'error');
      return false;
    }

    if (!$fm->acquireLock()) {
      $this->addConcertMessage($this->renderLockNotAcquiredMessage($fm), 'error');
      return false;
    }

    if ($fm->draftExists() && $fm->userHasOpenDraft()) {
      $editDraft = true;
      $draft = $fm->getDraft();
      // we want our FileManager to be based off of the draft they have created.
      $fm = new FileManager($this->getLoggedInUsername(), $filePath, Config::$draftDir . $draft['draftFilename'], $this->getDB());
    } else {
      $editDraft = false;
    }

    if ($this->getMethod() === 'POST' && $fm->editFile($_POST)) {
      $userCanPublish = PermissionsManager::userCanPublishFile($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath));
      if ($userCanPublish && $fm->stageFile()) {
        return true;
      } else if (!$userCanPublish) {
        return $this->savePendingDraft($fm);
      }
      // trying to save a new page
      return true;
    }

    $this->insertEditingResources($filePath, self::findRedirectPath(), null, null, ['isCreation' => 'true', 'fromFilePath' => $fromFilePath]);

    $draftFilename = $fm->makeEditableDraft($editDraft);

    if ($draftFilename === false) {
      return $this->renderErrorPage(Config::GENERIC_ERROR_MESSAGE);
    }

    $page = $this->displayPage($draftFilename, true);

    // remove our editable draft since it doesn't need to sit around anywhere anymore.
    unlink($draftFilename);
    return $page;
  }

  /**
   * Handles deleting a file
   *
   * @param  string $filePath Path to the file to delete
   * @return string|boolean String for confirmation, boolean otherwise
   */
  private function deletePage($filePath)
  {
    if (!self::isForwardedFromSiteNav() && !Utility::isPageEditable($filePath)) {
      $this->addConcertMessage(Config::SPECIAL_FILE_MESSAGE, 'error');
      return false;
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());

    if (!file_exists($filePath)) {
      return $this->redirect(dirname(Utility::removeDocRootFromPath($filePath)));
    }

    if ($fm->isNonEditableFile()) {
      if (isset($_GET['barebones'])) {
        return ['action' => 'return', 'value' => self::buildConcertMessageDiv(Config::SPECIAL_FILE_MESSAGE, 'error')];
      }
      $this->addConcertMessage(Config::SPECIAL_FILE_MESSAGE, 'error');
      return false;
    }

    if (!PermissionsManager::userCanDeletePage($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath))) {
      if (isset($_GET['barebones'])) {
        return ['action' => 'return', 'value' => self::buildConcertMessageDiv(Config::NOT_ALLOWED_TO_DELETE_MESSAGE, 'error')];
      }
      $this->addConcertMessage(Config::NOT_ALLOWED_TO_DELETE_MESSAGE, 'error');
      return false;
    }

    if (!$fm->acquireLock()) {
      if (isset($_GET['barebones'])) {
        return ['action' => 'return', 'value' => self::buildConcertMessageDiv($this->renderLockNotAcquiredMessage($fm), 'error')];
      }
      $this->addConcertMessage($this->renderLockNotAcquiredMessage($fm), 'error');
      return false;
    }

    if ($fm->draftExists()) {
      // someone has a draft open for this page.
      $message = $this->renderOpenDraftMessage($fm);
    } else {
      $message = '';
    }

    if ($this->getMethod() === 'POST' && isset($_POST['filePath'], $_POST['concertAction'], $_POST['deleteAction'])) {

      if ($_POST['deleteAction'] === 'confirmDelete' && urldecode($_POST['filePath']) === $filePath && $fm->stageForDeletion()) {
        if (isset($_GET['barebones'])) {
          $url = (new String(Utility::removeDocRootFromPath(dirname($filePath))))->getValue();
          return ['action' => 'return', 'value' => json_encode(['redirectUrl' => $url])];
        } else {
          return PageUtil::renderPageNotFound(true);
        }
      } else if ($_POST['deleteAction'] === 'cancelDelete') {
        $fm->stopEditing();
        $redirectPath = self::findRedirectPath();

        $url = (!empty($redirectPath)) ? $redirectPath : Utility::removeDocRootFromPath($filePath);
        if (isset($_GET['barebones'])) {
          return ['action' => 'return', 'value' => true];
        } else {
          return $this->redirect($url);
        }
      }
    }

    if (isset($_GET['barebones'])) {
      $scripts = '<script type="text/javascript">
            $(\'#concertDelete .deleteAction\').click(function(e) {
              e.preventDefault();
              var form = $(this).parents(\'form\');
              var action = $(this).attr(\'value\');

              var url  = form.attr(\'action\');
              var data = form.serialize();
              data += \'&deleteAction=\' + action;
              $.post(url, data, function(response) {
                $(\'#concertDelete .deleteAction\').colorbox.close();

                if (response && response.redirectUrl) {
                  setTimeout(function() {
                    window.location = response.redirectUrl;
                  }, 2000);
                }
              }, \'json\')
            })
          </script>';
    } else {
      $scripts = '';
    }

    // confirmation form
    $return = [
      'action' => 'return',
      'value'  => $this->renderTemplate('confirmDelete.html.twig', ['actionUrl' => $_SERVER['REQUEST_URI'], 'filePath' => $filePath, 'scripts' => $scripts, 'message' => $message]),
    ];
    return $return;
  }

  /**
   * Handles revisions
   *
   * @param  string $filePath Path to the file to handle revisions for
   * @param  string $redirectPath URL to redirect to on revision actions if different than $filePath
   * @return array
   */
  private function handleRevisions($filePath, $redirectPath = null)
  {
    // we don't want this set anymore
    unset($_GET['forwardedFrom']);

    $revisionsAPI = Utility::getRevisionsAPI($filePath, $this->getDB(), PermissionsManager::userCanManageRevisions($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath)));

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());

    if (!PermissionsManager::userCanViewRevisions($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath))) {
      $this->addConcertMessage(Config::NOT_ALLOWED_TO_VIEW_REVISIONS, 'error');
      return false;
    }

    Filters::add(RevisionsAPI::RENDER_REVISION_FILTER, function($content) {
      $contentArr = FileManager::separateContentByType($content);
      return implode("\n\r", $contentArr['content']);
    });

    if (PermissionsManager::userCanManageRevisions($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath))) {
      // user can manage revisions. Now add the restore hook action.
      Actions::add(RevisionsAPI::RESTORE_HOOK, function($revisionContent, $oldMessage, $restoreAction) use ($filePath, $fm, $redirectPath) {

        if (!$fm->acquireLock()) {
          return $this->redirectWithMessage($_SERVER['REQUEST_URI'], $this->renderLockNotAcquiredMessage($fm));
        }
        switch ($restoreAction) {
          case RevisionsAPI::UNDO_ACTION:
            $action = Config::UNDO_RESTORE_STAGE;
              break;
          case RevisionsAPI::RESTORE_ACTION:
          default:
            $action = Config::RESTORE_STAGE;
              break;
        }

        $fm->stageFile($action, $revisionContent);
        $redirectPath = ($redirectPath === null) ? $filePath : $redirectPath;

        $redirectPath = Utility::removeDocRootFromPath($redirectPath);

        $query = [
          'concert' => 'revisions',
        ];
        if (isset($_GET['concertAction'])) {
          $query['concertAction'] = $_GET['concertAction'];
        }

        if ($restoreAction === RevisionsAPI::UNDO_ACTION) {
          $_POST = [];

          $url = (new String($redirectPath))->addQueryString($query)->buildUrl()->getValue();
          return $this->redirectWithMessage($url, Config::UNDO_RESTORE_MESSAGE);

        } else {
          $_POST = [];

          $query['revisionsAction'] = 'thankYou';
          $url = (new String($redirectPath))->addQueryString($query)->buildUrl()->getValue();
          return $this->redirectWithMessage($url, Config::RESTORED_MESSAGE);
        }
      });
    }

    $moshed = self::alreadyMoshed();
    // revisions doesn't like concertMoshed being set in GET.
    unset($_GET['concertMoshed']);
    // render(true) so we get the content returned to us.
    $this->setContent($revisionsAPI->render(true));
    if ($moshed) {
      self::markMoshed();
    }

    return ['action' => 'return', 'value' => $this->renderPage()];
  }

  /**
   * Renders a person's recent activity
   *
   * @return string
   */
  public function viewRecentActivity()
  {
    $recentActivity = $this->getRecentActivity();

    if (isset($_GET['barebones'])) {
      return $this->renderView('recentActivity.html.twig', ['drafts' => $recentActivity['drafts'], 'published' => $recentActivity['published'], 'isBarebones' => true]);
    } else {
      $this->setTitle('Recent Concert Activity');
      return $this->renderTemplate('recentActivity.html.twig', ['drafts' => $recentActivity['drafts'], 'published' => $recentActivity['published'], 'isBarebones' => false]);
    }
  }

  /**
   * Gets all recent activity for the logged in user
   *
   * @param  string $username Username to get recent activity for. All recent activity will be used if username is false.
   * @param  integer $limit Limit of number of recent activity actions to pull
   * @return array Array with keys of drafts and published
   */
  private function getRecentActivity($username = null, $limit = 10)
  {
    $dbal = $this->getDB();
    if ($username === null) {
      $username = $this->getLoggedInUsername();
    }

    $return = [];
    $params = [];

    // drafts
    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('destFilepath')
      ->addSelect('draftFilename')
      ->addSelect('type')
      ->addSelect('additionalUsers')
      ->addSelect('date')
      ->from('drafts', 'd')
      ->orderBy('date', 'DESC')
      ->setMaxResults($limit);

    if ($username) {
      $qb->andWhere('username = :username');
      $params[':username'] = $username;
    } else {
      $qb->addSelect('username');
    }

    $return['drafts'] =  $dbal->fetchAll($qb->getSQL(), $params);

    // staged files
    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('destFilepath')
      ->addSelect('action')
      ->addSelect('date')
      ->addSelect('publishedDate')
      ->from('stagedFiles', 'sf')
      ->where('action != :mediaDir')
      ->orderBy('publishedDate', 'DESC')
      ->setMaxResults($limit);

    if ($username) {
      $qb->andWhere('username = :username');
    } else {
      $qb->addSelect('username');
    }

    $params[':mediaDir']      = Config::CREATE_MEDIA_DIRECTORY_STAGE;

    $return['published'] = $dbal->fetchAll($qb->getSQL(), $params);

    return $return;
  }

  /**
   * Gets global stats for Concert
   *   Returns an array of distinctFileActions, allFileActions, and topUsers
   *
   * @return array Array with keys of distinctFileActions, allFileActions, and topUsers.
   */
  private function getStats()
  {
    $dbal = $this->getDB();

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('COUNT(DISTINCT srcFilename) as counts')
      ->addSelect('action')
      ->from('stagedFiles', 'sf')
      ->groupBy('action')
      ->orderBy('counts', 'DESC');

    $distinctFileActions = $dbal->fetchAll($qb->getSQL());

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('COUNT(srcFilename) as counts')
      ->addSelect('action')
      ->from('stagedFiles', 'sf')
      ->groupBy('action')
      ->orderBy('counts', 'DESC');

    $allFileActions = $dbal->fetchAll($qb->getSQL());

    $qb = $dbal->createQueryBuilder();
    $qb->addSelect('DISTINCT username')
      ->addSelect('COUNT(destFilepath) as publishCount')
      ->from('stagedFiles', 's')
      ->where('action != :mediaDir')
      ->groupBy('username')
      ->orderBy('publishCount', 'DESC')
      ->setMaxResults(10);

    $topUsers = $dbal->fetchAll(
        $qb->getSQL(),
        [
          ':mediaDir'      => Config::CREATE_MEDIA_DIRECTORY_STAGE,
        ]
    );

    return [
      'distinctFileActions' => $distinctFileActions,
      'allFileActions'      => $allFileActions,
      'topUsers'            => $topUsers,
    ];
  }

  /**
   * Renders the dashboard for the current user
   *
   * @param  array $params Params from Router
   * @return string
   */
  public function dashboard(Array $params = [])
  {
    if (PermissionsManager::isUserSuperUser($this->getLoggedInUsername()) || PermissionsManager::isUserAdmin($this->getLoggedInUsername())) {
      $sites = PermissionsManager::getSitesFromBase('/');
      $admin = true;
    } else {
      $sites = PermissionsManager::getSitesForUser($this->getLoggedInUsername());
      $admin = false;
    }

    if ($admin && isset($params['dashboardType']) && $params['dashboardType'] === 'global') {
      // user wants to see recent activity for all users.
      $recentActivityUsername = false;
      $globalActivity         = true;

      $recentActivity = $this->getRecentActivity($recentActivityUsername, 20);
      $stats          = $this->getStats();
      $this->setSubTitle('Global Dashboard');
    } else {
      $recentActivityUsername = $this->getLoggedInUsername();
      $globalActivity         = false;

      $recentActivity = $this->getRecentActivity($recentActivityUsername);
      $stats          = null;
      $this->setSubTitle('Dashboard');
    }

    // build our recent activity
    $recentActivity = $this->renderView(
        'recentActivity.html.twig',
        [
          'drafts'         => $recentActivity['drafts'],
          'published'      => $recentActivity['published'],
          'isBarebones'    => false,
          'globalActivity' => $globalActivity,
        ]
    );

    if (($foundIndex = array_search('/', $sites)) !== false) {
      // the base site is in our sites. Remove it.
      unset($sites[$foundIndex]);
    }

    $this->addJavascripts(
        sprintf(
            '<script type="text/javascript">
              require.config({
                shim: {
                  "%1$s": ["baseJS"]
                }
              });
              require(["%1$s"], function() {
                $(".filterable")
                  .liveFilter();
              });
            </script>',
            Resource::renderResource(['path' => '/js/jquery/jquery.liveFilter.js', 'version' => '1'])
        )
    );

    $cssResource = Resource::renderCSS(['path' => Config::WEB_DIR . '/css/concert.css', 'version' => Config::CSS_VERSION]);
    $this->addStylesheets(sprintf(
        '<link rel="stylesheet" type="text/css" href="%s" />',
        $cssResource
    ));

    return $this->renderTemplate('dashboard.html.twig', ['recentActivity' => $recentActivity, 'sites' => $sites, 'isGlobal' => $globalActivity, 'stats' => $stats]);
  }

  /**
   * Renders a list of sites that exist in Concert
   *
   * @return string
   */
  public function listAllSites()
  {
    if (PermissionsManager::isUserSuperUser($this->getLoggedInUsername()) || PermissionsManager::isUserAdmin($this->getLoggedInUsername()) || $this->checkPermissions(
        'Concert',
        [
          'all',
          'callbacks' => [
            [
              'callback'   => 'hasDepartment',
              'parameters' => 'Gustavus Technology Services'
            ],
          ]
        ]
    )) {
      $sites = PermissionsManager::getSitesFromBase('/');
    } else {
      return PageUtil::renderAccessDenied();
    }

    if (($foundIndex = array_search('/', $sites)) !== false) {
      // the base site is in our sites. Remove it.
      unset($sites[$foundIndex]);
    }

    $this->addJavascripts(
        sprintf(
            '<script type="text/javascript">
              require.config({
                shim: {
                  "%1$s": ["baseJS"]
                }
              });
              require(["%1$s"], function() {
                $(".filterable")
                  .liveFilter();
              });
            </script>',
            Resource::renderResource(['path' => '/js/jquery/jquery.liveFilter.js', 'version' => '1'])
        )
    );

    $cssResource = Resource::renderCSS(['path' => Config::WEB_DIR . '/css/concert.css', 'version' => Config::CSS_VERSION]);
    $this->addStylesheets(sprintf(
        '<link rel="stylesheet" type="text/css" href="%s" />',
        $cssResource
    ));

    $this->setSubTitle('Concert Sites');
    return $this->renderTemplate('sites.html.twig', ['sites' => $sites]);
  }

  /**
   * Checks for any requests for users already working in Concert and also checks to see if it can add anything into the Template such as edit options or other actions.
   * Returns an array of actions to take.
   * Return Values:
   * <ul>
   *   <li>action: {boolean} Whether any action is required or not. PossibleValues:
   *     <ul>
   *       <li>return: This tells us that we need to return the value</li>
   *       <li>none: Nothing needs to happen</li>
   *     </ul>
   *   </li>
   *   <li>value: {string} Value for caller to return or add into the Template.</li>
   *   <li>redirectUrl: {string} URL to redirect to.</li>
   * </ul>
   *
   * @param  string|array $params String of the filepath to mosh on, or an associative array of parameters for moshing
   *   Possible keys:
   *   <ul>
   *     <li>filePath: Required. FilePath to mosh for</li>
   *     <li>dbal: Internal. Doctrine connection to use</li>
   *     <li>redirectPath: Internal. redirectPath to send to a few actions if needed</li>
   *
   * @throws InvalidArgumentException If $filePath is null and doesn't exist in $_POST
   * @return array Array containing actions the template render needs to take.
   */
  public function mosh($params)
  {
    if (GACConfig::isProductionBackup() || rtrim(Config::getRequiredDocRoot(), '/') !== rtrim($_SERVER['DOCUMENT_ROOT'], '/')) {
      // we don't want people to edit or do anything if we are working on our backup server or if from a different doc root
      return [
        'action' => 'none',
      ];
    }
    if (Utility::isRequestFromRemoteDomain()) {
      // we don't support multi-domains, so we don't want to do anything.
      return [
        'action' => 'none',
      ];
    }
    if (is_array($params)) {
      if (isset($params['dbal'])) {
        $this->setDBAL($params['dbal']);
      }

      assert('isset($params["filePath"])');
      $filePath = rawurldecode($params['filePath']);
    } else {
      $filePath = rawurldecode($params);
    }
    if (substr($filePath, -4) !== '.php') {
      $filePath = str_replace('//', '/', $filePath . '/index.php');
    }

    if (Config::GLOBAL_SHUTDOWN) {
      if (PermissionsManager::userHasAccessToSite($this->getLoggedInUsername(), Utility::removeDocRootFromPath($filePath))) {
        Template::addUserMessage(Config::CONCERT_DISABLED_MESSAGE);
      }
      return ['action' => 'none'];
    }

    if ($this->isLoggedIn() && !self::alreadyMoshed()) {
      // let ourselves know that we have already moshed this request.
      self::markMoshed();

      $filePath = Utility::addDocRootToPath($filePath);

      if (self::isSiteNavRequest() && !self::isForwardedFromSiteNav()) {
        // this has to happen before anything, because it will forward back to here and then everything else will get ran.
        $this->addMoshMenu();
        return $this->forward('handleSiteNavActions', ['filePath' => $filePath]);
      }

      if (self::isRequestingQuery()) {
        return ['action' => 'return', 'value' => $this->handleQueryRequest($filePath)];
      }

      // Check if the user wants to stop editing
      if (self::isRequestingLockRelease()) {
        return $this->stopEditing($filePath);
      }

      if (self::userIsDeleting()) {
        return $this->deletePage($filePath);
      }

      if (self::isRevisionRequest()) {
        $this->addMoshMenu();
        $redirectUrl = isset($params['redirectPath']) ? $params['redirectPath'] : null;
        return $this->handleRevisions($filePath, $redirectUrl);
      }

      // check if it is a draft request
      if (self::isDraftRequest() || $this->userIsEditingPublicDraft($filePath)) {
        $this->addMoshMenu();
        // pass onto draft controller to process this request
        return $this->forward('handleDraftActions', ['filePath' => $filePath]);
      }

      $filePathFromDocRoot = Utility::removeDocRootFromPath($filePath);
      if (PermissionsManager::userHasAccessToSite($this->getLoggedInUsername(), $filePathFromDocRoot)) {
        // user has access to this file's site. We want to show them the menu.
        if (!self::isInternalForward()) {
          // we don't want to clear any messages if we have forwarded back to here
          $this->setConcertMessage(null);
        }
        // mosh menu has to be added after the messages get reset because it might add messages
        $this->addMoshMenu();
      }
      // check to see if the user has access to edit this page
      if (PermissionsManager::userCanEditFile($this->getLoggedInUsername(), $filePathFromDocRoot) || (self::isForwardedFromSiteNav() && PermissionsManager::userCanEditSiteNav($this->getLoggedInUsername(), $filePathFromDocRoot))) {

        if (!file_exists($filePath)) {
          // we need to check to see if the user is trying to create a new page
          $result = $this->handleNewPageRequest($filePath);

          if ($result) {
            return $result;
          }
        } else if (self::userIsEditing() || self::userIsSaving()) {
          // user is editing or saving
          $editResult = $this->edit($filePath);
          if ($editResult) {
            return [
              'action' => 'return',
              'value'  => $editResult,
            ];
          }
        } else {
          if (self::userIsDoneEditing()) {
            return $this->stopEditing($filePath);
          }
          $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());
          if ($fm->userHasLock()) {
            // user has a lock for this page.
            $this->addConcertMessage(Config::CONTINUE_EDITING_MESSAGE);
          }
        }
      } else if (self::userIsEditing()) {
        // user is editing, but they don't have access to edit.
        $this->addConcertMessage(Config::NOT_ALLOWED_TO_EDIT_MESSAGE, 'error');
      }
    } else if (!self::alreadyMoshed() && $this->userIsViewingPublicDraft($filePath)) {
      // let ourselves know that we have already moshed this request.
      self::markMoshed();
      return $this->forward('handleDraftActions', ['filePath' => $filePath]);
    }
    return [
      'action' => 'none',
    ];
  }

  /**
   * Handles requests for querying for information
   *
   * @param  string $filePath Path to the file we are wanting information for
   * @return boolean
   */
  private function handleQueryRequest($filePath)
  {
    $query = self::getQueryFromRequest();

    switch ($query) {
      case 'hasSharedDraft':
        $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());
        $draft = $fm->getDraftForUser($this->getLoggedInUsername());
          return ($draft['type'] === Config::PUBLIC_DRAFT && !empty($draft['additionalUsers']));
      default:
          return false;
    }
  }

  /**
   * Stops editing a page and releases the locks
   *
   * @param  string $filePath Path of the page to stop editing
   * @return array
   */
  private function stopEditing($filePath)
  {
    if ($this->userIsEditingPublicDraft($filePath)) {
      return $this->forward('stopEditingPublicDraft', ['filePath' => $filePath]);
    }

    $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());

    $this->addMoshMenu();
    $fm->stopEditing();

    return [
      'action' => 'none',
      'value'  => true,
    ];
  }

  /**
   * Handles requests for creating new pages
   *
   * @param  string $filePath Path of the file to create
   * @return array
   */
  private function handleNewPageRequest($filePath)
  {
    // Note: Setting this to true will show a draft of the page if one exists instead of the 404 page. We want the 404 page so the user knows that this page doesn't exist yet.
    $showDraftInsteadOfErrorPage = false;
    if (self::userIsDoneEditing()) {
      $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());
      $fm->stopEditing();

      if ($showDraftInsteadOfErrorPage && $fm->userHasOpenDraft() && ($draft = $this->forward('showDraft', ['filePath' => $filePath, 'showSingle' => true]))) {
        return [
          'action' => 'return',
          'value'  => $draft,
        ];
      }
    } else if (self::userIsEditing() || self::userIsSaving()) {
      if (isset($_GET['srcFilePath'])) {
        if (isset(Config::$templates[$_GET['srcFilePath']])) {
          $fromFilePath = Config::$templates[$_GET['srcFilePath']]['location'];
        } else {
          $fromFilePath = self::isInternalForward() ? $_GET['srcFilePath'] : Utility::addDocRootToPath(urldecode($_GET['srcFilePath']));
          // we will have an absolute path if we were internally forwarded
        }
      } else {
        $fromFilePath = null;
      }

      $creationResult = $this->createNewPage($filePath, $fromFilePath);
      if ($creationResult) {
        return [
          'action' => 'return',
          'value'  => $creationResult,
        ];
      } else {
        // something happened in the request. Probably failed to acquire a lock.
        $fm = new FileManager($this->getLoggedInUsername(), $filePath, null, $this->getDB());
        if ($showDraftInsteadOfErrorPage && $fm->userHasOpenDraft() && ($draft = $this->forward('showDraft', ['filePath' => $filePath, 'showSingle' => true]))) {
          return [
            'action' => 'return',
            'value'  => $draft,
          ];
        }
      }
    }
    // nothing for us to do.
    return [
      'action' => 'none',
    ];
  }

  /**
   * Handles web requests to mosh
   *
   * @throws InvalidArgumentException If a filePath doesn't exist in $_POST
   * @return string Json encoded string
   */
  public function handleMoshRequest()
  {
    if (!isset($_POST['filePath'])) {
      throw new InvalidArgumentException('A file path is not specified.');
    }
    $filePath = $_POST['filePath'];

    $moshActions = $this->mosh($filePath);
    if (isset($moshActions['action'], $moshActions['value']) && $moshActions['action'] === 'return') {
      return Jsonizer::toJSON($moshActions['value']);
    }
    return Jsonizer::toJSON(null);
  }

  /**
   * Handles autocomplete results for user names
   *
   * @param  array $args Array from routing
   * @return string
   */
  public function autocompleteUser($args)
  {
    $value = $args['value'];

    $results = Autocomplete::executeQuery($value, 'FirstName,LastName,Username',
        ['Employee', 'Faculty', 'Administrator', 'Support Staff', 'Student'], 'Username,FirstName,LastName', null, false);

    $return = [];
    foreach ($results as $result) {
      if (isset($result->FirstName, $result->LastName, $result->Username)) {
        $return[] = [
          'label' => sprintf('%s %s (%s)', $result->FirstName, $result->LastName, $result->Username),
          'value' => $result->Username,
        ];
      }
    }

    return json_encode($return);
  }
}