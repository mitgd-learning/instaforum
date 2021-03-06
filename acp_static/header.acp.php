<?php
/**
 * header.acp.php
 * ACP static component: Header 
 */

// ------------------------------------------------------
// Security check
// ------------------------------------------------------
if(!defined('IF_IN_ACP'))
{
  exit();
}

?><!DOCTYPE html>
<html>
<head>
  <title><?php print IF_APP_NAME; ?> - <?php print isset($ACP_TITLE) ? $ACP_TITLE : 'Admin'; ?></title>
  <link rel="stylesheet" type="text/css" href="acp_static/style/default.css" />
  <script type="text/javascript" src="js/external/jquery.min.js"></script>
  <script type="text/javascript" src="acp_static/js/acp.js"></script>
</head>
<body>

  <!-- BEGIN: Sidebar -->
  <div class="sidebar">
    <div class="logo">
      <a href="./" class="logo"><?php print IF_APP_NAME; ?></a>
    </div>
    <div class="section">
      <span class="title">Board</span>
      <a href="?act=forums">Forums</a>
      <a href="?act=configuration">Configuration</a>
    </div>
    <div class="section">
      <span class="title">Users &amp; Permissions</span>
      <a href="?act=users">Users</a>
      <a href="?act=groups">Groups</a>
    </div>
    <div class="section">
      <span class="title">Integration</span>
      <a href="?act=hooks">Hooks</a>
      <a href="?act=style">Style Editor</a>
    </div>
    <div class="section">
      <span class="title">System</span>
      <a href="DB_Manager.php" target="_blank">Storage</a>
      <a href="?act=about">About</a>
      <a href="?act=logout">Log Out</a>
    </div>
  </div>
  <!-- END: Sidebar -->


  <!-- BEGIN: Body -->
  <div class="body">
