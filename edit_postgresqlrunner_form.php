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
    
        $mform->addElement('checkbox', 'use_question_bank', get_string('usequestionbank', 'qtype_postgresqlrunner'));
        $mform->addHelpButton('use_question_bank', 'usequestionbank', 'qtype_postgresqlrunner');
    
        $mform->addElement('textarea', 'sqlcode', get_string('sqlcode', 'qtype_postgresqlrunner'),
                           array('rows' => 10, 'cols' => 80, 'class' => 'postgresqlrunner text-monospace'));
        $mform->setType('sqlcode', PARAM_RAW);
        $mform->addHelpButton('sqlcode', 'sqlcode', 'qtype_postgresqlrunner');
    
        $mform->addElement('textarea', 'question_bank', get_string('questionbank', 'qtype_postgresqlrunner'),
                           array('rows' => 10, 'cols' => 80, 'class' => 'text-monospace'));
        $mform->setType('question_bank', PARAM_RAW);
        $mform->addHelpButton('question_bank', 'questionbank', 'qtype_postgresqlrunner');
    
        $mform->addElement('button', 'validatesql',
            get_string('validatesql', 'qtype_postgresqlrunner'),
            ['type' => 'button', 'class' => 'btn btn-secondary', 'id' => 'validate-sql']);
    
        $mform->addElement('static', 'validatesqlmsg', '', html_writer::tag(
            'div', '', ['id' => 'validate-sql-msg']));
    
        global $PAGE;
        $PAGE->requires->jquery();
        
        $PAGE->requires->strings_for_js(
            ['validatesqlok', 'validatesqlfail'],   
            'qtype_postgresqlrunner'                
        );
        $PAGE->requires->js(new moodle_url(
            '/question/type/postgresqlrunner/validate_sql.js'), true);
    
        $mform->addElement('textarea', 'template', get_string('template', 'qtype_postgresqlrunner'),
                           array('rows' => 10, 'cols' => 80, 'class' => 'text-monospace'));
        $mform->setType('template', PARAM_RAW);
        $mform->addHelpButton('template', 'template', 'qtype_postgresqlrunner');
    
        $mform->addElement('textarea', 'environment_init', get_string('environmentinit', 'qtype_postgresqlrunner'),
                           array('rows' => 8, 'cols' => 80, 'class' => 'text-monospace'));
        $mform->setType('environment_init', PARAM_RAW);
        $mform->addHelpButton('environment_init', 'environmentinit', 'qtype_postgresqlrunner');
    
        $mform->addElement('textarea', 'extra_code', get_string('extracode', 'qtype_postgresqlrunner'),
                           array('rows' => 8, 'cols' => 80, 'class' => 'text-monospace'));
        $mform->setType('extra_code', PARAM_RAW);
        $mform->addHelpButton('extra_code', 'extracode', 'qtype_postgresqlrunner');
    
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
    
        $PAGE->requires->js_init_code("
            document.getElementById('id_use_question_bank').addEventListener('change', function() {
                var sqlcodeField = document.getElementById('id_sqlcode');
                var questionBankField = document.getElementById('id_question_bank');
                if (this.checked) {
                    sqlcodeField.disabled = true;
                    questionBankField.disabled = false;
                } else {
                    sqlcodeField.disabled = false;
                    questionBankField.disabled = true;
                }
            });
            // Trigger change event on page load to set initial state
            document.getElementById('id_use_question_bank').dispatchEvent(new Event('change'));
        ");
    }
    
    public function validation($data, $files) {
        global $CFG;                
        $errors = parent::validation($data, $files);
    
        if (empty($data['use_question_bank']) && empty(trim($data['sqlcode']))) {
            $errors['sqlcode'] = get_string('sqlcodeorquestionbankrequired', 'qtype_postgresqlrunner');
        }
    
        if (!empty($data['use_question_bank'])) {
            if (empty(trim($data['question_bank']))) {
                $errors['question_bank'] = get_string('questionbankrequired', 'qtype_postgresqlrunner');
            } else {      
                $question_bank = json_decode($data['question_bank'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors['question_bank'] = get_string('invalidjson', 'qtype_postgresqlrunner');
                } else {
                    $question = $this->_customdata['question'] ?? new qtype_postgresqlrunner_question();
                    foreach ($question_bank as $task) {
                        if (!isset($task['sqlcode']) || !isset($task['questiontext'])) {
                            $errors['question_bank'] = get_string('invalidquestionbankformat', 'qtype_postgresqlrunner');
                            break;
                        }
                        try {
                            $validated_sql = $question->get_validated_sqlcode($task['sqlcode'], $data['question_bank']);
                            \qtype_postgresqlrunner\security\sql_validator::validate_sql($validated_sql);
                        } catch (Exception $e) {
                            $errors['question_bank'] = get_string('invalidquestionbanksql', 'qtype_postgresqlrunner') . ': ' . $e->getMessage();
                            break;
                        }
                    }
                }
            }
        } else {
            try {
                if (!empty(trim($data['sqlcode']))) {
                    \qtype_postgresqlrunner\security\sql_validator::validate_sql($data['sqlcode']);
                }
            } catch (Exception $e) {
                $errors['sqlcode'] = $e->getMessage();   
            }
        }
    
        if (!empty($data['extra_code'])) {
            try {
                \qtype_postgresqlrunner\security\sql_validator::validate_sql($data['extra_code']);
                if (stripos(trim($data['extra_code']), 'SELECT') !== 0) {
                    $errors['extra_code'] = get_string('extracodemustselect', 'qtype_postgresqlrunner');
                }
            } catch (Exception $e) {
                $errors['extra_code'] = $e->getMessage();
            }
        }
    
        return $errors;
    }
    
    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        
        if (empty($question->options)) {
            return $question;
        }
        
        $question->sqlcode = $question->options->sqlcode;
        $question->question_bank = $question->options->question_bank;
        $question->use_question_bank = $question->options->use_question_bank;
        $question->expected_result = $question->options->expected_result;
        $question->template = $question->options->template;
        $question->environment_init = $question->options->environment_init;
        $question->extra_code = $question->options->extra_code;
        $question->grading_type = $question->options->grading_type;
        $question->case_sensitive = $question->options->case_sensitive;
        $question->allow_ordering_difference = $question->options->allow_ordering_difference;
        
        return $question;
    }
}