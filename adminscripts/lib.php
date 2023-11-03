<?php

function feedback_load_xml_data($xmlcontent) {
    global $CFG;
    require_once($CFG->dirroot.'/lib/xmlize.php');

    if (!$xmlcontent = feedback_check_xml_utf8($xmlcontent)) {
        return false;
    }

    $data = xmlize($xmlcontent, 1, 'UTF-8');

    if (intval($data['FEEDBACK']['@']['VERSION']) != 200701) {
        return false;
    }
    $data = $data['FEEDBACK']['#']['ITEMS'][0]['#']['ITEM'];
    return $data;
}

function feedback_import_loaded_data(&$data, $feedbackid) {
    global $CFG, $DB;

    feedback_load_feedback_items();

    $deleteolditems = optional_param('deleteolditems', 0, PARAM_INT);

    $error = new stdClass();
    $error->stat = true;
    $error->msg = array();

    if (!is_array($data)) {
        $error->msg[] = get_string('data_is_not_an_array', 'local_adminscripts');
        $error->stat = false;
        return $error;
    }

    if ($deleteolditems) {
        feedback_delete_all_items($feedbackid);
        $position = 0;
    } else {
        //items will be add to the end of the existing items
        $position = $DB->count_records('feedback_item', array('feedback'=>$feedbackid));
    }

    //depend items we are storing temporary in an mapping list array(new id => dependitem)
    //we also store a mapping of all items array(oldid => newid)
    $dependitemsmap = array();
    $itembackup = array();
    foreach ($data as $item) {
        $position++;
        //check the typ
        $typ = $item['@']['TYPE'];

        //check oldtypes first
        switch($typ) {
            case 'radio':
                $typ = 'multichoice';
                $oldtyp = 'radio';
                break;
            case 'dropdown':
                $typ = 'multichoice';
                $oldtyp = 'dropdown';
                break;
            case 'check':
                $typ = 'multichoice';
                $oldtyp = 'check';
                break;
            case 'radiorated':
                $typ = 'multichoicerated';
                $oldtyp = 'radiorated';
                break;
            case 'dropdownrated':
                $typ = 'multichoicerated';
                $oldtyp = 'dropdownrated';
                break;
            default:
                $oldtyp = $typ;
        }

        $itemclass = 'feedback_item_'.$typ;
        if ($typ != 'pagebreak' AND !class_exists($itemclass)) {
            $error->stat = false;
            $error->msg[] = 'type ('.$typ.') not found';
            continue;
        }
        $itemobj = new $itemclass();

        $newitem = new stdClass();
        $newitem->feedback = $feedbackid;
        $newitem->template = 0;
        $newitem->typ = $typ;
        $newitem->name = trim($item['#']['ITEMTEXT'][0]['#']);
        $newitem->label = trim($item['#']['ITEMLABEL'][0]['#']);
        if ($typ === 'captcha' || $typ === 'label') {
            $newitem->label = '';
            $newitem->name = '';
        }
        $newitem->options = trim($item['#']['OPTIONS'][0]['#']);
        $newitem->presentation = trim($item['#']['PRESENTATION'][0]['#']);
        //check old types of radio, check, and so on
        switch($oldtyp) {
            case 'radio':
                $newitem->presentation = 'r>>>>>'.$newitem->presentation;
                break;
            case 'dropdown':
                $newitem->presentation = 'd>>>>>'.$newitem->presentation;
                break;
            case 'check':
                $newitem->presentation = 'c>>>>>'.$newitem->presentation;
                break;
            case 'radiorated':
                $newitem->presentation = 'r>>>>>'.$newitem->presentation;
                break;
            case 'dropdownrated':
                $newitem->presentation = 'd>>>>>'.$newitem->presentation;
                break;
        }

        if (isset($item['#']['DEPENDITEM'][0]['#'])) {
            $newitem->dependitem = intval($item['#']['DEPENDITEM'][0]['#']);
        } else {
            $newitem->dependitem = 0;
        }
        if (isset($item['#']['DEPENDVALUE'][0]['#'])) {
            $newitem->dependvalue = trim($item['#']['DEPENDVALUE'][0]['#']);
        } else {
            $newitem->dependvalue = '';
        }
        $olditemid = intval($item['#']['ITEMID'][0]['#']);

        if ($typ != 'pagebreak') {
            $newitem->hasvalue = $itemobj->get_hasvalue();
        } else {
            $newitem->hasvalue = 0;
        }
        $newitem->required = intval($item['@']['REQUIRED']);
        $newitem->position = $position;
        $newid = $DB->insert_record('feedback_item', $newitem);

        $itembackup[$olditemid] = $newid;
        if ($newitem->dependitem) {
            $dependitemsmap[$newid] = $newitem->dependitem;
        }

    }
    //remapping the dependency
    foreach ($dependitemsmap as $key => $dependitem) {
        $newitem = $DB->get_record('feedback_item', array('id'=>$key));
        $newitem->dependitem = $itembackup[$newitem->dependitem];
        $DB->update_record('feedback_item', $newitem);
    }

    return $error;
}

function feedback_check_xml_utf8($text) {
    //find the encoding
    $searchpattern = '/^\<\?xml.+(encoding=\"([a-z0-9-]*)\").+\?\>/is';

    if (!preg_match($searchpattern, $text, $match)) {
        return false; //no xml-file
    }

    if (isset($match[0]) AND !isset($match[1])) { //no encoding given. we assume utf-8
        return $text;
    }

    //encoding is given in $match[2]
    if (isset($match[0]) AND isset($match[1]) AND isset($match[2])) {
        $enc = $match[2];
        return core_text::convert($text, $enc);
    }
}

function create_question_category ($newcategory, $newinfo, $newinfoformat, $idnumber = null) {

    global $DB;

    $parentid = $DB->get_field('question_categories', 'id', array('name' => 'Default for System'));

    $context = context_system::instance();

    $cat = new stdClass();
    $cat->parent = $parentid;
    $cat->contextid = $context->id;
    $cat->name = $newcategory;
    $cat->info = $newinfo;
    $cat->infoformat = $newinfoformat;
    $cat->sortorder = 999;
    $cat->stamp = make_unique_id_code();
    $cat->idnumber = $idnumber;
    // Check if the category already exists
    if ($catPresent = $DB->get_record_sql("SELECT * FROM {question_categories} WHERE contextid = ? AND name LIKE ?", array($context->id, $newcategory))){
        return $catPresent;
    } else {
        $cat->id = $DB->insert_record("question_categories", $cat);

        //$category = new stdClass();
        $cat->contextid = $context->id;
        $event = \core\event\question_category_created::create_from_question_category_instance($cat);
        $event->trigger();

        if ($cat->id > 0) {
            return $cat;
        } else {
            return 0;
        }
    }
}

function as_readdata($filename) {
    /// Returns complete file with an array, one item per line

    if (is_readable($filename)) {
        $filearray = file($filename);

        /// Check for Macintosh OS line returns (ie file on one line), and fix
        if (preg_match("/\r/", $filearray[0]) AND !preg_match("/\n/", $filearray[0])) {
            return explode("\r", $filearray[0]);
        } else {
            return $filearray;
        }
    }
    return false;
}

function as_readquestions($lines) {
    /// Parses an array of lines into an array of questions,
    /// where each item is a question object as defined by
    /// readquestion().   Questions are defined as anything
    /// between blank lines.

    $questions = array();
    $currentquestion = array();

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            if (!empty($currentquestion)) {
                //if ($question = as_readquestion($currentquestion)) {
                    $questions[] = $currentquestion;
                //}
                $currentquestion = array();
            }
        } else {
            $currentquestion[] = $line;
        }
    }

    if (!empty($currentquestion)) {  // There may be a final question
        //if ($question = as_readquestion($currentquestion)) {
            $questions[] = $currentquestion;
        //}
    }

    return $questions;
}


/**
 * Count all non-category questions in the questions array.
 *
 * @param array questions An array of question objects.
 * @return int The count.
 *
 */
function as_count_questions($questions) {
    $count = 0;

    if (!is_array($questions)) {
        return $count;
    }
    foreach ($questions as $question) {
        if (!is_object($question) || !isset($question->qtype) ||
                ($question->qtype == 'category')) {
            continue;
        }
        $count++;
    }
    return $count;
}

function import_questions_into_category($category, $filename){

    global $DB, $COURSE;

    $contexts =  array(context_system::instance());
    // file checks out ok
    $fileisgood = false;

    $result = new stdClass();
    $result->file = $filename;
    $result->statusid = 0;
    $result->statustext = '';

    require_once(__DIR__ . '/../../question/format.php');
    require_once(__DIR__ . '/../../question/format/gift/format.php');

    // No. of questions already present in category
    $result->catquestions = $DB->count_records ('question', array('category' => $category->id));

    $qformat = new \qformat_gift();

    // load data into class
    $qformat->setCategory($category);
    $qformat->setContexts($contexts);
    $qformat->setCourse($COURSE);
    $qformat->setFilename($filename);
    $qformat->setRealfilename($filename);

    // Process questions in file
    if ($lines = as_readdata($qformat->filename)) {
        if ($filequestions = as_readquestions($lines)) {   // Extract all the questions
            $result->questions = $filequestions;
            $result->questionscount = count($filequestions);
            if ($result->questionscount == $result->catquestions) {

                $result->statusid = 7;
                $result->statustext = get_string('questionsalreadyimported', 'local_adminscripts');
            } else {
                if ($result->catquestions > 0 && as_count_questions($filequestions) > 0) {
                    // If some of the questions already imported, delete them first
                    foreach($result->catquestions as $question) {
                        question_delete_question($question->id);
                    }
                }
                if ($result->catquestions == 0) {
                    // Now start the import of all the questions
                    // Do anything before that we need to
                    if (!$qformat->importpreprocess()) {
                        $result->statusid = 1;
                        $result->statustext = get_string('errorwhilepreprocess', 'local_adminscripts');
                        return $result;
                    }
                    // Process the uploaded file
                    if (!$qformat->importprocess()) {
                        $result->statusid = 2;
                        $result->statustext = get_string('errorwhileimportprocess', 'local_adminscripts');
                        return $result;
                    }
                    // In case anything needs to be done after
                    if (!$qformat->importpostprocess()) {
                        $result->statusid = 3;
                        $result->statustext = get_string('errorwhilepostprocess', 'local_adminscripts');
                        return $result;
                    }
                    // Log the import into this category.
                    $eventparams = [
                            'contextid' => $qformat->category->contextid,
                            'other' => ['format' => 'gift', 'categoryid' => $qformat->category->id],
                    ];
                    $event = \core\event\questions_imported::create($eventparams);
                    $event->trigger();
                    $result->statusid = 4;
                    $result->statustext = get_string('questionimportsuccess', 'local_adminscripts');
                }
            }
        } else {
            $result->statusid = 5;
            $result->statustext = get_string('noquestionsinfile', 'question');
        }
    } else {
        $result->statusid = 6;
        $result->statustext = get_string('cannotread', 'question');
    }
    return $result;
}

function create_new_quiz($courseid, $questioncat, $section = 1, $noofquestions = 10, $passgrade = 80) {

    global $DB;

    $course = get_course($courseid);
    $add = 1;

    // Set quiz object with name received and default values
    $moduleinfo = new stdClass();
    $moduleinfo->name = 'CE Test';
    $moduleinfo->course = $course->id;
    $moduleinfo->timecreated = time();
    $moduleinfo->timemodified = time();
    $moduleinfo->timeopen = 0;
    $moduleinfo->timeclose = 0;
    $moduleinfo->timelimit = 0;
    $moduleinfo->overduehandling = 'autosubmit';
    $moduleinfo->graceperiod = 0;
    $moduleinfo->grade = 0;
    $moduleinfo->attempts = 3;
    $moduleinfo->questionsperpage = 1;
    $moduleinfo->repaginatenow = 0;
    $moduleinfo->navmethod = 'free';
    $moduleinfo->shuffleanswers = 0;
    $moduleinfo->preferredbehaviour = 'immediatefeedback';
    $moduleinfo->canredoquestions = 0;
    $moduleinfo->attemptonlast = 0;
    $moduleinfo->showuserpicture = 0;
    $moduleinfo->decimalpoints = 0;
    $moduleinfo->questiondecimalpoints = 0;
    $moduleinfo->showblocks = 0;
    $moduleinfo->subnet = 0;
    $moduleinfo->delay1 = 0;
    $moduleinfo->delay2 = 0;
    $moduleinfo->browsersecurity = 0;
    $moduleinfo->quizpassword = '';
    $moduleinfo->module = $DB->get_field('modules', 'id', array('name' => 'quiz'));
    $moduleinfo->visible = 1;
    $moduleinfo->visibleold = 1;
    $moduleinfo->visibleoncoursepage = 0;
    $moduleinfo->modulename = 'quiz';
    $moduleinfo->intro = '';
    $moduleinfo->introformat = 1;
    $moduleinfo->section = $section;
    // Review values
    $moduleinfo->attemptduring = 1;
    $moduleinfo->attemptimmediately = 1;
    $moduleinfo->attemptopen = 1;
    $moduleinfo->attemptclose = 0;

    $moduleinfo->correctnessduring = 1;
    $moduleinfo->correctnessimmediately = 1;
    $moduleinfo->correctnessopen = 1;
    $moduleinfo->correctnessclose = 0;

    $moduleinfo->marksduring = 1;
    $moduleinfo->marksimmediately = 1;
    $moduleinfo->marksopen = 1;
    $moduleinfo->marksclose = 0;

    $moduleinfo->specificfeedbackduring = 1;
    $moduleinfo->specificfeedbackimmediately = 1;
    $moduleinfo->specificfeedbackopen = 1;
    $moduleinfo->specificfeedbackclose = 0;

    $moduleinfo->generalfeedbackduring = 1;
    $moduleinfo->generalfeedbackimmediately = 1;
    $moduleinfo->generalfeedbackopen = 1;
    $moduleinfo->generalfeedbackclose = 0;

    $moduleinfo->rightanswerduring = 1;
    $moduleinfo->rightanswerimmediately = 1;
    $moduleinfo->rightansweropen = 1;
    $moduleinfo->rightanswerclose = 0;

    $moduleinfo->overallfeedbackduring = 1;
    $moduleinfo->overallfeedbackimmediately = 1;
    $moduleinfo->overallfeedbackopen = 1;
    $moduleinfo->overallfeedbackclose = 0;

    $moduleinfo = add_moduleinfo ($moduleinfo, $course);
    list($module, $context, $cw, $cm, $data) = prepare_new_moduleinfo_data($course, $moduleinfo->modulename, $section);

    return $moduleinfo;
}
?>