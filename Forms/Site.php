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
      'container-class' => 'grid-15 tgrid-15 mgrid-50 malpha',
      'element-class'   => 'positive',
    ];
    $deleteButton = [
      'type'            => 'button',
      'action'          => 'Gustavus.FormBuilder.removeDuplicateContainer(this);',
      'title'           => '&nbsp;',
      'subtitle'        => '&nbsp;',
      'value'           => 'Del',
      'container-class' => 'grid-15 tgrid-15 mgrid-50 omega',
      'element-class'   => 'red',
    ];

    $children = [];

    if (empty($site)) {
      // we don't want to be able to change a site's root by editing.
      $children[] = [
        'name'            => 'siteinfo',
        'type'            => 'section',
        'title'           => 'Site Info',
        'container-class' => 'grid-90 tgrid-90 mgrig-100 alpha omega prefix-5 suffix-5 tprefix-5 tsuffix-5',
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
        'container-class' => 'grid-90 tgrid-90 mgrid-100 prefix-5 suffix-5 tprefix-5 tsuffix-5 alpha omega',
        'duplicatable'    => true,
        'children'        => [
          [
            'name'               => 'file',
            'type'               => 'text',
            'title'              => 'Excluded File',
            'subtitle'           => '&nbsp;',
            'container-class'    => 'grid-70 tgrid-70 mgrid-100 alpha momega',
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
          'container-class' => 'grid-90 tgrid-90 mgrid-100 prefix-5 suffix-5 tprefix-5 tsuffix-5 alpha omega',
          'duplicatable'    => true,
          'children'        => [
            [
              'name'               => 'file',
              'type'               => 'text',
              'title'              => 'Excluded File',
              'value'              => $excludedFile,
              'subtitle'           => '&nbsp;',
              'container-class'    => 'grid-70 tgrid-70 mgrid-100 malpha momega',
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
      'container-class' => 'grid-90 tgrid-90 mgrid-100 prefix-5 suffix-5 tprefix-5 tsuffix-5 alpha omega',
      'children'        => $excludedFilesChildren,
    ];

    $children[] = [
      'name'            => 'peoplesection',
      'type'            => 'section',
      'title'           => 'Add People',
      'container-class' => 'grid-90 tgrid-90 mgrid-100 prefix-5 suffix-5 tprefix-5 tsuffix-5 alpha omega',
      'children'        => self::buildPeopleSection($site),
    ];

    $children[] = [
      'type'            => 'submit',
      'value'           => 'Submit',
      'name'            => 'submit',
      'container-class' => 'alpha grid-20 tgrid-30 mgrid-100 momega',
    ];

    $config = [
      'name'            => 'editsite',
      'type'            => 'form',
      'action'          => $_SERVER['REQUEST_URI'],
      'method'          => 'post',
      'container-class' => 'grid-100 tgrid-100 mgrid-100 alpha omega',
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
      'container-class' => 'grid-25 tgrid-25 mgrid-50 malpha',
      'element-class'   => 'positive',
    ];
    $deletePersonButton = [
      'type'            => 'button',
      'action'          => 'Gustavus.FormBuilder.removeDuplicateContainer(this);',
      'title'           => '&nbsp;',
      'subtitle'        => '&nbsp;',
      'value'           => 'Delete Person',
      'container-class' => 'omega grid-25 tgrid-25 mgrid-50',
      'element-class'   => 'negative',
    ];

    if (empty($site['people'])) {
      return [
        [
          'name'            => 'personpermissions',
          'type'            => 'section',
          'container-class' => 'grid-90 tgrid-90 mgrid-100 prefix-5 suffix-5 tprefix-5 tsuffix-5 alpha omega',
          'duplicatable'    => true,
          'children'        => [
            [
              'name'               => 'username',
              'type'               => 'text',
              'title'              => 'Username',
              'container-class'    => 'grid-50 tgrid-50 mgrid-100 alpha momega',
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
              'container-class' => 'grid-50 tgrid-50 mgrid-100 malpha omega',
              'children'        => self::buildAccessLevelChildren(),
              'validators'      => new ConditionalValidator(new ElementReference('username', 1), new PresenceValidator(), new PresenceValidator('Please specify at least one access level')),
            ],
            [
              'name'            => 'includedfiles',
              'type'            => 'text',
              'title'           => 'Included Files',
              'container-class' => 'grid-50 tgrid-50 mgrid-100 alpha momega',
            ],
            [
              'name'            => 'excludedfiles',
              'type'            => 'text',
              'title'           => 'Excluded Files',
              'container-class' => 'grid-50 tgrid-50 mgrid-100 malpha omega',
            ],
            [
              'name'            => 'expirationdate',
              'type'            => 'date',
              'title'           => 'Expiration Date',
              'subtitle'           => '&nbsp;',
              'container-class' => 'grid-50 tgrid-50 mgrid-100 alpha momega',
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
          'container-class' => 'grid-90 tgrid-90 mgrid-100 prefix-5 suffix-5 tprefix-5 tsuffix-5 alpha omega',
          'duplicatable'    => true,
          'children'        => [
            [
              'name'               => 'username',
              'type'               => 'text',
              'title'              => 'Username',
              'value'              => $person['username'],
              'container-class'    => 'grid-50 tgrid-50 mgrid-100 alpha momega',
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
              'container-class' => 'grid-50 tgrid-50 mgrid-100 malpha momega omega',
              'children'        => self::buildAccessLevelChildren(),
              'validators'      => new ConditionalValidator(new ElementReference('username', 1), new PresenceValidator(), new PresenceValidator('Please specify at least one access level')),
            ],
            [
              'name'            => 'includedfiles',
              'type'            => 'text',
              'title'           => 'Included Files',
              'value'           => $person['includedFiles'],
              'container-class' => 'grid-50 tgrid-50 mgrid-100 alpha momega',
            ],
            [
              'name'            => 'excludedfiles',
              'type'            => 'text',
              'title'           => 'Excluded Files',
              'value'           => $person['excludedFiles'],
              'container-class' => 'grid-50 tgrid-50 mgrid-100 malpha momega omega',
            ],
            [
              'name'            => 'expirationdate',
              'type'            => 'date',
              'title'           => 'Expiration Date',
              'value'           => $person['expirationDate'],
              'subtitle'        => '&nbsp;',
              'container-class' => 'grid-50 tgrid-50 mgrid-100 alpha momega',
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
