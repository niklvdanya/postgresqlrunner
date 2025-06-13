<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_qtype_postgresqlrunner_install() {
    global $CFG, $DB;

    $dbinfo = $DB->get_server_info();
    if ($dbinfo['dbtype'] !== 'pgsql') {
        return true;
    }

    $config = require($CFG->dirroot . '/question/type/postgresqlrunner/config.php');
    $conn_details = json_decode($config['db_connection'], true);
    if (!$conn_details || !isset($conn_details['host'], $conn_details['dbname'], $conn_details['user'], $conn_details['password'])) {
        mtrace("Error: Invalid database connection parameters in config.php");
        return false;
    }

    $test_conn = @pg_connect("host={$conn_details['host']} port=" . (isset($conn_details['port']) ? $conn_details['port'] : 5432) . 
                             " dbname=postgres user={$conn_details['user']} password={$conn_details['password']}");
    if (!$test_conn) {
        mtrace("Error: Cannot connect to PostgreSQL server: " . pg_last_error());
        return false;
    }

    $db_exists = pg_query($test_conn, "SELECT 1 FROM pg_database WHERE datname = '" . pg_escape_string($test_conn, $conn_details['dbname']) . "'");
    if (!$db_exists || pg_num_rows($db_exists) == 0) {
        mtrace("Error: Database {$conn_details['dbname']} does not exist");
        pg_close($test_conn);
        return false;
    }
    pg_close($test_conn);

    $sql_file = $CFG->dirroot . '/question/type/postgresqlrunner/sql/init.sql';
    if (!file_exists($sql_file)) {
        mtrace("Error: SQL initialization file not found at {$sql_file}");
        return false;
    }

    $sql = file_get_contents($sql_file);
    $commands = [];
    $current_command = '';
    $lines = explode("\n", $sql);

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '--') === 0) {
            continue;
        }

        $current_command .= ' ' . $line;
        if (substr(rtrim($line), -1) === ';') {
            $commands[] = trim($current_command);
            $current_command = '';
        }
    }

    if (!empty($current_command)) {
        $commands[] = trim($current_command);
    }

    foreach ($commands as $command) {
        if (empty($command)) {
            continue;
        }

        try {
            $DB->execute($command);
        } catch (\Exception $e) {
            mtrace("Warning: Could not execute SQL command: " . $command);
            mtrace("Error: " . $e->getMessage());
        }
    }

    try {
        $tables = ['qtype_postgresqlrunner_session', 'qtype_postgresqlrunner_roles'];
        $table_exists = [];

        foreach ($tables as $table) {
            $table_exists[$table] = $DB->get_manager()->table_exists($table);
        }

        if (!$table_exists['qtype_postgresqlrunner_session']) {
            $table = new xmldb_table('qtype_postgresqlrunner_session');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('token', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sessionid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('data', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('expiry', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('created', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('token', XMLDB_KEY_UNIQUE, array('token'));

            $table->add_index('sessionid', XMLDB_INDEX_NOTUNIQUE, array('sessionid'));
            $table->add_index('expiry', XMLDB_INDEX_NOTUNIQUE, array('expiry'));
            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

            $DB->get_manager()->create_table($table);
        }

        if (!$table_exists['qtype_postgresqlrunner_roles']) {
            $table = new xmldb_table('qtype_postgresqlrunner_roles');
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('rolename', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sessionid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('created', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('expiry', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('rolename', XMLDB_KEY_UNIQUE, array('rolename'));

            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
            $table->add_index('expiry', XMLDB_INDEX_NOTUNIQUE, array('expiry'));

            $DB->get_manager()->create_table($table);
        }
    } catch (\Exception $e) {
        mtrace("Error during table creation: " . $e->getMessage());
        return false;
    }

    return true;
}