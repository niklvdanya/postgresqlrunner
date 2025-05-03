<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/questionbase.php');

class qtype_postgresqlrunner_question extends question_graded_automatically {
    public $sqlcode;
    public $expected_result;
    public $db_connection;
    public $template;
    public $grading_type;
    public $case_sensitive;
    public $allow_ordering_difference;

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

    public function check_sql_answer($answer) {
        if (empty($answer)) {
            return 0;
        }

        try {
            $result = $this->execute_sql_query($answer);
            $expected = json_decode($this->expected_result, true);
            
            if ($this->compare_results($result, $expected)) {
                return 1.0;
            } else {
                return 0.0;
            }
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function execute_sql_query($sql) {
        $conn_details = json_decode($this->db_connection, true);
        
        $dbhost = $conn_details['host'];
        $dbname = $conn_details['dbname'];
        $dbuser = $conn_details['user'];
        $dbpass = $conn_details['password'];
        $dbport = isset($conn_details['port']) ? $conn_details['port'] : 5432;
        
        $conn_string = "host=$dbhost port=$dbport dbname=$dbname user=$dbuser password=$dbpass";
        $conn = pg_connect($conn_string);
        
        if (!$conn) {
            throw new Exception('Не удалось подключиться к базе данных PostgreSQL');
        }
        
        $result = pg_query($conn, $sql);
        
        if (!$result) {
            pg_close($conn);
            throw new Exception('Ошибка выполнения SQL-запроса: ' . pg_last_error($conn));
        }
        
        $data = array();
        $num_fields = pg_num_fields($result);
        $fields = array();
        
        for ($i = 0; $i < $num_fields; $i++) {
            $fields[] = pg_field_name($result, $i);
        }
        
        while ($row = pg_fetch_assoc($result)) {
            $data[] = $row;
        }
        
        pg_close($conn);
        
        return array(
            'fields' => $fields,
            'data' => $data
        );
    }
    
    public function get_sql_result($sql) {
        return $this->execute_sql_query($sql);
    }

    protected function compare_results($actual, $expected) {
        if ($this->grading_type == 'exact') {
            return $this->compare_exact($actual, $expected);
        } else if ($this->grading_type == 'partial') {
            return $this->compare_partial($actual, $expected);
        }
        
        return false;
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

    public function get_correct_response() {
        return array('answer' => $this->sqlcode);
    }

    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);
        } else {
            return parent::check_file_access($qa, $options, $component, $filearea, $args, $forcedownload);
        }
    }
}