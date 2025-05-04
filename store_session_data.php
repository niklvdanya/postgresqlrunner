<?php
define('AJAX_SCRIPT', true);
require_once('../../../../config.php');
require_once($CFG->dirroot . '/question/type/postgresqlrunner/classes/security/connection_manager.php');

require_sesskey();

require_login();

header('Content-Type: application/json');

$action = required_param('action', PARAM_ALPHA);
$session_id = required_param('session_id', PARAM_ALPHANUM);

if (!preg_match('/^conn_[a-z0-9]{10,13}$/', $session_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid session ID format']);
    die();
}

$max_age = 300;

$user_key = 'postgresqlrunner_' . $USER->id . '_' . $session_id;

if ($action === 'store') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        die();
    }
    
    $conn_password = required_param('conn_password', PARAM_RAW);

    if (empty($conn_password)) {
        echo json_encode(['success' => false, 'error' => 'Empty password']);
        die();
    }
    
    $encrypted_password = \qtype_postgresqlrunner\security\connection_manager::encrypt_connection_string($conn_password);

    $_SESSION[$user_key] = [
        'password' => $encrypted_password,
        'timestamp' => time()
    ];
    
    echo json_encode(['success' => true]);
    
} else if ($action === 'retrieve') {
    if (!isset($_SESSION[$user_key]) || 
        !isset($_SESSION[$user_key]['password']) || 
        !isset($_SESSION[$user_key]['timestamp'])) {
        echo json_encode(['success' => false, 'error' => 'No data found']);
        die();
    }
    
    $timestamp = $_SESSION[$user_key]['timestamp'];
    if (time() - $timestamp > $max_age) {
        unset($_SESSION[$user_key]);
        echo json_encode(['success' => false, 'error' => 'Data expired']);
        die();
    }
    
    $encrypted_password = $_SESSION[$user_key]['password'];
    $password = \qtype_postgresqlrunner\security\connection_manager::decrypt_connection_string($encrypted_password);
    
    echo json_encode([
        'success' => true,
        'password' => $password
    ]);
    
} else if ($action === 'delete') {
    if (isset($_SESSION[$user_key])) {
        unset($_SESSION[$user_key]);
    }
    
    echo json_encode(['success' => true]);
    
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}