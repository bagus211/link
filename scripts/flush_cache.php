<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

$centrifuge =  new Flagship\Container($config);
$centrifuge['cache']->flush();

// $centrifuge['session.cache']->flush();
