<?php
/**
 * @package  Concert
 * @author  Billy Visto
 */

namespace Gustavus\Concert;

require_once '/cis/lib/Gustavus/Concert/Assets/Composer/vendor/autoload.php';

use PhpParser\PrettyPrinter\Standard as StandardPrinter,
  PhpParser\Node\Expr\Array_ as ParserArray,
  //PHPParser_Node_Stmt_Function,
  //PHPParser_Node_Stmt_Break,
  PhpParser\Comment\Doc,
  PhpParser\Node\Expr;

/**
 * Adds our coding standard formating to the default PrettyPrinter
 *
 * @todo  Come back to this once PHPParser has support for whitespace.
 *
 * @package  Concert
 * @author  Billy Visto
 */
class GustavusPrettyPrinter extends StandardPrinter
{
  /**
   * {@inheritdoc}
   */
  public function pExpr_Array(ParserArray $node)
  {
    return '[' . str_replace(';', ',', $this->pStmts($node->items)) . "\n]";
  }

  /**
   * {@inheritdoc}
   */
  protected function pComments(array $comments)
  {
    $result = '';

    foreach ($comments as $comment) {
      if ($comment instanceof Doc) {
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
}