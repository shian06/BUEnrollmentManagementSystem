<?php
require_once 'includes/functions.php';
unset($_SESSION['staff_id'], $_SESSION['staff_name']);
redirect('login.php');
