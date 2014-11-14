<?php
$templatePreferences  = array(
  'localNavigation'   => TRUE,
  'auxBox'        => false,
  'templateRevision'    => 1,
//  'focusBoxColumns'   => 10,
  'bannerDirectory'   => 'alumni',
//  'view'          => 'template/views/general.html',
);

require_once 'template/request.class.php';
require_once 'rssgrabber/rssgrabber.class.php';
require_once '/cis/www/calendar/classes/puller.class.php';

use Gustavus\SocialMedia\SocialMedia,
    Gustavus\Resources\Resource,
    Gustavus\TwigFactory\TwigFactory,
    Gustavus\Utility\String as GACString;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en"><!-- InstanceBegin template="/Templates/Gustavus.2.dwt" codeOutsideHTMLIsLocked="true" -->
<head>
<title>Page Title | Gustavus Adolphus College</title>

<!-- CSS -->
<link rel="stylesheet" href="/css/base.v1.css" type="text/css"
  media="screen, projection">
<link rel="stylesheet" href="/css/contribute.css" type="text/css"
  media="screen, projection">

<!-- InstanceBeginNonEditable name="Head" -->
  <link rel="stylesheet" href="<?php echo Resource::renderCSS(array('path' => '/alumni/css/homepage.css', 'version' => time()), false, true); ?>" />

<!-- InstanceEndNonEditable -->

<!-- InstanceBeginNonEditable name="JavaScript" -->
<script type="text/javascript">
  Modernizr.load({
    load: ['/js/jquery/bxSlider/jquery.bxslider.js', '/alumni/js/tagged-calendar.js', '/alumni/js/jquery.blog-stats.js'],
    complete: function () {

      $(function () {

        if ($('#events-with-photos .event').length > 0) {
          $('#events-with-photos .events').bxSlider({
            auto: true,
            maxSlides: 2,
            pause: 10000,
            slideMargin: 10,
            slideWidth: 345,
            useCSS: false
          });
        }

        var resourceBoxesMaxHeight = 0;

        $('.resource-boxes .resource-box:not(.news) section')
          .css('min-height', $('.social-media').innerHeight() - 46);

        $("[data-blog-stat]").blogStats({location: '//alumni.blog.gustavus.edu/stats/'});

      });
    }
  });
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
<p class="contributeOnly">Your local navigation will appear here. If you would like to make changes to
your local navigation, please contact Web Services at
web@gustavus.edu.</p>
&nbsp;
</div>
<div id="page-content" class="grid_36 prefix_1 suffix_1 clearfix">
<div id="breadcrumb-trail">You are here: <a href="http://gustavus.edu">Home</a> / <a href="http://gustavus.edu">Section</a> / </div>


<h1 id="page-title">
<!-- InstanceBeginEditable name="Title" -->
Alumni &amp; Parent Engagement
<!-- InstanceEndEditable -->
</h1>

<div id="page-subtitle">
<!-- InstanceBeginNonEditable name="Subtitle" -->
<!-- InstanceEndEditable -->
</div>

<!-- InstanceBeginEditable name="Content" -->

<div class="clear"></div>

<!-- Tagged Calendar -->
<div class="tagged-calendar">
  <header>
    <h2>Upcoming Events</h2>
    <ul class="tag-list">
      <li class="tag active" data-tag-panel="1">All</li>
      <li class="tag" data-tag-panel="2">Homecoming</li>
      <li class="tag" data-tag-panel="3">Reunions</li>
      <li class="tag" data-tag-panel="4">Young Alumni</li>
      <li class="tag" data-tag-panel="5">Signature Events</li>
      <li class="tag" data-tag-panel="6">Athletics</li>
      <li class="tag" data-tag-panel="7">Arts</li>
    </ul>
  </header>
  <section>
    <?php

    $tagList = array('Homecoming', 'Reunions', 'YoungAlumni');

    $cp = new CalendarPuller(array(
      'showPastEvents'    => false,
      'showNextEvent'     => false,
      'maxUpcomingEvents' => 6,
      'cacheForSeconds'   => 10,
      'descriptionLength' => 255,
      'eventView'         => __DIR__ . '/views/event.html.twig',
      'eventsView'        => __DIR__ . '/views/events.html.twig',
      'tags'              => ['inclusive' => ['Alumni']]
    ));

    echo '<div id="tag-panel-1" class="tag-panel grid_36 alpha omega active">' . $cp->format(array('events' => $cp->upcomingEvents)) . '</div>';

    foreach ($tagList as $index => $tag) {
      $cp->tags = ['conjuction' => ['Alumni', $tag]];

      $cp->loadEvents();

      echo '<div id="tag-panel-' . ($index+2) . '" class="tag-panel grid_36 alpha omega">' . $cp->format(array('events' => $cp->upcomingEvents)) . '</div>';

    }

    $categoryList = array('Signature Event', 'Athletic', 'Art');

    $cp = new CalendarPuller(array(
      'showPastEvents'    => false,
      'showNextEvent'     => false,
      'maxUpcomingEvents' => 6,
      'cacheForSeconds'   => 10,
      'descriptionLength' => 255,
      'eventView'         => __DIR__ . '/views/event.html.twig',
      'eventsView'        => __DIR__ . '/views/events.html.twig',
    ));

    foreach ($categoryList as $index => $category) {
      $cp->categories = [$category];

      $cp->loadEvents();

      echo '<div id="tag-panel-' . ($index+5) . '" class="tag-panel grid_36 alpha omega">' . $cp->format(array('events' => $cp->upcomingEvents)) . '</div>';

    }

    ?>
  </section>

</div>

<!-- Events with Photos-->
<div id="events-with-photos" class="grid_36 alpha omega">
  <?php
    $cp = new CalendarPuller([
      'tags'              => ['inclusive' => ['Alumni Feature']],
      'eventView'         => __DIR__ . '/views/imageEvent.html.twig',
      'eventsView'        => __DIR__ . '/views/imageEvents.html.twig',
      'fileWidth'  => 345,
      'fileHeight' => 160,
      'fileCrop' => '345:160',
      'onlyEventsWithFiles' => true,
      'thickboxFiles' => false
    ]);
    echo $cp->format(['events' => $cp->upcomingEvents]);
  ?>
</div>

<!-- @Gustavus -->
<div class="grid_24 alpha">
  <div class="grid_24 alpha omega center">
    <a href="javascript:void">
      <img src="/gimli/w390/alumni/images/homepage/at-gustavus.png" alt="@Gustavus" />
    </a>
  </div>
  <div class="grid_24 alpha omega">
    <h3><?php echo date('F Y') ?> Issue</h3>
  </div>
  <div class="grid_15 alpha">
    <ul>
      <?php

        class Image {
          public static $src;

          public static $href;

          public static $title;

          public static function getFirstImageUrl($item, $itemNumber) {
            if ($itemNumber == 1) {
              $string = new GACString($item['content']);

              static::$src = $string->extractFirstImage();

              static::$href = $item['link'];

              static::$title = $item['title'];
            }

            return $item;
          }

        };

        $news = new RssGrabber(array(
          'maxItems'      => 5,
          'maxItemsPerFeed' => 5,
          'format'      => array(
            '<li><a href="%1$s">%2$s</a></li>',
          ),
          'callbacks' => 'Image::getFirstImageUrl',
        ));

        $news->add('http://alumni.blog.gustavus.edu/newsletter/feed/');

        echo $news->render();
      ?>
    </ul>
    <p><a href="javascript:void" class="button more">Read More</a></p>
  </div>
  <div class="grid_7 prefix_1 suffix_1 omega">
    <?php if (isset(Image::$src)) { ?>
      <a class="promo-photo" href="<?php echo Image::$href ?>" title="<?php echo Image::$title ?>">
        <img class="fancy" src="/gimli/w130?url=<?php echo Image::$src ?>" alt="<?php echo Image::$title ?>" />
      </a>
    <?php } ?>
  </div>
  <hr class="grid_24 alpha omega" />
</div>

<!-- More Headlines -->
<div class="grid_12 resource-boxes omega">
  <div class="resource-box news">
    <header>
      <h2>Other Gustavus News</h2>
    </header>
    <section>
      <?php
        $params  = array(
          'maxItems'        => 1,
          'format'          => '<li><a href="%1$s">%2$s</a></li>'
        );

        $r = '<ul>';

        $categories = ['first', 'second', 'third', 'fourth', 'fifth'];

        foreach ($categories as $category) {

          $news = new RssGrabber($params);
          $news->add('http://news.blog.gustavus.edu/category/for-news-page/' . $category . '/feed/');
          $r    .= $news->render();

        }

        echo $r . '</ul>';
      ?>
    </section>
  </div>
</div>

<!-- Resource Boxes -->
<div class="resource-boxes grid_24 alpha">
  <div class="grid_12 alpha resource-box affinity-groups">
    <header>
      <h2>Affinity Groups</h2>
    </header>
    <section>
      <?php

        $affinityTemplate = '{% spaceless %}
                              <div class="affinity-group {{ class }}"><a href="{{ link }}">
                                  <figure class="logo"></figure>
                                  <h3>{{ name }}</h3>
                                  <p class="subtitle">{{ supports }}</p>
                                </a></div>
                             {% endspaceless %}';

        $affinityGroups = array(
          [
            'name'     => 'Black & Gold',
            'supports' => 'Loyal Giving to the Annual Fund',
            'link'     => '/giving/stewardship/societies.php#black-and-gold',
            'class'    => 'black-and-gold'
          ],

          [
            'name'     => 'G Club',
            'supports' => 'Gustavus Athletics',
            'link'     => '/giving/gclub/',
            'class'    => 'g-club'
          ],

          [
            'name'     => '1862 Society',
            'supports' => 'Leadership Gifts to the Annual Fund',
            'link'     => '/giving/stewardship/societies.php#1862-Society',
            'class'    => 'eighteen-sixty-two-society'
          ],

          [
            'name'     => 'Gustavus Heritage',
            'supports' => 'Planned and Endowment Gifts',
            'link'     => '/giving/stewardship/societies.php#heritage-partnership',
            'class'    => 'heritage'
          ],

          [
            'name'     => 'Founders\' Society',
            'supports' => 'Cumulative Gifts of $150,000+',
            'link'     => '/giving/stewardship/societies.php#founders-society',
            'class'    => 'founders-society'
          ],

          [
            'name'     => 'Parents\' Club',
            'supports' => 'Gustavus Parents Fund',
            'link'     => '/giving/stewardship/societies.php#parents-club',
            'class'    => 'parents-club'
          ],

          [
            'name'     => 'Young Alumni',
            'supports' => 'Giving Time through Engagement',
            'link'     => '/alumni/youngalumni.php',
            'class'    => 'young-alumni'
          ],

          [
            'name'     => 'Friends of Music',
            'supports' => 'Gustavus Music and Ensemble Tours',
            'link'     => '/giving/friendsofmusic/',
            'class'    => 'friends-of-music'
          ],
        );

        shuffle($affinityGroups);

        echo TwigFactory::renderTwigStringTemplate($affinityTemplate, $affinityGroups[0]);
        echo TwigFactory::renderTwigStringTemplate($affinityTemplate, $affinityGroups[1]);
        echo TwigFactory::renderTwigStringTemplate($affinityTemplate, $affinityGroups[2]);

      ?>
      <p><a href="/giving/stewardship/societies.php" class="button">More Affinity Groups</a></p>
    </section>
  </div>
  <div class="grid_12 omega resource-box alumni-notes">
    <header>
      <h2>Alumni Notes</h2>
    </header>
    <section>
      <ul class="blog-stats">
          <li><a href="https://alumni.blog.gustavus.edu/tag/birth" data-blog-stat="tag:birth">Births and Adoptions</a></li>
          <li><a href="https://alumni.blog.gustavus.edu/tag/job" data-blog-stat="tag:job">Career</a></li>
          <li><a href="https://alumni.blog.gustavus.edu/tag/education" data-blog-stat="tag:education">Education</a></li>
          <li><a href="https://alumni.blog.gustavus.edu/tag/wedding" data-blog-stat="tag:wedding">Engagements/Marriages</a></li>
          <li><a href="https://alumni.blog.gustavus.edu/category/submitted" data-blog-stat="category:submitted">News</a></li>
      </ul>

      <p>We'd like to hear what you've been up to lately! Please <a href="/alumni/submit">share your news</a> with us!</p>
    </section>
  </div>
</div>

<!-- Social Media -->
<div class="grid_12 omega social-media">
  <h3 class="facebook">Like Us On Facebook</h3>
  <ul>
    <li><a href="https://www.facebook.com/gustavusadolphuscollege">Gustavus Adolphus College</a></li>
    <li><a href="https://www.facebook.com/GustavusYPG">Gustavus Young Alumni</a></li>
    <li><a href="https://www.facebook.com/gustavusadolphuscollege/app_190322544333196">Class Group Pages</a></li>
  </ul>
  <h3 class="twitter">Follow Us On Twitter</h3>
  <ul>
    <li><a href="https://twitter.com/GustieAlum">Gustavus Alumni</a></li>
    <li><a href="https://twitter.com/gustavusYA">Gustavus Young Alumni</a></li>
    <li><a href="https://twitter.com/gustavus">Gustavus Adolphus College</a></li>
  </ul>
  <h3 class="linkedin">Connect On LinkedIn</h3>
  <ul>
    <li><a href="https://www.linkedin.com/groups?home=&amp;gid=70212">Gustavus Alumni</a></li>
    <li><a href="https://www.linkedin.com/groups?home=&amp;gid=4885086">Gustavus Young Alumni</a></li>
  </ul>
</div>

<div class="grid_36 alpha omega grey-box">
  <div class="grid_12 alpha center">
    <p>Alumni &amp; Parent Engagement</p>
  </div>
  <div class="grid_12 center">
    <p><a href="mailto:alumni@gustavus.edu">alumni@gustavus.edu</a></p>
  </div>
  <div class="grid_12 omega center">
    <p><a href="tel:+18004878437">800-487-8437</a></p>
  </div>
</div>

<!-- InstanceEndEditable -->

<div id="focusBox">
<!-- InstanceBeginNonEditable name="FocusBox" -->
<!-- InstanceEndNonEditable -->
</div>

</div> <!-- #page-content -->

<div class="clear">&nbsp;</div>

</div> <!-- .container_48 -->
</div> <!-- #body-container -->
</div> <!-- #body -->

<div id="footer-container">
<div class="container_48">
&nbsp;
</div> <!-- .container_48 -->
</div> <!-- #footer-container -->

</body>
<!-- InstanceEnd --></html>
<?php TemplatePageRequest::end(); ?>