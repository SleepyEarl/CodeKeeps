<?php

require_once __DIR__ .'/config/config.php';

if (is_logged_in()) {
    header('Location: views/dashboard.php');
    exit;
}

header('Location: views/login.php');
exit;