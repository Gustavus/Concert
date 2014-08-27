<?php
/**
 * spellcheck.php
 *
 * Copyright, Moxiecode Systems AB
 * Released under LGPL License.
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

use Gustavus\Scout\Scout;

require('./includes/Engine.php');
require('./includes/EnchantEngine.php');
require('./includes/PSpellEngine.php');

require_once 'scout/scout.class.php';

$tinymceSpellCheckerConfig = array(
	'engine' => 'pspell', // enchant, pspell

	// Enchant options
	'enchant_dicts_path' => './dicts',

	// PSpell options
  'pspell.mode'     => 'fast',
  'pspell.spelling' => Scout::SPELLING,
  'pspell.jargon'   => Scout::JARGON,
  'pspell.encoding' => Scout::ENCODING,
  'pspell.personal' => Scout::DICTIONARY,
);

TinyMCE_Spellchecker_Engine::processRequest($tinymceSpellCheckerConfig);
?>