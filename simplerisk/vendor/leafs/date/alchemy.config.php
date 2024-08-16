<?php

return [
	// alchemy options
	'engine' => 'pest',

	// php unit options
	'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
	'xsi:noNamespaceSchemaLocation' => './vendor/phpunit/phpunit/phpunit.xsd',
	'bootstrap' => 'vendor/autoload.php',
	'colors' => true,

	// you can have multiple testsuites
	'testsuites' => [
		'directory' => './tests'
	],

	// coverage options
	'coverage' => [
		'processUncoveredFiles' => true,
		'include' => [
			'./app' => '.php',
			'./src' => '.php'
		]
	]
];
