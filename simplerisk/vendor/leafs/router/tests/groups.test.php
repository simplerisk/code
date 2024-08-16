<?php

use Leaf\Router;

class TGroup
{
    static $val = true;
}

test('route groups', function () {
    $_SERVER['REQUEST_URI'] = '/group/route';

    $router = new Router;

    TGroup::$val = true;

    $router->mount('/group', function () use ($router) {
        $router->get('/route', function () {
            TGroup::$val = false;
        });
    });

    $router->run();

    expect(TGroup::$val)->toBe(false);
});

test('route groups with array', function () {
    $_SERVER['REQUEST_URI'] = '/group/route';

    $router = new Router;

    TGroup::$val = true;

    $router->mount('/group', [function () use ($router) {
        $router->get('/route', function () {
            TGroup::$val = false;
        });
    }]);

    $router->run();

    expect(TGroup::$val)->toBe(false);
});

test('route groups with namespace', function () {
    $_SERVER['REQUEST_URI'] = '/group/route';

    $router = new Router;

    $router->mount('/group', ['namespace' => 'App\Controllers', function () use ($router) {
        $router->get('/route', 'ExampleController');
    }]);

    $router->run();

    // check if the namespace was registered
    expect(strpos(
        json_encode($router->routes()),
        'App\\\\Controllers\\\\ExampleController'
    ))->toBeTruthy();
});

test('route groups with different namespace', function () {
    $_SERVER['REQUEST_URI'] = '/group/route';

    $router = new Router;
    $router->setNamespace('Controllers');

    TGroup::$val = true;

    $router->mount('/group', ['namespace' => 'App\Controllers', function () use ($router) {
        $router->get('/route', 'ExampleController');
    }]);

    $router->run();

    // check if the App\Controllers namespace was registered
    // instead of the global Controllers namespace
    expect(strpos(
        json_encode($router->routes()),
        'App\\\\Controllers\\\\ExampleController'
    ))->toBeTruthy();
});

test('route groups support nested groups', function () {
    $_SERVER['REQUEST_URI'] = '/group/nested/route';

    $rx = new Router;

    TGroup::$val = true;

    $rx->mount('/group', function () use ($rx) {
        $rx->mount('/nested', function () use ($rx) {
            $rx->get('/route', function () use ($rx) {
                TGroup::$val = false;
            });
        });
    });

    $rx->run();

    expect(TGroup::$val)->toBe(false);
});

test('route groups support multiple nested groups', function () {
    $_SERVER['REQUEST_URI'] = '/group/nested/route';

    $rx2 = new Router;

    $rx2->mount('/group', function () use ($rx2) {
        $rx2->mount('/nested', function () use ($rx2) {
            $rx2->get('/route', function () {});
        });
        
        $rx2->mount('/nested2', function () use ($rx2) {
            $rx2->get('/route', function () {});
        });
    });

    $rx2Routes = $rx2->routes();

    expect($rx2Routes)->toBeArray();
    expect($rx2Routes[count($rx2Routes) - 2]['pattern'] ?? null)->toBe('/group/nested/route');
    expect($rx2Routes[count($rx2Routes) - 1]['pattern'] ?? null)->toBe('/group/nested2/route');
});

test('dynamic nested route groups', function () {
    $_SERVER['REQUEST_URI'] = '/hiddenGroup/1/route';

    $rx = new Router;

    TGroup::$val = true;

    $rx->mount('/hiddenGroup', function () use ($rx) {
        $rx->mount('/(\d+)', function () use ($rx) {
            $rx->get('/route', function () use ($rx) {
                TGroup::$val = 'Hidden response';
            });
        });
    });

    $rx->run();

    expect(TGroup::$val)->toBe('Hidden response');
});
