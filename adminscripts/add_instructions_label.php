<?php
require(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../course/modlib.php');
require_once(__DIR__.'/../../mod/label/lib.php');

// Get a list of all course IDs
$courses = $DB->get_records('course', null, '', 'id');

// Define the HTML content for the label.
$label_content = '<h5>4 Steps to Earn CE Credit:</h5>
<p dir="ltr"></p>
<ol>
    <li><span style="font-size: 0.9375rem;">Review the course materials</span></li>
    <li><span style="font-size: 0.9375rem;">Pass the CE test (3 chances to take)</span></li>
    <li><span style="font-size: 0.9375rem;">Complete the course evaluation</span></li>
    <li><span style="font-size: 0.9375rem;">Print your certificate of completion&nbsp;</span></li>
</ol>
<p></p>
<p dir="ltr"><span style="font-size: 0.9375rem;">Please use the course index in the left panel (click the
        yellow&nbsp;bubble to open if not visible) to quickly navigate between course
        topics.</span></p>
<p dir="ltr" style="text-align: left;">Happy Learning!</p>'; // Replace with your HTML content.

foreach ($courses as $course) {

        $section = 0; // Use the first section

        $moduleinfo = new stdClass();
        $moduleinfo->course = $course->id;
        $moduleinfo->section = $section;
        $moduleinfo->module = $DB->get_field('modules', 'id', array('name' => 'label'));
        $moduleinfo->modulename = 'label';
        $moduleinfo->visible = 1;
        $moduleinfo->visibleold = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo = set_moduleinfo_defaults($moduleinfo);
        $moduleinfo->name = 'Instructions';
        $moduleinfo->intro = $label_content;
        $moduleinfo->introformat = 1;
        $moduleinfo->timemodified = time();

        $moduleinfo = add_moduleinfo ($moduleinfo, $course);

        echo "Instructions has been added in the Course with the ID: {$course->id}, in Section $section.<br><br>";
    }
