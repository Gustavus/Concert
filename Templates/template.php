<?php
use Gustavus\TemplateBuilder\Builder;
$templatePreferences  = array(
  'localNavigation'   => true,
  'auxBox'            => true,
  'templateRevision'  => 2,
);

Builder::init();

$properties = [];
ob_start();
?>
<?php
$properties['head'] = ob_get_contents();
ob_clean();
?>
<?php
$properties['javascripts'] = ob_get_contents();
ob_clean();
?>
Title
<?php
$properties['title'] = ob_get_contents();
ob_clean();
?>
Sub-Title
<?php
$properties['subtitle'] = ob_get_contents();
ob_clean();
?>
<p>Body</p>
<?php
$properties['content'] = ob_get_contents();
ob_clean();
?>
<?php
$properties['focusBox'] = ob_get_contents();
ob_clean();

echo (new Builder($properties, $templatePreferences))->render();