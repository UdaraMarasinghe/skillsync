<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('HTTP_PATH','http://localhost/skillsync/');

require_once __DIR__ . '/db.php';
