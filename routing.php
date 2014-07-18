<?php
/**
 * @package Concert
 * @author  Billy Visto
 */

use Gustavus\Concert\Config,
  Gustavus\Gatekeeper\Gatekeeper;

return [
  'edit' => [
    'route'   => '/edit',
    'handler' => 'Gustavus\Concert\Controllers\MainController:edit',
    //'visibleTo' => ['Concert', ['all', 'callbacks' => ['Gustavus\Concert\Config::canEditPage']]]
  ],
  'mosh' => [
    'route'   => '/',
    'handler' => 'Gustavus\Concert\Controllers\MainController:handleMoshRequest',
    //'visibleTo' => ['Concert', ['all', 'callbacks' => ['Gustavus\Concert\Config::canEditPage']]]
  ],
  'handleDraftActions' => [
    'handler' => 'Gustavus\Concert\Controllers\DraftController:handleDraftActions'
  ],
  'showDraft' => [
    'handler' => 'Gustavus\Concert\Controllers\DraftController:showDraft'
  ],
  'drafts' => [
    'route'     => '/drafts/{draftName}',
    'handler'   => 'Gustavus\Concert\Controllers\DraftController:renderPublicDraft',
    //'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  'editDraft' => [
    'route'     => '/drafts/edit/{draftName}',
    'handler'   => 'Gustavus\Concert\Controllers\DraftController:editPublicDraft',
    'visibleTo' => ['Concert', [Gatekeeper::PERMISSION_ALL]],
  ],
  'stopEditingPublicDraft' => [
    'handler' => 'Gustavus\Concert\Controllers\DraftController:stopEditingPublicDraft',
  ],
];