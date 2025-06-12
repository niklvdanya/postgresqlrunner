<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/questionbase.php');
require_once($CFG->dirroot . '/question/type/postgresqlrunner/classes/security/sql_validator.php');
require_once($CFG->dirroot . '/question/type/postgresqlrunner/classes/security/connection_manager.php');

class qtype_postgresqlrunner_question extends question_graded_automatically {
    public $sqlcode;
    public $expected_result;
    public $template;
    public $grading_type;
    public $case_sensitive;
    public $allow_ordering_difference;
    
    protected $conn;
    protected $temp_prefix;
    protected $state_difference;
    protected $model_table_state;
    protected $student_table_state;
    protected $student_query_error;
    protected $model_query_error;

    public function get_expected_data() {
        return array('answer' => PARAM_RAW);
    }

    public function summarise_response(array $response) {
        if (isset($response['answer'])) {
            return $response['answer'];
        } else {
            return null;
        }
    }

    public function is_complete_response(array $response) {
        return array_key_exists('answer', $response) && $response['answer'] !== '';
    }

    public function get_validation_error(array $response) {
        if (!$this->is_complete_response($response)) {
            return get_string('pleaseanswer', 'qtype_postgresqlrunner');
        }
        return '';
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        return question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse, $newresponse, 'answer');
    }

    public function grade_response(array $response) {
        $grade = $this->check_sql_answer($response['answer']);
        return array($grade, question_state::graded_state_for_fraction($grade));
    }
    
    public function get_correct_response() {
        return array('answer' => $this->sqlcode);
    }

    protected function determine_query_type($query) {
        $query = trim($query);
        $first_word = strtoupper(explode(' ', $query)[0]);
        
        if (strpos($first_word, 'SELECT') === 0) {
            return 'SELECT';
        } else {
            return 'STATE_CHANGING';
        }
    }

    protected function setup_test_environment() {
        global $CFG;
        $config = require($CFG->dirroot . '/question/type/postgresqlrunner/config.php');
        $this->conn = \qtype_postgresqlrunner\security\connection_manager::get_connection(json_encode($config['db_connection']));
        $this->temp_prefix = 'temp_' . uniqid();
        
        $result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($this->conn, 'BEGIN');
        $result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($this->conn, 'BEGIN');
        if (!$result) {
            throw new Exception('Не удалось начать транзакцию: ' . pg_last_error($this->conn));
        }
        pg_free_result($result);
    }

    protected function cleanup_test_environment() {
        if (isset($this->conn) && pg_connection_status($this->conn) === PGSQL_CONNECTION_OK) {
            pg_query($this->conn, 'ROLLBACK');
            pg_close($this->conn);
            $this->conn = null;
        }
    }

    public function check_sql_answer($answer) {
        if (empty($answer)) {
            return 0;
        }
        
        try {
            \qtype_postgresqlrunner\security\sql_validator::validate_sql($answer);
            
            $query_type = $this->determine_query_type($answer);
            $this->setup_test_environment();
            
            if ($query_type === 'SELECT') {
                $grade = $this->check_select_query($answer);
            } else {
                $grade = $this->check_state_changing_query($answer);
            }
            
            if (isset($this->conn) && pg_connection_status($this->conn) === PGSQL_CONNECTION_OK) {
                $this->cleanup_test_environment();
            }
            
            return $grade;
        } catch (Exception $e) {
            if (isset($this->conn) && pg_connection_status($this->conn) === PGSQL_CONNECTION_OK) {
                $this->cleanup_test_environment();
            }
            $this->student_query_error = $e->getMessage();
            return 0.0;
        }
    }
    
    public function execute_sql_query($sql) {
        \qtype_postgresqlrunner\security\sql_validator::validate_sql($sql);
        
        if (!is_string($sql) || empty(trim($sql))) {
            throw new Exception('Некорректный SQL-запрос');
        }
        
        $conn_status = false;
        if (isset($this->conn) && pg_connection_status($this->conn) === PGSQL_CONNECTION_OK) {
            $conn_status = true;
        } else {
            $this->setup_test_environment();
        }
        
        $result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($this->conn, $sql);
        
        $data = array();
        $fields = array();
        
        if (pg_num_fields($result) > 0) {
            $num_fields = pg_num_fields($result);
            
            for ($i = 0; $i < $num_fields; $i++) {
                $fields[] = pg_field_name($result, $i);
            }
            
            $rows_count = 0;
            $max_rows = 500;
            
            while ($row = pg_fetch_assoc($result) and $rows_count < $max_rows) {
                $data[] = $row;
                $rows_count++;
            }
            
            if ($rows_count >= $max_rows) {
                pg_free_result($result);
                if (!$conn_status) {
                    $this->cleanup_test_environment();
                }
                throw new Exception('Запрос вернул слишком много строк. Ограничьте результат.');
            }
        }
        
        pg_free_result($result);
        if (!$conn_status) {
            $this->cleanup_test_environment();
        }
        
        return array(
            'fields' => $fields,
            'data' => $data
        );
    }
    
    protected function check_select_query($student_query) {
        $student_result = $this->execute_sql_query($student_query);
        $model_result = $this->execute_sql_query($this->sqlcode);
        
        if ($this->grading_type == 'exact') {
            return $this->compare_exact($student_result, $model_result) ? 1.0 : 0.0;
        } else if ($this->grading_type == 'partial') {
            return $this->compare_partial($student_result, $model_result) ? 1.0 : 0.0;
        }
        
        return 0.0;
    }

    protected function check_state_changing_query($student_query) {
        $tables_before = $this->get_all_tables();
        $snapshots_before = [];
        
        foreach ($tables_before as $table) {
            $snapshots_before[$table] = $this->get_table_state($table);
        }
        
        $model_query_result = null;
        try {
            $model_query_result = $this->execute_sql_query($this->sqlcode);
        } catch (Exception $e) {
            $this->model_query_error = $e->getMessage();
            return 0.0;
        }
        
        $tables_after_model = $this->get_all_tables();
        $snapshots_after_model = [];
        
        foreach ($tables_after_model as $table) {
            $snapshots_after_model[$table] = $this->get_table_state($table);
        }
        
        $result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($this->conn, 'ROLLBACK');
        if (!$result) {
            throw new Exception('Не удалось выполнить ROLLBACK: ' . pg_last_error($this->conn));
        }
        pg_free_result($result);
        
        $result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($this->conn, 'BEGIN');
        if (!$result) {
            throw new Exception('Не удалось начать новую транзакцию: ' . pg_last_error($this->conn));
        }
        pg_free_result($result);
        
        foreach ($snapshots_before as $table => $state) {
            $this->restore_table_state($table, $state);
        }
        
        $student_query_result = null;
        try {
            $student_query_result = $this->execute_sql_query($student_query);
        } catch (Exception $e) {
            $this->student_query_error = $e->getMessage();
            return 0.0;
        }
        
        $tables_after_student = $this->get_all_tables();
        $snapshots_after_student = [];
        
        foreach ($tables_after_student as $table) {
            $snapshots_after_student[$table] = $this->get_table_state($table);
        }
        
        if (count($tables_after_model) != count($tables_after_student)) {
            $this->state_difference = "Разное количество таблиц";
            return 0.0;
        }
        
        $all_tables = array_unique(array_merge($tables_after_model, $tables_after_student));
        
        foreach ($all_tables as $table) {
            if (!isset($snapshots_after_model[$table])) {
                $this->state_difference = "Таблица '{$table}' отсутствует после эталонного запроса";
                return 0.0;
            }
            
            if (!isset($snapshots_after_student[$table])) {
                $this->state_difference = "Таблица '{$table}' отсутствует после запроса студента";
                return 0.0;
            }
            
            if (!$this->compare_table_states($snapshots_after_model[$table], $snapshots_after_student[$table])) {
                $this->state_difference = "Состояние таблицы '{$table}' отличается";
                $this->model_table_state = $snapshots_after_model[$table];
                $this->student_table_state = $snapshots_after_student[$table];
                return 0.0;
            }
        }
        
        return 1.0;
    }

    protected function get_all_tables() {
        $query = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'";
        $result = pg_query_params($this->conn, $query, array());
        
        if (!$result) {
            return [];
        }
        
        $tables = [];
        while ($row = pg_fetch_assoc($result)) {
            $tables[] = $row['table_name'];
        }
        
        pg_free_result($result);
        return $tables;
    }

    protected function get_table_state($table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new Exception('Некорректное имя таблицы');
        }
        
        $structure_query = "
            SELECT column_name, data_type, character_maximum_length, 
                   is_nullable, column_default, ordinal_position
            FROM information_schema.columns 
            WHERE table_name = $1
            ORDER BY ordinal_position
        ";
        
        $structure_result = \qtype_postgresqlrunner\security\connection_manager::execute_parametrized_query(
            $this->conn, $structure_query, array($table)
        );
        
        $structure = [];
        while ($row = pg_fetch_assoc($structure_result)) {
            $structure[] = $row;
        }
        
        pg_free_result($structure_result);
        
        $constraints_query = "
            SELECT tc.constraint_name, tc.constraint_type, 
                   string_agg(kcu.column_name, ',') as columns
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu 
              ON tc.constraint_name = kcu.constraint_name
             AND tc.table_name = kcu.table_name
            WHERE tc.table_name = $1
            GROUP BY tc.constraint_name, tc.constraint_type
        ";
        
        $constraints_result = \qtype_postgresqlrunner\security\connection_manager::execute_parametrized_query(
            $this->conn, $constraints_query, array($table)
        );
        
        $constraints = [];
        if ($constraints_result) {
            while ($row = pg_fetch_assoc($constraints_result)) {
                $constraints[] = $row;
            }
            pg_free_result($constraints_result);
        }
        
        $data_query = "SELECT * FROM " . pg_escape_identifier($this->conn, $table);
        $data_result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query(
            $this->conn, $data_query
        );
        
        $data = [];
        if ($data_result) {
            $rows_count = 0;
            $max_rows = 500;
            
            while ($row = pg_fetch_assoc($data_result) and $rows_count < $max_rows) {
                $data[] = $row;
                $rows_count++;
            }
            
            if ($rows_count >= $max_rows) {
                pg_free_result($data_result);
                throw new Exception('Таблица содержит слишком много строк. Ограничьте результат.');
            }
            
            pg_free_result($data_result);
        }
        
        return [
            'structure' => $structure,
            'constraints' => $constraints,
            'data' => $data
        ];
    }

    protected function restore_table_state($table, $state) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new Exception('Некорректное имя таблицы');
        }
        
        if (empty($state['structure'])) {
            return;
        }
        
        $table_identifier = pg_escape_identifier($this->conn, $table);
        $drop_query = "DROP TABLE IF EXISTS {$table_identifier} CASCADE";
        $result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query(
            $this->conn, $drop_query
        );
        
        if (!$result) {
            throw new Exception("Не удалось удалить таблицу {$table}: " . pg_last_error($this->conn));
        }
        pg_free_result($result);
        
        $create_query = "CREATE TABLE {$table_identifier} (";
        
        $columns = [];
        foreach ($state['structure'] as $column) {
            $column_identifier = pg_escape_identifier($this->conn, $column['column_name']);
            $col_def = $column_identifier . ' ' . $column['data_type'];
            
            if ($column['character_maximum_length']) {
                $col_def .= '(' . (int)$column['character_maximum_length'] . ')';
            }
            
            if ($column['is_nullable'] === 'NO') {
                $col_def .= ' NOT NULL';
            }
            
            if ($column['column_default']) {
                $col_def .= ' DEFAULT ' . $column['column_default'];
            }
            
            $columns[] = $col_def;
        }
        
        $create_query .= implode(', ', $columns) . ')';
        $result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query(
            $this->conn, $create_query
        );
        
        if (!$result) {
            throw new Exception("Не удалось создать таблицу {$table}: " . pg_last_error($this->conn));
        }
        pg_free_result($result);
        
        foreach ($state['constraints'] as $constraint) {
            if ($constraint['constraint_type'] === 'PRIMARY KEY') {
                $constraint_identifier = pg_escape_identifier($this->conn, $constraint['constraint_name']);
                $query = "ALTER TABLE {$table_identifier} ADD CONSTRAINT {$constraint_identifier} PRIMARY KEY ({$constraint['columns']})";
                $result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query(
                    $this->conn, $query
                );
                
                if (!$result) {
                    throw new Exception("Не удалось добавить первичный ключ: " . pg_last_error($this->conn));
                }
                pg_free_result($result);
            }
        }
        
        if (!empty($state['data'])) {
            foreach ($state['data'] as $row) {
                $columns = array_keys($row);
                $values = array_values($row);
                
                $placeholders = array();
                for ($i = 1; $i <= count($values); $i++) {
                    $placeholders[] = '$' . $i;
                }
                
                $conn = $this->conn; 
                $column_identifiers = array_map(function($col) use ($conn) {
                    return pg_escape_identifier($conn, $col);
                }, $columns);
                
                $insert_query = "INSERT INTO {$table_identifier} (" . 
                                implode(', ', $column_identifiers) . 
                                ") VALUES (" . implode(', ', $placeholders) . ")";
                
                $result = \qtype_postgresqlrunner\security\connection_manager::execute_parametrized_query(
                    $this->conn, $insert_query, $values
                );
                
                if (!$result) {
                    throw new Exception("Не удалось вставить данные: " . pg_last_error($this->conn));
                }
                pg_free_result($result);
            }
        }
    }

    protected function compare_table_states($state1, $state2) {
        if (count($state1['structure']) !== count($state2['structure'])) {
            return false;
        }
        
        for ($i = 0; $i < count($state1['structure']); $i++) {
            if ($state1['structure'][$i]['column_name'] !== $state2['structure'][$i]['column_name'] ||
                $state1['structure'][$i]['data_type'] !== $state2['structure'][$i]['data_type'] ||
                $state1['structure'][$i]['is_nullable'] !== $state2['structure'][$i]['is_nullable']) {
                return false;
            }
        }
        
        $data1 = $this->sort_table_data($state1['data']);
        $data2 = $this->sort_table_data($state2['data']);
        
        if (count($data1) !== count($data2)) {
            return false;
        }
        
        for ($i = 0; $i < count($data1); $i++) {
            if (!$this->compare_rows($data1[$i], $data2[$i])) {
                return false;
            }
        }
        
        return true;
    }

    protected function sort_table_data($data) {
        if (empty($data)) {
            return [];
        }
        
        $serialized = [];
        foreach ($data as $row) {
            ksort($row);
            $serialized[] = serialize($row);
        }
        
        sort($serialized);
        
        $result = [];
        foreach ($serialized as $item) {
            $result[] = unserialize($item);
        }
        
        return $result;
    }

    protected function compare_rows($row1, $row2) {
        if (count($row1) !== count($row2)) {
            return false;
        }
        
        foreach ($row1 as $key => $value) {
            if (!isset($row2[$key])) {
                return false;
            }
            
            if ($this->case_sensitive) {
                if ((string)$value !== (string)$row2[$key]) {
                    return false;
                }
            } else {
                if (strtolower((string)$value) !== strtolower((string)$row2[$key])) {
                    return false;
                }
            }
        }
        
        return true;
    }

    protected function compare_exact($actual, $expected) {
        if (count($actual['fields']) != count($expected['fields'])) {
            return false;
        }
        
        for ($i = 0; $i < count($actual['fields']); $i++) {
            if ($this->case_sensitive) {
                if ($actual['fields'][$i] !== $expected['fields'][$i]) {
                    return false;
                }
            } else {
                if (strtolower($actual['fields'][$i]) !== strtolower($expected['fields'][$i])) {
                    return false;
                }
            }
        }
        
        if (count($actual['data']) != count($expected['data'])) {
            return false;
        }
        
        if ($this->allow_ordering_difference) {
            $sorted_actual = $this->sort_results($actual['data']);
            $sorted_expected = $this->sort_results($expected['data']);
            
            return $this->compare_data_sets($sorted_actual, $sorted_expected);
        } else {
            return $this->compare_data_sets($actual['data'], $expected['data']);
        }
    }

    protected function compare_partial($actual, $expected) {
        $fields_match = true;
        $expected_fields = array_map('strtolower', $expected['fields']);
        $actual_fields = array_map('strtolower', $actual['fields']);
        
        foreach ($expected_fields as $field) {
            if (!in_array($field, $actual_fields)) {
                $fields_match = false;
                break;
            }
        }
        
        if (!$fields_match) {
            return false;
        }
        
        $field_map = array();
        foreach ($expected_fields as $i => $field) {
            $field_map[$i] = array_search($field, $actual_fields);
        }
        
        $expected_row_count = count($expected['data']);
        $actual_row_count = count($actual['data']);
        
        if ($expected_row_count != $actual_row_count) {
            return false;
        }
        
        $mapped_actual = array();
        foreach ($actual['data'] as $row) {
            $new_row = array();
            foreach ($field_map as $expected_idx => $actual_idx) {
                $expected_field = $expected['fields'][$expected_idx];
                $actual_field = $actual['fields'][$actual_idx];
                $new_row[$expected_field] = $row[$actual_field];
            }
            $mapped_actual[] = $new_row;
        }
        
        if ($this->allow_ordering_difference) {
            $sorted_actual = $this->sort_results($mapped_actual);
            $sorted_expected = $this->sort_results($expected['data']);
            
            return $this->compare_data_sets($sorted_actual, $sorted_expected);
        } else {
            return $this->compare_data_sets($mapped_actual, $expected['data']);
        }
    }

    protected function sort_results($data) {
        $serialized = array();
        foreach ($data as $row) {
            $serialized[] = serialize($row);
        }
        
        sort($serialized);
        
        $result = array();
        foreach ($serialized as $item) {
            $result[] = unserialize($item);
        }
        
        return $result;
    }

    protected function compare_data_sets($actual, $expected) {
        for ($i = 0; $i < count($actual); $i++) {
            foreach ($expected[$i] as $key => $value) {
                if (!isset($actual[$i][$key])) {
                    return false;
                }
                
                if ($this->case_sensitive) {
                    if ((string)$actual[$i][$key] !== (string)$value) {
                        return false;
                    }
                } else {
                    if (strtolower((string)$actual[$i][$key]) !== strtolower((string)$value)) {
                        return false;
                    }
                }
            }
        }
        
        return true;
    }

    public function get_sql_result($sql) {
        try {
            if (!is_string($sql) || strlen(trim($sql)) < 3) {
                throw new Exception('Недопустимый SQL-запрос');
            }
            $this->setup_test_environment();
            
            $result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($this->conn, $sql);
            
            $data = array();
            $fields = array();
            
            if (pg_num_fields($result) > 0) {
                $num_fields = pg_num_fields($result);
                
                for ($i = 0; $i < $num_fields; $i++) {
                    $fields[] = pg_field_name($result, $i);
                }
                
                $rows_count = 0;
                $max_rows = 500;
                
                while ($row = pg_fetch_assoc($result) and $rows_count < $max_rows) {
                    $data[] = $row;
                    $rows_count++;
                }
                
                if ($rows_count >= $max_rows) {
                    pg_free_result($result);
                    $this->cleanup_test_environment();
                    throw new Exception('Запрос вернул слишком много строк. Ограничьте результат.');
                }
            }
            
            pg_free_result($result);
            $this->cleanup_test_environment();
            
            return array(
                'fields' => $fields,
                'data' => $data
            );
        } catch (Exception $e) {
            if (isset($this->conn) && pg_connection_status($this->conn) === PGSQL_CONNECTION_OK) {
                $this->cleanup_test_environment();
            }
            throw $e;
        }
    }

    public function get_student_table_state() {
        return isset($this->student_table_state) ? $this->student_table_state : null;
    }

    public function get_model_table_state() {
        return isset($this->model_table_state) ? $this->model_table_state : null;
    }

    public function get_state_difference() {
        return isset($this->state_difference) ? $this->state_difference : null;
    }

    public function get_student_query_error() {
        return isset($this->student_query_error) ? $this->student_query_error : null;
    }

    public function get_model_query_error() {
        return isset($this->model_query_error) ? $this->model_query_error : null;
    }

    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);
        } else {
            return parent::check_file_access($qa, $options, $component, $filearea, $args, $forcedownload);
        }
    }
}