<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_qtype_postgresqlrunner_upgrade($oldversion) {
    global $CFG, $DB;
    
    $dbman = $DB->get_manager();
    
    if ($oldversion < 2025050301) {
        // Проверка и создание роли student_role
        $dbinfo = $DB->get_server_info();
        if ($dbinfo['dbtype'] === 'pgsql') {
            try {
                $exists = $DB->record_exists_sql("SELECT 1 FROM pg_roles WHERE rolname = 'student_role'");
                
                if (!$exists) {
                    $sql_file = $CFG->dirroot . '/question/type/postgresqlrunner/sql/init.sql';
                    if (file_exists($sql_file)) {
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
                    }
                }
            } catch (Exception $e) {
                mtrace("Warning: Could not check for student_role existence");
                mtrace("Error: " . $e->getMessage());
            }
        }
        
        upgrade_plugin_savepoint(true, 2025050301, 'qtype', 'postgresqlrunner');
    }
    
    return true;
}