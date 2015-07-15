<?php
/**
 * @package Concert
 * @author  Billy Visto
 */

namespace Gustavus\Concert;

require_once '/cis/lib/Gustavus/Concert/Assets/Composer/vendor/autoload.php';

use Gustavus\Utility\Set,
  Gustavus\Gatekeeper\Gatekeeper,
  Gustavus\Utility\Debug,
  InvalidArgumentException,
  PhpParser\Node\Expr\Array_ as PHPParserArray,
  PhpParser\Parser,
  PhpParser\NodeTraverser,
  PhpParser\Lexer,
  PhpParser\Error as ParserError;

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
   * Elements that can't have any contents in them, and don't self close in HTML5. (XHTML's space-slash.)
   *   ie. Breaks are <br /> in XHTML, but <br> in HTML
   *
   * @var array
   */
  private static $voidElements = [
    'br',
    'hr',
    'img',
    'input',
    'link',
    'meta',
    'source',
    'area',
    'base',
    'col',
    'embed',
    'keygen',
    'menuitem',
    'param',
    'track',
    'wbr',
  ];

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
   * @param boolean $indentHTML Whether to indent HTML or not
   * @return string
   */
  public function getContent($wrapEditableContent = false, $indentHTML = true)
  {
    if ($this->isPHPContent()) {
      if ($wrapEditableContent || (Config::ALLOW_PHP_EDITS && $this->edited)) {
        // @todo convert GustavusPrettyPrinter to support whitespace once we start allowing php edits.
        $prettyPrinter = new GustavusPrettyPrinter;
        $wrapEditablePHPContent = Config::ALLOW_PHP_EDITS;
        if ($wrapEditableContent) {
          // we need to remove nodes that have already been defined
          $removed = $this->removeAlreadyDefinedPHPNodes();
        } else {
          $removed = false;
        }
        if (Config::ALLOW_PHP_EDITS) {
          $this->buildEditablePHPNodes($wrapEditablePHPContent);
        }
        if (Config::ALLOW_PHP_EDITS || $removed) {
          // we have removed contents, or may have wrapped editable contents
          $newContent = str_replace('    ', '  ', $prettyPrinter->prettyPrint($this->phpNodes));
          if (preg_match('`^(\v+)`', $this->content, $matches) === 1 && preg_match('`^\v`', $newContent) !== 1) {
            // the pretty printer removed a vertical whitespace from the beginning of the content.
            $newContent = (isset($matches[1])) ? $matches[1] . $newContent : "\n{$newContent}";
          }
          if (preg_match('`(\v+)$`', $this->content, $matches) === 1 && preg_match('`\v$`', $newContent) !== 1) {
            // the pretty printer removed a vertical whitespace from the end of the content.
            $newContent = (isset($matches[1])) ? $newContent . $matches[1]: "{$newContent}\n";
          }
          // remove any horizontal whitespace that exists right before any vertical whitespace.
          return $newContent;
        }
      }
      return $this->content;
    }

    // not php if we are here.
    if ($wrapEditableContent && in_array($this->getContentType(), Config::$editableContentTypes)) {
      return $this->wrapEditableContent($this->content);
    }
    if ($indentHTML) {
      return self::indentHTML($this->content);
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

    if (strpos($this->content, '=') === 0) {
      // the contents are using the shorthand echo. We need to change the template we use for building the php piece.
      $template = '<?%s?>';
    } else {
      $template = '<?php %s ?>';
    }

    try {
      $phpNodes = $parser->parse(sprintf($template, $this->content));
    } catch (ParserError $e) {
      $message = $e->getRawMessage();
      if (strpos($message, 'unexpected EOF') === false && strpos($message, 'expecting EOF') === false) {
        throw $e;
      }
      // something happened while trying to parse this content. It is probably some embedded php that closes a tag in a later-on php block.
      // i.e. <p><\?php if (true) { \?\>here<\?php } else { \?\>arst<\?php } \?\>
      // let's just parse the content, and not throw it in php tags so the parser will see it as a string.
      $phpNodes = $parser->parse($this->content);
    }
    return $phpNodes;
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

    if (self::isPHPContent()) {
      // we don't have to do anything with php content
      return sprintf('%s%s%s', $openingDiv, trim($content), $closingDiv);
    }

    // not php content
    $splitContents = self::separateEditableContents($content);

    return sprintf('%s%s%s%s%s', $splitContents['preEditableContents'], $openingDiv, $splitContents['editableContents'], $closingDiv, $splitContents['postEditableContents']);
  }

  /**
   * Separates editable contents out from the specified contents
   *   Editable contents will not have any unmatched tags inside of it.
   *
   * @param  string $content
   * @return array  Array with keys of preEditableContents, editableContents, and postEditableContents.
   */
  private static function separateEditableContents($content)
  {
    $preEditableContents  = '';
    $postEditableContents = '';
    $editableContents     = $content;

    // Find where we need to start and end our editable div.
    // We want to keep going until our editableContents don't contain unmatched tags.
    while (($offsets = array_filter(self::getUnMatchedOffsets($editableContents))) && !empty($offsets)) {
      // variable for storing the un-matched closing div's offset.
      $unMatchedClosingOffset = 0;
      if (!empty($offsets['closing'])) {
        // we have unMatched closing tags
        // we need to insert our editable div after the last one.
        // closing divs are sorted in the reverse order they are found, so we don't need to do any sorting here.
        foreach ($offsets['closing'] as $closingOffset) {
          // we want our preEditableContents to include the unmatched closing div so we can open our editable div afterwards
          // add any new preEditableContents onto the end of our current preEditableContents
          $preEditableContents .= substr($editableContents, 0, $closingOffset['length'] + $closingOffset['offset']);

          // we need this later to verify that our closing div will get thrown after our opening div.
          $unMatchedClosingOffset = $closingOffset['offset'];
          break;
        }
      }

      if (!empty($offsets['opening'])) {
        // we have unMatched opening tags
        // we need to insert our ending editable div before the first one.
        // closing divs are sorted in the order they are found, so we don't need to do any sorting here.
        foreach ($offsets['opening'] as $openingOffset) {
          if ($openingOffset['offset'] < $unMatchedClosingOffset) {
            // we can't close the div if we haven't opened it yet.
            continue;
          }

          // we want our postEditableContents to contain the unmatched opening tag so we can insert our closing div before it.
          // Add any new postEditableContents onto the beginning of our current postEditableContents
          $postEditableContents = substr($editableContents, $openingOffset['offset']) . $postEditableContents;

          break;
        }
      }
      $postEditableContentsLen = strlen($postEditableContents);
      if ($postEditableContentsLen === 0) {
        // if we call substr with -0 as the length it will get confused and break
        $editableContents = substr($content, strlen($preEditableContents));
      } else {
        $editableContents = substr($content, strlen($preEditableContents), -$postEditableContentsLen);
      }
    }

    return [
      'preEditableContents'  => $preEditableContents,
      'editableContents'     => $editableContents,
      'postEditableContents' => $postEditableContents,
    ];
  }

  /**
   * Gets all tags by type
   *   Types include opening, closing, and selfClosing.
   *   Note: Void tags are grouped in with selfClosing.
   *
   * @param  string $content Content to find tags in
   * @return array Array with keys of opening, closing, and selfClosing containing sub-arrays with keys of result and flattened. Result represents the regex result, and flattened is just the tag itself
   */
  private static function getAllTagsByType($content)
  {
    // Let's find all types of tags grouping void tags in with self-closing tags.
    // s for PCRE_DOTALL, and x for PCRE_EXTENDED
    $tagRegex = sprintf('`
      # find comments
      (?P<comments><!--.+?-->)|
      # find closing tags including closing comment tags that were not matched from our comment piece
      (?P<closing>-->|</[^>]+>)|
      # find self closing and void tags
      (?P<selfclosing>(?:<[^>]+/>)|(?:<(?:%s)(?=\W)[^>]*?>))|
      # find opening tags including opening comment tags that were not matched from our comment piece
      (?P<opening><!--|<[^>]+>)`sx', implode('|', self::$voidElements));

    preg_match_all($tagRegex, $content, $matches, PREG_OFFSET_CAPTURE);

    // flattener that changes the values of the array to the first index. (The second index will be the offset from the PREG_OFFSET_CAPTURE option)
    $openingFlattener = function($value) {
      // pull just the tag out
      preg_match('`<(\w+)`', $value[0], $tagMatch);
      if (!isset($tagMatch[1])) {
        // tag not found
        return $value[0];
      }
      return $tagMatch[1];
    };
    $closingFlattener = function($value) {
      // pull just the tag out
      preg_match('`</(\w+)`', $value[0], $tagMatch);
      if (!isset($tagMatch[1])) {
        // tag not found
        return $value[0];
      }
      return $tagMatch[1];
    };

    // the array we are filtering is multi-dimensional, so we want to check if the regex match ($value[0]) is empty
    $filterer = function($value) {
      return (is_array($value) ? !empty($value[0]) : !empty($value));
    };

    $return = [
      'opening' => [
        'result'    => [],
        'flattened' => [],
      ],
      'closing' => [
        'result'    => [],
        'flattened' => [],
      ],
      'selfClosing' => [
        'result'    => [],
        'flattened' => [],
      ],
    ];

    if (isset($matches['opening'])) {
      $return['opening']['result']    = array_filter($matches['opening'], $filterer);
      $return['opening']['flattened'] = array_filter(array_map($openingFlattener, $return['opening']['result']));
    }

    if (isset($matches['closing'])) {
      $return['closing']['result']    = array_filter($matches['closing'], $filterer);
      $return['closing']['flattened'] = array_filter(array_map($closingFlattener, $return['closing']['result']));
    }

    if (isset($matches['selfclosing'])) {
      $return['selfClosing']['result']    = array_filter($matches['selfclosing'], $filterer);
      $return['selfClosing']['flattened'] = array_filter(array_map($openingFlattener, $return['selfClosing']['result']));
    }
    return $return;
  }

  /**
   * Gets offsets for un-matched opening and closing tags
   *
   * @param  string $content Content to search for un-matched tags
   * @return array Array with keys of opening and closing and values are an array of their respective offsets (offset) and string length (length).
   *   Opening array will be sorted in the order they are found.
   *   Closing array will be sorted in reverse order they exist in the content.
   */
  private static function getUnMatchedOffsets($content)
  {
    if (isset($_GET['showUnMatchedTags']) && (PermissionsManager::isUserAdmin(Gatekeeper::getUsername()) || PermissionsManager::isUserSuperUser(Gatekeeper::getUsername()))) {
      $userIsDebugging = true;
      $offsetDebug = ['opening' => [], 'closing' => []];
    } else {
      $userIsDebugging = false;
    }
    // we need to make sure all divs have been closed otherwise our editable div will get ruined.
    $tags = self::getAllTagsByType($content);

    // make sure our flattened arrays are sorted by keys
    ksort($tags['opening']['flattened']);
    ksort($tags['closing']['flattened']);

    $unMatchedOpening = self::findUnMatchedOpeningTags($tags['opening']['flattened'], $tags['closing']['flattened']);
    $unMatchedOpeningOffsets = [];
    if (!empty($unMatchedOpening)) {
      // make sure we are sorted by keys
      ksort($unMatchedOpening);
      // we have unmatched opening tags
      foreach ($unMatchedOpening as $key => $unMatched) {
        if ($userIsDebugging) {
          $offsetDebug['opening'][] = [
            'tag'     => htmlspecialchars($tags['opening']['result'][$key][0]),
            // include 40 characters for our context.
            'context' => htmlspecialchars(substr($content, $tags['opening']['result'][$key][1], strlen($tags['opening']['result'][$key][0]) + 40)),
          ];
        }
        $unMatchedOpeningOffsets[] = [
          'offset' => $tags['opening']['result'][$key][1],
          'length' => strlen($tags['opening']['result'][$key][0]),
        ];
      }
    }

    $unMatchedClosing = self::findUnMatchedClosingTags($tags['opening']['flattened'], $tags['closing']['flattened']);
    $unMatchedClosingOffsets = [];
    if (!empty($unMatchedClosing)) {
      // make sure we are sorted by keys in reverse order
      krsort($unMatchedClosing);
      // we have unmatched closing tags
      foreach ($unMatchedClosing as $key => $unMatched) {
        if ($userIsDebugging) {
          $offsetDebug['closing'][] = [
            'tag'     => htmlspecialchars($tags['closing']['result'][$key][0]),
            // include 40 characters for our context.
            'context' => htmlspecialchars(substr($content, $tags['closing']['result'][$key][1], strlen($tags['closing']['result'][$key][0]) + 40)),
          ];
        }
        $unMatchedClosingOffsets[] = [
          'offset' => $tags['closing']['result'][$key][1],
          'length' => strlen($tags['closing']['result'][$key][0]),
        ];
      }
    }

    if ($userIsDebugging && (!empty($offsetDebug['opening']) || !empty($offsetDebug['closing']))) {
      echo sprintf('<pre>%s</pre>', Debug::dump($offsetDebug, true));
    }
    return [
      'opening' => $unMatchedOpeningOffsets,
      'closing' => $unMatchedClosingOffsets,
    ];
  }

  /**
   * Finds any keys that are less than the specified key
   *   Note: $array must be sorted by keys
   *
   * @param  array $array Array to filter
   * @param  integer $key   Key we want our found keys to be less than.
   * @return array
   */
  private static function findKeysLessThanKey(Array $array, $key)
  {
    $i = 0;
    foreach ($array as $arrKey => $arrValue) {
      if ($arrKey < $key) {
        ++$i;
      } else {
        break;
      }
    }
    return array_slice($array, 0, $i, true);
  }

  /**
   * Finds any keys that are greater than the specified key
   *   Note: $array must be sorted by keys
   *
   * @param  array $array Array to filter
   * @param  integer $key   Key we want our found keys to be greater than.
   * @return array
   */
  private static function findKeysGreaterThanKey(Array $array, $key)
  {
    foreach ($array as $arrKey => $arrValue) {
      if ($arrKey > $key) {
        break;
      } else {
        unset($array[$arrKey]);
      }
    }
    return $array;
  }

  /**
   * Finds any opening tags that don't have a closing tag to go along with them
   *   Note: $opening and $closing must be sorted by keys
   *
   * @param  array  $opening Array of opening tags
   * @param  array  $closing Array of closing tags
   * @return array  Array of opening tags that aren't matched
   */
  private static function findUnMatchedOpeningTags(Array $opening, Array $closing)
  {
    // let's try looking at our first closing tag and see if it has an opening tag
    // we will be finding all opening tags that have a matching closing tag and un-setting them leaving us with anything that doesn't have a matching closing tag
    foreach ($closing as $key => $closingTag) {

      $openingPriorToCurr = self::findKeysLessThanKey($opening, $key);
      krsort($openingPriorToCurr);

      if (($openingKey = array_search($closingTag, $openingPriorToCurr)) !== false) {
        unset($opening[$openingKey]);
      }
    }
    return $opening;
  }

  /**
   * Finds any closing tags that don't have an opening tag to go along with them
   *   Note: $opening and $closing must be sorted by keys
   *
   * @param  array  $opening Array of opening tags
   * @param  array  $closing Array of closing tags
   * @return array  Array of closing tags that aren't matched
   */
  private static function findUnMatchedClosingTags(Array $opening, Array $closing)
  {
    // let's try looking at our first opening tag and see if it has a closing tag
    // we will be finding all closing tags that have a matching opening tag and un-setting them leaving us with anything that doesn't have a matching opening tag
    foreach ($opening as $key => $openingTag) {
      if (!isset($closingAfterCurr)) {
        $closingAfterCurr = $closing;
      }
      $closingAfterCurr = self::findKeysGreaterThanKey($closingAfterCurr, $key);

      if (($closingKey = array_search($openingTag, $closingAfterCurr)) !== false){
        unset($closing[$closingKey], $closingAfterCurr[$closingKey]);
      }
    }
    return $closing;
  }

  /**
   * Indents HTML
   *
   * @param  string $content Content to indent
   * @return string
   */
  private static function indentHTML($content)
  {
    $tags = self::getAllTagsByType($content);

    $tagKeys = array_merge(array_keys($tags['opening']['flattened']), array_keys($tags['closing']['flattened']), array_keys($tags['selfClosing']['flattened']));
    if (empty($tagKeys)) {
      $numberOfTags = 0;
    } else {
      $numberOfTags = max($tagKeys) +1;
    }

    $offset = 0;
    $numberOfIndents = 0;
    $indentation = '  ';
    for ($i = 0; $i < $numberOfTags; ++$i) {
      if (isset($tags['opening']['result'][$i])) {
        // we have an opening tag.

        if ($i !== 0 && preg_match('`\v(\h+)?$`', substr($content, 0, $tags['opening']['result'][$i][1] + $offset), $matches) === 1) {
          // this one comes after a new line. We want to indent.
          if (isset($matches[1])) {
            // we already have some indentation.
            $offsetWithoutIndentation = $offset - strlen($matches[1]);
            $adjustedOffsetForRemovingIndentation = strlen($matches[1]);
          } else {
            $offsetWithoutIndentation = $offset;
            $adjustedOffsetForRemovingIndentation = 0;
          }

          $indent = str_repeat($indentation, $numberOfIndents);
          // add our indentation to the content
          $content = sprintf(
              '%s%s%s',
              substr($content, 0, $tags['opening']['result'][$i][1] + $offsetWithoutIndentation),
              $indent,
              substr($content, $tags['opening']['result'][$i][1] + $offset)
          );
          // we need to adjust the offset if we have removed any indentation.
          $offset += strlen($indent) - $adjustedOffsetForRemovingIndentation;
        }
        // increment our number of indents here since we want everything after it to be indented.
        ++$numberOfIndents;
      } else if (isset($tags['closing']['result'][$i])) {
        // we have a closing tag.

        // we want to un-indent
        $numberOfIndents = max(0, --$numberOfIndents);

        if ($i !== 0 && preg_match('`\v(\h+)?$`', substr($content, 0, $tags['closing']['result'][$i][1] + $offset), $matches) === 1) {
          // this one comes after a new line. We want to un-indent.
          if (isset($matches[1])) {
            // we already have some indentation.
            $offsetWithoutIndentation = $offset - strlen($matches[1]);
            $adjustedOffsetForRemovingIndentation = strlen($matches[1]);
          } else {
            $offsetWithoutIndentation = $offset;
            $adjustedOffsetForRemovingIndentation = 0;
          }

          $indent = str_repeat($indentation, $numberOfIndents);
          // add our indentation to the content
          $content = sprintf(
              '%s%s%s',
              substr($content, 0, $tags['closing']['result'][$i][1] + $offsetWithoutIndentation),
              $indent,
              substr($content, $tags['closing']['result'][$i][1] + $offset)
          );
          // we need to adjust the offset if we have removed any indentation.
          $offset += strlen($indent) - $adjustedOffsetForRemovingIndentation;
        }
      } else if (isset($tags['selfClosing']['result'][$i])) {
        // we have a self-closing tag.

        if ($i !== 0 && preg_match('`\v(\h+)?$`', substr($content, 0, $tags['selfClosing']['result'][$i][1] + $offset), $matches) === 1) {
          // this one comes after a new line. We want to indent.
          if (isset($matches[1])) {
            // we already have some indentation.
            $offsetWithoutIndentation = $offset - strlen($matches[1]);
            $adjustedOffsetForRemovingIndentation = strlen($matches[1]);
          } else {
            $offsetWithoutIndentation = $offset;
            $adjustedOffsetForRemovingIndentation = 0;
          }

          $indent = str_repeat($indentation, $numberOfIndents);
          // add our indentation to the content
          $content = sprintf(
              '%s%s%s',
              substr($content, 0, $tags['selfClosing']['result'][$i][1] + $offsetWithoutIndentation),
              $indent,
              substr($content, $tags['selfClosing']['result'][$i][1] + $offset)
          );
          // we need to adjust the offset if we have removed any indentation.
          $offset += strlen($indent) - $adjustedOffsetForRemovingIndentation;
        }
      }
    }
    return $content;
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
   * Removes nodes that will break things when they get re-defined.
   *   This includes:
   *   <ul>
   *     <li>Classes</li>
   *     <li>Functions</li>
   *   </ul>
   *
   * @return boolean True if contents have been removed false otherwise.
   */
  private function removeAlreadyDefinedPHPNodes()
  {
    if (!isset($this->phpNodes)) {
      $this->buildPHPNodes();
    }
    $removed = false;
    foreach ($this->phpNodes as $key => &$node) {
      if ($node->getType() === 'Stmt_Class') {
        // get the name of the class.
        $className = $node->name;

        if (class_exists($className)) {
          // class exists. Remove this node.
          unset($this->phpNodes[$key]);
          $removed = true;
        }
      } else if ($node->getType() === 'Stmt_Function') {
        // get the name of the function.
        $funcName = $node->name;

        if (function_exists($funcName)) {
          // function exists. Remove this node.
          unset($this->phpNodes[$key]);
          $removed = true;
        }
      }
    }
    return $removed;
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

      // Now we need to separate our editable contents out to make sure we are changing the same value as the user was trying to edit.
      // Editable content won't have any un-matched tags, so we only want to change that same content.
      $splitContents = self::separateEditableContents($this->content);

      // just add the new content between the preEditable and postEditable contents.
      $this->content = sprintf(
          '%s%s%s',
          $splitContents['preEditableContents'],
          $newContent,
          $splitContents['postEditableContents']
      );
      $this->edited = true;
      return true;
    }

    // Now edit php contents.
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