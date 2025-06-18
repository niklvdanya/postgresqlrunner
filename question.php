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
    public $environment_init;
    public $extra_code;
    public $grading_type;
    public $case_sensitive;
    public $allow_ordering_difference;
    
    protected $conn;
    protected $temp_prefix;
    protected $student_query_error;

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

    protected function setup_test_environment() {
        global $CFG;
        $config = require($CFG->dirroot . '/question/type/postgresqlrunner/config.php');
        $this->conn = \qtype_postgresqlrunner\security\connection_manager::get_connection(json_encode($config['db_connection']));
        $this->temp_prefix = 'temp_' . uniqid();
        
        $result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($this->conn, 'BEGIN');
        if (!$result) {
            throw new Exception('Не удалось начать транзакцию: ' . pg_last_error($this->conn));
        }
        pg_free_result($result);
        
        if (!empty($this->environment_init)) {
            try {
                \qtype_postgresqlrunner\security\sql_validator::validate_sql($this->environment_init);
                $init_result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($this->conn, $this->environment_init);
                if ($init_result) {
                    pg_free_result($init_result);
                }
            } catch (Exception $e) {
                throw new Exception('Ошибка инициализации окружения: ' . $e->getMessage());
            }
        }
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
            $this->setup_test_environment();

            $student_query = $answer;
            if (!empty($student_query)) {
                $student_result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($this->conn, $student_query);
                if ($student_result) {
                    pg_free_result($student_result);
                }
            }
            
            $student_select_result = $this->execute_select_query($this->extra_code ?: $student_query);
            
            $result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($this->conn, 'ROLLBACK');
            if (!$result) {
                throw new Exception('Не удалось выполнить ROLLBACK: ' . pg_last_error($this->conn));
            }
            pg_free_result($result);
            $this->setup_test_environment();
            
            if (!empty($this->sqlcode)) {
                $model_result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($this->conn, $this->sqlcode);
                if ($model_result) {
                    pg_free_result($model_result);
                }
            }
        
            $model_select_result = $this->execute_select_query($this->extra_code ?: $this->sqlcode);
            
            $this->cleanup_test_environment();

            if ($this->grading_type == 'exact') {
                return $this->compare_exact($student_select_result, $model_select_result) ? 1.0 : 0.0;
            } else if ($this->grading_type == 'partial') {
                return $this->compare_partial($student_select_result, $model_select_result) ? 1.0 : 0.0;
            }
            
            return 0.0;
        } catch (Exception $e) {
            $this->cleanup_test_environment();
            $this->student_query_error = $e->getMessage();
            return 0.0;
        }
    }

    public function get_sql_result($sql, $is_student = true) {
        try {
            if (!is_string($sql) || strlen(trim($sql)) < 3) {
                throw new Exception('Недопустимый SQL-запрос');
            }
            $this->setup_test_environment();
            
            $query_to_execute = $is_student ? $sql : $this->sqlcode;
            if (!empty($query_to_execute)) {
                $main_result = \qtype_postgresqlrunner\security\connection_manager::safe_execute_query($this->conn, $query_to_execute);
                if ($main_result) {
                    pg_free_result($main_result);
                }
            }

            $select_result = $this->execute_select_query($this->extra_code ?: $query_to_execute);
            
            $this->cleanup_test_environment();
            
            return $select_result;
        } catch (Exception $e) {
            $this->cleanup_test_environment();
            throw $e;
        }
    }
        
    protected function execute_select_query($sql) {
        if (empty($sql)) {
            throw new Exception('SELECT query is required for grading');
        }
        
        \qtype_postgresqlrunner\security\sql_validator::validate_sql($sql);
        
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
                throw new Exception('Запрос вернул слишком много строк. Ограничьте результат.');
            }
        }
        
        pg_free_result($result);
        
        return array(
            'fields' => $fields,
            'data' => $data
        );
    }

    public function execute_sql_query($sql) {
        return $this->execute_select_query($sql);
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

    public function get_student_query_error() {
        return isset($this->student_query_error) ? $this->student_query_error : null;
    }

    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);
        } else {
            return parent::check_file_access($qa, $options, $component, $filearea, $args, $forcedownload);
        }
    }
}