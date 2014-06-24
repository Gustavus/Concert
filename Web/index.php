<?php
use Gustavus\Concourse\Router,
  Gustavus\Concert\Config;

$request = (isset($_GET['request'])) ? $_GET['request'] : '';

echo Router::handleRequest(Config::ROUTING_LOCATION, $request);