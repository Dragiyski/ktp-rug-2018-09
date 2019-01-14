<?php

require(__DIR__ . '/reader.php');
require(__DIR__ . '/solver.php');

$data = file_get_contents('php://stdin');
$data = json_decode($data, true);

$kbFile = $file = $_SERVER['argv'][1];
$reader = new KnowledgeBaseReader();
$domain = $reader->parse($this->kb_file);

$state = KnowledgeState::initializeForDomain($domain);
if(isset($data['facts']) && is_array($data['facts'])) {
    foreach($data['facts'] as $factName => $factValue) {
        if($factName === 'undefined') {
            continue;
        }
        $state->facts[$factName] = $factValue;
    }
}

$solver = new Solver();
$result = $solver->backwardChain($domain, $state);
$output = array();
$output['facts'] = array();
foreach($state->facts as $factName => $factValue) {
    $output['facts'][$factName] = $factValue;
}
if($result instanceof AskedQuestion) {
    $output['question'] = array();
    $output['question']['skippable'] = $result->skippable;
    $output['question']['description'] = $result->question->description;
    $output['question']['answers'] = array();
    foreach($result->question->options as $option) {
        /* @var $option Option */
        $answer = array();
        $answer['description'] = $option;
    }
}
