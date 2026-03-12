<?php
require_once __DIR__ . '/config/session.php';
session_destroy();
redirect(BASE_URL . '/index.php');
