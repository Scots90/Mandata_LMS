<?php
// index.php
session_start();
require_once 'includes/functions.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/index.php');
    } else {
        redirect('dashboard.php');
    }
} else {
    redirect('login.php');
}