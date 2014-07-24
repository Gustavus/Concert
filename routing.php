<?php
/**
 * @package Concert
 * @author  Billy Visto
 */

use Gustavus\Concert\Config,
  Gustavus\Gatekeeper\Gatekeeper;

return [
  // 'edit' => [
  //   'route'   => '/edit',
  //   'handler' => 'Gustavus\Concert\Controllers\MainController:edit',
  //   //'visibleTo' => ['Concert', ['all', 'callbacks' => ['Gustavus\Concert\Config::canEditPage']]]
  // ],
  'mosh' => [
    'route'   => '/',
    'handler' => 'Gustavus\Concert\Controllers\MainController:handleMoshRequest',
    //'visibleTo' => ['Concert', ['all', 'callbacks' => ['Gustavus\Concert\Config::canEditPage']]]
  ],
  // draft functionality for forwarding requests
  'handleDraftActions' => [
    'handler' => 'Gustavus\Concert\Controllers\DraftController:handleDraftActions'
  ],
  'showDraft' => [
    'handler' => 'Gustavus\Concert\Controllers\DraftController:showDraft'
  ],
  // draft routes
  'drafts' => [
    'route'     => '/drafts/{draftName=.{32}}',
    'handler'   => 'Gustavus\Concert\Controllers\DraftController:renderPublicDraft',
    //'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  'editDraft' => [
    'route'     => '/drafts/edit/{draftName=.{32}}',
    'handler'   => 'Gustavus\Concert\Controllers\DraftController:editPublicDraft',
    'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  'stopEditingPublicDraft' => [
    'handler' => 'Gustavus\Concert\Controllers\DraftController:stopEditingPublicDraft',
  ],
  'addUsersToDraft' => [
    'route'     => '/drafts/addUsers/{draftName=.{32}}',
    'handler'   => 'Gustavus\Concert\Controllers\DraftController:addUsersToDraft',
    'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  // menu functionality
  'menus' => [
    'route'     => '/menus',
    'handler'   => 'Gustavus\Concert\Controllers\MenuController:renderMenu',
    //'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  // autocompletion
  'autocompleteUser' => [
    'route'   => '/autocompleteUser/{value}',
    'handler' => 'Gustavus\Concert\Controllers\MainController:autocompleteUser',
  ],
];