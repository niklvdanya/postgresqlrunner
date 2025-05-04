<?php
namespace qtype_postgresqlrunner\security;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/postgresqlrunner/classes/security/blacklist.php');

class connection_manager {
    
    public static function get_connection($db_connection) {
        global $CFG, $USER;
        
        $conn_details = json_decode($db_connection, true);
        if (!$conn_details || !is_array($conn_details)) {
            throw new \Exception('Некорректные параметры подключения к базе данных');
        }
        
        $required_fields = ['host', 'dbname', 'user', 'password'];
        foreach ($required_fields as $field) {
            if (!isset($conn_details[$field])) {
                throw new \Exception('Отсутствуют обязательные параметры подключения к базе данных');
            }
        }
        
        $dbhost = $conn_details['host'];
        $dbname = $conn_details['dbname'];
        $dbuser = $conn_details['user'];
        $dbpass = $conn_details['password'];
        $dbport = isset($conn_details['port']) ? (int)$conn_details['port'] : 5432;
        
        $session_id = session_id();
        $user_id = isset($USER->id) ? $USER->id : 0;
        $question_id = optional_param('questionid', 0, PARAM_INT);
        
        $unique_role_name = 'student_' . $user_id . '_q' . $question_id . '_' . 
                              substr(md5($session_id), 0, 8);
        $unique_role_password = bin2hex(random_bytes(16));
        
        $connection_params = array(
            'host' => $dbhost,
            'port' => $dbport,
            'dbname' => $dbname,
            'user' => $dbuser,
            'password' => $dbpass,
            'options' => "--client_encoding=UTF8"
        );
        
        $conn = @pg_connect(self::build_connection_string($connection_params));
        
        if (!$conn) {
            throw new \Exception('Не удалось подключиться к базе данных PostgreSQL');
        }
        
        pg_query($conn, "SET statement_timeout TO 2000");
        pg_query($conn, "SET search_path TO public");
        pg_query($conn, "SET client_min_messages TO warning");
        
        self::setup_isolated_role($conn, $unique_role_name, $unique_role_password, $dbname, $user_id, $question_id);
        
        return $conn;
    }
    
    private static function setup_isolated_role($conn, $role_name, $role_password, $dbname, $user_id, $question_id) {
        $role_exists = false;
        
        $role_check_result = pg_query_params($conn, 
            "SELECT 1 FROM pg_roles WHERE rolname = $1", 
            array($role_name)
        );
        
        if ($role_check_result && pg_num_rows($role_check_result) > 0) {
            $role_exists = true;
            pg_free_result($role_check_result);
        }
    
        if (!$role_exists) {
            $escaped_role_name = pg_escape_identifier($conn, $role_name);
            $escaped_password = pg_escape_literal($conn, $role_password);
            
            pg_query($conn, "BEGIN");
            
            try {
                $create_query = "CREATE ROLE " . $escaped_role_name . 
                               " WITH LOGIN PASSWORD " . $escaped_password . 
                               " CONNECTION LIMIT 3";
                               
                $result = pg_query($conn, $create_query);
                
                if (!$result) {
                    throw new \Exception('Не удалось создать временную роль для выполнения запросов');
                }
                pg_free_result($result);
                
                $alter_validity = "ALTER ROLE " . $escaped_role_name . " VALID UNTIL " . 
                                  pg_escape_literal($conn, date('Y-m-d H:i:s', time() + 1800));
                
                $result = pg_query($conn, $alter_validity);
                if (!$result) {
                    pg_query($conn, "COMMENT ON ROLE " . $escaped_role_name . 
                            " IS 'Временная роль для учебных заданий, создана " . date('Y-m-d H:i:s') . "'");
                } else {
                    pg_free_result($result);
                }
                
                $schema_name = 'student_' . $user_id . '_q' . $question_id;
                $escaped_schema = pg_escape_identifier($conn, $schema_name);
                
                $schema_exists = false;
                $schema_check = pg_query_params($conn, 
                    "SELECT 1 FROM information_schema.schemata WHERE schema_name = $1", 
                    array($schema_name)
                );
                
                if ($schema_check && pg_num_rows($schema_check) > 0) {
                    $schema_exists = true;
                }
                
                if ($schema_check) {
                    pg_free_result($schema_check);
                }
                
                if (!$schema_exists) {
                    $create_schema = "CREATE SCHEMA " . $escaped_schema;
                    pg_query($conn, $create_schema);
                }
                
                $escaped_dbname = pg_escape_identifier($conn, $dbname);
                
                $grant_connect = "GRANT CONNECT ON DATABASE " . $escaped_dbname . " TO " . $escaped_role_name;
                pg_query($conn, $grant_connect);
                
                $grant_usage_public = "GRANT USAGE ON SCHEMA public TO " . $escaped_role_name;
                pg_query($conn, $grant_usage_public);
                
                $grant_public = "GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA public TO " . $escaped_role_name;
                pg_query($conn, $grant_public);
                
                $grant_create = "GRANT CREATE ON SCHEMA public TO " . $escaped_role_name;
                pg_query($conn, $grant_create);
                
                $grant_usage_schema = "GRANT ALL ON SCHEMA " . $escaped_schema . " TO " . $escaped_role_name;
                pg_query($conn, $grant_usage_schema);
                
                $set_search_path = "ALTER ROLE " . $escaped_role_name . " SET search_path = " . 
                                  $escaped_schema . ", public";
                pg_query($conn, $set_search_path);
                
                $revoke_info_schema = "REVOKE ALL ON SCHEMA information_schema FROM " . $escaped_role_name;
                pg_query($conn, $revoke_info_schema);
                
                $revoke_pg_catalog = "REVOKE ALL ON SCHEMA pg_catalog FROM " . $escaped_role_name;
                pg_query($conn, $revoke_pg_catalog);
                
                pg_query($conn, "COMMIT");
                
            } catch (\Exception $e) {
                pg_query($conn, "ROLLBACK");
                throw $e;
            }
        }
    
        $set_role_query = "SET ROLE " . pg_escape_identifier($conn, $role_name);
        $result = pg_query($conn, $set_role_query);
        if (!$result) {
            throw new \Exception('Не удалось переключиться на ограниченную роль');
        }
        pg_free_result($result);
    }
    
    public static function cleanup_resources($conn, $user_id, $question_id) {
        pg_query($conn, "RESET ROLE");
        
        $role_pattern = 'student_' . $user_id . '_q' . $question_id . '_%';
        $role_result = pg_query_params($conn, 
            "SELECT rolname FROM pg_roles WHERE rolname LIKE $1", 
            array($role_pattern)
        );
        
        if ($role_result) {
            while ($row = pg_fetch_assoc($role_result)) {
                $role_name = $row['rolname'];
                $drop_role = "DROP ROLE IF EXISTS " . pg_escape_identifier($conn, $role_name);
                pg_query($conn, $drop_role);
            }
            pg_free_result($role_result);
        }
        
        $schema_name = 'student_' . $user_id . '_q' . $question_id;
        $drop_schema = "DROP SCHEMA IF EXISTS " . pg_escape_identifier($conn, $schema_name) . " CASCADE";
        pg_query($conn, $drop_schema);
    }
    
    private static function build_connection_string($params) {
        $connection_parts = array();
        foreach ($params as $key => $value) {
            if ($key === 'options') {
                $connection_parts[] = "$key='$value'";
            } else {
                $connection_parts[] = "$key=$value";
            }
        }
        return implode(' ', $connection_parts);
    }
    
    public static function sanitize_query($query) {
        if (!is_string($query) || empty(trim($query))) {
            throw new \Exception('Некорректный SQL-запрос');
        }
        
        return $query;
    }

    public static function log_security_event($type, $details, $severity = 'warning') {
        global $DB, $USER;
        
        $log_entry = new \stdClass();
        $log_entry->type = $type;
        $log_entry->details = $details;
        $log_entry->userid = isset($USER->id) ? $USER->id : 0;
        $log_entry->timecreated = time();
        $log_entry->severity = $severity;
        $log_entry->ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        try {
            if ($DB->get_manager()->table_exists('qtype_postgresqlrunner_security_log')) {
                $DB->insert_record('qtype_postgresqlrunner_security_log', $log_entry);
            }
        } catch (\Exception $e) {
            error_log('PostgreSQL Runner: ошибка логирования: ' . $e->getMessage());
        }
    }

    public static function safe_execute_query($conn, $query) {
        if (!\qtype_postgresqlrunner\security\sql_validator::is_internal_query_allowed($query)) {
            try {
                \qtype_postgresqlrunner\security\sql_validator::validate_sql($query);
            } catch (\Exception $e) {
                self::log_security_event('sql_validation_error', $e->getMessage() . ' Query: ' . substr($query, 0, 100), 'warning');
                throw $e;
            }
        }
        
        $query = self::sanitize_query($query);
        
        $result = @pg_query($conn, $query);
        
        if (!$result) {
            $error_message = pg_last_error($conn);
     
            self::log_security_event('sql_execution_error', $error_message, 'error');
            throw new \Exception('Ошибка выполнения SQL-запроса: ' . $error_message);
        }
        
        return $result;
    }
    
    public static function execute_parametrized_query($conn, $query, $params) {
        if (!\qtype_postgresqlrunner\security\sql_validator::is_internal_query_allowed($query)) {
            \qtype_postgresqlrunner\security\sql_validator::validate_sql($query);
        }
        
        $query = self::sanitize_query($query);
        
        $result = @pg_query_params($conn, $query, $params);
        
        if (!$result) {
            throw new \Exception('Ошибка выполнения SQL-запроса: ' . pg_last_error($conn));
        }
        
        return $result;
    }
}