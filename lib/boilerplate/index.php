<?php

$useBuild = false;

?><!doctype html>
<html>
<head>
    <meta charset='utf-8'>
    <title>JSONtoES6 - Prototype</title>
    <link rel='stylesheet' href='assets/css/project.css'/>
    <script src='//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js'></script>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <?php if ($useBuild): ?>
        <script src='build.js'></script>
    <?php else: ?>
        <script src='jspm_packages/system.js'></script>
        <script src='config.js'></script>
        <script>
            System.import('main');
        </script>
    <?php endif; ?>
</head>
<body>
<h1>Watch the console -- everything ok?</h1>
</body>
</html>