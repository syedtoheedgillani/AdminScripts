<?php
/**
 * Admin Scripts plugin permissions
 *
 * @package    local_adminscripts
 * @copyright  2007-2022 Mahtab Hussain, Syed {@link http://paktaleem.org}
 * @license    All rights reserved.
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = array(

    'local/adminscripts:runscripts' => array(
        'riskbitmask' => RISK_XSS,

        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/course:manageactivities'
    ),

);
