<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/renderer.php');
require_once($CFG->dirroot . '/question/type/postgresqlrunner/question.php');

class qtype_postgresqlrunner_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        $question = $qa->get_question();
        $currentanswer = $qa->get_last_qt_var('answer');
        
        $inputname = $qa->get_qt_field_name('answer');
        $inputattributes = array(
            'name' => $inputname,
            'id' => $inputname,
            'class' => 'postgresqlrunner form-control',
            'rows' => 15,
            'cols' => 80
        );

        if ($options->readonly) {
            $inputattributes['readonly'] = 'readonly';
        }

        $questiontext = $question->format_questiontext($qa);
        $placeholder = false;
        
        if (preg_match('~\[\[RESPONSE\]\]~', $questiontext)) {
            $placeholder = true;
            $inputattributes['class'] .= ' qtype_postgresqlrunner_response';
            $questiontext = preg_replace('~\[\[RESPONSE\]\]~', 
                                        html_writer::tag('textarea', s($currentanswer), $inputattributes), 
                                        $questiontext);
        }
        
        $template = '';
        if (!empty($question->template)) {
            $template = $question->template;
            
            if ($currentanswer == '' && !$options->readonly) {
                $currentanswer = $template;
            }
        }

        $result = html_writer::tag('div', $questiontext, array('class' => 'qtext'));

        if (!$placeholder) {
            $result .= html_writer::start_tag('div', array('class' => 'ablock'));
            $result .= html_writer::tag('textarea', s($currentanswer), $inputattributes);
            $result .= html_writer::end_tag('div');
        }

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div', 
                                               $question->get_validation_error(array('answer' => $currentanswer)), 
                                               array('class' => 'validationerror'));
        }

        $this->page->requires->js_call_amd('qtype_postgresqlrunner/pgrunner', 'init', 
                                          array($inputname));

        return $result;
    }

    public function specific_feedback(question_attempt $qa) {
        $question = $qa->get_question();
        $currentanswer = $qa->get_last_qt_var('answer');
        
        if ($currentanswer === null || $currentanswer === '') {
            return '';
        }
        
        $answer = $question->get_correct_response();
        
        if ($answer) {
            $fraction = $qa->get_fraction();
            
            if ($fraction == 1) {
                $feedback = html_writer::tag('div', get_string('correctresult', 'qtype_postgresqlrunner'), 
                                           array('class' => 'correct'));
                
                if (stripos(trim($currentanswer), 'SELECT') === 0) {
                    try {
                        $result = $question->get_sql_result($currentanswer);
                        $feedback .= html_writer::tag('h4', get_string('yourresult', 'qtype_postgresqlrunner'));
                        $feedback .= $this->render_result_table($result);
                    } catch (Exception $e) {
                    }
                }
            } else {
                $feedback = html_writer::tag('div', get_string('incorrectresult', 'qtype_postgresqlrunner'), 
                                           array('class' => 'incorrect'));
                
                try {
                    $query_type = $this->determine_query_type($currentanswer);
                    
                    if ($query_type === 'SELECT') {
                        $student_result = $question->get_sql_result($currentanswer);
                        $expected_result = $question->get_sql_result($question->sqlcode);
                        
                        $feedback .= html_writer::start_tag('div', array('class' => 'result-comparison'));
                        $feedback .= html_writer::tag('h4', get_string('yourresult', 'qtype_postgresqlrunner'));
                        $feedback .= $this->render_result_table($student_result);
                        
                        $feedback .= html_writer::tag('h4', get_string('expectedresult', 'qtype_postgresqlrunner'));
                        $feedback .= $this->render_result_table($expected_result);
                        $feedback .= html_writer::end_tag('div');
                    } else {
                        $student_error = $question->get_student_query_error();
                        if ($student_error) {
                            $feedback .= html_writer::tag('div', get_string('queryerror', 'qtype_postgresqlrunner') . ': ' . $student_error, 
                                                       array('class' => 'sql-error'));
                        } else {
                            $state_difference = $question->get_state_difference();
                            if ($state_difference) {
                                $feedback .= html_writer::tag('div', $state_difference, array('class' => 'sql-error'));
                                
                                $student_state = $question->get_student_table_state();
                                $model_state = $question->get_model_table_state();
                                
                                if ($student_state && $model_state) {
                                    $feedback .= html_writer::start_tag('div', array('class' => 'state-comparison'));
                                    
                                    $feedback .= html_writer::tag('h4', get_string('yourtablestate', 'qtype_postgresqlrunner'));
                                    $feedback .= $this->render_table_state($student_state);
                                    
                                    $feedback .= html_writer::tag('h4', get_string('expectedtablestate', 'qtype_postgresqlrunner'));
                                    $feedback .= $this->render_table_state($model_state);
                                    
                                    $feedback .= html_writer::end_tag('div');
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $feedback .= html_writer::tag('div', $e->getMessage(), array('class' => 'sql-error'));
                }
            }
            
            return $feedback;
        }
        
        return '';
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

    protected function render_result_table($result) {
        $output = html_writer::start_tag('table', array('class' => 'sql-result-table'));
        
        $output .= html_writer::start_tag('thead');
        $output .= html_writer::start_tag('tr');
        
        foreach ($result['fields'] as $field) {
            $output .= html_writer::tag('th', s($field));
        }
        
        $output .= html_writer::end_tag('tr');
        $output .= html_writer::end_tag('thead');
        
        $output .= html_writer::start_tag('tbody');
        
        foreach ($result['data'] as $row) {
            $output .= html_writer::start_tag('tr');
            
            foreach ($result['fields'] as $field) {
                $value = isset($row[$field]) ? $row[$field] : '';
                $output .= html_writer::tag('td', s($value));
            }
            
            $output .= html_writer::end_tag('tr');
        }
        
        if (count($result['data']) == 0) {
            $output .= html_writer::tag('tr', 
                                       html_writer::tag('td', 
                                                      get_string('noresults', 'qtype_postgresqlrunner'),
                                                      array('colspan' => count($result['fields']))));
        }
        
        $output .= html_writer::end_tag('tbody');
        $output .= html_writer::end_tag('table');
        
        return $output;
    }

    protected function render_table_state($state) {
        $output = '';
        
        $output .= html_writer::start_tag('div', array('class' => 'table-structure'));
        $output .= html_writer::tag('h5', get_string('tablestructure', 'qtype_postgresqlrunner'));
        
        $output .= html_writer::start_tag('table', array('class' => 'sql-result-table'));
        $output .= html_writer::start_tag('thead');
        $output .= html_writer::start_tag('tr');
        $output .= html_writer::tag('th', get_string('columnname', 'qtype_postgresqlrunner'));
        $output .= html_writer::tag('th', get_string('datatype', 'qtype_postgresqlrunner'));
        $output .= html_writer::tag('th', get_string('nullable', 'qtype_postgresqlrunner'));
        $output .= html_writer::tag('th', get_string('defaultvalue', 'qtype_postgresqlrunner'));
        $output .= html_writer::end_tag('tr');
        $output .= html_writer::end_tag('thead');
        
        $output .= html_writer::start_tag('tbody');
        foreach ($state['structure'] as $column) {
            $output .= html_writer::start_tag('tr');
            $output .= html_writer::tag('td', s($column['column_name']));
            
            $datatype = $column['data_type'];
            if ($column['character_maximum_length']) {
                $datatype .= '(' . $column['character_maximum_length'] . ')';
            }
            $output .= html_writer::tag('td', s($datatype));
            
            $output .= html_writer::tag('td', s($column['is_nullable']));
            $output .= html_writer::tag('td', s($column['column_default'] ? $column['column_default'] : ''));
            $output .= html_writer::end_tag('tr');
        }
        $output .= html_writer::end_tag('tbody');
        $output .= html_writer::end_tag('table');
        $output .= html_writer::end_tag('div');
        
        $output .= html_writer::start_tag('div', array('class' => 'table-data'));
        $output .= html_writer::tag('h5', get_string('tabledata', 'qtype_postgresqlrunner'));
        
        if (empty($state['data'])) {
            $output .= html_writer::tag('p', get_string('nodatainside', 'qtype_postgresqlrunner'));
        } else {
            $output .= html_writer::start_tag('table', array('class' => 'sql-result-table'));
            $output .= html_writer::start_tag('thead');
            $output .= html_writer::start_tag('tr');
            
            $columns = array_keys($state['data'][0]);
            foreach ($columns as $column) {
                $output .= html_writer::tag('th', s($column));
            }
            
            $output .= html_writer::end_tag('tr');
            $output .= html_writer::end_tag('thead');
            
            $output .= html_writer::start_tag('tbody');
            foreach ($state['data'] as $row) {
                $output .= html_writer::start_tag('tr');
                
                foreach ($row as $value) {
                    $output .= html_writer::tag('td', s($value));
                }
                
                $output .= html_writer::end_tag('tr');
            }
            $output .= html_writer::end_tag('tbody');
            $output .= html_writer::end_tag('table');
        }
        
        $output .= html_writer::end_tag('div');
        
        return $output;
    }

    public function correct_response(question_attempt $qa) {
        return '';
    }
}