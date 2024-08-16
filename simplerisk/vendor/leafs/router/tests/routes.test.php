<?php

use Leaf\Router;

class TRoute
{
    static $val = true;
}

test('router match', function () {
    $_SERVER['REQUEST_METHOD'] = 'PUT';
    $_SERVER['REQUEST_URI'] = '/put';

    TRoute::$val = true;

    $router = new Router;
    $router->match('PUT', '/put', function () {
        TRoute::$val = false;
    });
    $router->run();

    expect(TRoute::$val)->toBe(false);
});

test('get route', function () {
    TRoute::$val = true;

    $router = new Router;
    $router->get('/', function () {
        TRoute::$val = false;
    });
    $router->run();

    expect(TRoute::$val)->toBe(false);
});

test('post route', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/delete';

    TRoute::$val = true;

    $router = new Router;
    $router->post('/delete', function () {
        TRoute::$val = false;
    });
    $router->run();

    expect(TRoute::$val)->toBe(false);
});

test('put route', function () {
    $_SERVER['REQUEST_METHOD'] = 'PUT';
    $_SERVER['REQUEST_URI'] = '/patch';

    TRoute::$val = true;

    $router = new Router;
    $router->put('/patch', function () {
        TRoute::$val = false;
    });
    $router->run();

    expect(TRoute::$val)->toBe(false);
});

test('patch route', function () {
    $_SERVER['REQUEST_METHOD'] = 'PATCH';
    $_SERVER['REQUEST_URI'] = '/';

    TRoute::$val = true;

    $router = new Router;
    $router->patch('/', function () {
        TRoute::$val = false;
    });
    $router->run();

    expect(TRoute::$val)->toBe(false);
});

test('options route', function () {
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    $_SERVER['REQUEST_URI'] = '/';

    TRoute::$val = true;

    $router = new Router;
    $router->options('/', function () {
        TRoute::$val = false;
    });
    $router->run();

    expect(TRoute::$val)->toBe(false);
});

test('delete route', function () {
    $_SERVER['REQUEST_METHOD'] = 'DELETE';
    $_SERVER['REQUEST_URI'] = '/';

    TRoute::$val = true;

    $router = new Router;
    $router->delete('/', function () {
        TRoute::$val = false;
    });
    $router->run();

    expect(TRoute::$val)->toBe(false);
});

test('route should return url by route name', function () {
    $router = new Router;
    $router->match('GET', '/route/url', ['handler', 'name' => 'route-name']);

    $routeUrl = $router->route('route-name');

    expect($routeUrl)->toBe('/route/url');
});

test('route should throw exception if no route found for name', function () {
    $router = new Router;

    expect(fn() => $router->route('non-existent-route-name'))->toThrow(Exception::class);
});
