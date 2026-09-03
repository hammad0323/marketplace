<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/seo.php'; ?>
<link rel="icon" href="<?= upload_url(get_setting('site_favicon')) ?: asset_url('images/favicon.png') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
<style>
:root{
  --primary-color:<?= clean(get_setting('primary_color','#2f6fed')) ?>;
  --secondary-color:<?= clean(get_setting('secondary_color','#0b1f3a')) ?>;
  --accent-color:<?= clean(get_setting('accent_color','#ff7a1a')) ?>;
  --button-color:<?= clean(get_setting('button_color','#2f6fed')) ?>;
  --header-color:<?= clean(get_setting('header_color','#ffffff')) ?>;
  --footer-color:<?= clean(get_setting('footer_color','#0b1f3a')) ?>;
  --background-color:<?= clean(get_setting('background_color','#f5f7fb')) ?>;
  --text-color:<?= clean(get_setting('text_color','#222222')) ?>;
}
</style>
</head>
<body class="<?= isset($bodyClass) ? clean($bodyClass) : '' ?>">
<?php if (!isset($hideNavbar)) require __DIR__ . '/navbar.php'; ?>
<?php require __DIR__ . '/alerts.php'; ?>
<main class="site-main">
