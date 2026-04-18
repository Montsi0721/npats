<?php
require_once __DIR__ . '/includes/config.php';
if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'LOGOUT', '');
}
session_unset();
session_destroy();
redirect(APP_URL . '/index.html');
