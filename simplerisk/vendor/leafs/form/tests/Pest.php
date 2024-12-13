<?php

class Cache
{
    public static $cache = null;

    public static function set($item)
    {
        self::$cache = $item;
    }

    public static function get()
    {
        return self::$cache;
    }
}

function validator()
{
    if (!Cache::get()) {
        Cache::set(new \Leaf\Form());
    }

    return Cache::get();
}
