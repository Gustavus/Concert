<?php
use Gustavus\GACMailer\EmailMessage,
  Gustavus\FileUploader\FileUploader,
  Gustavus\Config\Config;
$templatePreferences  = array(
  'localNavigation'   => TRUE,
  'auxBox'        => TRUE,
  'templateRevision'    => 1,
//  'focusBoxColumns'   => 12,
//  'bannerDirectory'   => 'alumni',
//  'view'          => 'template/views/general.html',
);
require_once 'template/request.class.php';
require_once '/cis/www/alumni/submit/SubmitConfig.php';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<!-- InstanceBegin template="/Templates/Gustavus.2.dwt" codeOutsideHTMLIsLocked="true" -->
<head>
<title>Page Title | Gustavus Adolphus College</title>

<!-- CSS -->
<link rel="stylesheet" href="/css/base.v1.css" type="text/css"
  media="screen, projection">


<link rel="stylesheet" href="/css/contribute.css" type="text/css"
  media="screen, projection">
<!-- InstanceBeginNonEditable name="Head" -->
<link rel="stylesheet" href="/js/swfupload/swfupload.css" type="text/css"
  media="screen, projection">
<link rel="stylesheet" href="/alumni/submit/style.css" type="text/css"
  media="screen, projection">
<!-- InstanceEndNonEditable -->
<!-- InstanceBeginNonEditable name="JavaScript" -->
<?php
//session_start();
?>
<script type="text/javascript">
Modernizr.load([
  '/min/f=/js/swfupload/swfupload.js,/js/swfupload/plugins/swfupload.queue.js,/alumni/submit/fileprogress.js,/alumni/submit/handlers.js'
]);
</script>

<!-- InstanceEndNonEditable -->


<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>

<body class="col2-nobanner">
<div id="masthead">
<div id="masthead-container">
<div class="container_48 clearfix">
<div id="logo" class="grid_16 prefix_1 clearfix"><a
  href="http://gustavus.edu">Gustavus Adolphus College</a></div>
<div id="tagline" class="grid_19 push_11">Make your life count</div>
</div>
</div>
</div>
<div id="body">
<div id="body-container">
<div class="container_48 clearfix">
<div id="local-navigation" class="grid_8 prefix_1 suffix_1">
<p class="contributeOnly">Your local navigation will appear here. If you
would like to make changes to your local navigation, please contact Web
Services at web@gustavus.edu.</p>
&nbsp;</div>
<div id="page-content" class="grid_36 prefix_1 suffix_1 clearfix">
<div id="breadcrumb-trail">You are here: <a href="http://gustavus.edu">Home</a>
/ <a href="http://gustavus.edu">Section</a> /</div>


<h1 id="page-title"><!-- InstanceBeginEditable name="Title" --> Submit Your
News <!-- InstanceEndEditable --></h1>

<div id="page-subtitle"><!-- InstanceBeginNonEditable name="Subtitle" -->
<!-- InstanceEndEditable --></div>

<!-- InstanceBeginEditable name="Content" -->
<?php

function isFormSubmitted()
{
  if (isset($_POST['submit'])) {
    return true;
  } else {
    return false;
  }
}

/**
 * @return boolean|string String of the error message, true if it is valid
 */
function validateForm()
{
  $required = array('news', 'firstName', 'lastName', 'classYear', 'email');

  foreach($required as $name) {
    if (!isset($_POST[$name]) || trim($_POST[$name]) == '') {
      return '<strong>Oops!</strong> It looks like you missed some required fields.';
    }
  }
  if (!is_numeric($_POST['classYear'])) {
    return '<strong>Oops!</strong> Class year must be numeric.';
  }

  if (!empty($_POST['botcheck'])) {
    return '<strong>Oops!</strong> Something unexpected happened and we were unable to process your request.';
  }

  return true;
}

/**
 * @return void
 */
function setFormDefaults()
{
  if (isFormSubmitted()) {
    return setSubmittedFormDefaults();
  } else {
    return setFreshFormDefaults();
  }
}

function initializeFormArray()
{
  global $form;

  if (!isset($form) || !is_array($form)) {
    $form = array();
  }
}

/**
 * @return void
 */
function setSubmittedFormDefaults()
{
  global $form;

  initializeFormArray();

  $form['type']   = (isset($_POST['type'])) ? $_POST['type'] : NULL;
  $form['news']   = $_POST['news'];
  $form['username'] = $_POST['username'];
  $form['firstName']  = $_POST['firstName'];
  $form['lastName'] = $_POST['lastName'];
  $form['maidenName'] = $_POST['maidenName'];
  $form['email']    = $_POST['email'];
  $form['classYear']  = $_POST['classYear'];
}

/**
 * @return void
 */
function setFreshFormDefaults()
{
  global $template, $form;

  initializeFormArray();

  if ($template->isUserLoggedIn()) {
    $form['username'] = $template->user('username');
    $form['firstName']  = $template->user('bestFirstName');
    $form['lastName'] = $template->user('lastName');
    $form['maidenName'] = $template->user('maidenName');
    $form['classYear']  = $template->user('classYear');
    $form['email']    = $template->user('bestEmailAddress');
  }
}

function form($variable)
{
  global $form;

  if (array_key_exists($variable, $form)) {
    return htmlspecialchars($form[$variable]);
  } else {
    return NULL;
  }
}

/**
 * @return void
 */
function includeForm()
{
  setFormDefaults();
  include 'submitForm.php';
  return;
}

/**
 * @return void
 */
function displayError($message)
{
  echo '<p class="error">' . $message . '</p>';

  echo '<script type="text/javascript">
    Modernizr.load([{
      load: Gustavus.baseSrc,
      complete : function() {
        $(document).ready(function() {
          $(\'.required :input[value=""], .required :input.exampleText\').parents(\'.required\').addClass(\'requiredBlank\');
        });
      }
    }]);
    </script>';
}

function displaySuccess()
{
  echo '<p class="message"><strong>Thank you!</strong> We have received your news.</p>';
}

/**
 * @return void
 */
function processForm()
{
  $_POST  = Format::trimRecursive($_POST);

  email();
  blog();
  deleteAttachments();
  displaySuccess();
  return;
}

function name()
{
  $name = ($_POST['maidenName'] && $_POST['maidenName'] !== $_POST['lastName'])
    ? "{$_POST['firstName']} {$_POST['maidenName']} {$_POST['lastName']}"
    : "{$_POST['firstName']} {$_POST['lastName']}";

  return $name;
}

function email()
{
  $name = name();

  $message = new EmailMessage();
  $message->addReplyTo($_POST['email'], $name)
    ->setCharset('utf-8')
    ->setFrom('noreply@gustavus.edu', 'Gustavus Website')
    ->addTo('bvisto@gustavus.edu')
    ->setDebuggingRecipients('bvisto@gustavus.edu');

  if (!Config::isBeta()) {
    $message->addTo('alumni-blog@gustavus.edu');
  }

  $year     = Format::shortYear((int) $_POST['classYear']);
  $message->setSubject("[Alumni News] $name $year");

  $body = "
  <h1>$name $year</h1>
  " . Format::paragraphs($_POST['news']);

  $message->addPart($body, 'text/html')
    ->addPart($_POST['news'], 'text/plain');

  if (isset($_POST['uploadName']) && is_array($_POST['uploadName']) && count($_POST['uploadName'])) {
    foreach((array) $_POST['uploadName'] as $file) {
      $message->attachFile(
        SubmitConfig::$fileUploaderOptions['uploadLocation'] . $file,
        $file,
        'image/jpeg'
      );
    }
  }

  $message->send();
}

function blog()
{
  require_once 'class-IXR.php';

  define('BLOG_ID', 37);
  define('RPC_USERNAME', 'xml-rpc');
  define('RPC_PASSWORD', 'e=@<OoA~/8)&1u');

  $rpc  = new IXR_Client('http://alumni.blog.gustavus.edu/xmlrpc.php');

  // Send the files first so they can automatically be attached to the post
  $attachments  = array();

  if (isset($_POST['uploadName']) && is_array($_POST['uploadName']) && count($_POST['uploadName'])) {
    foreach((array) $_POST['uploadName'] as $file) {
      $bits = new IXR_Base64(file_get_contents(SubmitConfig::$fileUploaderOptions['uploadLocation'] . $file));

      $post = array(
        'name'      => $file,
        'type'      => 'image/jpeg',
        'bits'      => $bits,
        'overwrite' => FALSE,
      );

      $status = $rpc->query(
        'wp.uploadFile',
        BLOG_ID,
        RPC_USERNAME,
        RPC_PASSWORD,
        $post,
        FALSE
      );

      $response   = $rpc->getResponse();
      $attachments[]  = $response['url'];
    }
  }

  $body = $_POST['news'];

  // Add text to the body of the post so the attachments can be attached to
  // the post
  if (count($attachments)) {
    $body .= "\n\n<div class=\"nodisplay\"><strong>Attachments:</strong>";
    foreach ($attachments as $url) {
      $body .= "\n$url";
    }
    $body .= '</div>';
  }

  // Send the post as a draft
  $post = array(
    'title'        => trim(name() . ' ' . Format::shortYear((int) $_POST['classYear'])),
    'description'  => $body,
    'categories'   => array('Submitted'),
    'mt_keywords'  => array($_POST['classYear'], name()),
    'wp_author_id' => 1621, //alumni-blog  old = 216 // Erin Wilken
  );

  $status = $rpc->query(
       "metaWeblog.newPost",
       BLOG_ID,
       RPC_USERNAME,
       RPC_PASSWORD,
       $post,
       FALSE
  );
}

/**
 * Deletes all attachments associated to this submission
 *
 * @return void
 */
function deleteAttachments()
{
  if (isset($_POST['uploadName'])) {
    $uploader = new FileUploader(SubmitConfig::$fileUploaderOptions);
    foreach ($_POST['uploadName'] as $file) {
      $uploader->deleteFile($file);
    }
  }
}

if (isFormSubmitted()) {
  if (($message = validateForm()) === true) {
    processForm();
  } else {
    displayError($message);
    includeForm();
  }
} else {
  includeForm();
}
?>
<!-- InstanceEndEditable -->

<div id="focusBox"><!-- InstanceBeginNonEditable name="FocusBox" -->
<!-- InstanceEndNonEditable -->
</div>

</div>
<!-- #page-content -->

<div class="clear">&nbsp;</div>

</div>
<!-- .container_48 --></div>
<!-- #body-container --></div>
<!-- #body -->

<div id="footer-container">
<div class="container_48">&nbsp;</div>
<!-- .container_48 --></div>
<!-- #footer-container -->

</body>
<!-- InstanceEnd -->
</html>
<?php TemplatePageRequest::end(); ?>