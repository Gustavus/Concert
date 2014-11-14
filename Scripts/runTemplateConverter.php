<?php
/**
 * @package  Concert
 * @subpackage Scripts
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Scripts;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
  echo 'This can only be run from the command line.';
  exit;
}

if (!isset($argv[1])) {
  echo 'Please specify a file path.';
  exit;
}

$pwu_data = posix_getpwuid(posix_geteuid());
$username = $pwu_data['name'];

if ($username !== 'root') {
  // this should only be run as root. Other users won't have permissions to save the file properly.
  echo 'This should only be run as root.';
  exit;
}

// @todo Finish this. Need a script that loops through all php files in a directory and passes them to this script.
$newPageContents = (new TemplateConverter($argv[1]))->convert();