<?php
$templatePreferences  = array(
  'localNavigation'   => TRUE,
  'auxBox'        => false,
  'templateRevision'    => 1,
  'focusBoxColumns'   => 14,
 'bannerDirectory'   => 'alumni/youngalumni/',
//  'view'          => 'template/views/general.html',
);

use Gustavus\SocialMedia\SocialMedia,
  Gustavus\TemplateBuilder\Builder;

Builder::init();

$templateBuilderProperties = [];
ob_start();
?>

<?php
$templateBuilderProperties['head'] = ob_get_contents();
ob_clean();
?>

<?php
$templateBuilderProperties['javascripts'] = ob_get_contents();
ob_clean();
?>

Events

<?php
$templateBuilderProperties['title'] = ob_get_contents();
ob_clean();
?>

<?php
$templateBuilderProperties['subtitle'] = ob_get_contents();
ob_clean();
?>

<div class="grid_36 alpha omega">

  <p><a href="/alumni/gather/reunions/spring.php">Spring Reunions</a> | <a href="
  /alumni/gather/networking.php">Networking</a> | <a href="/alumni/gather/chapters/events.php">Chapter Events</a> | <a href="/alumni/gather/homecoming.php">Homecoming</a> | <a href="/alumni/gather/reunions/fall.php">Fall Reunions</a></p>

<p>In addition to the gatherings noted on other pages, Gustavus also encourages alumni stay connected to the College through these events. </p>
<ul>
  <li><strong>Gustie Breakfasts</strong> - Engage with other alumni and learn something new about your alma mater at the monthly Gustavus alumni breakfast. Gustie Breakfasts are held on campus the second Wednesday of each month and in the Twin Cities on the third Wednesday of each month. <a href="/alumni/events/gustiebreakfast/index.php">Full list of Gustie Breakfasts online</a>.</li>
  <li><strong>Faculty and Administrator Service Awards Dinner</strong> - The College and the Gustavus Alumni Association recognizes 2014 retirees and the commitment of faculty and administrators who have served the College 10, 15, 20, 25, 30, 35, 40, and 45 years. The event is held on May 21, 1014 in the Alumni Hall. <a href="/calendar/faculty-and-administrator-service-and-retirement-dinner-3/38898">Register here</a>.</li>
  <li><strong>Gustavus Networking Event</strong> - Join Gustavus alumni and currents students for a bi-annual evening of networking. <a href="/calendar/gustavus-networking-event-3/40314">More information about the Gustavus Networking events is available online</a>.</li>
  <li><strong>Gustavus Alumni College</strong> - The Alumni Association offers a series of presentations and lectures by current and emeritus faculty and distinguished alumni. Learn more [LINK to /gather/alumnicollege]</li>
  <li><strong>Athletics Hall of Fame</strong> - The Gustavus Adolphus College Hall of Fame was established in 1978 at which time 19 "Charter Members" were inducted either as coaches or as athletes. Gustavus inducts new members into its Athletics Hall of Fame each fall. Athletics Hall of Fame Day is celebrated annually in the Fall. <a href="/athletics/halloffame/">Checkout the Hall of Fame</a>.</li>
</ul>
</div>

<div class="grid_36 alpha omega">

<?php
  require_once($_SERVER['DOCUMENT_ROOT'] . '/modules/components/classes/calendarPuller.class.php');
  $cp = new CalendarPuller(array(
    'showPastEvents'  => FALSE,
    'showNextEvent'   => FALSE,
    'maxUpcomingEvents' => 10,
    'tags'      => array('alumni'),
  ));
  $cp->display();
  ?>

</div>

<?php
$templateBuilderProperties['content'] = ob_get_contents();
ob_clean();
?>

<?php
$templateBuilderProperties['focusBox'] = ob_get_contents();
ob_clean();

echo (new Builder($templateBuilderProperties, $templatePreferences))->render();