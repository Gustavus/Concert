<?php
/**
 * This is a custom plugin to allow people to browse files on the filesystem for creating links
 * @package Concert
 * @subpackage FileBrowser
 * @author Billy Visto
 */
use Gustavus\Template\Config as TemplateConfig,
  Gustavus\Concert\Config,
  Gustavus\Concert\Utility,
  Gustavus\Concert\PermissionsManager,
  Gustavus\Resources\Resource,
  Gustavus\Concourse\RoutingUtil,
  Gustavus\TwigFactory\TwigFactory;

// render our view that populates the tinymce window
echo TwigFactory::renderTwigFileSystemTemplate(
    '/cis/lib/Gustavus/Concert/Views/filebrowser.js.twig',
    [
      'fileTreeScript' => RoutingUtil::buildUrl(Config::ROUTING_LOCATION, 'newPageMenuFiles', ['fileTree' => 'fromFile'], Config::WEB_DIR),
      'jQueryVersion'  => TemplateConfig::JQUERY_VERSION,
      'templateCSSVersion' => TemplateConfig::CSS_VERSION,
      'siteBase'       => PermissionsManager::findParentSiteForFile(Utility::removeDocRootFromPath($_GET['filePath'])),
    ]
);