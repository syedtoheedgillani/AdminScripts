<?php

/**
 * Admin Scripts to import Quiz activities.
 *
 * @package    local_adminscripts
 * @copyright  2007-2022 Mahtab Hussain, Syed {@link http://paktaleem.org}
 * @license    All rights reserved.
 */

use core\session\file;

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/../../mod/quiz/lib.php');
require_once(__DIR__.'/lib_deletepre.php');     // Delete all existing questions in categories
require_once(__DIR__.'/../../course/modlib.php');
require_once(__DIR__.'/../../question/category_class.php');
require_once(__DIR__.'/../../lib/questionlib.php');


require_login();
$PAGE->set_pagelayout('admin');
$PAGE->set_context(context_system::instance());

$PAGE->set_url('/local/adminscripts/quizimport.php', array());
$PAGE->set_title(get_string('adminscripts', 'local_adminscripts'));
$PAGE->set_heading(get_string('quizimport', 'local_adminscripts'));
$PAGE->navbar->add(get_string('quizimport', 'local_adminscripts'));
echo $OUTPUT->header();

// Get path to the folder where feedback files are present

$table = new html_table();
$table->attributes['class'] = 'generaltable local_quizimport';
$table->head  = array (get_string('coursename', 'local_adminscripts'), 'Questions Import', 'Quiz');
$table->align = array ('left', 'left', 'left');

// Start a loop
$quizzesfolder = $CFG->dataroot .'/repository/quizzes';
    $scan = scandir($quizzesfolder);
    $options = array();
    if(!empty($scan)){
        $quizfiles = array();
        foreach($scan as $file) {
            if (is_file($quizzesfolder . "/$file") && ($file <> '.' && $file <> '..')) {
                $quizfiles[substr($file,0,-4)] = $quizzesfolder . '/' . $file;
            }
        }

        
        foreach($quizfiles as $filename => $filepath) {
            // for a max_execution_time = 300, 1000 feedback activities can be processed.
            if ($filename <= 2000) {
                echo 'Course id: ' . $filename . '<br>';

                //Delete any existing Quiz activity
                $mods = get_course_mods($filename);
                if(!empty($mods)){
                    foreach ($mods as $mod) {
                        if ($mod->modname == 'quiz') {
                            // Delete the quiz
                            if(course_delete_module($mod->id)){
                                echo 'Previous Quiz activity deleted.';
                            }
                        }
                    }
                }
                // Create a question category in system context name be like "courseid_coursename"
                $catdata = new stdClass();
                $catdata->infoformat = 1;
                $catdata->info       = '';
                $category = create_question_category($filename, $catdata->info, $catdata->infoformat);
                if (is_object($category)) {
                    // Process question import
                    $questionimportresults = import_questions_into_category($category, $filepath);
                    if ($questionimportresults->statusid == 7 || $questionimportresults->statusid == 4){
                        // Create a quiz activity in relevant course
                        $quiz = create_new_quiz($filename, $category->id);

                        // Add questions to the quiz activity
                        quiz_add_random_questions($quiz, 1, $category->id, 10, 0);
                        quiz_delete_previews($quiz);
                        quiz_update_sumgrades($quiz);


                        // Add progress data in a table
                        $courseurl = new moodle_url('/course/view.php', array('id' => $quiz->course));
                        $course = get_course($quiz->course);
                        $coursename = html_writer::link($courseurl, $course->fullname);

                        $quizurl = new moodle_url('/mod/quiz/view.php', array('id' => $quiz->coursemodule));
                        $quizname = html_writer::link($quizurl, 'Created '. $quiz->name);
                        if($quiz->id > 0 ) {
                            $activitystatus = $quizname;
                        } else {
                            $activitystatus = 'Failed';
                        }


                        $table->data[] = array (
                            $coursename, $questionimportresults->statustext, $activitystatus);
                    }
                }
            } else if ($category == 0) {
                echo 'Category could not be created';
            }

        }
    }

// End of the loop

// Show the progress data on page.
echo html_writer::table($table);

echo $OUTPUT->footer();