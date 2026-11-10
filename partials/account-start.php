<?php
/** Opens the account layout. Expects $active, $title. */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
page_set(['title' => $title, 'robots' => 'noindex,nofollow']);
require FS_ROOT . '/partials/header.php';
?>
<div class="container py-3">
  <div class="page-head pt-0"><?= breadcrumbs_html([['Home', url('')], ['My Account', url('my-account/')], [$title, null]]) ?><h1 class="h3"><?= e($title) ?></h1></div>
  <?= render_flashes() ?>
  <div class="row g-3">
    <div class="col-lg-3"><?php require FS_ROOT . '/partials/account-nav.php'; ?></div>
    <div class="col-lg-9">
