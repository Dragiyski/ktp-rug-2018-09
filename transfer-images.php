<?php
$dbPath = __DIR__ . '/app/db/products.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$result = $db->query('SELECT * FROM products');
if($result === false) {
    var_dump($db->errorInfo());
    exit;
}
$products = [];
while(($row = $result->fetch(PDO::FETCH_ASSOC)) !== FALSE) {
    $source = parse_url($row['image'])['path'];
    $ext = pathinfo($source, PATHINFO_EXTENSION);
    $relative = 'images/' . $row['id'] . '.' . $ext;
    $destination = __DIR__ . '/app/www/' . $relative;
//    var_dump($source);
    var_dump($destination);
    if(file_exists($destination)) {
        continue;
    }
    copy($source, $destination);
    if(!$db->query("UPDATE products SET image = '$relative' WHERE id = {$row['id']}")) {
        var_dump($db->errorInfo());
        exit;
    }
}
