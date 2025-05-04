<?php
namespace qtype_postgresqlrunner\security;

defined('MOODLE_INTERNAL') || die();

class sql_validator {
    
    private static $allowed_query_types = array(
        'SELECT' => true,
        'INSERT' => true,
        'UPDATE' => true,
        'CREATE TABLE' => true,
        'BEGIN' => true,
        'COMMIT' => true,
        'ROLLBACK' => true,
        'SAVEPOINT' => true
    );
    
    private static $allowed_clauses = array(
        'SELECT' => array('FROM', 'WHERE', 'GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT', 'OFFSET', 'JOIN'),
        'INSERT' => array('INTO', 'VALUES', 'RETURNING'),
        'UPDATE' => array('SET', 'WHERE', 'RETURNING'),
        'CREATE TABLE' => array('IF NOT EXISTS')
    );
    
    private static $allowed_functions = array(
        'AVG', 'COUNT', 'MAX', 'MIN', 'SUM', 
        'LENGTH', 'LOWER', 'UPPER', 'TRIM', 'SUBSTRING',
        'CONCAT', 'NOW', 'CURRENT_DATE', 'CURRENT_TIME',
        'COALESCE', 'NULLIF', 'ROUND', 'ABS', 'RANDOM',
        'DATE_PART', 'TO_CHAR', 'TO_DATE', 'EXTRACT',
        'CASE', 'WHEN', 'THEN', 'ELSE', 'END'
    );
    
    private static $allowed_operators = array(
        'AND', 'OR', 'NOT', 'IS NULL', 'IS NOT NULL', 
        'IN', 'BETWEEN', 'LIKE', 'ILIKE', 'SIMILAR TO',
        '=', '<>', '!=', '<', '>', '<=', '>=',
        '+', '-', '*', '/', '%', '||'
    );
    
    private static $system_tables_prefixes = array(
        'pg_', 'information_schema.', 'user_', 'auth_', 
        'mdl_', 'moodle_', 'config_', 'session_', 'log_',
        'temp_', 'sys_', 'system_', 'admin_', 'auth_'
    );
    
    private static $max_query_length = 2048;
    private static $max_tokens = 200;
    
    public static function validate_sql($sql) {
        if (!is_string($sql) || strlen(trim($sql)) < 3) {
            throw new \Exception('Недопустимый SQL-запрос');
        }
        
        if (strlen($sql) > self::$max_query_length) {
            throw new \Exception('SQL-запрос слишком длинный');
        }
        
        $tokens = self::tokenize_sql($sql);
        
        if (count($tokens) > self::$max_tokens) {
            throw new \Exception('SQL-запрос слишком сложный');
        }
        
        $result = self::parse_and_validate($tokens);
        
        if (!$result['valid']) {
            throw new \Exception($result['error']);
        }
        
        return true;
    }
    
    private static function tokenize_sql($sql) {
        $normalized = self::normalize_sql($sql);
        $tokens = array();
        $current_token = '';
        $in_string = false;
        $string_delimiter = '';
        $i = 0;
        $length = strlen($normalized);
        
        while ($i < $length) {
            $char = $normalized[$i];
            $next_char = ($i < $length - 1) ? $normalized[$i + 1] : '';
            
            if ($in_string) {
                $current_token .= $char;
                if ($char === $string_delimiter && $next_char !== $string_delimiter) {
                    $in_string = false;
                    $tokens[] = $current_token;
                    $current_token = '';
                } elseif ($char === $string_delimiter && $next_char === $string_delimiter) {
                    $current_token .= $next_char;
                    $i++;
                }
            } elseif ($char === "'" || $char === '"') {
                if ($current_token !== '') {
                    $tokens[] = $current_token;
                    $current_token = '';
                }
                $in_string = true;
                $string_delimiter = $char;
                $current_token = $char;
            } elseif (ctype_space($char)) {
                if ($current_token !== '') {
                    $tokens[] = $current_token;
                    $current_token = '';
                }
            } elseif (in_array($char, array('(', ')', ',', ';', '='))) {
                if ($current_token !== '') {
                    $tokens[] = $current_token;
                    $current_token = '';
                }
                $tokens[] = $char;
            } else {
                $current_token .= $char;
            }
            
            $i++;
        }
        
        if ($current_token !== '') {
            $tokens[] = $current_token;
        }
        
        return $tokens;
    }
    
    private static function normalize_sql($sql) {
        $sql = preg_replace('/\s+/', ' ', trim($sql));
        
        $clean_sql = '';
        $in_comment = false;
        $in_line_comment = false;
        $in_string = false;
        $string_delimiter = '';
        $i = 0;
        $length = strlen($sql);
        
        while ($i < $length) {
            $char = $sql[$i];
            $next_char = ($i < $length - 1) ? $sql[$i + 1] : '';
            
            if ($in_line_comment) {
                if ($char === "\n" || $char === "\r") {
                    $in_line_comment = false;
                }
            } elseif ($in_comment) {
                if ($char === '*' && $next_char === '/') {
                    $in_comment = false;
                    $i++;
                }
            } elseif ($in_string) {
                $clean_sql .= $char;
                if ($char === $string_delimiter && $next_char !== $string_delimiter) {
                    $in_string = false;
                } elseif ($char === $string_delimiter && $next_char === $string_delimiter) {
                    $clean_sql .= $next_char;
                    $i++;
                }
            } elseif ($char === '-' && $next_char === '-') {
                $in_line_comment = true;
                $i++;
            } elseif ($char === '/' && $next_char === '*') {
                $in_comment = true;
                $i++;
            } elseif ($char === "'" || $char === '"') {
                $in_string = true;
                $string_delimiter = $char;
                $clean_sql .= $char;
            } else {
                $clean_sql .= $char;
            }
            
            $i++;
        }
        
        return $clean_sql;
    }
    
    private static function parse_and_validate($tokens) {
        if (empty($tokens)) {
            return array('valid' => false, 'error' => 'Пустой SQL-запрос');
        }
        
        $first_token = strtoupper($tokens[0]);
        
        switch ($first_token) {
            case 'SELECT':
                return self::validate_select_query($tokens);
            case 'INSERT':
                return self::validate_insert_query($tokens);
            case 'UPDATE':
                return self::validate_update_query($tokens);
            case 'CREATE':
                return self::validate_create_query($tokens);
            case 'BEGIN':
            case 'COMMIT':
            case 'ROLLBACK':
            case 'SAVEPOINT':
                return self::validate_transaction_query($tokens);
            default:
                return array('valid' => false, 'error' => 'Неразрешенный тип SQL-запроса');
        }
    }
    
    private static function validate_select_query($tokens) {
        $has_from = false;
        $table_names = array();
        
        for ($i = 1; $i < count($tokens); $i++) {
            $token = strtoupper($tokens[$i]);
            
            if ($token === 'FROM') {
                $has_from = true;
                
                if ($i < count($tokens) - 1) {
                    $table_name = $tokens[$i + 1];
                    
                    if (self::is_system_table($table_name)) {
                        return array('valid' => false, 'error' => 'Доступ к системной таблице запрещен: ' . $table_name);
                    }
                    
                    $table_names[] = $table_name;
                }
            } elseif (in_array($token, array('JOIN', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'FULL JOIN'))) {
                if ($i < count($tokens) - 1) {
                    $table_name = $tokens[$i + 1];
                    
                    if (self::is_system_table($table_name)) {
                        return array('valid' => false, 'error' => 'Доступ к системной таблице запрещен: ' . $table_name);
                    }
                    
                    $table_names[] = $table_name;
                }
            }
            
            if (strpos($token, 'PG_') === 0 || strpos($token, 'INFORMATION_SCHEMA.') === 0) {
                return array('valid' => false, 'error' => 'Доступ к системной функции или таблице запрещен: ' . $token);
            }
            
            $disallowed_tokens = array(
                'DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE DATABASE', 
                'DROP DATABASE', 'CREATE USER', 'ALTER USER', 'DROP USER',
                'GRANT', 'REVOKE', 'EXECUTE', 'WAITFOR', 'DELAY', 'BENCHMARK'
            );
            
            foreach ($disallowed_tokens as $disallowed) {
                if ($token === $disallowed) {
                    return array('valid' => false, 'error' => 'Запрещенная команда: ' . $disallowed);
                }
            }
            
            if ($token === 'UNION') {
                if ($i < count($tokens) - 1 && strtoupper($tokens[$i + 1]) !== 'ALL') {
                    return array('valid' => false, 'error' => 'Запрещенная конструкция: UNION без ALL');
                }
            }
        }
        
        if (!$has_from) {
            return array('valid' => false, 'error' => 'SELECT-запрос должен содержать оператор FROM');
        }
        
        return array('valid' => true);
    }
    
    private static function validate_insert_query($tokens) {
        $has_into = false;
        $has_values = false;
        $table_name = '';
        
        for ($i = 1; $i < count($tokens); $i++) {
            $token = strtoupper($tokens[$i]);
            
            if ($token === 'INTO') {
                $has_into = true;
                
                if ($i < count($tokens) - 1) {
                    $table_name = $tokens[$i + 1];
                    
                    if (self::is_system_table($table_name)) {
                        return array('valid' => false, 'error' => 'Доступ к системной таблице запрещен: ' . $table_name);
                    }
                }
            } elseif ($token === 'VALUES') {
                $has_values = true;
            }
            
            $disallowed_tokens = array(
                'DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE DATABASE', 
                'DROP DATABASE', 'CREATE USER', 'ALTER USER', 'DROP USER',
                'GRANT', 'REVOKE', 'EXECUTE', 'WAITFOR', 'DELAY', 'BENCHMARK'
            );
            
            foreach ($disallowed_tokens as $disallowed) {
                if ($token === $disallowed) {
                    return array('valid' => false, 'error' => 'Запрещенная команда: ' . $disallowed);
                }
            }
        }
        
        if (!$has_into) {
            return array('valid' => false, 'error' => 'INSERT-запрос должен содержать оператор INTO');
        }
        
        if (!$has_values) {
            return array('valid' => false, 'error' => 'INSERT-запрос должен содержать оператор VALUES');
        }
        
        return array('valid' => true);
    }
    
    private static function validate_update_query($tokens) {
        $has_set = false;
        $has_where = false;
        $table_name = '';
        
        if (count($tokens) > 1) {
            $table_name = $tokens[1];
            
            if (self::is_system_table($table_name)) {
                return array('valid' => false, 'error' => 'Доступ к системной таблице запрещен: ' . $table_name);
            }
        }
        
        for ($i = 2; $i < count($tokens); $i++) {
            $token = strtoupper($tokens[$i]);
            
            if ($token === 'SET') {
                $has_set = true;
            } elseif ($token === 'WHERE') {
                $has_where = true;
            }
            
            $disallowed_tokens = array(
                'DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE DATABASE', 
                'DROP DATABASE', 'CREATE USER', 'ALTER USER', 'DROP USER',
                'GRANT', 'REVOKE', 'EXECUTE', 'WAITFOR', 'DELAY', 'BENCHMARK'
            );
            
            foreach ($disallowed_tokens as $disallowed) {
                if ($token === $disallowed) {
                    return array('valid' => false, 'error' => 'Запрещенная команда: ' . $disallowed);
                }
            }
        }
        
        if (!$has_set) {
            return array('valid' => false, 'error' => 'UPDATE-запрос должен содержать оператор SET');
        }
        
        if (!$has_where) {
            return array('valid' => false, 'error' => 'UPDATE-запрос должен содержать оператор WHERE для безопасности');
        }
        
        return array('valid' => true);
    }
    
    private static function validate_create_query($tokens) {
        if (count($tokens) < 3) {
            return array('valid' => false, 'error' => 'Неверный синтаксис CREATE запроса');
        }
        
        $second_token = strtoupper($tokens[1]);
        
        if ($second_token !== 'TABLE') {
            return array('valid' => false, 'error' => 'Разрешено только создание таблиц');
        }
        
        $start_index = 2;
        if (count($tokens) > 3 && strtoupper($tokens[2]) === 'IF' && 
            strtoupper($tokens[3]) === 'NOT' && strtoupper($tokens[4]) === 'EXISTS') {
            $start_index = 5;
        }
        
        if (count($tokens) <= $start_index) {
            return array('valid' => false, 'error' => 'Неверный синтаксис CREATE TABLE запроса');
        }
        
        $table_name = $tokens[$start_index];
        
        if (self::is_system_table($table_name)) {
            return array('valid' => false, 'error' => 'Запрещено создавать таблицы с зарезервированными именами: ' . $table_name);
        }
        
        for ($i = $start_index + 1; $i < count($tokens); $i++) {
            $token = strtoupper($tokens[$i]);
            
            $disallowed_tokens = array(
                'DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'GRANT', 'REVOKE', 
                'EXECUTE', 'WAITFOR', 'DELAY', 'BENCHMARK', 'REFERENCE', 
                'FOREIGN KEY', 'TRIGGER', 'PROCEDURE', 'FUNCTION',
                'SECURITY DEFINER', 'SECURITY INVOKER', 'SET search_path'
            );
            
            foreach ($disallowed_tokens as $disallowed) {
                if ($token === $disallowed) {
                    return array('valid' => false, 'error' => 'Запрещенная команда в CREATE TABLE: ' . $disallowed);
                }
            }
        }
        
        return array('valid' => true);
    }
    
    private static function validate_transaction_query($tokens) {
        $type = strtoupper($tokens[0]);
        
        if (!in_array($type, array('BEGIN', 'COMMIT', 'ROLLBACK', 'SAVEPOINT'))) {
            return array('valid' => false, 'error' => 'Неразрешенный тип транзакционного запроса');
        }
        
        if ($type === 'BEGIN' && count($tokens) > 1) {
            $second_token = strtoupper($tokens[1]);
            if ($second_token !== 'TRANSACTION' && $second_token !== ';') {
                return array('valid' => false, 'error' => 'Неверный синтаксис BEGIN запроса');
            }
        }
        
        if ($type === 'COMMIT' && count($tokens) > 1) {
            $second_token = strtoupper($tokens[1]);
            if ($second_token !== 'TRANSACTION' && $second_token !== ';') {
                return array('valid' => false, 'error' => 'Неверный синтаксис COMMIT запроса');
            }
        }
        
        if ($type === 'ROLLBACK' && count($tokens) > 1) {
            $second_token = strtoupper($tokens[1]);
            if ($second_token !== 'TRANSACTION' && $second_token !== 'TO' && $second_token !== ';') {
                return array('valid' => false, 'error' => 'Неверный синтаксис ROLLBACK запроса');
            }
        }
        
        if ($type === 'SAVEPOINT' && count($tokens) < 2) {
            return array('valid' => false, 'error' => 'SAVEPOINT запрос должен содержать имя точки сохранения');
        }
        
        return array('valid' => true);
    }
    
    private static function is_system_table($table_name) {
        $table_lower = strtolower($table_name);
        
        foreach (self::$system_tables_prefixes as $prefix) {
            if (strpos($table_lower, strtolower($prefix)) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function is_internal_query_allowed($sql) {
        $internal_queries = array(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = \'public\'',
            'SELECT column_name, data_type, character_maximum_length, is_nullable, column_default, ordinal_position FROM information_schema.columns WHERE table_name = $1 ORDER BY ordinal_position',
            'SELECT tc.constraint_name, tc.constraint_type, string_agg(kcu.column_name, \',\') as columns FROM information_schema.table_constraints tc JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name AND tc.table_name = kcu.table_name WHERE tc.table_name = $1 GROUP BY tc.constraint_name, tc.constraint_type'
        );
        
        $sql_normalized = preg_replace('/\s+/', ' ', trim($sql));
        
        foreach ($internal_queries as $allowed) {
            $allowed_normalized = preg_replace('/\s+/', ' ', trim($allowed));
            if (strcasecmp($sql_normalized, $allowed_normalized) === 0) {
                return true;
            }
        }
        
        return false;
    }
}