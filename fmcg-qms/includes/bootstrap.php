<?php
/**
 * Single entry point included by every page: loads the full engine stack in dependency order.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/calculations.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/tool_engine.php';
require_once __DIR__ . '/issue_engine.php';
require_once __DIR__ . '/capa_engine.php';
require_once __DIR__ . '/kpi_engine.php';
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/reporting.php';
