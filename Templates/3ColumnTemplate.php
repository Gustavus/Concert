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
<div class="grid-100 tgrid-100 mgrid-100 alpha omega">
  <div class="grid-33 tgrid-33 mgrid-100 alpha momega">
    Column 1
  </div>
  <div class="grid-33 tgrid-33 mgrid-100 momega malpha">
    Column 2
  </div>
  <div class="grid-33 tgrid-33 mgrid-100 omega malpha">
    Column 3
  </div>
</div>
<?php
$properties['content'] = ob_get_contents();
ob_clean();
?>
<?php
$properties['focusBox'] = ob_get_contents();
ob_clean();

echo (new Builder($properties, $templatePreferences))->render();