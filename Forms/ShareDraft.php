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
   * @param  string $actionUrl Url the form posts to
   * @return array
   */
  public static function getConfig($draft, $actionUrl)
  {
    // note: this form must post to a concert url in case the file we are editing doesn't exist and we lose our post data from the error page redirect
    $config = [
      'name'            => 'addusers',
      'type'            => 'form',
      'action'          => $actionUrl,
      'method'          => 'post',
      'container-class' => 'grid-100 alpha omega',
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

    $autocompletePath = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'autocompleteUser', ['value' => '{value}'], Config::WEB_DIR);

    $addButton = [
      'type'            => 'button',
      'action'          => 'Gustavus.FormBuilder.duplicateContainer(this, true); Extend.apply(\'autocompleteUser\');',
      'title'           => '&nbsp;',
      'subtitle'        => '&nbsp;',
      'value'           => 'Add',
      'container-class' => 'grid-15 tgrid-15 mgrid-50',
      'element-class'   => 'positive',
    ];
    $deleteButton = [
      'type'            => 'button',
      'action'          => 'Gustavus.FormBuilder.removeDuplicateContainer(this);',
      'title'           => '&nbsp;',
      'subtitle'        => '&nbsp;',
      'value'           => 'Del',
      'container-class' => 'grid-15 tgrid-15 mgrid-50',
      'element-class'   => 'negative',
    ];

    $additionalUsers = $draft['additionalUsers'];
    if (empty($additionalUsers)) {
      $return[] = [
        'name'            => 'person',
        'type'            => 'section',
        'container-class' => 'grid-90 prefix-5 suffix-5 tgrid-90 tprefix-5 tsuffix-5  alpha omega',
        'duplicatable'    => true,
        'children'        => [
          [
            'name'               => 'username',
            'type'               => 'text',
            'title'              => 'User Name',
            'subtitle'           => 'First part of Gustavus E-mail',
            'container-class'    => 'grid-70 tgrid-70',
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
          'container-class' => 'grid-90 prefix-5 suffix-5 tgrid-90 tprefix-5 tsuffix-5  alpha omega',
          'duplicatable'    => true,
          'children'        => [
            [
              'name'               => 'username',
              'value'              => $additionalUser,
              'type'               => 'text',
              'title'              => 'User Name',
              'subtitle'           => 'First part of Gustavus E-mail',
              'container-class'    => 'grid-70 tgrid-70',
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
      'container-class' => 'alpha grid-20 tgrid-20',
      'element-class'   => 'concertSubmitAddUsers'
    ];

    // now add all of this into a section
    $return = [
      'name'            => 'adduserssection',
      'type'            => 'section',
      'title'           => 'Add collaborators to your draft',
      'container-class' => 'grid-100 tgrid-100 alpha omega',
      'children'        => $return,
    ];
    return $return;
  }
}
