<?php
/**
 * @package Concert
 * @author  Billy Visto
 */

namespace Gustavus\Concert;
use InvalidArgumentException,
  PHPParser_Node_Expr_Array,
  PHPParser_Parser,
  PHPParser_NodeTraverser,
  PHPParser_PrettyPrinter_Default,
  PHPParser_Lexer;

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
        $prettyPrinter = new GustavusPrettyPrinter;
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
    $parser = new PHPParser_Parser(new PHPParser_Lexer);

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
        $traverser = new PHPParser_NodeTraverser;
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
    return sprintf('<div class="editable" data-index="%s">%s</div>%s', $index, trim($content), Config::EDITABLE_DIV_CLOSING_IDENTIFIER);
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
        if ($node->expr instanceof PHPParser_Node_Expr_Array) {
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
   * Edits the node's value specified by $index with $newContent.
   *
   * @param  string $index      Identifier for this node
   * @param  string $newContent Content to replace with
   * @return boolean True on success. False on failure.
   */
  public function editValue($index, $newContent)
  {
    if (!$this->isPHPContent() && in_array($this->getContentType(), Config::$editableContentTypes)) {
      // save our original value before editing
      $this->valuesBeforeEdit = $this->content;
      // non php content is editable
      $this->content = $newContent;
      $this->edited = true;
      return true;
    }

    if (!Config::ALLOW_PHP_EDITS || !in_array($this->getContentType(), Config::$editableContentTypes)) {
      return false;
    }

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