<?php
/**
 * Serves the login/register modal markup on demand so it's not sitting in
 * every page's initial DOM (extra weight + a duplicate login/register form
 * on pages that already have their own, e.g. login.php/register.php).
 * Fetched once by assets/js/auth-modal.js the first time a guest opens it.
 */
require __DIR__ . '/../config/config.php';
header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/../includes/auth-modal.php';
