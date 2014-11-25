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
      'container-class' => 'grid_36 alpha omega',
      'children'        => [
        [
          'name'               => 'username',
          'type'               => 'text',
          'title'              => 'Username',
          'container-class'    => 'grid_14 alpha',
          'element-class'      => 'autocompleteUser',
          'element-attributes' => [
            'data-autocompletepath' => $autocompletePath,
          ],
        ],
        [
          'type'            => 'submit',
          'value'           => 'Search',
          'name'            => 'submit',
          'container-class' => 'alpha grid_4',
        ],
      ],
    ];

    return $config;
  }
}
