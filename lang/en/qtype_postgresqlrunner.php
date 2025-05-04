<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'PostgreSQL Runner';
$string['pluginname_help'] = 'A question type that allows running PostgreSQL queries and comparing their results with expected values.';
$string['pluginname_link'] = 'question/type/postgresqlrunner';
$string['pluginnameadding'] = 'Adding a PostgreSQL Runner question';
$string['pluginnameediting'] = 'Editing a PostgreSQL Runner question';
$string['pluginnamesummary'] = 'A question type that allows students to write PostgreSQL queries and checks them by running them against a database.';

$string['sqlheader'] = 'SQL settings';
$string['sqlcode'] = 'Sample SQL code';
$string['sqlcode_help'] = 'Enter the sample SQL code that provides the correct answer to the question.';
$string['expectedresult'] = 'Expected result';
$string['expectedresult_help'] = 'Enter the expected result in JSON format. The format should be: {"fields": ["field1", "field2", ...], "data": [{"field1": "value1", "field2": "value2", ...}, ...]}';
$string['additionalinfo'] = 'Additional information';
$string['additionalinfo_help'] = 'Optional field for additional information or explanation about the query.';
$string['dbconnection'] = 'Database connection settings';
$string['dbconnection_help'] = 'Enter the database connection settings in JSON format. The format should be: {"host": "localhost", "dbname": "postgres", "user": "postgres", "password": "password", "port": 5432}';
$string['template'] = 'Code template';
$string['template_help'] = 'Enter a code template that will be provided to students as a starting point for their answer.';
$string['gradingtype'] = 'Grading type';
$string['gradingtype_help'] = 'Select how the student\'s answer will be graded. "Exact" requires the result to match exactly (including field names). "Partial" allows different field names but the data must match.';
$string['gradingtypeexact'] = 'Exact match';
$string['gradingtypepartial'] = 'Partial match (ignore field names)';
$string['casesensitive'] = 'Case sensitive comparison';
$string['casesensitive_help'] = 'If enabled, the comparison between expected and actual results will be case sensitive.';
$string['alloworderingdifference'] = 'Allow different ordering of results';
$string['alloworderingdifference_help'] = 'If enabled, the order of rows in the result will be ignored during comparison.';

$string['pleaseanswer'] = 'Please enter your SQL query.';
$string['noresults'] = 'No results';
$string['correctresult'] = 'Correct! Your query produces the expected result.';
$string['incorrectresult'] = 'Incorrect result. Your query did not produce the expected result.';
$string['yourresult'] = 'Your result:';
$string['expectedresult_display'] = 'Expected result:';
$string['correctanswer'] = 'A correct answer would be: {$a}';
$string['invalidjson'] = 'Invalid JSON format';
$string['invaliddbconnection'] = 'Invalid database connection settings. Required fields: host, dbname, user';

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