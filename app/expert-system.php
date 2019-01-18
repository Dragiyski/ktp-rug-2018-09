<?php

require_once __DIR__ . '/solver.php';

$processors = array(
    'boolean' => function($value, $key, &$filters) {
        $filters[] = "$key = $value";
    },
    'sugar_free' => function($value, $key, &$filters) {
        if($value === 'yes') {
            $filters[] = "(0.0 + sugar) / serving_size * 100 <= 1";
        } else if($value === 'no') {
            $filters[] = "(0.0 + sugar) / serving_size * 100 > 1";
        }
    },
    'range' => function($value, $key, &$filters) {
        $value = trim($value);
        if(preg_match('/(?:>=?|<=?|=)[0-9]+/i', $value) || preg_match('/between\s+[0-9]+\s+and\s+[0-9]+/i', $value)) {
            $filters[] = "$key $value";
        }
    }
);

function expert_advice_product(KnowledgeDomain $domain, KnowledgeState $state) {
    global $processors;
    $facts = array();
    $filters = array();
    foreach($domain->goals as $goal) {
        /* @var $goal Goal */
        if(isset($state->facts[$goal->name]) && $state->facts[$goal->name] !== STATE_UNDEFINED) {
            $value = $state->facts[$goal->name];
            $facts[$goal->name] = $value;
            if(in_array($goal->name, array('has_sweeteners', 'is_carbonated', 'has_caffeine'), true)) {
                $processors['boolean']($value, $goal->name, $filters);
            } else if(in_array($goal->name, array('price', 'diet'), true)) {
                $processors['range']($value, $goal->name, $filters);
            } else if($goal->name === 'sugar_free') {
                $processors[$goal->name]($value, $goal->name, $filters);
            } else if($goal->name === 'manufacturer') {
                $filters[] = "manufacturer = \"$value\"";
            }
        }
    }
    if(isset($state->facts['energy_drink']) && !$state->facts['energy_drink']) {
        $filters[] = "energy_drink = 0";
    }
    if(isset($state->facts['diet']) && is_numeric($state->facts['diet'])) {
        $maxSugarPerDay = ($state->facts['diet'] / 4) * (2.5 / 100);
        $filters[] = "sugar <= $maxSugarPerDay";
    }
    if(isset($state->facts['price']) && is_numeric($state->facts['price'])) {
        $filters[] = "price <= {$state->facts['price']}";
    }
    if(isset($state->facts['package_size'])) {
        if($state->facts['package_size'] === 'small') {
            $filters[] = "serving_per_unit <= 3";
        }
        if($state->facts['package_size'] === 'large') {
            $filters[] = "serving_per_product > 3";
        }
    }
    $sql = 'SELECT * FROM products WHERE ' . implode(' AND ', $filters) . ' ORDER BY sugar, price, calories';
    $dbPath = __DIR__ . '/db/products.sqlite';
    $db = new PDO('sqlite:' . $dbPath);
    $result = $db->query($sql);
    if($result === false) {
        var_dump($db->errorInfo());
        exit;
    }
    $products = [];
    while(($row = $result->fetch(PDO::FETCH_ASSOC)) !== FALSE) {
        $products[] = $row;
    }
    $result->closeCursor();
    var_dump($facts);
    var_dump($filters);
    var_dump($sql);
    var_dump($products);
    exit;
}