<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'PostgreSQL Runner';
$string['pluginname_help'] = 'A question type that allows running PostgreSQL queries and comparing their results with expected values.';
$string['pluginname_link'] = 'question/type/postgresqlrunner';
$string['pluginnameadding'] = 'Adding a PostgreSQL Runner question';
$string['pluginnameediting'] = 'Editing a PostgreSQL Runner question';
$string['pluginnamesummary'] = 'A question type that allows students to write PostgreSQL queries and checks them by running them against a database.';

$string['sqlheader'] = 'SQL settings';
$string['sqlcode'] = 'SQL Code';
$string['sqlcode_help'] = 'Enter the sample SQL code that provides the correct answer to the question.';
$string['validatesql']      = 'Validate SQL';
$string['validatesqlok']    = 'SQL looks good ✔️';
$string['validatesqlfail']  = 'Error: {$a}';

$string['additionalinfo'] = 'Additional information';
$string['additionalinfo_help'] = 'Optional field for additional information or explanation about the query.';
$string['template'] = 'Code Template';
$string['template_help'] = 'Enter a code template that will be provided to students as a starting point for their answer.';
$string['gradingtype'] = 'Grading Type';
$string['gradingtype_help'] = 'Select how the student\'s answer will be graded. "Exact" requires the result to match exactly (including field names). "Partial" allows different field names but the data must match.';
$string['gradingtypeexact'] = 'Exact match';
$string['gradingtypepartial'] = 'Partial match (ignore field names)';
$string['casesensitive'] = 'Case Sensitivity';
$string['casesensitive_help'] = 'If enabled, the comparison between expected and actual results will be case sensitive.';
$string['alloworderingdifference'] = 'Result Ordering';
$string['alloworderingdifference_help'] = 'If enabled, the order of rows in the result will be ignored during comparison.';

$string['pleaseanswer'] = 'Please enter your SQL query.';
$string['noresults'] = 'No results';
$string['correctresult'] = 'Correct! Your query produces the expected result.';
$string['incorrectresult'] = 'Incorrect result. Your query did not produce the expected result.';
$string['yourresult'] = 'Your result:';
$string['expectedresult_display'] = 'Expected result:';
$string['correctanswer'] = 'A correct answer would be: {$a}';
$string['invalidjson'] = 'Invalid JSON format';

$string['queryerror'] = 'Query error';
$string['yourtablestate'] = 'State of the table after your query';
$string['expectedtablestate'] = 'Expected table state';
$string['tablestructure'] = 'Table structure';
$string['tabledata'] = 'Table data';
$string['columnname'] = 'Column name';
$string['datatype'] = 'Data type';
$string['nullable'] = 'Nullable';
$string['defaultvalue'] = 'Default value';
$string['nodatainside'] = 'Table contains no data';
$string['forbidden_command'] = 'Forbidden SQL command detected';
$string['system_table_access'] = 'Access to system tables is not allowed';