<?php

if (version_compare(PHP_VERSION, '7.2.5', '<')) {
    echo 'Minimum requirement is PHP 7.2.5 You are using: '.PHP_VERSION."\n";
    die;
}

if (! is_writable(__DIR__.'/../private/logs/')) {
    echo 'Folder not writable: /private/logs/'."\n";
    die;
}

if (! is_writable(__DIR__.'/../repository/')) {
    echo 'Folder not writable: /repository/'."\n";
    die;
}

if (! file_exists(__DIR__.'/../configuration.php')) {
    copy(__DIR__.'/../configuration_sample.php', __DIR__.'/../configuration.php');
}

require __DIR__.'/../vendor/autoload.php';

if (! defined('APP_ENV')) {
    define('APP_ENV', 'production');
}

if (! defined('APP_PUBLIC_PATH')) {
    define('APP_PUBLIC_PATH', '');
}

define('APP_PUBLIC_DIR', __DIR__);
define('APP_VERSION', '7.6.0');

use Filegator\App;
use Filegator\Config\Config;
use Filegator\Container\Container;
use Filegator\Kernel\Request;
use Filegator\Kernel\Response;
use Filegator\Kernel\StreamedResponse;
use Filegator\Services\Router\Router;
use Filegator\Services\Service;
use Filegator\Services\Auth\AuthInterface;

$config = require __DIR__.'/../configuration.php';

class RedirectRouter implements Service {
    protected $request;

    protected $auth;

    protected $container;

    protected $user;

    public function __construct(Request $request, AuthInterface $auth, Container $container)
    {
        $this->request = $request;
        $this->container = $container;
        $this->user = $auth->user() ?: $auth->getGuest();
    }

    public function init(array $config = [])
    {
        $httpMethod = $this->request->getMethod();

        $routes = require $config['routes_file'];

        $dispatcher = FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $r) use ($routes) {
            if ($routes && ! empty($routes)) {
                foreach ($routes as $params) {
                    if ($this->user->hasRole($params['roles']) && $this->user->hasPermissions($params['permissions'])) {
                        $r->addRoute($params['route'][0], $params['route'][1], $params['route'][2]);
                    }
                }
            }
        });

        $routeInfo = $dispatcher->dispatch($httpMethod, '/onedrive/redirect');

        $controller = '\Filegator\Controllers\ErrorController';
        $action = 'notFound';
        $params = [];

        switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::FOUND:
            $handler = explode('@', $routeInfo[1]);
            $controller = $handler[0];
            $action = $handler[1];
            $params = $routeInfo[2];

            break;
        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            $action = 'methodNotAllowed';

            break;
        }

        $this->container->call([$controller, $action], $params);
    }
}

$config['services'][Router::class]['handler'] = RedirectRouter::class;

new App(new Config($config), Request::createFromGlobals(), new Response(), new StreamedResponse(), new Container());
