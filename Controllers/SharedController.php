<?php
/**
 * @package Concert
 * @subpackage Controllers
 * @author  Billy Visto
 */
namespace Gustavus\Concert\Controllers;

use Gustavus\Concourse\Controller as ConcourseController,
  Gustavus\Resources\Resource,
  Gustavus\Concert\Config;

/**
 * Controller to handle shared functionality for other controllers
 *
 * @package Concert
 * @subpackage Controllers
 * @author  Billy Visto
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
}
