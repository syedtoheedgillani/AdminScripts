<?php

/**
 * Admin Scripts plugin index
 *
 * @package    local_adminscripts
 * @copyright  2007-2022 Mahtab Hussain, Syed {@link http://paktaleem.org}
 * @license    All rights reserved.
 */


require(__DIR__.'/../../config.php');
require_once(__DIR__.'/locallib.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);

unset($id);

require_course_login($course, true);
$PAGE->set_pagelayout('incourse');

$PAGE->set_url('/mod/book/index.php', array('id' => $course->id));
$PAGE->set_title($course->shortname.': '.$strbooks);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strbooks);
echo $OUTPUT->header();

\mod_book\event\course_module_instance_list_viewed::create_from_course($course)->trigger();

// Create links to different administration scripts

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';


$strsectionname = get_string('sectionname', 'format_'.$course->format);
$table->head  = array ($strsectionname, $strname, $strintro);
$table->align = array ('center', 'left', 'left');


$table->data[] = array (
    $printsection,
    html_writer::link(new moodle_url('view.php', array('id' => $cm->id)), format_string($book->name), $class),
    format_module_intro('book', $book, $cm->id));

echo html_writer::table($table);

echo $OUTPUT->footer();
