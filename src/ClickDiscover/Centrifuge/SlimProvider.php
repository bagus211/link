<?php

namespace ClickDiscover\Centrifuge;

use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

use Psr7Middlewares\Middleware;


class SlimProvider {

    protected $settings;
    protected $container;
    protected $services;
    public $app;

    public function __construct($settings) {
        $this->settings = $settings;
        $this->container = new \Slim\Container($this->settings);
        $this->services  = new ServiceProvider();
        $this->services->register($this->container);

        $this->app = new \Slim\App($this->container);
        $this->addMiddlewares();

        // Add renderers
        $this->addTwig();
        $this->addPlates();

        return $this->app;
    }

    public function addTwig() {
        $this->container['twig'] = function ($c) {
            $view = new \Slim\Views\Twig($c['settings']['paths']['templates.path'], [
                'cache' => '/tmp',
                'debug' => true,
                'cache' => false
            ]);
            $view->addExtension(new \Slim\Views\TwigExtension(
                $c['router'],
                $c['request']->getUri()
            ));
            return $view;
        };
    }

    public function addPlates() {
        $container['plates'] = function ($c) {
            $templateRoot =
                $container['settings']['paths']['templates.path'] .
                $container['settings']['paths']['relative.landers'];

            $plates = new \League\Plates\Engine($templateRoot);
            $plates->loadExtension(new \Flagship\Plates\VariantExtension);
            $plates->loadExtension(new \Flagship\Plates\HtmlExtension);
            $view = new \ClickDiscover\View\PlatesEngine($plates, $c['settings']['paths']['relative.static']);
            $view->addFolder('admin', $c['settings']['paths']['templates.path'] . '/admin');
            return $view;
        };
    }

    public function addMiddlewares() {
        $app = $this->app;

        Middleware::setStreamFactory(function ($file, $mode) {
            return new \Slim\Http\Body(fopen('php://temp', 'r+'));
        });

        $app->add(Middleware::AccessLog($this->container['logger']));
        $app->add(Middleware::ClientIp());
        $app->add(
            Middleware::TrailingSlash(false)
                ->redirect(301)
        );


        // Appends trailing / to urls
        // $app->add(function (Request $request, Response $response, callable $next) {
            // $uri = $request->getUri();
            // $path = $uri->getPath();
            // if ($path != '/' && substr($path, -1) == '/') {
                // // permanently redirect paths with a trailing slash
                // // to their non-trailing counterpart
                // $uri = $uri->withPath(substr($path, 0, -1));
                // return $response->withRedirect((string)$uri, 301);
            // }

            // return $next($request, $response);
        // });

    }
}
