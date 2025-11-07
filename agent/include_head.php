<?php
/*
 * Agent Head HTML - Consistent with MikhMon styling
 * This file provides MikhMon styling for agent pages
 */

// Set default theme if not set
$theme = $_SESSION['agent_theme'] ?? 'dark';
$themecolor = $_SESSION['agent_themecolor'] ?? '#3a4149';
$hotspotname = 'Agent Panel';
?>
<!DOCTYPE html>
<html>
<head>
    <title>MIKHMON Agent - <?= $hotspotname; ?></title>
    <meta charset="utf-8">
    <meta http-equiv="cache-control" content="private" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?= $themecolor ?>" />
    <!-- Font Awesome -->
    <link rel="stylesheet" type="text/css" href="../css/font-awesome/css/font-awesome.min.css" />
    <!-- Mikhmon UI -->
    <link rel="stylesheet" href="../css/mikhmon-ui.<?= $theme; ?>.min.css">
    <!-- favicon -->
    <link rel="icon" href="../img/favicon.png" />
    <!-- jQuery -->
    <script src="../js/jquery.min.js"></script>
    <!-- pace -->
    <link href="../css/pace.<?= $theme; ?>.css" rel="stylesheet" />
    <script src="../js/pace.min.js"></script>
</head>
<body>
<div class="wrapper">
<div class="content-wrapper">

