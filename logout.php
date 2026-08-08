<?php
require __DIR__ . '/config/config.php';

logout_user();
session_start();
flash_set('info', 'You have been logged out.');
redirect('/');
