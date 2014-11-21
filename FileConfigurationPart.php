<?php
/**
 * @package Concert
 * @author  Billy Visto
 */

namespace Gustavus\Concert;

require_once '/cis/lib/Gustavus/Concert/Assets/Composer/vendor/autoload.php';

use Gustavus\Utility\Set,
  InvalidArgumentException,
  PhpParser\Node\Expr\Array_ as PHPParserArray,
  PhpParser\Parser,
  PhpParser\NodeTraverser,
  PhpParser\PrettyPrinter\Standard as PrettyPrinter,
  PhpParser\Lexer;

/**
 * Object representing an individual piece of the file configuration
 *
 * @package Concert
 * @author  Billy Visto
 */
class FileConfigurationPart
{
  /**
   * The type of the content
   * @var string
   */
  private $contentType;

  /**
   * The current content
   * @var string
   */
  private $content;

  /**
   * The current key in the configuration array
   * @var integer
   */
  private $key;

  /**
   * Parsed php nodes
   *
   * @var string
   */
  private $phpNodes;

  /**
   * Editable php nodes
   *
   * @var array Array of nodes keyed by their index.
   */
  private $editablePHPNodeValues = [];

  /**
   * Original values
   *
   * @var array|string Array for php content type. String otherwise
   */
  private $valuesBeforeEdit = [];

  /**
   * Flag to designate that this item has been edited
   *
   * @var boolean
   */
  private $edited = false;

  /**
   * Sets up the object
   *
   * @param array $params Params to populate the object with
   *
   * @throws  InvalidArgumentException If contentType, content, or key is missing from the params array
   */
  public function __construct(array $params = array())
  {
    if (!isset($params['contentType'], $params['content'], $params['key'])) {
      throw new InvalidArgumentException('The expected keys of contentType, content, and key were not found.');
    }

    $this->contentType = $params['contentType'];
    $this->content     = $params['content'];
    $this->key         = $params['key'];
  }

  /**
   * Checks to see if the current content type is a php type
   *
   * @return boolean
   */
  private function isPHPContent()
  {
    return $this->contentType === Config::PHP_CONTENT_TYPE;
  }

  /**
   * Gets the content type
   *
   * @return string
   */
  public function getContentType()
  {
    return $this->contentType;
  }

  /**
   * Gets the key
   *
   * @return integer
   */
  public function getKey()
  {
    return $this->key;
  }

  /**
   * Gets the content
   *
   * @param boolean $wrapEditableContent Whether we want editable content wrapped in our editable html container
   * @return string
   */
  public function getContent($wrapEditableContent = false)
  {
    if ($this->isPHPContent()) {
      if (Config::ALLOW_PHP_EDITS && in_array($this->getContentType(), Config::$editableContentTypes) && ($wrapEditableContent || $this->edited)) {
        // @todo convert GustavusPrettyPrinter to the new version once we start allowing php edits.
        //$prettyPrinter = new GustavusPrettyPrinter;
        $prettyPrinter = new PrettyPrinter;
        $this->buildEditablePHPNodes($wrapEditableContent);
        return str_replace('    ', '  ', $prettyPrinter->prettyPrint($this->phpNodes));
      }
      return $this->content;
    }

    // not php if we are here.
    if ($wrapEditableContent && in_array($this->getContentType(), Config::$editableContentTypes)) {
      return $this->wrapEditableContent($this->content);
    }
    return $this->content;
  }

  /**
   * Parses php content
   *
   * @return array Array of parsed nodes
   */
  private function parseContent()
  {
    if (!$this->isPHPContent()) {
      return false;
    }
    $parser = new Parser(new Lexer);

    return $parser->parse(sprintf('<?php %s ?>', $this->content));
  }

  /**
   * Builds php configuration parts.
   *   Sets $this->phpNodes to an array of PHPParser nodes or null if not php content
   *
   * @return void
   */
  private function buildPHPNodes()
  {
    if (!$this->isPHPContent()) {
      $this->phpNodes = null;
    } else {
      if (!isset($this->phpNodes)) {
        $phpNodes = $this->parseContent();
        $traverser = new NodeTraverser;
        $this->phpNodes = $traverser->traverse($phpNodes);
      }
    }
  }

  /**
   * Gets phpNodes. Builds them if they don't exist.
   *
   * @return array|null Array of PHPParser nodes or null if not php content
   */
  private function getPHPNodes()
  {
    if (!isset($this->phpNodes)) {
      $this->phpNodes = $this->buildPHPNodes();
    }
    return $this->phpNodes;
  }

  /**
   * Wraps editable content into a span with class of editable. Span includes data attribute of data-index to identify itself.
   *
   * @param  string $content content to wrap
   * @param  integer $subKey  sub key off of the current key
   * @param  integer $subSubKey  sub key off of the sub key
   * @return string
   */
  private function wrapEditableContent($content, $subKey = null, $subSubKey = null)
  {
    $index = $this->buildEditableIndex($subKey, $subSubKey);

    $closingDiv = sprintf('</div>%s', Config::EDITABLE_DIV_CLOSING_IDENTIFIER);
    $openingDiv = sprintf('<div class="editable" data-index="%s">', $index);
    if (!self::isPHPContent()) {
      // we need to look for un-matched tags
      $offsetOffset = 0;
      $offsets = $this->getUnMatchedOffsets($content);

      if (!empty($offsets['closing'])) {
        // we have unMatched closing tags
        // we need to insert our editable div after the last one.
        // closing divs are sorted in reverse order they are found, so we don't need to do any sorting here.
        foreach ($offsets['closing'] as $closingOffset) {
          // set this before using it since we want to insert things after it.
          $offsetOffset += $closingOffset['length'];
          $content = sprintf(
              '%s%s%s',
              substr($content, 0, $offsetOffset + $closingOffset['offset']),
              $openingDiv,
              substr($content, $offsetOffset + $closingOffset['offset'])
          );
          // add the openingDiv into our offset and remove the current offset length since it is already included in the string.
          $offsetOffset += strlen($openingDiv) - $closingOffset['length'];
          // set our opening div to an empty string so we don't insert it again
          $openingDiv = '';
          break;
        }
      }

      if (!empty($offsets['opening'])) {
        // we have unMatched opening tags
        // we need to insert our ending editable div before the first one.
        // closing divs are sorted in the order they are found, so we don't need to do any sorting here.
        foreach ($offsets['opening'] as $openingOffset) {
          $content = sprintf(
              '%s%s%s',
              substr($content, 0, $offsetOffset + $openingOffset['offset']),
              $closingDiv,
              substr($content, $offsetOffset + $openingOffset['offset'])
          );
          // set this after we use it since we want to insert things before it.
          $offsetOffset += $openingOffset['length'] + strlen($closingDiv);
          // set our closing div to an empty string so we don't insert it again
          $closingDiv = '';
          break;
        }
      }
    }

    return sprintf('%s%s%s', $openingDiv, trim($content), $closingDiv);
  }

  /**
   * Gets offsets for un-matched opening and closing tags
   *
   * @param  string $content Content to search for un-matched tags
   * @return array Array with keys of opening and closing and values are an array of their respective offsets (offset) and string length (length).
   *   Opening array will be sorted in the order they are found.
   *   Closing array will be sorted in reverse order they exist in the content.
   */
  private function getUnMatchedOffsets($content)
  {
    // we need to make sure all divs have been closed otherwise our editable div will get ruined.
    preg_match_all('`(?P<closing></[^>]+>)|(?P<selfclosing><[^!>]+/>)|(?P<opening><[^!>]+>)`x', $content, $matches, PREG_OFFSET_CAPTURE);

    // flattener that changes the values of the array to the first index. (The second index will be the offset from the PREG_OFFSET_CAPTURE option)
    $flattener = function($value) {
      return $value[0];
    };

    if (isset($matches['opening'])) {
      $opening = array_filter($matches['opening']);
      $flattenedOpening = array_filter(array_map($flattener, $opening));
    } else {
      $opening = [];
    }
    if (isset($matches['closing'])) {
      $closing = array_filter($matches['closing']);
      $flattenedClosing = array_filter(array_map($flattener, $closing));
    } else {
      $closing = [];
    }

    $unMatchedOpening = $this->findUnMatchedOpeningTags($flattenedOpening, $flattenedClosing);
    $unMatchedOpeningOffsets = [];
    if (!empty($unMatchedOpening)) {
      // make sure we are sorted by keys
      ksort($unMatchedOpening);
      // we have unmatched opening tags
      foreach ($unMatchedOpening as $key => $unMatched) {
        $unMatchedOpeningOffsets[] = [
          'offset' => $opening[$key][1],
          'length' => strlen($unMatched),
        ];
      }
    }

    $unMatchedClosing = $this->findUnMatchedClosingTags($flattenedOpening, $flattenedClosing);
    $unMatchedClosingOffsets = [];
    if (!empty($unMatchedClosing)) {
      // make sure we are sorted by keys in reverse order
      krsort($unMatchedClosing);
      // we have unmatched closing tags
      foreach ($unMatchedClosing as $key => $unMatched) {
        $unMatchedClosingOffsets[] = [
          'offset' => $closing[$key][1],
          'length' => strlen($unMatched),
        ];
      }
    }

    return [
      'opening' => $unMatchedOpeningOffsets,
      'closing' => $unMatchedClosingOffsets,
    ];
  }


  /**
   * Finds any keys that are less than the specified key
   *
   * @param  array $array Array to filter
   * @param  integer $key   Key we want our found keys to be less than.
   * @return array
   */
  private function findKeysLessThanKey(Array $array, $key) {
    $newArray = [];
    foreach ($array as $arrKey => $arrValue) {
      if ($arrKey < $key) {
        $newArray[$arrKey] = $arrValue;
      } else {
        break;
      }
    }
    return $newArray;
  }

  /**
   * Finds any keys that are greater than the specified key
   *
   * @param  array $array Array to filter
   * @param  integer $key   Key we want our found keys to be greater than.
   * @return array
   */
  private function findKeysGreaterThanKey(Array $array, $key) {
    $newArray = [];
    foreach ($array as $arrKey => $arrValue) {
      if ($arrKey > $key) {
        $newArray[$arrKey] = $arrValue;
      }
    }
    return $newArray;
  }

  /**
   * Finds any opening tags that don't have a closing tag to go along with them
   *
   * @param  array  $opening Array of opening tags
   * @param  array  $closing Array of closing tags
   * @return array  Array of opening tags that aren't matched
   */
  private function findUnMatchedOpeningTags(Array $opening, Array $closing)
  {
    // let's try looking at our first closing tag and finding anything that matches
    foreach ($closing as $key => $closingTag) {
      preg_match('`</(\w+)`', $closingTag, $tagMatch);
      if (!isset($tagMatch[1])) {
        // tag not found
        continue;
      }
      $tagMatch = $tagMatch[1];
      $openingPriorToCurr = $this->findKeysLessThanKey($opening, $key);
      krsort($openingPriorToCurr);
      foreach ($openingPriorToCurr as $openingKey => $openingTag) {
        if (preg_match(sprintf('`<%s`', $tagMatch), $openingTag)) {
          // we have a matching tag.
          // now let's unset this key from our original opening tags.
          unset($opening[$openingKey]);
          break;
        }
      }
    }
    return $opening;
  }

  /**
   * Finds any closing tags that don't have an opening tag to go along with them
   *
   * @param  array  $opening Array of opening tags
   * @param  array  $closing Array of closing tags
   * @return array  Array of closing tags that aren't matched
   */
  private function findUnMatchedClosingTags(Array $opening, Array $closing)
  {
    // let's try looking at our first closing tag and finding anything that matches
    foreach ($opening as $key => $openingTag) {
      preg_match('`<(\w+)`', $openingTag, $tagMatch);
      if (!isset($tagMatch[1])) {
        // tag not found
        continue;
      }
      $tagMatch = $tagMatch[1];
      $closingAfterCurr = $this->findKeysGreaterThanKey($closing, $key);
      foreach ($closingAfterCurr as $closingKey => $closingTag) {
        if (preg_match(sprintf('`</%s`', $tagMatch), $closingTag)) {
          // we have a matching tag.
          // now let's unset this key from our original closing tags.
          unset($closing[$closingKey]);
          break;
        }
      }
    }
    return $closing;
  }

  /**
   * Builds the index for the specified key combo
   *
   * @param  integer $subKey  sub key off of the current key
   * @param  integer $subSubKey  sub key off of the sub key
   * @return string
   */
  private function buildEditableIndex($subKey = null, $subSubKey = null)
  {
    $index = (string) $this->key;
    if ($subKey !== null) {
      $index .= '-' . $subKey;
    }
    if ($subSubKey !== null) {
      $index .= '-' . $subSubKey;
    }
    return $index;
  }

  /**
   * Builds editable phpNodes. Wraps their values in a span with class of editable
   *
   * @param  boolean $wrapEditable Whether we want to wrap editable nodes or not.
   * @return void
   */
  private function buildEditablePHPNodes($wrapEditable = false)
  {
    if (!Config::ALLOW_PHP_EDITS) {
      return;
    }
    if (!isset($this->phpNodes)) {
        $this->buildPHPNodes();
    }
    foreach ($this->phpNodes as $key => &$node) {
      if (in_array($node->getType(), Config::$editablePHPNodeTypes)) {
        // we can edit this node
        if ($node->expr instanceof PHPParserArray) {
          // we need to look into the array to find our values
          foreach ($node->expr->items as $subKey => &$item) {
            if (in_array($item->value->getType(), Config::$editablePHPExprTypes)) {
              // add this to our editable array.
              $this->editablePHPNodeValues[$this->buildEditableIndex($key, $subKey)] = &$item->value->value;

              if ($wrapEditable) {
                // wrap our value for editing
                $item->value->value = $this->wrapEditableContent($item->value->value, $key, $subKey);
              }
            }
          }
        } else if (in_array($node->expr->getType(), Config::$editablePHPExprTypes)) {
          // we can edit this expr
          $this->editablePHPNodeValues[$this->buildEditableIndex($key)] = &$node->expr->value;

          if ($wrapEditable) {
            // wrap our value for editing
            $node->expr->value = $this->wrapEditableContent($node->expr->value, $key);
          }
        }
      }
    }
  }

  /**
   * Sanitizes contents. Removes php and scripts.
   *
   * @param  string $content Content to sanitize
   * @return string
   */
  private static function sanitize($content)
  {
    $separated = FileManager::separateContentByType($content);

    if (isset($separated[Config::OTHER_CONTENT_TYPE])) {
      $filtered = array_filter($separated[Config::OTHER_CONTENT_TYPE], function($value) {
          return preg_match('`^\s+$`', $value) !== 1;
      });
      return implode("\n", $filtered);
    } else {
      return '';
    }
  }

  /**
   * Edits the node's value specified by $index with $newContent.
   *
   * @param  string $index      Identifier for this node
   * @param  string $newContent Content to replace with
   * @return boolean True on success. False on failure.
   */
  public function editValue($index, $newContent)
  {
    // sanitize our contents
    $newContent = self::sanitize($newContent);

    if (!$this->isPHPContent() && in_array($this->getContentType(), Config::$editableContentTypes)) {
      // non php content is editable
      // save our original value before editing
      $this->valuesBeforeEdit = $this->content;

      //Now we need to see if we have un-matched tags. We don't want to destroy these since these aren't sent when editing a page.
      $startReplacementOffset = 0;
      $endReplacementOffset   = strlen($this->content);

      $offsets = $this->getUnMatchedOffsets($this->content);

      if (!empty($offsets['closing'])) {
        // we have unMatched closing tags
        // closing divs are sorted in reverse order they are found, so we don't need to do any sorting here.
        foreach ($offsets['closing'] as $closingOffset) {
          // we want to start our replacement after the un-matched closing tag
          $startReplacementOffset = $closingOffset['offset'] + $closingOffset['length'];
        }
      }

      if (!empty($offsets['opening'])) {
        // we have unMatched opening tags
        // closing divs are sorted in the order they are found, so we don't need to do any sorting here.
        foreach ($offsets['opening'] as $openingOffset) {
          // we want to end our replacement before the un-matched opening tag.
          $endReplacementOffset = $openingOffset['offset'];
        }
      }

      $this->content = sprintf(
          '%s%s%s',
          substr($this->content, 0, $startReplacementOffset),
          $newContent,
          substr($this->content, $endReplacementOffset)
      );
      $this->edited = true;
      return true;
    }

    if (!Config::ALLOW_PHP_EDITS || !in_array($this->getContentType(), Config::$editableContentTypes)) {
      return false;
    }

    // @todo When we enable php editing, we want to make sure this content doesn't get thrown in double quotes and get evaluated.
    // make sure our editable nodes are built.
    if (empty($this->editablePHPNodeValues)) {
      $this->buildEditablePHPNodes();
    }
    if (isset($this->editablePHPNodeValues[$index])) {

      // save our original value before editing.
      $this->valuesBeforeEdit[$index] = $this->editablePHPNodeValues[$index];
      // edit
      $this->editablePHPNodeValues[$index] = $newContent;
      $this->edited = true;
      return true;
    } else {
      // this node doesn't appear to be editable.
      // @todo Throw exception or display a message saying that this node isn't editable.
      return false;
    }
  }

  /**
   * Gets the original values for items that have been edited
   *
   * @return array|string|null Array for php content edits. String otherwise. Null if nothing has been edited
   */
  public function getValueBeforeEdit()
  {
    if (empty($this->valuesBeforeEdit)) {
      return null;
    }
    return $this->valuesBeforeEdit;
  }
}