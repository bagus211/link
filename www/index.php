<?php
if(php_sapi_name() === 'cli-server') {
    if (preg_match('/\/static\//', $_SERVER['REQUEST_URI'])) {
        return false;
    }
    if (strpos($_SERVER['PHP_SELF'], '/index.php') === false) {
        $_SERVER['PHP_SELF'] = '/index.php' . $_SERVER['PHP_SELF'];
        $_SERVER['SCRIPT_NAME'] = '/index.php';
    }
}

require_once dirname(__DIR__) . '/config.php';
require CENTRIFUGE_ROOT . '/vendor/autoload.php';

$config     = $config['settings'];
$centrifuge = new Flagship\Container($config);
$app        = new Slim\Slim($config['application']);
$bootstrap  = new Flagship\SlimBootstrap($app, $centrifuge);
$app        = $bootstrap->bootstrap();


require_once $config['paths']['routes'] . 'admin.php';
require_once $config['paths']['routes'] . 'main.php';

$app->run();
?>
