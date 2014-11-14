<?php

if (!isset($argv[1])) {
  echo 'Please specify a file path.';
  exit;
}

if (PHP_SAPI !== 'cli') {
  echo 'This can only be run from the command line.';
  exit;
}

$pwu_data = posix_getpwuid(posix_geteuid());
$username = $pwu_data['name'];

if ($username !== 'root') {
  // this should only be run as root. Other users won't have permissions to save the file properly.
  echo 'This should only be run as root.';
  exit;
}
// make sure our document root is set
$_SERVER['DOCUMENT_ROOT'] = '/cis/www';

if (isset($argv[2]) && !isset($_SERVER['HOSTNAME'])) {
  // make sure we have a host name set in case it doesn't get set automatically
  $_SERVER['HOSTNAME'] = $argv[2];
}

// Attempt to publish this file.
echo (new \Gustavus\Concert\FileManager($username, $argv[1]))->publishFile();