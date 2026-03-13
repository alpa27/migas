<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
logActivity('LOGOUT', 'User keluar dari sistem', 'auth');
session_destroy();
redirect(BASE_URL . '/index.php');