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
  'moshRequest' => [
    'route'   => '/',
    'handler' => 'Gustavus\Concert\Controllers\MainController:handleMoshRequest',
    //'visibleTo' => ['Concert', ['all', 'callbacks' => ['Gustavus\Concert\Config::canEditPage']]]
  ],
  // mosh action for forwarding
  'mosh' => [
    'handler' => 'Gustavus\Concert\Controllers\MainController:mosh',
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
    // we aren't using colorbox or anything to load the menu. Now.
    //'route'     => '/menus',
    'handler'   => 'Gustavus\Concert\Controllers\MenuController:renderMenu',
  ],
  'newPageMenu' => [
    'route'     => '/menus/newPage',
    'handler'   => 'Gustavus\Concert\Controllers\MenuController:renderNewPageForm',
    'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  'newPageMenuFiles' => [
    'route'     => '/menus/newPage/{fileTree=toFile|fromFile}',
    'handler'   => 'Gustavus\Concert\Controllers\MenuController:renderNewPageForm',
    'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  // autocompletion
  'autocompleteUser' => [
    'route'   => '/autocompleteUser/{value}',
    'handler' => 'Gustavus\Concert\Controllers\MainController:autocompleteUser',
  ],
  // site nav actions
  'handleSiteNavActions' => [
    'handler' => 'Gustavus\Concert\Controllers\SiteNavController:handleSiteNavActions',
  ],
  // email actions
  'emailSharedDraft' => [
    'handler' => 'Gustavus\Concert\Controllers\EmailController:notifyUsersOfSharedDraft',
  ],
  'emailSharedDraftSaved' => [
    'handler' => 'Gustavus\Concert\Controllers\EmailController:notifyOwnerOfDraftEdit',
  ],
  'emailPendingDraft' => [
    'handler' => 'Gustavus\Concert\Controllers\EmailController:notifyPublishersOfPendingDraft',
  ],
  'emailPendingDraftPublished' => [
    'handler' => 'Gustavus\Concert\Controllers\EmailController:notifyPendingDraftOwnerOfPublish',
  ],
  'emailPendingDraftRejected' => [
    'handler' => 'Gustavus\Concert\Controllers\EmailController:notifyPendingDraftOwnerOfRejection',
  ],
  // FileManager requests
  'fileManagerRequest' => [
    'route'   => '/filemanager/{request}',
    'handler' => 'Gustavus\Concert\Controllers\MainController:handleFileManagerRequest',
  ],
  'fileManagerResources' => [
    'route'   => '/filemanager/{request=*}',
    'handler' => 'Gustavus\Concert\Controllers\MainController:handleFileManagerRequest',
  ],
  // used for building urls
  'fileManager' => [
    'route'   => '/filemanager/{request}',
    'handler' => null,
  ],
  'recentActivity' => [
    'route'     => '/recentActivity',
    'handler'   => 'Gustavus\Concert\Controllers\MainController:viewRecentActivity',
    'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
];