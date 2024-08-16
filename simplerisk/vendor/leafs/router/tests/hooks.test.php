<?php

use Leaf\Router;

test('hook execution order', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/post';

    $router = new Router;

    $router->hook('router.after.dispatch', function () {
        echo '5';
    });

    $router->hook('router.before.route', function () {
        echo '2';
    });

    $router->hook('router.before.dispatch', function () {
        echo '3';
    });

    // our main route
    $router->post('/post', function () {
        echo '4';
    });

    $router->hook('router.after.route', function () {
        echo '6';
    });

    $router->hook('router.after', function () {
        echo '7';
    });

    $router->hook('router.before', function () {
        echo '1';
    });

    ob_start();
    $router->run();

    expect(ob_get_contents())->toBe('1234567');
    ob_end_clean();

    // cleanup
    $router->hook('router.before', function () {});
    $router->hook('router.before.route', function () {});
    $router->hook('router.before.dispatch', function () {});
    $router->hook('router.after.dispatch', function () {});
    $router->hook('router.after.route', function () {});
    $router->hook('router.after', function () {});
});
