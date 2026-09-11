<?php
require_once __DIR__ . '/app/bootstrap.php';
requireLogin();

// attendance.php already provides a calendar view — redirect there
redirect('attendance.php');
