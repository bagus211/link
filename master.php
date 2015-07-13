<?php

require_once "defines.php";
require_once "models/product.php";
require_once "models/step.php";
require_once "models/tracking.php";
require 'vendor/autoload.php';

$templates    = new League\Plates\Engine(__DIR__.'/templates/');
$db = new PDO(PDO_URL);
$sql = <<<SQL
    SELECT l.*, w.template, w.assets FROM landers l
    INNER JOIN websites w ON (w.id = l.website_id)
    WHERE l.id = 2;
SQL;
$res = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
$tracking = new Tracking($res['tracking_tags']);

if ($res['offer'] == 'adexchange') {
    $sql = "SELECT affiliate_id, vertical, country FROM ae_parameters WHERE id = ".$res['param_id'];
    $params = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
    $products = Product::fetchFromAdExchange($params['affiliate_id'], $params['vertical'], $params['country']);
    $steps = Step::fromProducts($products);
    $template = substr($res['template'], 0, -4);
    $assets = 'templates/' . $res['assets'];
    echo $templates->render('landers/good_housekeeping/alleure', ['steps' => $steps, 'tracking' => $tracking, 'assets' => $assets]);
}
