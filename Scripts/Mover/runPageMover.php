<?php
/**
 * @package  Concert
 * @subpackage Scripts\Converter
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Scripts\Mover;

use Gustavus\Utility\Debug;

if (PHP_SAPI !== 'cli') {
  echo 'This can only be run from the command line.';
  exit;
}

$pwuData = posix_getpwuid(posix_geteuid());
$username = $pwuData['name'];

if ($username !== 'root') {
  // this should only be run as root.
  echo 'This should only be run as root.';
  exit;
}

$helpText = '  Usage: runPageMover.php filePath destinationPath touchFilesystem
  Options:
    h|-h|--h|help|-help|help: displays this help text.
  Arguments:
    filePath: Path to the file or directory to move.
    destinationPath: Path to the destination file or directory.
    touchFilesystem [true]: Whether to try to perform the move operation on the filesystem or just update configurations.
  Example usage: php runPageMover.php /cis/lib/Concert/Scripts/Converter/testing/index.php /cis/lib/Concert/Scripts/Converter/testing/new/index.php true
';

if (!isset($argv[1])) {
  echo 'Please specify a file path.';
  echo "\n{$helpText}";
  exit;
}
if (!isset($argv[2])) {
  echo 'Please specify a destination path.';
  echo "\n{$helpText}";
  exit;
}
if (in_array($argv[1], ['-h', '--h', 'h', '-help', '--help', 'help'])) {
  echo $helpText;
  exit;
}

if (isset($argv[3])) {
  $touchFilesystem = ($argv[3] === 'false') ? false : true;
} else {
  $touchFilesystem = true;
}

// make sure our document root is set
$_SERVER['DOCUMENT_ROOT'] = '/cis/www';

$moveResult = (new PageMover($argv[1], $argv[2], $touchFilesystem))->move();

$return = sprintf('Called with runPageMover.php %s %s %s', $argv[1], $argv[2], $touchFilesystem);
$return .= Debug::dump($moveResult, true);

echo $return;