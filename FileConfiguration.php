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
   * File size of the current file
   * @var integer
   */
  private $fileSize;

  /**
   * Path of the file this configuration represents
   * @var integer
   */
  private $filePath;

  /**
   * Array of keys of edited FileConfigurationParts
   * @var array
   */
  private $fileConfigurationPartsEdited = [];

  /**
   * Object constructor
   *
   * @param array $fileConfigurationArray fileConfigurationArray to build off of
   * @param string $filePath Path of the file we are representing
   * @param integer $fileSize Size of the file
   */
  public function __construct($fileConfigurationArray, $filePath = null, $fileSize = null)
  {
    $this->fileConfigurationArray = $fileConfigurationArray;
    $this->fileSize = $fileSize;
    $this->filePath = $filePath;
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
          $this->fileConfigurationParts[$key] = new FileConfigurationPart(['contentType' => $contentType, 'key' => $key, 'content' => $content], $this->filePath);
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
   * @param boolean $adjustPHPForExecution Whether to convert php to be able to be executed from a new location
   * @return string
   */
  public function buildFile($wrapEditableContent = false, $adjustPHPForExecution = false)
  {
    $file = '';
    if (empty($this->fileSize)) {
      // no fileSize was specified. Default indenting html to true.
      $indentHTML = true;
    } else {
      $indentHTML = $this->fileSize < Config::PERFORMANCE_HIT_FILE_SIZE;
    }

    $partsCount = count($this->fileConfigurationParts);
    for ($i = 0; $i < $partsCount;) {
      $configurationPart = $this->fileConfigurationParts[$i];
      // increment here so we don't have to do any extra math when we check to see if we are the last part below.
      ++$i;
      if ($configurationPart->getContentType() === Config::PHP_CONTENT_TYPE) {
        if ($i === 1) {
          $newLine = '';
        } else {
          $newLine = "\n";
        }
        if ($i === $partsCount) {
          // last piece and we are php. Don't add the php closing tag.
          $template = '%s<?php%s';
        } else {
          $template = "%s<?php%s?>\n";
        }
        $file .= sprintf($template, $newLine, $configurationPart->getContent($wrapEditableContent, $indentHTML, $adjustPHPForExecution));

      } else {
        $file .= sprintf('%s%s%s',
            ($i === 1 ? '' : "\n"),
            $configurationPart->getContent($wrapEditableContent, $indentHTML, $adjustPHPForExecution),
            ($i === $partsCount ? '' : "\n")
        );
      }
    }
    // make sure we don't have more than 3 line breaks. This will avoid growing whitespace after every edit depending on the edits. (Non edited content will maintain it's whitespace, whereas edited content will not)
    $file = preg_replace('`\v{3,}`', "\n\n", $file);
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