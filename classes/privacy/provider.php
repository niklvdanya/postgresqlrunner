<?php
namespace qtype_postgresqlrunner\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;

class provider implements 
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'qtype_postgresqlrunner_options',
            [
                'questionid' => 'privacy:metadata:qtype_postgresqlrunner_options:questionid',
                'sqlcode' => 'privacy:metadata:qtype_postgresqlrunner_options:sqlcode',
                'expected_result' => 'privacy:metadata:qtype_postgresqlrunner_options:expected_result',
                'db_connection' => 'privacy:metadata:qtype_postgresqlrunner_options:db_connection',
                'template' => 'privacy:metadata:qtype_postgresqlrunner_options:template',
                'grading_type' => 'privacy:metadata:qtype_postgresqlrunner_options:grading_type',
                'case_sensitive' => 'privacy:metadata:qtype_postgresqlrunner_options:case_sensitive',
                'allow_ordering_difference' => 'privacy:metadata:qtype_postgresqlrunner_options:allow_ordering_difference',
            ],
            'privacy:metadata:qtype_postgresqlrunner_options'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    public static function export_user_data(approved_contextlist $contextlist) {
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
    }

    public static function get_users_in_context(userlist $userlist) {
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
    }
}