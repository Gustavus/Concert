<?php
use Gustavus\TemplateBuilder\Builder;

$templatePreferences  = array(
  'localNavigation'   => TRUE,
  'auxBox'        => TRUE,
  'templateRevision'    => 2, // updated by template conversion script
  'focusBoxType' => 'links', // text or links
//  'focusBoxColumns'   => 10,
//  'bannerDirectory'   => 'general',
//  'view'          => 'template/views/general.html',
);
$properties = [];
ob_start();
?>
Contact a Coach
<?php
$properties['title'] = ob_get_contents();
ob_clean();
?>
<div id="newInput"></div>
<p>arstarstarst</p>

<p class="buttonbar">
  <a href="#" class="button facebook">Face</a>
  <a href="#" class="button twitter">Twit</a>
  <a href="#" class="button youtube">You</a>
  <a href="#" class="button wordpress">word press</a>
</p>
<?php
$properties['content'] = ob_get_contents();
ob_clean();
?>
<p class="focusBoxHeader">Header</p>test
<?php
$properties['focusBox'] = ob_get_contents();
ob_clean();
?>
more stuff
<?php
$properties['focusBox'] .= ob_get_contents();
ob_clean();

echo (new Builder($properties, $templatePreferences))->render();