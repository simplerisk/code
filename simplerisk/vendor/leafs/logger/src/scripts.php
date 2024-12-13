<?php

if (class_exists('Leaf\Config')) {
    \Leaf\Config::addScript(function () {
        \Leaf\Config::singleton('logWriter', function ($c) {
            return is_object($c['log.writer']) ?
                $c['log.writer'] :
                new \Leaf\LogWriter($c['log.dir'] . $c['log.file'], $c['log.open'] ?? true);
        });

        \Leaf\Config::singleton('log', function ($c) {
            $log = new \Leaf\Log(\Leaf\Config::get("logWriter"));
            $log->enabled($c['log.enabled']);
            $log->level($c['log.level']);

            return $log;
        });
    });
}
