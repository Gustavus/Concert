<?php
/**
 * @package  Concert
 * @subpackage Form
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Forms;

use Gustavus\Concert\Config,
  Gustavus\Concourse\RoutingUtil;

/**
 * Class to build a Formbuilder configuration for searching for a user
 *
 * @package  Concert
 * @subpackage Forms
 * @author  Billy Visto
 */
class UserSearch
{
  /**
   * Builds the configuration for searching for a user
   *
   * @return array
   */
  public static function getConfig()
  {
    $autocompletePath = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'autocompleteUser', ['value' => '{value}'], Config::WEB_DIR);

    $config = [
      'name'            => 'usersearch',
      'type'            => 'form',
      'action'          => $_SERVER['REQUEST_URI'],
      'method'          => 'post',
      'container-class' => 'grid-100 tgrid-100 mgrid-100 alpha omega',
      'children'        => [
        [
          'name'               => 'username',
          'type'               => 'text',
          'title'              => 'Username',
          'container-class'    => 'grid-70 tgrid-70 mgrid-100 alpha',
          'element-class'      => 'autocompleteUser',
          'element-attributes' => [
            'data-autocompletepath' => $autocompletePath,
          ],
        ],
        [
          'type'            => 'submit',
          'value'           => 'Search',
          'name'            => 'submit',
          'container-class' => 'alpha grid-30 tgrid-30 mgrid-100',
        ],
      ],
    ];

    return $config;
  }
}
