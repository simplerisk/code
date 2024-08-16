<?php

if (class_exists('Leaf\Config')) {
    \Leaf\Config::addScript(function ($app) {
        $app->register('logWriter', function ($c) use ($app) {
            $logWriter = $app->config('log.writer');
            $file = $app->config('log.dir') . $app->config('log.file');

            return is_object($logWriter) ? $logWriter : new \Leaf\LogWriter($file, $app->config('log.open') ?? true);
        });

        $app->register('log', function ($c) use ($app) {
            $log = new \Leaf\Log($c->logWriter);
            $log->enabled($app->config('log.enabled'));
            $log->level($app->config('log.level'));

            return $log;
        });
    });
}
