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

    return true;
}