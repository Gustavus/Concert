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
 * Class to build a Formbuilder configuration for sharing drafts
 *
 * @package  Concert
 * @subpackage Forms
 * @author  Billy Visto
 */
class ShareDraft
{
  /**
   * Builds the configuration for public email forms
   *
   * @param  array $draft Draft we are sharing
   * @return array
   */
  public static function getConfig($draft)
  {
    $config = [
      'name'            => 'addusers',
      'type'            => 'form',
      'action'          => $_SERVER['REQUEST_URI'],
      'method'          => 'post',
      'container-class' => 'grid_20 alpha omega',
      'children'        => self::buildChildren($draft),
    ];

    return $config;
  }

  /**
   * Builds the children configuration
   *
   * @param  array $draft
   * @return array
   */
  private static function buildChildren($draft)
  {
    $return = [];

    $autocompletePath = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'autocompleteUser', ['value' => '{value}']);

    $addButton = [
      'type'            => 'button',
      'action'          => 'Gustavus.FormBuilder.duplicateContainer(this, true); Extend.apply(\'autocompleteUser\');',
      'title'           => '&nbsp;',
      'subtitle'        => '&nbsp;',
      'value'           => 'Add',
      'container-class' => 'grid_2',
      'element-class'   => 'positive',
    ];
    $deleteButton = [
      'type'            => 'button',
      'action'          => 'Gustavus.FormBuilder.removeDuplicateContainer(this);',
      'title'           => '&nbsp;',
      'subtitle'        => '&nbsp;',
      'value'           => 'Del',
      'container-class' => 'omega grid_2',
      'element-class'   => 'negative',
    ];

    $additionalUsers = $draft['additionalUsers'];
    if (empty($additionalUsers)) {
      $return[] = [
        'name'            => 'person',
        'type'            => 'section',
        'container-class' => 'grid_17 prefix_1 suffix_1 alpha omega',
        'duplicatable'    => true,
        'children'        => [
          [
            'name'               => 'username',
            'type'               => 'text',
            'title'              => 'User Name',
            'subtitle'           => 'First part of Gustavus E-mail',
            'container-class'    => 'grid_12',
            'element-class'      => 'autocompleteUser',
            'element-attributes' => [
              'data-autocompletepath' => $autocompletePath,
            ],
          ],
          $addButton,
          $deleteButton,
        ],
      ];
    } else {
      foreach ($additionalUsers as $additionalUser) {
        $return[] = [
          'name'            => 'person',
          'type'            => 'section',
          'container-class' => 'grid_17 prefix_1 suffix_1 alpha omega',
          'duplicatable'    => true,
          'children'        => [
            [
              'name'               => 'username',
              'value'              => $additionalUser,
              'type'               => 'text',
              'title'              => 'User Name',
              'subtitle'           => 'First part of Gustavus E-mail',
              'container-class'    => 'grid_12',
              'element-class'      => 'autocompleteUser',
              'element-attributes' => [
                'data-autocompletepath' => $autocompletePath,
              ],
            ],
            $addButton,
            $deleteButton,
          ],
        ];
      }
    }

    $return[] = [
      'type'            => 'submit',
      'value'           => 'Submit',
      'name'            => 'submit',
      'container-class' => 'alpha grid_4',
      'element-class'   => 'concertSubmitAddUsers'
    ];
    return $return;
  }
}
