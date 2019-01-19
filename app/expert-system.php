<?php

require_once __DIR__ . '/solver.php';

$processors = array(
    'boolean' => function ($value, $key, &$filters) {
        $filters[] = "$key = $value";
    },
    'sugar_free' => function ($value, $key, &$filters) {
        if ($value === 'yes') {
            $filters[] = "(0.0 + sugar) / serving_size * 100 <= 1";
        } else if ($value === 'no') {
            $filters[] = "(0.0 + sugar) / serving_size * 100 > 1";
        }
    },
    'range' => function ($value, $key, &$filters) {
        $value = trim($value);
        if (preg_match('/(?:>=?|<=?|=)[0-9]+/i', $value) || preg_match('/between\s+[0-9]+\s+and\s+[0-9]+/i', $value)) {
            $filters[] = "$key $value";
        }
    }
);

function applyReasoning(&$row, KnowledgeState $state, PDO $db, &$statements) {
    $row['reasons'] = array();
    if ($row['sugar_free']) {
        $row['reasons'][] = 'The product is sugar-free.';
    } else {
        $statements['get_average_sugar_per_category']->bindValue('category', $row['category']);
        if(!$statements['get_average_sugar_per_category']->execute()) {
            $errInfo = $db->errorInfo();
            throw new RuntimeException($errInfo[2]);
        }
        $average = $statements['get_average_sugar_per_category']->fetch(PDO::FETCH_NUM)[0];
        $statements['less_than_average_sugar_in_category']->bindValue('category', $row['category']);
        if (!$statements['less_than_average_sugar_in_category']->execute()) {
            $errInfo = $db->errorInfo();
            throw new RuntimeException($errInfo[2]);
        }
        $listIds = [];
        while (($idRow = $statements['less_than_average_sugar_in_category']->fetch(PDO::FETCH_ASSOC)) !== false) {
            $listIds[] = $idRow['id'];
        }
        unset($idRow);
        if (in_array($row['id'], $listIds)) {
            $row['reasons'][] = "The product contains less than the average amount ({$average}g) of sugar within its category: {$row['category']}";
        }
        $statements['less_than_average_sugar_in_category']->closeCursor();

    }
    if (!$row['has_sweeteners']) {
        $row['reasons'][] = 'The product does not contain artificial sweeteners.';
    }
    if (isset($state->facts['is_carbonated']) && $state->facts['is_carbonated'] !== STATE_UNDEFINED) {
        if ($row['is_carbonated']) {
            $row['reasons'][] = 'The product is a carbonated drink.';
        } else {
            $row['reasons'][] = 'The product is a flat (non-carbonated) drink';
        }
    }
    if (isset($state->facts['manufacturer']) && $state->facts['manufacturer'] !== STATE_UNDEFINED) {
        if ($row['manufacturer'] === 'Coca Cola') {
            $row['reasons'][] = 'This product is produced by the Coca Cola Company.';
        } else if ($row['manufacturer'] === 'Pepsi Co.') {
            $row['reasons'][] = 'This product is produced by the Pepsi Company.';
        }
    }
    if (!isset($state->facts['energy_drink']) || $state->facts['energy_drink'] === STATE_UNDEFINED || !$state->facts['energy_drink']) {
        if (isset($state->facts['has_caffeine']) && $state->facts['has_caffeine'] !== STATE_UNDEFINED) {
            if ($row['has_caffeine']) {
                $row['reasons'][] = 'This product contains caffeine.';
            } else {
                $row['reasons'][] = 'This product does not contain caffeine';
            }
        }
    }
    if(isset($state->facts['package_size'])) {
        if($state->facts['package_size'] === 'large') {
            if(isset($state->facts['weekly_usage']) && $state->facts['weekly_usage'] === 'heavy') {
                $row['reasons'][] = 'This is a large package, suitable for a consumer that consumes more than 7L soft drinks per week.';
            } else if(isset($state->facts['diversity']) && $state->facts['diversity'] === 'no') {
                $row['reasons'][] = 'This is a large package, suitable for prolonged consumption.';
            }
        } else if($state->facts['package_size'] === 'small') {
            if($row['serving_per_product'] > 3) {
                $row['reasons'][] = 'This is a large package of many small packages, suitable for prolonged light consumption.';
            }
        }
    }
}

function expert_advice_product(KnowledgeDomain $domain, KnowledgeState $state) {
    global $processors;
    $facts = array();
    $filters = array();
    foreach ($domain->goals as $goal) {
        /* @var $goal Goal */
        if (isset($state->facts[$goal->name]) && $state->facts[$goal->name] !== STATE_UNDEFINED) {
            $value = $state->facts[$goal->name];
            $facts[$goal->name] = $value;
            if (in_array($goal->name, array('has_sweeteners', 'is_carbonated', 'has_caffeine'), true)) {
                $processors['boolean']($value, $goal->name, $filters);
            } else if (in_array($goal->name, array('price', 'diet'), true)) {
                $processors['range']($value, $goal->name, $filters);
            } else if ($goal->name === 'sugar_free') {
                $processors[$goal->name]($value, $goal->name, $filters);
            } else if ($goal->name === 'manufacturer') {
                $filters[] = "manufacturer = \"$value\"";
            }
        }
    }
    if (isset($state->facts['energy_drink']) && !$state->facts['energy_drink']) {
        $filters[] = "energy_drink = 0";
    }
    if (isset($state->facts['diet']) && is_numeric($state->facts['diet'])) {
        $maxSugarPerDay = ($state->facts['diet'] / 4) * (5 / 100);
        $filters[] = "sugar <= $maxSugarPerDay";
    }
    if (isset($state->facts['price']) && is_numeric($state->facts['price'])) {
        $filters[] = "price <= {$state->facts['price']}";
    }
    if (isset($state->facts['package_size'])) {
        if ($state->facts['package_size'] === 'small') {
            $filters[] = "serving_per_unit <= 3";
        }
        if ($state->facts['package_size'] === 'large') {
            $filters[] = "serving_per_product > 3";
        }
    }
    $sql =
        'SELECT *, ((0.0 + sugar) / serving_size * 100) AS "sugar_per_100", (((0.0 + sugar) / serving_size * 100) <= 1) AS "sugar_free" FROM products WHERE ' .
        implode(' AND ', $filters) .
        ' ORDER BY sugar, price, calories';
    $dbPath = __DIR__ . '/db/products.sqlite';
    $db = new PDO('sqlite:' . $dbPath);
    $result = $db->query($sql);
    if ($result === false) {
        var_dump($db->errorInfo());
        exit;
    }
    $products = [];
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== FALSE) {
        $row['sugar_free'] = boolval($row['sugar_free']);
        $row['has_sweeteners'] = boolval($row['has_sweeteners']);
        $row['has_caffeine'] = boolval($row['has_caffeine']);
        $row['energy_drink'] = boolval($row['energy_drink']);
        $row['is_carbonated'] = boolval($row['is_carbonated']);
        $row['serving_per_unit'] = intval($row['serving_per_unit'], 10);
        $row['serving_per_product'] = intval($row['serving_per_product'], 10);
        $products[] = $row;
    }
    $result->closeCursor();
    $statements = array();
    $statements['less_than_average_sugar_in_category'] =
        $db->prepare(
            'SELECT id FROM products WHERE category = :category AND (0.0 + sugar) / serving_size * 100 > 1 AND sugar < (SELECT AVG(sugar) FROM products WHERE category = :category AND (0.0 + sugar) / serving_size * 100 > 1)'
        );
    if ($statements['less_than_average_sugar_in_category'] === false) {
        $errInfo = $db->errorInfo();
        throw new RuntimeException($errInfo[2]);
    }
    $statements['get_average_sugar_per_category'] = $db->prepare('SELECT ROUND(AVG(sugar), 2) FROM products WHERE category = :category AND (0.0 + sugar) / serving_size * 100 > 1');
    if($statements['get_average_sugar_per_category'] === false) {
        $errInfo = $db->errorInfo();
        throw new RuntimeException($errInfo[2]);
    }
    foreach ($products as &$product) {
        applyReasoning($product, $state, $db, $statements);
    }
    $template = new Template('templates/products.phtml');
    $template->products = $products;
    return $template;
}