<?php
/**
 * LOGOUT
 */
require __DIR__ . '/includes/bootstrap.php';
logout();
session_start();
flash('success', 'You have been logged out.');
redirect('login.php');
