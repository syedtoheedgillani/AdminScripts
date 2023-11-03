<?php
require(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../course/modlib.php');
require_once(__DIR__.'/../../mod/label/lib.php');

// Get a list of all course IDs
$courses = $DB->get_records('course', null, '', 'id');

// Define the HTML content for the label.
$label_content = '<p style="text-align: center;"><img src="https://adriandev.paktaleem.org/local/adminscripts/pix/congratssvg.svg" alt="A Graduation Cap and the phrase Congrats!" width="300" height="300" role="presentation" class="img-fluid atto_image_button_text-bottom" style="font-size: 0.9375rem;"></p>
<p style="text-align: center;"><strong style="font-size: 0.9375rem;">Congratulations on the successful completion of your CE course!</strong><br></p>
<p dir="ltr" style="text-align: center;">To access your certificate, please click the link below:</p>
<p dir="ltr" style="text-align: center;"><a href="https://adriandev.paktaleem.org/local/legacy/report">View Certificate</a><br></p>'; // Replace with your HTML content.

foreach ($courses as $course) {
    // Find the last section in the course
    $last_section = $DB->get_record_sql(
        "SELECT MAX(section) AS lastsection FROM {course_sections} WHERE course = :courseid",
        array('courseid' => $course->id) // Use $course->id instead of $course_id
    );

    if (!$last_section || !isset($last_section->lastsection)) {
        echo "Could not determine the last section of the course.";
    } else {
        $section = $last_section->lastsection; // Use the next available section

        $moduleinfo = new stdClass();
        $moduleinfo->course = $course->id;
        $moduleinfo->section = $section;
        $moduleinfo->module = $DB->get_field('modules', 'id', array('name' => 'label'));
        $moduleinfo->modulename = 'label';
        $moduleinfo->visible = 1;
        $moduleinfo->visibleold = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo = set_moduleinfo_defaults($moduleinfo);
        $moduleinfo->name = 'Congratulations';
        $moduleinfo->intro = $label_content;
        $moduleinfo->introformat = 1;
        $moduleinfo->timemodified = time();

        $moduleinfo = add_moduleinfo ($moduleinfo, $course);

        echo "Certificate has been added in the Course with the ID: {$course->id}, in Section $section.<br><br>";
    }
}
