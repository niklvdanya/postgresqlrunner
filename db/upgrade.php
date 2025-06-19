<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_qtype_postgresqlrunner_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025061803) {
        $table = new xmldb_table('qtype_postgresqlrunner_options');

        $field = new xmldb_field('extra_code', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'environment_init');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $DB->execute("UPDATE {qtype_postgresqlrunner_options} SET extra_code = '' WHERE extra_code IS NULL");

        $oldversion = 2025061803;
    }

    if ($oldversion < 2025061905) {
        $table = new xmldb_table('qtype_postgresqlrunner_options');
        $field = new xmldb_field('question_bank', XMLDB_TYPE_TEXT, null, null, false, null, null, 'sqlcode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('use_question_bank', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'question_bank');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('sqlcode', XMLDB_TYPE_TEXT, null, null, false, null, null, 'questionid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }

        $oldversion = 2025061905;
    }

    return true;
}