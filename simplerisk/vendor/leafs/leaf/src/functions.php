<?php

declare(strict_types=1);

if (!function_exists('app')) {
    /**
     * Return the Leaf instance
     *
     */
    function app(): Leaf\App
    {
        if (!(\Leaf\Config::getStatic('app'))) {
            \Leaf\Config::singleton('app', function () {
                return new \Leaf\App();
            });
        }

        return \Leaf\Config::get('app');
    }
}

if (!function_exists('_env')) {
    /**
     * Gets the value of an environment variable.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    function _env($key, $default = null)
    {
        $env = array_merge(getenv() ?? [], $_ENV ?? []);
        $value = $env[$key] ??= null;

        if ($value === null) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;

            case 'false':
            case '(false)':
                return false;

            case 'empty':
            case '(empty)':
                return '';

            case 'null':
            case '(null)':
                return;
        }

        if (strpos($value, '"') === 0 && strpos($value, '"') === strlen($value) - 1) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
