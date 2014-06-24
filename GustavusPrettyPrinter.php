<?php
/**
 * @package  Concert
 * @author  Billy Visto
 */

namespace Gustavus\Concert;
use PHPParser_PrettyPrinter_Default,
  PHPParser_Node_Expr_Array,
  //PHPParser_Node_Stmt_Function,
  PHPParser_Node_Stmt_Break;

/**
 * Adds our coding standard formating to the default PrettyPrinter
 *
 * @todo  Come back to this once PHPParser has support for whitespace.
 *
 * @package  Concert
 * @author  Billy Visto
 */
class GustavusPrettyPrinter extends PHPParser_PrettyPrinter_Default
{
  /**
   * {@inheritdoc}
   */
  public function pExpr_Array(PHPParser_Node_Expr_Array $node)
  {
    return "[\n" . str_replace(';', ',', $this->pStmts($node->items)) . "\n]";
  }

  /**
   * {@inheritdoc}
   */
  protected function pComments(array $comments)
  {
    $result = '';

    foreach ($comments as $comment) {
      if ($comment instanceof \PHPParser_Comment_Doc) {
        // doc block comment
        // add a new line before it.
        $result .= "\n" . $comment->getReformattedText() . "\n";
      } else {
        // normal comment like this.
        $result .= $comment->getReformattedText() . "\n";
      }
    }

    return $result;
  }

  protected function pStmts(array $nodes, $indent = true)
  {
    $pNodes = array();
    foreach ($nodes as $node) {
        // var_dump(get_class($node));
        // var_dump($node->subNodes);
        // var_dump($node->attributes);
        // var_dump($node->getAttribute('comments'));
        $pNodes[] = $this->pComments($node->getAttribute('comments', array()))
                  . $this->p($node)
                  . ($node instanceof PHPParser_Node_Expr ? ';' : '');
    }

    if ($indent) {
        return '    ' . preg_replace(
            '~\n(?!$|' . $this->noIndentToken . ')~',
            "\n" . '    ',
            implode("\n", $pNodes)
        );
    } else {
        return implode("\n", $pNodes);
    }
  }
}