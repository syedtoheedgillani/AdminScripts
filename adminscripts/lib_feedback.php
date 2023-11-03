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
?>