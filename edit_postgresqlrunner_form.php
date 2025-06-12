<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/postgresqlrunner/questiontype.php');

class qtype_postgresqlrunner_edit_form extends question_edit_form {

    public function qtype() {
        return 'postgresqlrunner';
    }
    
    protected function definition_inner($mform) {
        $mform->addElement('header', 'sqlheader', get_string('sqlheader', 'qtype_postgresqlrunner'));
    
        $mform->addElement('textarea', 'sqlcode', get_string('sqlcode', 'qtype_postgresqlrunner'),
                           array('rows' => 10, 'cols' => 80, 'class' => 'postgresqlrunner text-monospace'));
        $mform->setType('sqlcode', PARAM_RAW);
        $mform->addRule('sqlcode', null, 'required', null, 'client');
        $mform->addHelpButton('sqlcode', 'sqlcode', 'qtype_postgresqlrunner');
    
        $mform->addElement('textarea', 'template', get_string('template', 'qtype_postgresqlrunner'),
                           array('rows' => 10, 'cols' => 80, 'class' => 'text-monospace'));
        $mform->setType('template', PARAM_RAW);
        $mform->addHelpButton('template', 'template', 'qtype_postgresqlrunner');
    
        $gradingtypes = array(
            'exact' => get_string('gradingtypeexact', 'qtype_postgresqlrunner'),
            'partial' => get_string('gradingtypepartial', 'qtype_postgresqlrunner')
        );
        $mform->addElement('select', 'grading_type', get_string('gradingtype', 'qtype_postgresqlrunner'), $gradingtypes);
        $mform->setDefault('grading_type', 'exact');
        $mform->addHelpButton('grading_type', 'gradingtype', 'qtype_postgresqlrunner');
    
        $mform->addElement('advcheckbox', 'case_sensitive', get_string('casesensitive', 'qtype_postgresqlrunner'));
        $mform->setDefault('case_sensitive', 0);
        $mform->addHelpButton('case_sensitive', 'casesensitive', 'qtype_postgresqlrunner');
    
        $mform->addElement('advcheckbox', 'allow_ordering_difference', get_string('alloworderingdifference', 'qtype_postgresqlrunner'));
        $mform->setDefault('allow_ordering_difference', 0);
        $mform->addHelpButton('allow_ordering_difference', 'alloworderingdifference', 'qtype_postgresqlrunner');
    
        $this->add_interactive_settings();
    }
    
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }

    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        
        if (empty($question->options)) {
            return $question;
        }
        
        $question->sqlcode = $question->options->sqlcode;
        $question->expected_result = $question->options->expected_result;
        $question->template = $question->options->template;
        $question->grading_type = $question->options->grading_type;
        $question->case_sensitive = $question->options->case_sensitive;
        $question->allow_ordering_difference = $question->options->allow_ordering_difference;
        
        return $question;
    }
}