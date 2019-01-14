<?php

require_once __DIR__ . '/../util.php';
require_once __DIR__ . '/../solver.php';
require_once __DIR__ . '/../reader.php';
require_once __DIR__ . '/../webfrontend.php';

date_default_timezone_set('Europe/Amsterdam');

$errors = array();
$kbFile = __DIR__ . '/../../sugar.xml';

$frontEnd = new WebFrontend($kbFile);
header('Content-Type: text/html; charset=utf-8', true);
$frontEnd->main();
