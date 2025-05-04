<?php
namespace qtype_postgresqlrunner\security;

defined('MOODLE_INTERNAL') || die();

class connection_manager {
    
    public static function get_connection($db_connection) {
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
        
        if (!filter_var($dbhost, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) &&
            !filter_var($dbhost, FILTER_VALIDATE_IP)) {
            throw new \Exception('Некорректный адрес хоста базы данных');
        }
        
        if ($dbport < 1 || $dbport > 65535) {
            throw new \Exception('Некорректный порт базы данных');
        }
        
        $conn_string = sprintf(
            "host=%s port=%d dbname=%s user=%s password=%s options='--client_encoding=UTF8'",
            $dbhost,
            $dbport,
            $dbname,
            $dbuser,
            $dbpass
        );
        
        $conn = @pg_connect($conn_string);
        
        if (!$conn) {
            throw new \Exception('Не удалось подключиться к базе данных PostgreSQL');
        }
        
        pg_query($conn, "SET statement_timeout TO 5000");
        pg_query($conn, "SET search_path TO public");
        pg_query($conn, "SET client_min_messages TO warning");
        
        $role_exists = false;
        $role_check_result = pg_query($conn, "SELECT 1 FROM pg_roles WHERE rolname = 'student_role'");
        if ($role_check_result && pg_num_rows($role_check_result) > 0) {
            $role_exists = true;
            pg_free_result($role_check_result);
        }

        if (!$role_exists) {
            $create_result = @pg_query($conn, "CREATE ROLE student_role WITH LOGIN PASSWORD 'secure_password_123'");
            if ($create_result) {
                pg_free_result($create_result);
                @pg_query($conn, "GRANT CONNECT ON DATABASE " . pg_escape_identifier($conn, $dbname) . " TO student_role");
                @pg_query($conn, "GRANT USAGE ON SCHEMA public TO student_role");
                @pg_query($conn, "GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA public TO student_role");
                @pg_query($conn, "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE ON TABLES TO student_role");
                @pg_query($conn, "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, USAGE ON SEQUENCES TO student_role");

                $role_check_result = pg_query($conn, "SELECT 1 FROM pg_roles WHERE rolname = 'student_role'");
                if ($role_check_result && pg_num_rows($role_check_result) > 0) {
                    $role_exists = true;
                    pg_free_result($role_check_result);
                }
            }
        }

        if ($role_exists) {
            @pg_query($conn, "SET role student_role");
        }
        
        return $conn;
    }
    
    public static function sanitize_query($query) {
        if (!is_string($query) || empty(trim($query))) {
            throw new \Exception('Некорректный SQL-запрос');
        }
        
        return $query;
    }
    
    public static function safe_execute_query($conn, $query) {
        if (!\qtype_postgresqlrunner\security\blacklist::is_internal_query_allowed($query)) {
            \qtype_postgresqlrunner\security\blacklist::validate_sql($query);
        }
        
        $query = self::sanitize_query($query);
        $result = pg_query($conn, $query);
        
        if (!$result) {
            throw new \Exception('Ошибка выполнения SQL-запроса: ' . pg_last_error($conn));
        }
        
        return $result;
    }
    
    public static function execute_parametrized_query($conn, $query, $params) {
        if (!\qtype_postgresqlrunner\security\blacklist::is_internal_query_allowed($query)) {
            \qtype_postgresqlrunner\security\blacklist::validate_sql($query);
        }
        
        $query = self::sanitize_query($query);
        $result = pg_query_params($conn, $query, $params);
        
        if (!$result) {
            throw new \Exception('Ошибка выполнения SQL-запроса: ' . pg_last_error($conn));
        }
        
        return $result;
    }
    
    public static function obfuscate_connection_details($db_connection) {
        $conn_details = json_decode($db_connection, true);
        if (!$conn_details || !is_array($conn_details)) {
            return json_encode(['error' => 'Invalid connection details']);
        }
        
        if (isset($conn_details['password'])) {
            $conn_details['password'] = '********';
        }
        
        return json_encode($conn_details);
    }
    
    public static function get_secure_display_connection($db_connection) {
        $conn_details = json_decode($db_connection, true);
        if (!$conn_details || !is_array($conn_details)) {
            return json_encode(['error' => 'Invalid connection details']);
        }
        
        if (isset($conn_details['password'])) {
            $conn_details['password'] = '********';
        }
        
        return json_encode($conn_details);
    }
}