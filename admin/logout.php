<?php
require_once '../includes/Auth.php';
Auth::start();
Auth::logout();
header('Location: login.php');
exit;
