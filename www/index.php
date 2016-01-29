<?php
if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $file = __DIR__ . $_SERVER['REQUEST_URI'];
    if (is_file($file)) {
        return false;
    }
}

$rootDir = dirname(__DIR__);
require $rootDir . '/vendor/autoload.php';

use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

$settings = require $rootDir . '/settings.php';
$slim = new ClickDiscover\Centrifuge\SlimProvider($settings);
$app = $slim->app;


$app->get('/debug/{obj}', function ($req, $res, $args) use ($app) {
    echo '<pre>';
    $c = $app->getContainer();
    $obj = $args['obj'];
    if($obj != 'this') {
        $c = $c->get($obj);
    }
    print_r($c);
    echo '</pre>';
});

$app->get('/content/{id:[0-9]+}', function (Request $req, Response $res, $args) {
    $lander = $this->landers->fetch($args['id']);
    // 404 on Not Found

    $html = $this->plates->landerRender($lander);
    $res->getBody()->write($html);
    return $res;
});

$app->get('/content/{landerId:[0-9]+}/click[/{linkId:[0-9]+}]', function (Request $req, Response $res, $args) {
    // Old Procedure
    // 1) Get Lander From Session -> Referrer -> Query param fp_lid
    // 2) Read a global redirect URL set in config.php
    // 2b) Track the event
    // 3) Redirect to url and fill in some query params
    $linkId = $req->getAttribute("linkId", 1);

    echo '<pre>';
    echo "Clicked link # " . $linkId . " on Content / " . $args['landerId'] . PHP_EOL;
    echo PHP_EOL . "Client IP: " . \Psr7Middlewares\Middleware\ClientIp::getIp($req);
    echo PHP_EOL . "Args: ";
    print_r($args);
    echo '</pre>';

})->setName('click');



$app->run();
?>
