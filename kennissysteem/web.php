#!/usr/bin/env php
<?php

require(__DIR__ . '/www/webfrontend.php');

$_SERVER['DOCUMENT_ROOT'] = $_SERVER['CLI_ROOT'];

$query = $_SERVER['QUERY_STRING'];
parse_str($query, $_GET);

if($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    $postData = file_get_contents('php://stdin');
    parse_str($postData, $_POST);
}

$file = __DIR__ . '/knowledgebases/crossing.xml';

$frontend = new WebFrontend($file);
$frontend->main();
