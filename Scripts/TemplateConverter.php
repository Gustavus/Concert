<?php
/**
 * @package  Concert
 * @subpackage Scripts
 * @author  Billy Visto
 */

namespace Gustavus\Concert\Scripts;

use RuntimeException;

/**
 * Class to handle converting a specific page
 *
 * @package  Concert
 * @subpackage Scripts
 * @author  Billy Visto
 */
class TemplateConverter
{
  /**
   * Contents of the page we are converting
   *
   * @var string
   */
  private $pageContent;

  /**
   * First php block. This should contain any template preferences.
   *
   * @var string
   */
  private $firstPHPBlock;

  /**
   * Properties array variable
   *
   * @var string
   */
  private $propertiesVariableName = 'templateBuilderProperties';

  /**
   * Alias of the builder when calling functions on it
   *
   * @var string
   */
  private $builderAlias = 'Builder';

  /**
   * Mapping of page sections.
   * Array with key of original template section identifier and value of new identifier
   *
   * @var array
   */
  private static $pageSections = [
    'Head'            => 'head',
    'JavaScript'      => 'javascripts',
    'LocalNavigation' => 'localNavigation',
    'Title'           => 'title',
    'Subtitle'        => 'subtitle',
    'Content'         => 'content',
    'FocusBox'        => 'focusBox',
  ];

  /**
   * Mapping of page sections.
   * Array with key of original template section identifier and value of new identifier
   *
   * @var array
   */
  private static $ignoreEmptyPageSections = [
    'LocalNavigation',
  ];

  /**
   * Constructor
   *
   * @param string $filePath Path to the file to convert
   *
   * @throws  RuntimeException If the specified file doesn't exist
   *
   * @return  void
   */
  public function __construct($filePath)
  {
    if (file_exists($filePath)) {
      $this->pageContent = file_get_contents($filePath);
    } else {
      throw new RuntimeException(sprintf('The specified file: "%s" doesn\'t exist', $filePath));
    }
  }

  /**
   * Checks to see if the current page exists in the current template
   *
   * @return boolean
   */
  public function isPageTemplated()
  {
    $firstPHPBlock = $this->getFirstPHPBlock();
    if (strpos($firstPHPBlock, '$templatePreferences') !== false && strpos($firstPHPBlock, 'template/request.class.php') !== false) {
      // we have a templated page.
      return true;
    } else {
      return false;
    }
  }

  /**
   * Grabs a section from the template container
   *
   * @param string $sectionName Name of the section to grab (e.g. 'Title' or 'Content')
   * @return string
   */
  private function extractSection($sectionName)
  {
    assert('is_string($sectionName)');

    $pattern  = sprintf('`<!--\s*InstanceBegin(?>Non)?Editable(?>[^>]*?name=)"%s".*?-->(.*)`s', preg_quote($sectionName));

    preg_match($pattern, $this->pageContent, $matches);

    if (!isset($matches[1])) {
      return '';
    }

    $pattern  = '`<!--\s*InstanceEnd(?>Non)?Editable.*`s';
    return trim(preg_replace($pattern, '', $matches[1]));
  }

  /**
   * Grabs the first block of php code
   *
   * @return string
   */
  private function getFirstPHPBlock()
  {
    if (!empty($this->firstPHPBlock)) {
      return $this->firstPHPBlock;
    }

    $firstPHPRegex = '`(?:
      # Make sure we are at the beginning of the file
      ^
      # look for newlines or spaces
      (?:\A[\h*\v*])?

      # look for an opening php tag
      (
        (?:<\?)(?:php)?

        # capture everything until the end of the file or a closing php tag
        .+?
          (?:\?>|(?:\?>)?[\h\v]*?\z)
      )
    )`smx';
    //s for PCRE_DOTALL, m for PCRE_MULTILINE, and x for PCRE_EXTENDED

    preg_match($firstPHPRegex, $this->pageContent, $matches);

    if (isset($matches[1])) {
      $this->firstPHPBlock = $matches[1];
    } else {
      $this->firstPHPBlock = null;
    }

    return $this->firstPHPBlock;
  }

  /**
   * Converts the first php block.
   *   Removes old template stuff adds template builder stuff.
   *
   * @return string
   */
  private function convertFirstPHPBlock()
  {
    $firstPHPBlock = $this->getFirstPHPBlock();

    // remove template/request.class.php.
    $firstPHPBlock = preg_replace('`require_once\h*[\'"]template/request.class.php[\'"]\h*?;\h*?\v`', '', $firstPHPBlock);

    preg_match('`(use [^;]+;)`sx', $firstPHPBlock, $matches);

    if (isset($matches[1])) {
      $useStatement = $matches[1];
      $newUseStatement = $this->convertUseStatement($useStatement);
      $firstPHPBlock = str_replace($useStatement, $newUseStatement, $firstPHPBlock);
    } else {
      $replacement = "\nuse Gustavus\\TemplateBuilder\\Builder;\n\nBuilder::init();\n?>";
      $firstPHPBlock = str_replace('?>', $replacement, $firstPHPBlock);
    }

    while (strpos($this->pageContent, $this->propertiesVariableName) !== false) {
      // we need to try a different variable name
      $this->propertiesVariableName .= 'Array';
    }
    $replacement = sprintf("\n\$%s = [];\nob_start();\n?>", $this->propertiesVariableName);
    $firstPHPBlock = str_replace('?>', $replacement, $firstPHPBlock);

    return $firstPHPBlock;
  }

  /**
   * Converts a use statment to include TemplateBuilder
   *
   * @param  string $useStatement Use statement to convert
   * @return string
   */
  private function convertUseStatement($useStatement)
  {
    // check to see if TemplateBuilder is already used.
    if (preg_match('`TemplateBuilder\\\Builder(?:\h*as\h*([^;]+))?`sx', $useStatement, $matches)) {
      // template builder is already included
      if (isset($matches[1])) {
        // template builder is included as an alias
        $this->builderAlias = $matches[1];
      }
      if (strpos($this->pageContent, sprintf('%s::init()', $this->builderAlias)) === false) {
        // a call to init doesn't exist. We need to include it.
        $useStatement = sprintf("%s\n%s::init();\n", $useStatement, $this->builderAlias);
      }
      // all we need to do.
      return $useStatement;
    }


    if (strpos($useStatement, ',') !== false) {
      // we have multiple uses. We want to see how they are indented.
      preg_match('`,\v*(\h+)`', $useStatement, $matches);
      $indentation = isset($matches[1]) ? $matches[1] : '  ';
    } else {
      // default to two spaces.
      $indentation = '  ';
    }

    $replacement = sprintf(",\n%sGustavus\\TemplateBuilder\\Builder;\n\nBuilder::init();", $indentation);
    return str_replace(';', $replacement, $useStatement);

  }

  /**
   * Performs the conversion
   *
   * @return void
   */
  public function convert()
  {
    if (!$this->isPageTemplated()) {
      return false;
    }

    $newPage = $this->convertFirstPHPBlock();

    $propertyAssignmentView = "\n<?php\n\${$this->propertiesVariableName}['%s'] = ob_get_contents();\nob_clean();\n?>\n";

    $i = 0;
    $lastI = count(self::$pageSections);
    foreach (self::$pageSections as $oldSectionName => $newSectionName) {
      ++$i;
      $section = $this->extractSection($oldSectionName);
      if (in_array($oldSectionName, self::$ignoreEmptyPageSections) && empty($section)) {
        // the current section is empty, and in our ignored array. Don't actually put this into the template.
        continue;
      }
      if ($i === $lastI) {
        // we are on our last iteration and need to adjust the view.
        $propertyAssignmentView = "\n<?php\n\${$this->propertiesVariableName}['%s'] = ob_get_contents();\nob_clean();\n\necho (new {$this->builderAlias}(\${$this->propertiesVariableName}, \$templatePreferences))->render();\n?>";
      }
      if (empty($section)) {
        $newPage .= sprintf($propertyAssignmentView, $newSectionName);
      } else {
        $newPage .= sprintf(
            "\n%s\n%s",
            $this->extractSection($oldSectionName),
            sprintf($propertyAssignmentView, $newSectionName)
        );
      }
    }

    return $newPage;
  }
}