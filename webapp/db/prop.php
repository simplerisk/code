<?php

return [
    'propel' => [
        'database' => [
            'connections' => [
                'lessrisk' => [
                    'adapter'    => 'mysql',
                    'classname'  => 'Propel\Runtime\Connection\ConnectionWrapper',
                    'dsn'        => 'mysql:host='.DB_HOSTNAME.';dbname='.DB_DATABASE,
                    'user'       => DB_USERNAME,
                    'password'   => DB_PASSWORD,
                    'settings'   => [
                        'charset' => 'utf8',
                        'queries' => [
                            'utf8' => "SET NAMES utf8 COLLATE utf8_unicode_ci, COLLATION_CONNECTION = utf8_unicode_ci, COLLATION_DATABASE = utf8_unicode_ci, COLLATION_SERVER = utf8_unicode_ci"
                        ]
                    ],
                    'attributes' => []
                ]
            ]
        ],
        'runtime' => [
            'defaultConnection' => 'lessrisk',
            'connections' => ['lessrisk']
        ],
        'generator' => [
            'defaultConnection' => 'lessrisk',
            'connections' => ['lessrisk']
        ]
    ]
];