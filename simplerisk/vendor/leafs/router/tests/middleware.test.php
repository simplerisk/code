<?php

use Leaf\Router;

class TMid
{
    static $callstack = '';
}

test('leaf middleware', function () {
	TMid::$callstack = '';

	class AppMid {
		public function call()
		{
			TMid::$callstack .= '1';
		}
	}

	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['REQUEST_URI'] = '/';

    $router = new Router;

	$router->use(new AppMid);
	$router->get('/', function () {
		TMid::$callstack .= '2';
	});

	$router->run();

	expect(TMid::$callstack)->toBe('12');
});

test('in-route middleware', function () {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SERVER['REQUEST_URI'] = '/';

	$m = function () {
		echo '1';
	};

    $router = new Router;
	
	$router->post('/', ['middleware' => $m, function () {
		echo '2';
	}]);

	ob_start();
	$router->run();

	expect(ob_get_contents())->toBe('12');
	ob_end_clean();
});

test('before route middleware', function () {
	$_SERVER['REQUEST_METHOD'] = 'PUT';
	$_SERVER['REQUEST_URI'] = '/';

    $router = new Router;

	$router->before('PUT', '/', function () {
		echo '1';
	});
	$router->put('/', function () {
		echo '2';
	});

	ob_start();
	$router->run();

	expect(ob_get_contents())->toBe('12');
	ob_end_clean();
});

test('before router middleware', function () {
	$_SERVER['REQUEST_METHOD'] = 'PATCH';
	$_SERVER['REQUEST_URI'] = '/test';

    $router = new Router;

	$router->before('PATCH', '/.*', function () {
		echo '1';
	});
	$router->patch('/test', function () {
		echo '2';
	});

	ob_start();
	$router->run();

	expect(ob_get_contents())->toBe('12');
	ob_end_clean();
});

test('after router middleware', function () {
	$_SERVER['REQUEST_METHOD'] = 'PUT';
	$_SERVER['REQUEST_URI'] = '/test';

    $router = new Router;

	$router->put('/test', function () {
		echo '1';
	});

	ob_start();
	$router->run(function () {
        echo '2';
    });

	expect(ob_get_contents())->toBe('12');
	ob_end_clean();

	// resets
	$router->hook('router.after', function () {});
});

test('middleware is only called for routes that run', function () {
	$_SERVER['REQUEST_METHOD'] = 'PUT';
	$_SERVER['REQUEST_URI'] = '/users/disable/5';

	$router = new Router;

	$router->group('/users', function () use ($router) {
		/**
		 * Disables a user
		 */
		$router->put('/disable/{id}', [
			'middleware' => function () {
				echo 'mid 1';
			},
			function ($id) {
				echo 'test 1';
			}
		]);

		$router->put('/enable/{id}', [
			'middleware' => function () {
				echo 'mid 2';
			},
			function ($id)  {
				echo 'test 2';
			}
		]);
		
		$router->put('/delete/{id}', [
			'middleware' => function () {
				echo 'mid 3';
			},
			function ($id)  {
				echo 'test 3';
			}
		]);
	});

	ob_start();
	$router->run();

	expect(ob_get_contents())->toBe('mid 1test 1');
	ob_end_clean();
});

test('in-route named middleware', function () {
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['REQUEST_URI'] = '/thisRoute';

	$router = new Router;
	$router->registerMiddleware('mid1', function () use ($router) {
		echo 'named middleware --- ';
	});

	$router->get('/thisRoute', ['middleware' => 'mid1', function () {
		echo 'route';
	}]);

	ob_start();
	$router->run();

	expect(ob_get_contents())->toBe('named middleware --- route');

	ob_end_clean();
});

test('in-route middleware + groups', function () {
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['REQUEST_URI'] = '/thatGroup/thatRoute';

	$router = new Router;
	$router->registerMiddleware('mid1', function () use ($router) {
		echo 'named middleware 3 --- ';
	});

	$router->group('/thatGroup', ['middleware' => 'mid1', function () use ($router) {
		$router->get('/thatRoute', function () {
			echo 'route';
		});
	}]);

	ob_start();
	$router->run();

	expect(ob_get_contents())->toBe('named middleware 3 --- route');

	ob_end_clean();
});

test('in-route named middleware + groups', function () {
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['REQUEST_URI'] = '/thisGroup/thisRoute';

	$router = new Router;
	$router->registerMiddleware('mid1', function () use ($router) {
		echo 'named middleware 2 --- ';
	});

	$router->group('/thisGroup', ['middleware' => 'mid1', function () use ($router) {
		$router->get('/thisRoute', function () {
			echo 'route';
		});
	}]);

	ob_start();
	$router->run();

	expect(ob_get_contents())->toBe('named middleware 2 --- route');

	ob_end_clean();
});

test('in-route named middleware + groups + sub groups', function () {
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['REQUEST_URI'] = '/thisGroup/thisSubGroup/thisRoute';

	$router = new Router;
	$router->registerMiddleware('mid1', function () use ($router) {
		echo 'named middleware 3 --- ';
	});

	$router->group('/thisGroup', ['middleware' => 'mid1', function () use ($router) {
		$router->group('/thisSubGroup', function () use ($router) {
			$router->get('/thisRoute', function () {
				echo 'route';
			});
		});
	}]);

	ob_start();
	$router->run();

	expect(ob_get_contents())->toBe('named middleware 3 --- route');

	ob_end_clean();
});
