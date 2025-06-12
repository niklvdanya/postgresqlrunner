<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/postgresqlrunner/question.php');

class qtype_postgresqlrunner extends question_type {
    public function extra_question_fields() {
        return array('qtype_postgresqlrunner_options', 
                    'sqlcode', 
                    'expected_result', 
                    'template', 
                    'grading_type',
                    'case_sensitive',
                    'allow_ordering_difference');
    }

    public function move_files($questionid, $oldcontextid, $newcontextid) {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    protected function delete_files($questionid, $contextid) {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
    }

    public function save_question_options($question) {
        global $DB;
    
        $context = $question->context;
        
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('qtype_postgresqlrunner_options')) {
            return false;
        }
        
        $options = $DB->get_record('qtype_postgresqlrunner_options', array('questionid' => $question->id));
    
        if (!$options) {
            $options = new stdClass();
            $options->questionid = $question->id;
            $options->sqlcode = $question->sqlcode;
            $options->expected_result = $question->expected_result;
            $options->template = isset($question->template) ? $question->template : '';
            $options->grading_type = isset($question->grading_type) ? $question->grading_type : 'exact';
            $options->case_sensitive = isset($question->case_sensitive) ? $question->case_sensitive : 0;
            $options->allow_ordering_difference = isset($question->allow_ordering_difference) ? $question->allow_ordering_difference : 0;
            
            $options->id = $DB->insert_record('qtype_postgresqlrunner_options', $options);
        } else {
            $options->sqlcode = $question->sqlcode;
            $options->expected_result = $question->expected_result;
            $options->template = isset($question->template) ? $question->template : '';
            $options->grading_type = isset($question->grading_type) ? $question->grading_type : 'exact';
            $options->case_sensitive = isset($question->case_sensitive) ? $question->case_sensitive : 0;
            $options->allow_ordering_difference = isset($question->allow_ordering_difference) ? $question->allow_ordering_difference : 0;
    
            $DB->update_record('qtype_postgresqlrunner_options', $options);
        }
    
        $this->save_hints($question);
        return true;
    }

    public function get_question_options($question) {
        global $DB;
        
        $question->options = $DB->get_record('qtype_postgresqlrunner_options', 
                                             array('questionid' => $question->id));
                                             
        if (!$question->options) {
            return false;
        }
        
        $question->sqlcode = $question->options->sqlcode;
        $question->expected_result = $question->options->expected_result;
        $question->template = $question->options->template;
        $question->grading_type = $question->options->grading_type;
        $question->case_sensitive = $question->options->case_sensitive;
        $question->allow_ordering_difference = $question->options->allow_ordering_difference;
        
        parent::get_question_options($question);
        return true;
    }

    public function get_random_guess_score($questiondata) {
        return 0;
    }

    public function get_possible_responses($questiondata) {
        return array(array($questiondata->id => 
            array(0 => get_string('incorrect', 'qtype_postgresqlrunner'),
                  1 => get_string('correct', 'qtype_postgresqlrunner'))));
    }

    public function import_from_xml($data, $question, qformat_xml $format, $extra=null) {
        if (!isset($data['@']['type']) || $data['@']['type'] != 'postgresqlrunner') {
            return false;
        }

        $question = parent::import_from_xml($data, $question, $format, $extra);
        $format->import_hints($question, $data, true);

        $question->sqlcode = $format->getpath($data, array('#', 'sqlcode', 0, '#'), '');
        $question->expected_result = $format->getpath($data, array('#', 'expected_result', 0, '#'), '');
        $question->template = $format->getpath($data, array('#', 'template', 0, '#'), '');
        $question->grading_type = $format->getpath($data, array('#', 'grading_type', 0, '#'), 'exact');
        $question->case_sensitive = $format->getpath($data, array('#', 'case_sensitive', 0, '#'), 0);
        $question->allow_ordering_difference = $format->getpath($data, array('#', 'allow_ordering_difference', 0, '#'), 0);

        return $question;
    }

    public function export_to_xml($question, qformat_xml $format, $extra=null) {
        $output = parent::export_to_xml($question, $format, $extra);

        $output .= '    <sqlcode>' . $format->writetext($question->options->sqlcode) . "</sqlcode>\n";
        $output .= '    <expected_result>' . $format->writetext($question->options->expected_result) . "</expected_result>\n";
        $output .= '    <template>' . $format->writetext($question->options->template) . "</template>\n";
        $output .= '    <grading_type>' . $format->writetext($question->options->grading_type) . "</grading_type>\n";
        $output .= '    <case_sensitive>' . $question->options->case_sensitive . "</case_sensitive>\n";
        $output .= '    <allow_ordering_difference>' . $question->options->allow_ordering_difference . "</allow_ordering_difference>\n";

        return $output;
    }
    
    public function display_question_settings_form($question, $context) {
        global $PAGE;
        $PAGE->requires->js_call_amd('qtype_postgresqlrunner/admin', 'init', array());
        parent::display_question_settings_form($question, $context);
    }
}