<?php
namespace qtype_postgresqlrunner\security;

defined('MOODLE_INTERNAL') || die();

class blacklist {
    private static $forbidden_commands = [
        'DROP', 'TRUNCATE', 'DELETE', 'ALTER', 'CREATE DATABASE', 'DROP DATABASE',
        'CREATE USER', 'ALTER USER', 'DROP USER', 'GRANT', 'REVOKE',
        'SHUTDOWN', 'RELOAD', 'PROCESS', 'FILE', 'SUPER',
        'REPLICATION', 'LOCK TABLES',
        'CREATE FUNCTION', 'CREATE PROCEDURE', 'CREATE TRIGGER',
        'SET GLOBAL', 'SHOW DATABASES', 'INFORMATION_SCHEMA.TABLES',
        'PG_SLEEP', 'PG_READ_FILE', 'COPY FROM PROGRAM', 'COPY TO PROGRAM',
        'LOAD', 'VACUUM', 'ANALYZE', 'NOTIFY', 'EXPLAIN', 'CHECKPOINT',
        'SET ROLE', 'RESET ROLE', 'SET SESSION AUTHORIZATION', 
        'DO', 'SECURITY DEFINER', 'SECURITY INVOKER', 'SET search_path',
        'CREATE EXTENSION', 'ALTER EXTENSION', 'DROP EXTENSION',
        'PG_CATALOG', 'INFORMATION_SCHEMA', 'PG_SHADOW', 'PG_AUTHID',
        'current_setting', 'set_config', 'pg_read_binary_file', 
        'pg_ls_dir', 'pg_stat_file', 'current_database', 'current_schemas',
        'current_user', 'session_user', 'inet_client_addr', 'inet_client_port',
        'inet_server_addr', 'inet_server_port', 'version'
    ];
    
    private static $read_only_tables = [
        'pg_', 'user', 'password', 'config', 'settings', 'auth',
        'role', 'permission', 'session', 'admin', 'security'
    ];
    
    public static function validate_sql($sql) {
        $normalized_sql = self::normalize_sql($sql);
        
        foreach (self::$forbidden_commands as $command) {
            $normalized_command = strtoupper(trim($command));
            if (self::contains_forbidden_command($normalized_sql, $normalized_command)) {
                throw new \Exception('Запрещенная команда: ' . $command);
            }
        }
        
        if (!self::is_student_allowed_query($normalized_sql)) {
            throw new \Exception('Неразрешенный тип запроса. Разрешены только учебные SQL запросы.');
        }
        
        foreach (self::$read_only_tables as $table_prefix) {
            $pattern = '/\b' . preg_quote($table_prefix, '/') . '/i';
            if (preg_match($pattern, $sql)) {
                throw new \Exception('Доступ к системной таблице запрещен: ' . $table_prefix);
            }
        }
        
        return true;
    }
    
    private static function normalize_sql($sql) {
        $sql = preg_replace('/\s+/', ' ', trim($sql));
        $sql = preg_replace('/\/\*.*?\*\//', '', $sql);
        $sql = preg_replace('/--.*?(\r|\n|$)/', '', $sql);
        $sql = preg_replace('/\/\*.*$/s', '', $sql);
        $sql = preg_replace('/^.*\*\//s', '', $sql);
        $sql = strtoupper($sql);
        return $sql;
    }
    
    private static function contains_forbidden_command($sql, $command) {
        $tokens = preg_split('/[\s\,\(\)\;\"\'\`]/', $sql);
        return in_array($command, $tokens) || strpos($sql, $command . '(') !== false || strpos($sql, $command . ' ') !== false;
    }
    
    private static function is_student_allowed_query($sql) {
        $allowed_prefixes = [
            'SELECT ', 'INSERT INTO ', 'UPDATE ', 'CREATE TABLE ', 
            'BEGIN', 'COMMIT', 'ROLLBACK', 'SAVEPOINT'
        ];
        
        foreach ($allowed_prefixes as $prefix) {
            if (strpos($sql, $prefix) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function is_internal_query_allowed($sql) {
        $whitelist = [
            'SELECT table_name FROM information_schema.tables WHERE table_schema = \'public\'',
            'SELECT column_name, data_type, character_maximum_length, is_nullable, column_default, ordinal_position FROM information_schema.columns WHERE table_name = $1 ORDER BY ordinal_position',
            'SELECT tc.constraint_name, tc.constraint_type, string_agg(kcu.column_name, \',\') as columns FROM information_schema.table_constraints tc JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name AND tc.table_name = kcu.table_name WHERE tc.table_name = $1 GROUP BY tc.constraint_name, tc.constraint_type'
        ];
        
        $normalized_sql = preg_replace('/\s+/', ' ', trim($sql));
        $normalized_sql = strtoupper($normalized_sql);
        
        foreach ($whitelist as $allowed_query) {
            $normalized_allowed = preg_replace('/\s+/', ' ', trim($allowed_query));
            $normalized_allowed = strtoupper($normalized_allowed);
            if ($normalized_sql === $normalized_allowed) {
                return true;
            }
        }
        
        return false;
    }
}