<?php

/**
 * Admin Scripts to import feedback activities.
 *
 * @package    local_adminscripts
 * @copyright  2007-2022 Mahtab Hussain, Syed {@link http://paktaleem.org}
 * @license    All rights reserved.
 */

use core\session\file;

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../mod/feedback/lib.php');
require_once(__DIR__.'/lib_feedback.php');
require_once(__DIR__.'/../../course/modlib.php');

require_login();
$PAGE->set_pagelayout('admin');
$PAGE->set_context(context_system::instance());

$PAGE->set_url('/local/adminscripts/feedbackimport.php', array());
$PAGE->set_title(get_string('adminscripts', 'local_adminscripts'));
$PAGE->set_heading(get_string('feedbackimport', 'local_adminscripts'));
$PAGE->navbar->add(get_string('feedbackimport', 'local_adminscripts'));
echo $OUTPUT->header();

// Get path to the folder where feedback files are present
$feedbacksfolder = $CFG->dataroot . '/repository/feedback';
$scan = scandir($feedbacksfolder);
$options = array();

// Create an instance of html_table
$table = new html_table();
$table->head = array('Course Name', 'Status', 'Import Status');
$table->data = array();

if (!empty($scan)) {
    $feedbackfiles = array();
    foreach ($scan as $file) {
        if (is_file($feedbacksfolder . "/$file") && ($file <> '.' && $file <> '..')) {
            $feedbackfiles[substr($file, 0, -4)] = $feedbacksfolder . '/' . $file;
        }
    }

    foreach ($feedbackfiles as $filename => $filepath) {
        // Check if the course exists with the given course id
        $courseid = $filename;
        if ($course = $DB->get_record('course', array('id' => $courseid))) {
            $section = 1;
            $moduleinfo = new stdClass();
            $moduleinfo->course = $course->id;
            $moduleinfo->section = $section;
            $moduleinfo->modulename = 'feedback';

            // Process the file to import feedback questions
            $xmlcontent = file_get_contents($filepath);

            if (!$xmldata = feedback_load_xml_data($xmlcontent)) {
                $importstatus = html_writer::tag('p', 'Cannot load XML file.' . $filename);
            }

            // Retrieve the existing feedback activity
            $feedbackactivity = $DB->get_record('feedback', array('course' => $course->id, 'name' => 'Course Feedback'));

            if ($feedbackactivity) {
                // Remove old questions
                feedback_delete_all_items($feedbackactivity->id);

                // Import the new questions
                $importerror = feedback_import_loaded_data($xmldata, $feedbackactivity->id);

                if ($importerror->stat == true) {
                    $importstatus = 'Imported.';
                } else {
                    $importstatus = 'Failed ';
                }
            } else {
                $importstatus = 'No feedback activity found.';
            }

            // Add progress data in a table
            $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
            $coursename = html_writer::link($courseurl, $course->fullname);
            $table->data[] = array (
                $coursename, 'Feedback Updated', $importstatus);
        } else {
            $table->data[] = array (
                'Course with id ' . $filename . ' does not exist.', 'N/A', 'N/A');
        }
    }
}

$updatedCount = 0;
$errorCount = 0;

foreach ($table->data as $row) {
    $status = $row[1];

    if ($status === 'Feedback Updated') {
        $updatedCount++;
    } elseif ($status === 'N/A') {
        $errorCount++;
    }
}

echo "Courses Updated: $updatedCount<br>";
echo "Courses with Error: $errorCount";

// Show the progress data on the page.
echo html_writer::table($table);

echo $OUTPUT->footer();