<?php

declare(strict_types=1);

if (!function_exists('path')) {
    /**
     * Return the Leaf instance
     *
     */
    function path(string $path): Leaf\FS\Path
    {
        return new \Leaf\FS\Path($path);
    }
}

if (!function_exists('storage')) {
    /**
     * Return the Leaf instance
     *
     */
    function storage(): Leaf\FS\Storage
    {
        if (!(\Leaf\Config::getStatic('storage'))) {
            \Leaf\Config::singleton('storage', function () {
                return new \Leaf\FS\Storage();
            });
        }

        return \Leaf\Config::get('storage');
    }
}
