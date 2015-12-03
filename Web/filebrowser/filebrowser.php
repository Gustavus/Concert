<?php
use Gustavus\Template\Config as TemplateConfig,
  Gustavus\Concert\Config,
  Gustavus\Concert\Utility,
  Gustavus\Concert\PermissionsManager,
  Gustavus\Resources\Resource,
  Gustavus\Concourse\RoutingUtil,
  Gustavus\TwigFactory\TwigFactory;

echo TwigFactory::renderTwigFileSystemTemplate(
    '/cis/lib/Gustavus/Concert/Views/filebrowser.js.twig',
    [
      'fileTreeScript' => RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'newPageMenuFiles', ['fileTree' => 'fromFile'], Config::WEB_DIR),
      'jQueryVersion'  => TemplateConfig::JQUERY_VERSION,
      'templateCSSVersion' => TemplateConfig::CSS_VERSION,
      'siteBase'       => PermissionsManager::findParentSiteForFile(Utility::removeDocRootFromPath($_GET['filePath'])),
    ]
);