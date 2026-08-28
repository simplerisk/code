<?php

/*
|--------------------------------------------------------------------------
| Leaf\Config polyfill
|--------------------------------------------------------------------------
|
| leafs/logger only uses Leaf\Config when it is available (it ships with
| the full framework). For LogWriter style tests we provide a minimal
| polyfill with the same static API.
|
*/

if (!class_exists('Leaf\Config')) {
    class LeafConfigPolyfill
    {
        protected static $settings = [];

        public static function getStatic()
        {
            return static::$settings;
        }

        public static function singleton($name, $callback)
        {
            static::$settings[$name] = $callback;
        }

        public static function get($item = null)
        {
            if ($item === null) {
                return static::$settings;
            }

            return static::$settings[$item] ?? null;
        }

        public static function set($item, $value = null)
        {
            if (is_array($item)) {
                foreach ($item as $key => $val) {
                    static::$settings[$key] = $val;
                }
            } else {
                static::$settings[$item] = $value;
            }
        }
    }

    class_alias('LeafConfigPolyfill', 'Leaf\Config');
}

/*
|--------------------------------------------------------------------------
| Test helpers
|--------------------------------------------------------------------------
*/

/**
 * A tiny in-memory spy writer used by the Log tests.
 */
class SpyWriter
{
    public array $writes = [];

    public $returnValue = 1;

    public function write($message, $level)
    {
        $this->writes[] = ['message' => $message, 'level' => $level];

        return $this->returnValue;
    }
}

/**
 * An object with __toString for message casting tests.
 */
class StringableMessage
{
    public function __toString(): string
    {
        return 'stringable message';
    }
}

function testLogDir(): string
{
    return sys_get_temp_dir() . '/leaf-logger-tests-' . getmypid();
}

function removeDirRecursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}
