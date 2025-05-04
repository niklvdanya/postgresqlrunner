<?php
define('AJAX_SCRIPT', true);
require_once('../../../../config.php');

require_sesskey();
require_login();

header('Content-Type: application/json');

echo json_encode(['success' => true]);