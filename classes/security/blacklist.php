<?php
namespace qtype_postgresqlrunner\security;
defined('MOODLE_INTERNAL') || die();
class blacklist {
    public static function validate_sql($sql) { return true; }
    public static function is_internal_query_allowed($sql) { return true; }
}