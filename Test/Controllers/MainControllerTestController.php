<?php
/**
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Test\Controllers;

use Gustavus\FormBuilderMK2\ElementRenderers\ElementRenderer,
  Gustavus\Concourse\Test\RouterTestUtil,
  Gustavus\Concert\Controllers\MainController;

/**
 * Test controller for MainController
 *
 * @package Concert
 * @subpackage Test
 * @author  Billy Visto
 */
class MainControllerTestController extends MainController
{
  /**
   * overloads renderPage so it doesn't try to start outbut buffers
   *
   * @return array
   */
  protected function renderPage()
  {
    $this->addSessionMessages();
    return [
      'title'           => $this->getTitle(),
      'subtitle'        => $this->getSubtitle(),
      'content'         => $this->getContent(),
      'localNavigation' => $this->getLocalNavigation(),
      'focusBox'        => $this->getFocusBox(),
      'stylesheets'     => $this->getStylesheets(),
      'javascripts'     => $this->getJavascripts(),
    ];
  }

  /**
   * overloads redirect so it doesn't try to redirect when called
   * @param  string $path
   * @param  integer $statusCode Redirection status code
   * @return void
   */
  protected function redirect($path = '/', $statusCode = 303)
  {
    $_POST = [];
    return ['redirect' => $path];
  }

  /**
   * {@inheritdoc}
   */
  protected function addFormBuilderResources(ElementRenderer $renderer, array $extraCSSResources = null, array $extraJSResources = null)
  {
    $this->setStylesheets('');
    $this->setJavascripts('');
  }

  /**
   * {@inheritdoc}
   */
  protected function insertEditingResources($filePath, $redirectPath = null, array $visibleButtons = null, array $additionalButtons = null, array $additionalJSOptions = null)
  {
    $origDocRoot = $_SERVER['DOCUMENT_ROOT'];
    $_SERVER['DOCUMENT_ROOT'] = '/cis/www/';
    parent::insertEditingResources($filePath, $redirectPath, $visibleButtons, $additionalButtons, $additionalJSOptions);
    $_SERVER['DOCUMENT_ROOT'] = $origDocRoot;
  }

  /**
   * Adds css and js needed for filtering
   *
   * @return  void
   */
  protected function addFilteringJsAndCss()
  {
    $this->setStylesheets('');
    $this->setJavascripts('');
  }

  /**
   * Forwards specifying the test controller to forward to
   *
   * {@inheritdoc}
   */
  protected function forward($alias, array $parameters = array())
  {
    return RouterTestUtil::forward($this->getRoutingConfiguration(), $alias, $parameters, [
          'Gustavus\Concert\Controllers\DraftController' => 'Gustavus\Concert\Test\Controllers\DraftControllerTestController',
          'Gustavus\Concert\Controllers\MenuController' => 'Gustavus\Concert\Test\Controllers\MenuControllerTestController',
          'Gustavus\Concert\Controllers\SiteNavController' => 'Gustavus\Concert\Test\Controllers\SiteNavControllerTestController',
          'Gustavus\Concert\Controllers\MainController' => 'Gustavus\Concert\Test\Controllers\MainControllerTestController',
          'Gustavus\Concert\Controllers\EmailController' => 'Gustavus\Concert\Controllers\EmailController']);
  }
}
