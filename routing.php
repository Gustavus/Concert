<?php
/**
 * @package Concert
 * @author  Billy Visto
 */

use Gustavus\Concert\Config,
  Gustavus\Gatekeeper\Gatekeeper;

return [
  'dashboard' => [
    'route'   => '/',
    'handler' => 'Gustavus\Concert\Controllers\MainController:dashboard',
    'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  'globalDashboard' => [
    'route'     => '/{dashboardType=global}',
    'handler'   => 'Gustavus\Concert\Controllers\MainController:dashboard',
    'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  'listAllSites' => [
    'route'     => '/sites',
    'handler'   => 'Gustavus\Concert\Controllers\MainController:listAllSites',
    'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  'moshRequest' => [
    'route'   => '/mosh',
    'handler' => 'Gustavus\Concert\Controllers\MainController:handleMoshRequest',
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
  // Permissions
  'sites' => [
    'route'     => '/permissions/sites',
    'handler'   => 'Gustavus\Concert\Controllers\PermissionsController:showSites',
    'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  'editSite' => [
    'route'     => '/permissions/editSite/{site=\d+}',
    'handler'   => 'Gustavus\Concert\Controllers\PermissionsController:editSite',
    'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  'deleteSite' => [
    'route'     => '/permissions/deleteSite/{site=\d+}',
    'handler'   => 'Gustavus\Concert\Controllers\PermissionsController:deleteSite',
    'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  'createSite' => [
    'route'     => '/permissions/createSite',
    'handler'   => 'Gustavus\Concert\Controllers\PermissionsController:createSite',
    'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  'userSearch' => [
    'route'     => '/permissions/userSearch',
    'handler'   => 'Gustavus\Concert\Controllers\PermissionsController:userSearch',
    'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
];