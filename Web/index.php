<?php
use Gustavus\Concourse\Router,
  Gustavus\Concert\Config,
  Gustavus\Utility\Jsonizer;

$request = (isset($_GET['request'])) ? $_GET['request'] : '';

$response = Router::handleRequest(Config::ROUTING_LOCATION, $request);

if (is_array($response)) {
  echo Jsonizer::toJSON($response);
} else {
  echo $response;
}