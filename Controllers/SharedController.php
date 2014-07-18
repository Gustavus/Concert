<?php
/**
 * @package Concert
 * @subpackage Controllers
 * @author  Billy Visto
 */
namespace Gustavus\Concert\Controllers;

use Gustavus\Concourse\Controller as ConcourseController,
  Gustavus\Resources\Resource,
  Gustavus\Concert\Config,
  Gustavus\Extensibility\Filters,
  Gustavus\Concert\PermissionsManager;

/**
 * Controller to handle shared functionality for other controllers
 *
 * @package Concert
 * @subpackage Controllers
 * @author  Billy Visto
 *
 * @todo  write tests
 */
class SharedController extends ConcourseController
{
  /**
   * @var string $applicationTitle
   */
  protected $applicationTitle = 'Concert';

  /**
   * {@inheritdoc}
   */
  protected function getLocalNavigation()
  {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRoutingConfiguration()
  {
    return Config::ROUTING_LOCATION;
  }

  /**
   * Gets Doctrine's entity manager for Concert
   *
   * @param  boolean $new whether to use a new entitymanager or not
   * @return \Doctrine\ORM\EntityManager
   */
  protected function getDoctrine($new = false)
  {
    return $this->getEM('/cis/lib/Gustavus/Concert', Config::DB, $new);
  }

  /**
   * Gets the repository for the specified entity
   *
   * @param  string $name
   * @param  boolean $new whether to use a new entitymanager or not
   * @return \Doctrine\ORM\EntityRepository
   */
  protected function getRepository($name, $new = false)
  {
    return $this->getDoctrine($new)->getRepository('\Gustavus\Concert\Entities\\' . $name);
  }

  /**
   * Calls parent's renderView throwing in the full path to the views
   *
   * {@inheritdoc}
   */
  protected function renderView($template, array $args = array(), $modifyEnvironment = true)
  {
    return parent::renderView('/cis/lib/Gustavus/Concert/Views/' . $template, $args, $modifyEnvironment);
  }

  /**
   * Adds JS to page
   *
   * @todo  remove this. Or convert to tinymce.
   *
   * @return  void
   */
  protected function addJS()
  {
    $this->addJavascripts(sprintf(
        '<script type="text/javascript">
          var CKEDITOR_BASEPATH = \'/js/ckeditor/\';
          Modernizr.load([
            "%s",
          ]);
        </script>',
        Resource::renderResource([['path' => '/js/ckeditor/ckeditor.js'], ['path' => '/js/ckeditor/adapters/jquery.js'], ['path' => Config::WEB_DIR . '/js/concert.js', 'version' => Config::JS_VERSION]])
    ));
  }

  /**
   * Adds CSS to page
   *
   * @return  void
   */
  protected function addCSS()
  {
    $this->addStylesheets(
        sprintf(
            '<link rel="stylesheet" type="text/css" href="%s" />',
            Resource::renderCSS(['path' => Config::WEB_DIR . '/css/concert.css', 'version' => Config::CSS_VERSION])
        )
    );
  }

  /**
   * Injects resources required for editing pages
   *
   * @param string $filePath FilePath of the file we are editing
   * @param  array $visibleButtons Array of buttons that we want to display
   * @return void
   */
  protected function insertEditingResources($filePath, array $visibleButtons = null, array $additionalButtons = null)
  {
    Filters::add('scripts', function($content) use ($filePath) {
        $script = sprintf(
            '<script type="text/javascript">
              Modernizr.load({
                load: [
                  "%s",
                  "%s"
                ],
                complete: function() {
                  Gustavus.Concert.filePath = "%s"
                }
              });
            </script>',
            Resource::renderResource(['path' => Config::WEB_DIR . '/js/tinymce/tinymce.min.js', 'version' => 0]),
            Resource::renderResource(['path' => Config::WEB_DIR . '/js/concert.js', 'version' => Config::JS_VERSION]),
            Config::removeDocRootFromPath($filePath)
        );
        return $content . $script;
    }, 11);

    Filters::add('head', function($content) {
        $css = sprintf(
            '<link rel="stylesheet" type="text/css" href="%s" />',
            Resource::renderCSS(['path' => Config::WEB_DIR . '/css/concert.css', 'version' => Config::CSS_VERSION])
        );
        return $content . $css;
    }, 11);

    $userCanPublishFile = PermissionsManager::userCanPublishFile($this->getLoggedInUsername(), Config::removeDocRootFromPath($filePath));

    if ($visibleButtons === null) {
      $visibleButtons = Config::$defaultEditingButtons;
    }

    Filters::add('body', function($content) use ($userCanPublishFile, $visibleButtons, $additionalButtons) {
      return $content . $this->renderView(
          'actionButtons.html.twig',
          [
            'userCanPublishFile' => $userCanPublishFile,
            'visibleButtons'     => $visibleButtons,
            'additionalButtons'  => $additionalButtons,
          ]
      );
    }, 9999);
  }

  // Action checks

  /**
   * Checks to see if we have already moshed in this request or not
   *
   * @return boolean
   */
  protected function alreadyMoshed()
  {
    return (isset($_GET['concertMoshed']) && $_GET['concertMoshed'] === 'true');
  }

  /**
   * Sets a variable saying that we have already moshed this request.
   *
   * @return void
   */
  protected function markMoshed()
  {
    $_GET['concertMoshed'] = 'true';
  }

  /**
   * Checks to see if the user wants to edit the page
   *
   * @return boolean
   */
  protected function userWantsToEdit()
  {
    return (isset($_GET['concert']) && $_GET['concert'] === 'edit');
  }

  /**
   * Checks to see if the user wants to edit the page
   *
   * @return boolean
   */
  protected function userWantsToStopEditing()
  {
    return (isset($_GET['concert']) && $_GET['concert'] === 'stopEditing');
  }

  /**
   * Checks to see if the user is currently saving an edit
   *
   * @return boolean
   */
  protected function userIsSaving()
  {
    return (isset($_POST['concertAction']) && $_POST['concertAction'] === 'save');
  }

  /**
   * Checks to see if the user is saving a draft
   *
   * @return boolean
   */
  protected function userIsSavingDraft()
  {
    return ($this->userIsSavingPublicDraft() || $this->userIsSavingPrivateDraft());
  }

  /**
   * Checks to see if the user is trying to save a public draft
   *
   * @return boolean
   */
  protected function userIsSavingPublicDraft()
  {
    return (isset($_POST['saveAction']) && $_POST['saveAction'] === 'savePublicDraft');
  }

  /**
   * Checks to see if the user is trying to save a protected draft
   *
   * @return boolean
   */
  protected function userIsSavingPrivateDraft()
  {
    return (isset($_POST['saveAction']) && $_POST['saveAction'] === 'savePrivateDraft');
  }

  /**
   * Checks to see if the user wants to view a draft
   *
   * @return boolean
   */
  protected function userWantsToViewDraft()
  {
    return (isset($_GET['concert']) && $_GET['concert'] === 'viewDraft');
  }

  /**
   * Checks to see if the user wants to delete a draft
   *
   * @return boolean
   */
  protected function userIsDeletingDraft()
  {
    return (isset($_POST['saveAction']) && $_POST['saveAction'] === 'discardDraft');
  }

  /**
   * Checks to see if the user is wanting to do things with drafts
   *
   * @return boolean
   */
  protected function isDraftRequest()
  {
    return ($this->userWantsToViewDraft() || $this->userIsSavingDraft() || $this->userIsDeletingDraft());
  }

  /**
   * Checks to see if the user wants to release their lock
   *
   * @return boolean
   */
  protected function isRequestingLockRelease()
  {
    return (isset($_POST['concertAction']) && $_POST['concertAction'] === 'stopEditing');
  }

  /**
   * Checks to see if the user is requesting to query something
   *
   * @return boolean
   */
  protected function isRequestingQuery()
  {
    return (isset($_POST['concertAction']) && $_POST['concertAction'] === 'query');
  }

  protected function getQueryFromRequest()
  {
    if ($this->isRequestingQuery() && isset($_POST['query'])) {
      return $_POST['query'];
    }
    return null;
  }
}
