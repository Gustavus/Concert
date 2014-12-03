<?php
$templatePreferences  = array(
  'localNavigation'   => TRUE,
  'auxBox'        => FALSE,
  'templateRevision'    => 1,
  'focusBoxColumns'   => 12,
//  'bannerDirectory'   => 'general',
//  'view'          => 'template/views/general.html',
);

use Gustavus\TemplateBuilder\Builder as GACBuilder;

GACBuilder::init();

$templateBuilderProperties = [];
ob_start();
?>
<?php
$templateBuilderProperties['head'] = ob_get_contents();
ob_clean();
?>

<script src="/modules/javascript/AC_QuickTime.js"
              language="JavaScript" type="text/javascript">
            </script>

<?php
$templateBuilderProperties['javascripts'] = ob_get_contents();
ob_clean();
?>

Local Nav Here

<?php
$templateBuilderProperties['localNavigation'] = ob_get_contents();
ob_clean();
?>

Lindau Symposium

<?php
$templateBuilderProperties['title'] = ob_get_contents();
ob_clean();
?>

<?php
$templateBuilderProperties['subtitle'] = ob_get_contents();
ob_clean();
?>

<p class="leadin"><span class="three-line-dropcap">G</span>ustavus to host the 2013 Lindau Symposium featuring distinguished author, Dr. Arthur C. Brooks as the keynote speaker.
    Dr. Brooks will give a lecture on
          <em><strong>&quot;In Pursuit of Happiness&quot; </strong></em>on <strong>Thursday</strong>,<strong> April 18, 2013 at 7:00 p.m.
    in Alumni Hall</strong>.</p>

      <h4><a href="http://www.gustavus.edu/go/streaming" class="button">Watch the lecture live!</a></h4>

      <p>Arthur C. Brooks is an expert on public policy and economics who has published extensively on social entrepreneurship and the connections between culture, politics and economic life. A behavioral economist by training, he has written three books on the social value and economic impact of nonprofits and charitable giving. He is the author of a major study of association membership titled Generations and the Future of Association Participation.<br />
        </p>
        <p>His book, <em>The Battle</em>, looks at the new culture war facing America, a battle between free enterprise and social democracy. In 2008, Arthur released two books—<em>Gross National Happiness</em>, which explores the relationship between values and happiness and his book <em>Social Entrepreneurship </em>combines the methods of the entrepreneur with leading edge nonprofit and public management tools.<br />
        </p>
        <p>Arthur’s first book offers surprising perspectives on the values and practices of conservatives and liberals in America. <em>Who Really Cares: The Surprising Truth About Compassionate Conservatism</em> proves with hard numbers that conservatives give far more to charity than liberals and explains why values make the difference.<br />
        </p>
        <p>Arthur C. Brooks is President of the American Enterprise Institute. Previously, he was Louis A. Bantle Professor of Business and Government Policy at Syracuse University’s Maxwell School of Citizenship and Public Affairs and Whitman School of Management and Research Director of the William E. Smith Institute for Association Research. He contributes regularly to The Wall Street Journal’s editorial page.</p>
    <p>For more information contact the President's Office at<a href="http://presidentevents@gustavus.edu"> presidentevents@gustavus.edu</a> or 507-933-7538.</p>

    <p>&nbsp;</p>



    <div class="center"></div>

<?php
$templateBuilderProperties['content'] = ob_get_contents();
ob_clean();
?>

<img src="/slir/w230/events/lindau/images/ABrooks-customerphoto07.jpg" alt="Dr. Arthur C. Brooks" class="fancy" />
    <h2>Lindau Symposium</h2>
    <h3>Arthur C. Brooks<br /><small>"Government Policy and Economics"</small></h3>
    <p><strong>7:00 p.m., Thursday, April 18, 2013</strong><br />
    Alumni Hall, Gustavus Adolphus College</p>

<?php
$templateBuilderProperties['focusBox'] = ob_get_contents();
ob_clean();

echo (new Builder($templateBuilderProperties, $templatePreferences))->render();
?>