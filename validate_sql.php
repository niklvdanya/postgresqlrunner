<?php

define('AJAX_SCRIPT', true);

require_once(dirname(__DIR__, 3) . '/config.php'); 

require_sesskey();      
require_login();        

header('Content-Type: application/json');

$use_question_bank = optional_param('use_question_bank', 0, PARAM_INT);
$question_bank = optional_param('question_bank', '', PARAM_RAW);
$sql = optional_param('sql', '', PARAM_RAW);
$environment_init = optional_param('environment_init', '', PARAM_RAW);
$extra_code = optional_param('extra_code', '', PARAM_RAW);

try {
    require_once($CFG->dirroot .
        '/question/type/postgresqlrunner/classes/security/sql_validator.php');
    require_once($CFG->dirroot .
        '/question/type/postgresqlrunner/classes/security/connection_manager.php');

    if ($use_question_bank) {
        if (empty(trim($question_bank))) {
            throw new Exception(get_string('questionbankrequired', 'qtype_postgresqlrunner'));
        }
        $question_bank_data = json_decode($question_bank, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(get_string('invalidjson', 'qtype_postgresqlrunner'));
        }
        foreach ($question_bank_data as $task) {
            if (!isset($task['sqlcode']) || !isset($task['questiontext'])) {
                throw new Exception(get_string('invalidquestionbankformat', 'qtype_postgresqlrunner'));
            }
            \qtype_postgresqlrunner\security\sql_validator::validate_sql($task['sqlcode']);
        }
    } else {
        if (empty(trim($sql))) {
            throw new Exception(get_string('sqlcodeorquestionbankrequired', 'qtype_postgresqlrunner'));
        }
        \qtype_postgresqlrunner\security\sql_validator::validate_sql($sql);
    }

    if (!empty($environment_init)) {
        \qtype_postgresqlrunner\security\sql_validator::validate_sql($environment_init);
    }

    if (!empty($extra_code)) {
        \qtype_postgresqlrunner\security\sql_validator::validate_sql($extra_code);
        if (stripos(trim($extra_code), 'SELECT') !== 0) {
            throw new Exception(get_string('extracodemustselect', 'qtype_postgresqlrunner'));
        }
    }

    $config = require($CFG->dirroot .
        '/question/type/postgresqlrunner/config.php');

    $conn = \qtype_postgresqlrunner\security\connection_manager::get_connection(
                json_encode($config['db_connection']));

    \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($conn, 'BEGIN');
    
    if (!empty($environment_init)) {
        \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($conn, $environment_init);
    }
    
    if ($use_question_bank) {
        foreach ($question_bank_data as $task) {
            \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($conn, $task['sqlcode']);
        }
    } else {
        \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($conn, $sql);
    }
    
    if (!empty($extra_code)) {
        \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($conn, $extra_code);
    }
    
    \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($conn, 'ROLLBACK');
    pg_close($conn);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {   
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;