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

$table = new html_table();
$table->attributes['class'] = 'generaltable local_feedbackimport';
$table->head  = array (get_string('coursename', 'local_adminscripts'), 'Feedback', 'Questions Imported');
$table->align = array ('left', 'left', 'left');

// Start a loop
$feedbacksfolder = $CFG->dataroot .'/repository/feedback';
    $scan = scandir($feedbacksfolder);
    $options = array();
    if(!empty($scan)){
        $feedbackfiles = array();
        foreach($scan as $file) {
            if (is_file($feedbacksfolder . "/$file") && ($file <> '.' && $file <> '..')) {
                $feedbackfiles[substr($file,0,-4)] = $feedbacksfolder . '/' . $file;
            }
        }

        foreach($feedbackfiles as $filename => $filepath) {
            // for a max_execution_time = 300, 1000 feedback activities can be processed.
            if ($filename <= 1000) {
            // Create a feedback activity in relevant course
            $courseid = $filename;
                if ($course = get_course($courseid)) {
                    $section = 1;
                    $add = 1;

                    $moduleinfo = new stdClass();
                    $moduleinfo->course = $course->id;
                    $moduleinfo->section = $section;
                    $moduleinfo->module = $DB->get_field('modules','id',array('name' => 'feedback'));
                    $moduleinfo->modulename = 'feedback';
                    $moduleinfo->visible = 1;
                    $moduleinfo->visibleold = 1;
                    $moduleinfo->visibleoncoursepage = 1;
                    $moduleinfo = set_moduleinfo_defaults($moduleinfo);
                    $moduleinfo->name = 'Course Feedback';
                    $moduleinfo->intro = '';
                    $moduleinfo->introformat = 1;
                    $moduleinfo->anonymous = 1;
                    $moduleinfo->email_notification = 0;
                    $moduleinfo->multiple_submit = 0;
                    $moduleinfo->autonumbering = 0;
                    $moduleinfo->site_after_submit = '';
                    $moduleinfo->page_after_submit = '';
                    $moduleinfo->page_after_submit_editor = array('text' => '', 'itemid' => 0, 'format' => 1);
                    $moduleinfo->page_after_submitformat = 1;
                    $moduleinfo->publish_stats = 0;
                    $moduleinfo->timeopen = 0;
                    $moduleinfo->timeclose = 0;
                    $moduleinfo->timemodified = time();
                    $moduleinfo->completionsubmit = 0;

                    $moduleinfo = add_moduleinfo ($moduleinfo, $course);
                    list($module, $context, $cw, $cm, $data) = prepare_new_moduleinfo_data($course, $moduleinfo->modulename, $section);

                    // Process the file to import feedback questions
                    $xmlcontent = file_get_contents($filepath);

                    if (!$xmldata = feedback_load_xml_data($xmlcontent)) {
                        $importstatus = html_writer::tag('p', 'Cannot load XML file.'. $filename);
                    }

                    $importerror = feedback_import_loaded_data($xmldata, $moduleinfo->id);
                    if ($importerror->stat == true) {
                        $importstatus = 'Imported.';
                    } else {
                        $importstatus = 'Failed '. $importerror->status;
                    }

                    // Add progress data in a table
                    $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
                    $coursename = html_writer::link($courseurl, $course->fullname);
                    if($moduleinfo->id > 0 ) {
                        $activitystatus = 'Created';
                    } else {
                        $activitystatus = 'Failed';
                    }
                    $table->data[] = array (
                        $coursename, $activitystatus, $importstatus);
                }
            } else {
                $table->data[] = array (
                    'Course with id '.$filename. ' does not exist.', 'N/A', 'N/A');
            }
        }
    }

// End of the loop

// Show the progress data on page.
echo html_writer::table($table);

echo $OUTPUT->footer();