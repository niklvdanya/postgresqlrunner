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
            } else {
                $feedback = html_writer::tag('div', get_string('incorrectresult', 'qtype_postgresqlrunner'), 
                                           array('class' => 'incorrect'));
                
                try {
                    $user_result = $question->get_sql_result($currentanswer);
                    $expected = json_decode($question->expected_result, true);
                    
                    $feedback .= html_writer::start_tag('div', array('class' => 'result-comparison'));
                    $feedback .= html_writer::tag('h4', get_string('yourresult', 'qtype_postgresqlrunner'));
                    $feedback .= $this->render_result_table($user_result);
                    
                    $feedback .= html_writer::tag('h4', get_string('expectedresult', 'qtype_postgresqlrunner'));
                    $feedback .= $this->render_result_table($expected);
                    $feedback .= html_writer::end_tag('div');
                } catch (Exception $e) {
                    $feedback .= html_writer::tag('div', $e->getMessage(), array('class' => 'sql-error'));
                }
            }
            
            return $feedback;
        }
        
        return '';
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

    public function correct_response(question_attempt $qa) {
        return '';
    }
}