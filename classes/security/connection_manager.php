<?php
namespace qtype_postgresqlrunner\security;

defined('MOODLE_INTERNAL') || die();

class connection_manager {
    
    public static function get_connection($db_connection) {
        global $CFG;
        
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
        
        $dbhost = self::sanitize_connection_param($conn_details['host']);
        $dbname = self::sanitize_connection_param($conn_details['dbname']);
        $dbuser = self::sanitize_connection_param($conn_details['user']);
        $dbpass = $conn_details['password'];
        $dbport = isset($conn_details['port']) ? (int)$conn_details['port'] : 5432;
        
        if (!filter_var($dbhost, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) &&
            !filter_var($dbhost, FILTER_VALIDATE_IP)) {
            throw new \Exception('Некорректный адрес хоста базы данных');
        }
        
        if ($dbport < 1 || $dbport > 65535) {
            throw new \Exception('Некорректный порт базы данных');
        }
        
        $salt = isset($CFG->passwordsaltmain) ? $CFG->passwordsaltmain : '';
        $session_id = session_id();
        $unique_role_name = 'student_role_' . substr(md5($session_id . $salt), 0, 8);
        $unique_role_password = bin2hex(random_bytes(16));
        
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
        
        pg_query($conn, "SET statement_timeout TO 3000");
        pg_query($conn, "SET search_path TO public");
        pg_query($conn, "SET client_min_messages TO warning");
        
        $role_exists = false;
        $role_check_query = "SELECT 1 FROM pg_roles WHERE rolname = $1";
        $role_check_result = pg_query_params($conn, $role_check_query, array($unique_role_name));
        
        if ($role_check_result && pg_num_rows($role_check_result) > 0) {
            $role_exists = true;
            pg_free_result($role_check_result);
        }
    
        if (!$role_exists) {
            $create_query = "CREATE ROLE $1 WITH LOGIN PASSWORD $2 CONNECTION LIMIT 5 VALID UNTIL current_timestamp + interval '1 hour'";
            $create_result = @pg_query_params($conn, $create_query, array($unique_role_name, $unique_role_password));
            
            if ($create_result) {
                pg_free_result($create_result);
                
                @pg_query_params($conn, 
                    "GRANT CONNECT ON DATABASE $1 TO $2", 
                    array(pg_escape_identifier($conn, $dbname), $unique_role_name)
                );
                
                @pg_query_params($conn, 
                    "GRANT USAGE ON SCHEMA public TO $1", 
                    array($unique_role_name)
                );
                
                @pg_query_params($conn, 
                    "GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA public TO $1", 
                    array($unique_role_name)
                );
                
                @pg_query_params($conn, 
                    "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE ON TABLES TO $1", 
                    array($unique_role_name)
                );
                
                @pg_query_params($conn, 
                    "ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, USAGE ON SEQUENCES TO $1", 
                    array($unique_role_name)
                );
                
                @pg_query_params($conn,
                    "REVOKE ALL ON SCHEMA information_schema FROM $1",
                    array($unique_role_name)
                );
                
                @pg_query_params($conn,
                    "REVOKE ALL ON SCHEMA pg_catalog FROM $1",
                    array($unique_role_name)
                );
    
                $role_check_result = pg_query_params($conn, $role_check_query, array($unique_role_name));
                if ($role_check_result && pg_num_rows($role_check_result) > 0) {
                    $role_exists = true;
                    pg_free_result($role_check_result);
                }
            }
        }
    
        if ($role_exists) {
            @pg_query_params($conn, "SET ROLE $1", array($unique_role_name));
        }
        
        return $conn;
    }
    
    private static function sanitize_connection_param($param) {
        if (!is_string($param)) {
            return '';
        }
        
        $param = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $param);
        return substr($param, 0, 64);
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
        
        $result = @pg_query($conn, $query);
        
        if (!$result) {
            throw new \Exception('Ошибка выполнения SQL-запроса: ' . self::sanitize_error_message(pg_last_error($conn)));
        }
        
        return $result;
    }
    
    public static function execute_parametrized_query($conn, $query, $params) {
        if (!\qtype_postgresqlrunner\security\blacklist::is_internal_query_allowed($query)) {
            \qtype_postgresqlrunner\security\blacklist::validate_sql($query);
        }
        
        $query = self::sanitize_query($query);
        
        $result = @pg_query_params($conn, $query, $params);
        
        if (!$result) {
            throw new \Exception('Ошибка выполнения SQL-запроса: ' . self::sanitize_error_message(pg_last_error($conn)));
        }
        
        return $result;
    }
    
    private static function sanitize_error_message($message) {
        $sensitive_terms = [
            'password', 'user', 'login', 'authenticate', 'connection', 'host',
            'dbname', 'database', 'host', 'port', 'role', 'permission'
        ];
        
        foreach ($sensitive_terms as $term) {
            if (stripos($message, $term) !== false) {
                return 'Ошибка SQL-запроса. Пожалуйста, проверьте синтаксис.';
            }
        }
        
        return $message;
    }
    
    public static function obfuscate_connection_details($db_connection) {
        $conn_details = json_decode($db_connection, true);
        if (!$conn_details || !is_array($conn_details)) {
            return json_encode(['error' => 'Invalid connection details']);
        }
        
        if (isset($conn_details['password'])) {
            $conn_details['password'] = '********';
        }
        
        if (isset($conn_details['user'])) {
            $conn_details['user'] = substr($conn_details['user'], 0, 3) . '****';
        }
        
        return json_encode($conn_details);
    }
    
    public static function get_secure_display_connection($db_connection) {
        return self::obfuscate_connection_details($db_connection);
    }
    
    public static function encrypt_connection_string($conn_string) {
        global $CFG;
        
        if (empty($conn_string)) {
            return '';
        }
        
        $salt = isset($CFG->passwordsaltmain) ? $CFG->passwordsaltmain : '';
        $key = hash('sha256', $salt, true);
        $iv = random_bytes(16);
        
        $encrypted = openssl_encrypt(
            $conn_string,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($encrypted === false) {
            return '';
        }
        
        $result = base64_encode($iv . $encrypted);
        return $result;
    }
    
    public static function decrypt_connection_string($encrypted_string) {
        global $CFG;
        
        if (empty($encrypted_string)) {
            return '';
        }
        
        $salt = isset($CFG->passwordsaltmain) ? $CFG->passwordsaltmain : '';
        $key = hash('sha256', $salt, true);
        
        $data = base64_decode($encrypted_string);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($decrypted === false) {
            return '';
        }
        
        return $decrypted;
    }
}