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
  Gustavus\Extensibility\Filters;

/**
 * Handles main Concert actions
 *
 * @package Concert
 * @subpackage Controller
 * @author  Billy Visto
 */
class MainController extends SharedController
{
  public function edit($page = null)
  {
    if ($page === null && !isset($_GET['page'])) {
      return $this->renderErrorPage('Oops! It looks like there was no page specified to edit.');
    }
    if ($page === null) {
      $page = '/' . $_GET['page'];
    }

    $fm = new FileManager($_SERVER['DOCUMENT_ROOT'] . $page, $this->getLoggedInUsername());

    if ($this->getMethod() === 'POST') {
      // trying to save an edit

      $result = $fm->editFile($_POST);
      if ($result && $fm->save()) {
        return true;
        //return $this->redirect($this->buildUrl('edit') . $page);
      }
    }

    // $this->addJavascripts(sprintf(
    //     '<script type="text/javascript">
    //       Modernizr.load([
    //         //"//tinymce.cachefly.net/4.0/tinymce.min.js",
    //         "%s",
    //         "%s"
    //       ]);
    //     </script>',
    //     Resource::renderResource(['path' => Config::WEB_DIR . '/js/tinymce/tinymce.min.js', 'version' => 0]),
    //     Resource::renderResource(['path' => Config::WEB_DIR . '/js/concert.js', 'version' => Config::JS_VERSION])
    //     //Resource::renderResource([['path' => Config::WEB_DIR . '/js/tinymce/js/tinymce/tinymce.min.js', 'version' => 0], ['path' => Config::WEB_DIR . '/js/cms.js', 'version' => Config::JS_VERSION]])
    // ));

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
          //Resource::renderResource([['path' => Config::WEB_DIR . '/js/tinymce/js/tinymce/tinymce.min.js', 'version' => 0], ['path' => Config::WEB_DIR . '/js/cms.js', 'version' => Config::JS_VERSION]])
        );
        return $content . $script;
    }, 11);

    //$this->addJS();
    //$this->addCSS();
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

    $draftFileName = $fm->makeDraft();

    if ($draftFileName === false) {
      return $this->renderErrorPage('Something happened');
    }

    return (new File($draftFileName))->loadAndEvaluate();
    // @todo, this shouldn't throw the content in the template. It should just render.
    $this->addContent((new File($draftFileName))->loadAndEvaluate());

    $this->addContent('<br/><button type="submit" id="concertSave" class="concert">Save</button>
      <br/>
      <br/>
      <a href="#" class="button" id="toggleShowingEditableContent" data-show="false">Show editable areas</a>');
    return $this->renderPage();
  }
}