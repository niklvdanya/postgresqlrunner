<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_qtype_postgresqlrunner_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    return true;
}