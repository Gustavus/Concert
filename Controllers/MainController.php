<?php
/**
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 */
namespace Gustavus\Concert\Controllers;

use Gustavus\Concert\Config,
  Gustavus\Concert\FileManager,
  Gustavus\Utility\File,
  Gustavus\Resources\Resource,
  Gustavus\Extensibility\Filters,
  Gustavus\Gatekeeper\Gatekeeper,
  Gustavus\Concert\PermissionsManager;

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
   * Handles editing a page
   *
   * @param  string $page Page we are trying to edit
   * @return string|boolean String of the rendered edit page or boolean on save
   */
  public function edit($page = null)
  {
    if ($page === null && !isset($_GET['page'])) {
      return $this->renderErrorPage('Oops! It looks like there was no page specified to edit.');
    }
    if ($page === null) {
      $page = '/' . $_GET['page'];
    }

    $fm = new FileManager($_SERVER['DOCUMENT_ROOT'] . $page, $this->getLoggedInUsername());

    if (!$fm->acquireLock()) {
      $this->setSessionMessage('Oops! We were unable to create a lock for this file. Someone else must currently be editing it. Please try back later.', false, $page);
      return false;
    }

    if ($this->getMethod() === 'POST') {
      // trying to save an edit

      $result = $fm->editFile($_POST);
      if ($result && $fm->stageFile()) {
        return true;
        //return $this->redirect($this->buildUrl('edit') . $page);
      }
    }

    Filters::add('scripts', function($content) {
        $script = sprintf(
            '<script type="text/javascript">
              Modernizr.load([
                //"//tinymce.cachefly.net/4.0/tinymce.min.js",
                "%s",
                "%s"
              ]);
            </script>',
            Resource::renderResource(['path' => Config::WEB_DIR . '/js/tinymce/tinymce.min.js', 'version' => 0]),
            Resource::renderResource(['path' => Config::WEB_DIR . '/js/concert.js', 'version' => Config::JS_VERSION])
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

    Filters::add('body', function($content) {
      return $content . '<br/><button type="submit" id="concertSave" class="concert">Save</button>
      <br/>
      <br/>
      <a href="#" class="button" id="toggleShowingEditableContent" data-show="false">Show editable areas</a>';
    }, 9999);

    $draftFileName = $fm->makeEditableDraft();

    if ($draftFileName === false) {
      return $this->renderErrorPage('Something happened');
    }

    return (new File($draftFileName))->loadAndEvaluate();
  }

  /**
   * Checks for any requests for users already working in Concert and also checks to see if it can add anything into the Template such as edit options or other actions.
   * Returns an array of actions to take.
   * Return Values:
   * <ul>
   *   <li>action: {boolean} Whether any action is require or not. PossibleValues:
   *     <ul>
   *       <li>action: {string} Action needed to take. Possible keys: 'return' and 'none'.</li>
   *       <li>value: {string} Value to return if the action is "return".</li>
   *     </ul>
   *   </li>
   *   <li>value: {string} Value for caller to return or add into the Template.</li>
   * </ul>
   *
   * @return array Array containing actions the template render needs to take.
   */
  public function mosh()
  {
    if (Gatekeeper::isLoggedIn()) {
      // check to see if the user has access to edit this page
      if (!$this->alreadyMoshed() && PermissionsManager::userCanEditFile(Gatekeeper::getUsername(), $_SERVER['SCRIPT_NAME'])) {
        if ($this->userWantsToEdit() || $this->userIsSaving()) {
          // let ourselves know that we have already moshed this request.
          $this->markMoshed();
          Filters::add('userBox', function($content) {
            // @todo make this remove concert stuff from the url
            return $content . '<a href="?concert=stopedit" class="button red concertEditPage">Stop Editing</a>';
          });
          $editResult = $this->edit($_SERVER['SCRIPT_NAME']);
          if ($editResult) {
            return [
              'action' => 'return',
              'value'  => $editResult,
            ];
          }
        } else {
          Filters::add('userBox', function($content) {
            return $content . '<a href="?concert=edit" class="button red concertEditPage">Edit Page</a>';
          });
          $fm = new FileManager($_SERVER['SCRIPT_NAME'], $this->getLoggedInUsername());
          $fm->stopEditing();
        }
      }
    }
    return [
      'action' => 'none',
    ];
  }

  /**
   * Checks to see if we have already moshed in this request or not
   *
   * @return boolean
   */
  private function alreadyMoshed()
  {
    return (isset($_GET['concert']) && $_GET['concert'] === 'moshed');
  }

  /**
   * Sets a variable saying that we have already moshed this request.
   *
   * @return void
   */
  private function markMoshed()
  {
    $_GET['concert'] = 'moshed';
  }

  /**
   * Checks to see if the user wants to edit the page
   *
   * @return boolean
   */
  private function userWantsToEdit()
  {
    return (isset($_GET['concert']) && $_GET['concert'] === 'edit');
  }

  /**
   * Checks to see if the user is currently saving an edit
   *
   * @return boolean
   */
  private function userIsSaving()
  {
    return (isset($_POST['concertAction']) && $_POST['concertAction'] === 'save');
  }
}