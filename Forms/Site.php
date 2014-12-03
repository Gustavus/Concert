<?php
/**
 * @package  Concert
 * @subpackage Form
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Forms;

use Gustavus\Concert\Config,
  Gustavus\Concert\PermissionsManager,
  Gustavus\Concourse\RoutingUtil,
  Gustavus\FormBuilderMk2\DataValidators\PresenceValidator,
  Gustavus\FormBuilderMk2\DataValidators\ConditionalValidator,
  Gustavus\FormBuilderMk2\Util\ElementReference;

/**
 * Class to build a Formbuilder configuration for creating sites
 *
 * @package  Concert
 * @subpackage Forms
 * @author  Billy Visto
 */
class Site
{
  /**
   * Username of the currently logged in user
   *
   * @var string
   */
  private static $username;

  /**
   * Builds the configuration for creating a site
   *
   * @param  array $site Site we are wanting to edit if we are editing
   * @param  string $username Username of the currently logged in person
   * @return array
   */
  public static function getConfig($site = null, $username = null)
  {
    // set our username
    self::$username = $username;

    $addButton = [
      'type'            => 'button',
      'action'          => 'Gustavus.FormBuilder.duplicateContainer(this, true);',
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

    $children = [];

    if (empty($site)) {
      // we don't want to be able to change a site's root by editing.
      $children[] = [
        'name'            => 'siteinfo',
        'type'            => 'section',
        'title'           => 'Site Info',
        'container-class' => 'grid_34 alpha omega prefix_1 suffix_1',
        'children' => [
          'name'               => 'siteroot',
          'type'               => 'text',
          'title'              => 'Site Root',
          'validation-message' => 'Required',
          'validators'         => new PresenceValidator('Please specify a root for the site.'),
        ],
      ];
    }

    $excludedFilesChildren = [];
    if (empty($site['excludedFiles'])) {
      $excludedFilesChildren[] = [
        'name'            => 'excludedfile',
        'type'            => 'section',
        'container-class' => 'grid_32 prefix_1 suffix_1 alpha omega',
        'duplicatable'    => true,
        'children'        => [
          [
            'name'               => 'file',
            'type'               => 'text',
            'title'              => 'Excluded File',
            'subtitle'           => '&nbsp;',
            'container-class'    => 'grid_16',
          ],
          $addButton,
          $deleteButton,
        ],
      ];
    } else {
      $excludedFiles = explode(',', $site['excludedFiles']);
      foreach ($excludedFiles as $excludedFile) {
        $excludedFilesChildren[] = [
          'name'            => 'excludedfile',
          'type'            => 'section',
          'container-class' => 'grid_32 prefix_1 suffix_1 alpha omega',
          'duplicatable'    => true,
          'children'        => [
            [
              'name'               => 'file',
              'type'               => 'text',
              'title'              => 'Excluded File',
              'value'              => $excludedFile,
              'subtitle'           => '&nbsp;',
              'container-class'    => 'grid_16',
            ],
            $addButton,
            $deleteButton,
          ],
        ];
      }
    }
    $children[] = [
      'name'            => 'excludedfilessection',
      'type'            => 'section',
      'title'           => 'Site Level Excluded Files',
      'container-class' => 'grid_34 alpha omega prefix_1 suffix_1',
      'children'        => $excludedFilesChildren,
    ];

    $children[] = [
      'name'            => 'peoplesection',
      'type'            => 'section',
      'title'           => 'Add People',
      'container-class' => 'grid_34 alpha omega prefix_1 suffix_1',
      'children'        => self::buildPeopleSection($site),
    ];

    $children[] = [
      'type'            => 'submit',
      'value'           => 'Submit',
      'name'            => 'submit',
      'container-class' => 'alpha grid_4',
    ];

    $config = [
      'name'            => 'editsite',
      'type'            => 'form',
      'action'          => $_SERVER['REQUEST_URI'],
      'method'          => 'post',
      'container-class' => 'grid_36 alpha omega',
      'children'        => $children,
    ];

    return $config;
  }

  /**
   * Builds access level options
   *
   * @return array
   */
  private static function buildAccessLevelChildren()
  {
    $return = [];
    foreach (Config::$availableAccessLevels as $accessLevel => $label) {
      if ($accessLevel === Config::SUPER_USER && !PermissionsManager::isUserSuperUser(self::$username)) {
        continue;
      }
      $return[] = [
        'type'  => 'option',
        'value' => $accessLevel,
        'title' => $label,
      ];
    }
    return $return;
  }

  /**
   * Builds the people section of the form
   *
   * @param  array $site $site we are editing if we are editing.
   * @return array
   */
  private static function buildPeopleSection($site)
  {
    $autocompletePath = RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'autocompleteUser', ['value' => '{value}'], Config::WEB_DIR);
    $addPersonButton = [
      'type'            => 'button',
      'action'          => 'Extend.apply(\'selectDuplicated\', Gustavus.FormBuilder.duplicateContainer(this, false)); Extend.apply(\'autocompleteUser\');',
      'title'           => '&nbsp;',
      'subtitle'        => '&nbsp;',
      'value'           => 'Add Person',
      'container-class' => 'grid_7 prefix_1',
      'element-class'   => 'positive',
    ];
    $deletePersonButton = [
      'type'            => 'button',
      'action'          => 'Gustavus.FormBuilder.removeDuplicateContainer(this);',
      'title'           => '&nbsp;',
      'subtitle'        => '&nbsp;',
      'value'           => 'Delete Person',
      'container-class' => 'omega grid_7',
      'element-class'   => 'negative',
    ];

    if (empty($site['people'])) {
      return [
        [
          'name'            => 'personpermissions',
          'type'            => 'section',
          'container-class' => 'grid_32 prefix_1 suffix_1 alpha omega',
          'duplicatable'    => true,
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
              'name'            => 'accesslevel',
              'type'            => 'select',
              'multivalue'      => true,
              'title'           => 'Access Level',
              'element-class'   => 'longSelect',
              'container-class' => 'grid_15',
              'children'        => self::buildAccessLevelChildren(),
              'validators'      => new ConditionalValidator(new ElementReference('username', 1), new PresenceValidator(), new PresenceValidator('Please specify at least one access level')),
            ],
            [
              'name'            => 'includedfiles',
              'type'            => 'text',
              'title'           => 'Included Files',
              'container-class' => 'grid_14 alpha',
            ],
            [
              'name'            => 'excludedfiles',
              'type'            => 'text',
              'title'           => 'Excluded Files',
              'container-class' => 'grid_15',
            ],
            [
              'name'            => 'expirationdate',
              'type'            => 'date',
              'title'           => 'Expiration Date',
              'subtitle'           => '&nbsp;',
              'container-class' => 'grid_14 alpha',
            ],
            $addPersonButton,
            $deletePersonButton,
          ],
        ],
      ];
    } else {
      $return = [];
      foreach ($site['people'] as $person) {
        $return[] = [
          'name'            => 'personpermissions',
          'type'            => 'section',
          'container-class' => 'grid_32 prefix_1 suffix_1 alpha omega',
          'duplicatable'    => true,
          'children'        => [
            [
              'name'               => 'username',
              'type'               => 'text',
              'title'              => 'Username',
              'value'              => $person['username'],
              'container-class'    => 'grid_14 alpha',
              'element-class'      => 'autocompleteUser',
              'element-attributes' => [
                'data-autocompletepath' => $autocompletePath,
              ],
            ],
            [
              'name'            => 'accesslevel',
              'type'            => 'select',
              'multivalue'      => true,
              'title'           => 'Access Level',
              'value'           => $person['accessLevel'],
              'element-class'   => 'longSelect',
              'container-class' => 'grid_15',
              'children'        => self::buildAccessLevelChildren(),
              'validators'      => new ConditionalValidator(new ElementReference('username', 1), new PresenceValidator(), new PresenceValidator('Please specify at least one access level')),
            ],
            [
              'name'            => 'includedfiles',
              'type'            => 'text',
              'title'           => 'Included Files',
              'value'           => $person['includedFiles'],
              'container-class' => 'grid_14 alpha',
            ],
            [
              'name'            => 'excludedfiles',
              'type'            => 'text',
              'title'           => 'Excluded Files',
              'value'           => $person['excludedFiles'],
              'container-class' => 'grid_15',
            ],
            [
              'name'            => 'expirationdate',
              'type'            => 'date',
              'title'           => 'Expiration Date',
              'value'           => $person['expirationDate'],
              'subtitle'        => '&nbsp;',
              'container-class' => 'grid_14 alpha',
            ],
            $addPersonButton,
            $deletePersonButton,
          ],
        ];
      }
      return $return;
    }
  }
}
