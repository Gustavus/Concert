<?php
use Gustavus\Concourse\Router,
  Gustavus\Concert\Config,
  Gustavus\Utility\Jsonizer,
  Gustavus\Utility\PageUtil,
  Gustavus\Config\Config as GACConfig;

if (GACConfig::isProductionBackup()) {
  // we don't want people to edit or do anything if we are working on our backup server
  PageUtil::renderAccessDenied();
  exit;
}

$request = (isset($_GET['request'])) ? $_GET['request'] : '';

$response = Router::handleRequest(Config::ROUTING_LOCATION, $request);

if (is_array($response)) {
  echo Jsonizer::toJSON($response);
} else {
  echo $response;
}