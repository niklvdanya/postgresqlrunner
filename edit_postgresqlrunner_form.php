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
    
        $mform->addElement('textarea', 'expected_result', get_string('expectedresult', 'qtype_postgresqlrunner'),
                           array('rows' => 5, 'cols' => 80, 'class' => 'text-monospace'));
        $mform->setType('expected_result', PARAM_RAW);
        $mform->addHelpButton('expected_result', 'expectedresult', 'qtype_postgresqlrunner');
    
        $mform->addElement('textarea', 'db_connection', get_string('dbconnection', 'qtype_postgresqlrunner'),
                           array('rows' => 5, 'cols' => 80, 'class' => 'text-monospace'));
        $mform->setType('db_connection', PARAM_RAW);
        $mform->addRule('db_connection', null, 'required', null, 'client');
        $mform->addHelpButton('db_connection', 'dbconnection', 'qtype_postgresqlrunner');
        $mform->setDefault('db_connection', '{"host": "db.example.com", "dbname": "practice_db", "user": "readonly_user", "password": "change_me_123", "port": 5432}');
    
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
        
        if (!empty($data['db_connection'])) {
            $db_connection = $data['db_connection'];
            if (!$this->is_valid_json($db_connection)) {
                $errors['db_connection'] = get_string('invalidjson', 'qtype_postgresqlrunner');
            } else {
                $db_data = json_decode($db_connection, true);
                if (!isset($db_data['host']) || !isset($db_data['dbname']) || !isset($db_data['user']) || !isset($db_data['password'])) {
                    $errors['db_connection'] = get_string('invaliddbconnection', 'qtype_postgresqlrunner');
                } else {
                    if (!filter_var($db_data['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) &&
                        !filter_var($db_data['host'], FILTER_VALIDATE_IP)) {
                        $errors['db_connection'] = 'Некорректный адрес хоста базы данных';
                    }
                    
                    if (isset($db_data['port'])) {
                        $port = (int)$db_data['port'];
                        if ($port < 1 || $port > 65535) {
                            $errors['db_connection'] = 'Некорректный порт базы данных';
                        }
                    }
                }
            }
        }
        
        if (!empty($data['sqlcode'])) {
            try {
                \qtype_postgresqlrunner\security\blacklist::validate_sql($data['sqlcode']);
            } catch (\Exception $e) {
                $errors['sqlcode'] = $e->getMessage();
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
        $question->expected_result = $question->options->expected_result;
        $question->db_connection = $question->options->db_connection;
        $question->template = $question->options->template;
        $question->grading_type = $question->options->grading_type;
        $question->case_sensitive = $question->options->case_sensitive;
        $question->allow_ordering_difference = $question->options->allow_ordering_difference;
        
        return $question;
    }
    
    private function is_valid_json($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}