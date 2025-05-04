<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_qtype_postgresqlrunner_install() {
    global $CFG, $DB;
    
    $dbinfo = $DB->get_server_info();
    if ($dbinfo['dbtype'] !== 'pgsql') {
        return true;
    }
    
    $sql_file = $CFG->dirroot . '/question/type/postgresqlrunner/sql/init.sql';
    if (!file_exists($sql_file)) {
        return false;
    }
    
    $sql = file_get_contents($sql_file);
    $commands = explode(';', $sql);
    
    foreach ($commands as $command) {
        $command = trim($command);
        if (empty($command)) {
            continue;
        }
        
        try {
            $DB->execute($command);
        } catch (Exception $e) {
            mtrace("Warning: Could not execute SQL command: " . $command);
            mtrace("Error: " . $e->getMessage());
        }
    }
    
    return true;
}