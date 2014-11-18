<?php
/**
 * @package  Concert
 * @subpackage Scripts
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Scripts;

use Gustavus\Concert\Utility,
  RuntimeException,
  DateTime;

if (PHP_SAPI !== 'cli') {
  echo 'This can only be run from the command line.';
  exit;
}

$helpText = '  Usage: runTemplateConverter.php filePath saveBackup
  Options:
    h|-h|--h|help|-help|help: displays this help text.
  Arguments:
    filePath: Path to the file to convert.
    saveBackup [true]: Whether to make a backup of the file or not. Saves the file as file-bkup-m-d-Y.php.
  Example usage: php runTemplateConverter.php /cis/lib/Concert/Scripts/testing/index.php true
';

if (!isset($argv[1])) {
  echo 'Please specify a file path.';
  echo "\n{$helpText}";
  exit;
}
if (in_array($argv[1], ['-h', '--h', 'h', '-help', '--help', 'help'])) {
  echo $helpText;
  exit;
}

if (isset($argv[2])) {
  $backup = ($argv[2] === 'false') ? false : true;
} else {
  $backup = true;
}

$pwu_data = posix_getpwuid(posix_geteuid());
$username = $pwu_data['name'];

if ($username !== 'root') {
  // this should only be run as root. Other users won't have permissions to save the file properly.
  echo 'This should only be run as root.';
  exit;
}

// @todo Finish this. Need a script that loops through all php files in a directory and passes them to this script.
$filePath = $argv[1];
$newPageContents = (new TemplateConverter($filePath))->convert();
if (!$newPageContents) {
  echo 'false';
  return;
}

$group = Utility::getGroupForFile($filePath);

if (file_exists($filePath)) {
  $path = $filePath;
} else {
  // the file doesn't exist, but we want to know who the owner needs to be, so let's use the owner of the directory
  $dir = dirname($filePath);
  while (!is_dir($dir)) {
    $dir = dirname($dir);
  }
  $path = $dir;
}

$uid      = fileowner($path);
$pwu_data = posix_getpwuid($uid);
$owner    = $pwu_data['name'];

if ($backup) {
  // save a backup file.
  $backupFilePath = str_replace('.php', '-bkup-' . (new DateTime())->format('m-d-Y') . '.php', $filePath);
  if (!copy($filePath, $backupFilePath)) {
    throw new RuntimeException(sprintf('A backup of %s could not be created', $filePath));
  }
  // make sure we maintain the original owner and group
  chgrp($backupFilePath, $group);
  chown($backupFilePath, $owner);
}

if (!file_put_contents($filePath, $newPageContents)) {
  throw new RuntimeException(sprintf('The file %s could not be saved', $filePath));
}

// make sure we maintain the original owner and group
chgrp($filePath, $group);
chown($filePath, $owner);

echo 'true';
return;