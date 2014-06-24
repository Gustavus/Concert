<?php
/**
 * @package Concert
 * @author  Billy Visto
 */

namespace Gustavus\Concert;

use Gustavus\Concert\FileConfigurationPart,
  Gustavus\Concert\Config,
  UnexpectedValueException;

/**
 * File configuration of the file we are trying to edit
 *
 * @package Concert
 * @author  Billy Visto
 */
class FileConfiguration
{
  /**
   * Array of FileConfigurationPart objects
   * @var array
   */
  private $fileConfigurationParts = [];

  /**
   * Array of our current configuration
   * @var array
   */
  private $fileConfigurationArray;

  /**
   * Array of keys of edited FileConfigurationParts
   * @var array
   */
  private $fileConfigurationPartsEdited = [];

  /**
   * Object constructor
   * @param array $fileConfigurationArray fileConfigurationArray to build off of
   */
  public function __construct($fileConfigurationArray)
  {
    $this->fileConfigurationArray = $fileConfigurationArray;
    $this->buildFileConfigurationParts();
  }

  /**
   * Builds the FileConfigurationPart objects from the current configuration
   *
   * @throws  UnexpectedValueException If the configuration part already exists in our fileConfigurationParts array
   * @return void
   */
  private function buildFileConfigurationParts()
  {
    foreach (Config::$contentTypes as $contentType) {
      if (!isset($this->fileConfigurationArray[$contentType])) {
        continue;
      }
      foreach ($this->fileConfigurationArray[$contentType] as $key => $content) {
        if (!empty($content)) {
          if (isset($this->fileConfigurationParts[$key])) {
            throw new UnexpectedValueException('The configuration part already exists.');
          }
          $this->fileConfigurationParts[$key] = new FileConfigurationPart(['contentType' => $contentType, 'key' => $key, 'content' => $content]);
        }
      }
    }
  }

  /**
   * Gets the FileConfigurationParts
   *
   * @return array
   */
  public function getFileConfigurationParts()
  {
    if (!isset($this->fileConfigurationParts)) {
      $this->buildFileConfigurationParts();
    }
    return $this->fileConfigurationParts;
  }

  /**
   * Builds a file from the FileConfigurationPart objects.
   *
   * @param boolean $wrapEditableContent Whether we want to build the file for for editing or not.
   * @return string
   */
  public function buildFile($wrapEditableContent = false)
  {
    $file = '';
    $partsCount = count($this->fileConfigurationParts);
    for ($i = 0; $i < $partsCount;) {
      $configurationPart = $this->fileConfigurationParts[$i];
      // increment here so we don't have to do any extra math when we check to see if we are the last part below.
      ++$i;
      if ($configurationPart->getContentType() === Config::PHP_CONTENT_TYPE) {

        if ($i === $partsCount) {
          // last piece and we are php. Don't add the php closing tag.
          $template = '<?php%s';
        } else {
          $template = '<?php%s?>';
        }
        $file .= sprintf($template, $configurationPart->getContent($wrapEditableContent));

      } else {
        $file .= $configurationPart->getContent($wrapEditableContent);
      }
    }
    return $file;
  }

  /**
   * Takes a full index identifier and gives us the part key.
   *
   * @param  string $index Identifier built by fileConfigurationPart key-subKey-subSubKey
   * @return string
   */
  private static function getFilePartKey($index)
  {
    preg_match('`^(\d+)-?`', $index, $matches);
    return isset($matches[1]) ? $matches[1] : null;
  }

  /**
   * Edits the file with the specified contents
   *
   * @param  array  $edits Associative array keyed by fileConfigurationPart index
   * @return boolean True on success false on failure.
   */
  public function editFile($edits)
  {
    $edited = false;

    foreach ($edits as $index => $newContent) {
      $key = self::getFilePartKey($index);
      if (isset($this->getFileConfigurationParts()[$key])) {
        $partEdited = $this->getFileConfigurationParts()[$key]->editValue($index, $newContent);
        if ($partEdited) {
          $this->fileConfigurationPartsEdited[$key] = true;
          $edited = true;
        }
      }
    }
    return $edited;
  }

  /**
   * Returns an array of keys that have been edited
   *
   * @return array
   */
  public function getFileConfigurationPartsEdited()
  {
    return $this->fileConfigurationPartsEdited;
  }

  /**
   * Gets the ConfigurationPart for a specific edited key.
   *   This key must be in $this->fileConfigurationPartsEdited
   *
   * @param  string $key Identifer for the part to get
   * @return FileConfigurationPart|null Null if key hasn't been edited
   */
  public function getEditedFileConfigurationPart($key)
  {
    if (isset($this->fileConfigurationPartsEdited[$key])) {
      return $this->fileConfigurationParts[$key];
    }
    return null;
  }
}