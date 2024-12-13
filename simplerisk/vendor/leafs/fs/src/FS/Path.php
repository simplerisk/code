<?php

declare(strict_types=1);

namespace Leaf\FS;

class Path
{
    public string $pathToParse;

    public function __construct($path)
    {
        $this->pathToParse = $path;
    }

    /**
     * Return the parent directory of the path
     * @return string
     */
    public function dirname()
    {
        return dirname($this->pathToParse);
    }

    /**
     * Return the last part of the path
     * @return string
     */
    public function basename()
    {
        return basename($this->pathToParse);
    }

    /**
     * Return the extension of the path
     * @return string
     */
    public function extension()
    {
        return pathinfo($this->pathToParse, PATHINFO_EXTENSION);
    }

    /**
     * Join multiple path parts using the correct directory separator
     * @param array $paths
     * @return string
     */
    public function join(...$paths)
    {
        return (new Path($this->pathToParse . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $paths)))->normalize();
    }

    /**
     * Fix the path to use the correct directory separator
     * @return string
     */
    public function normalize()
    {
        if (realpath($this->pathToParse)) {
            $this->pathToParse = realpath($this->pathToParse);
        } else {
            $path = '__FS_NORMALIZE_START__' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->pathToParse);
            $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');

            $normalized = [];

            foreach ($parts as $part) {
                if ($part === '.') {
                    continue;
                }

                if ($part === '..') {
                    array_pop($normalized);
                    continue;
                }

                $normalized[] = $part;
            }

            $this->pathToParse = implode(DIRECTORY_SEPARATOR, $normalized);
            $this->pathToParse = str_replace('__FS_NORMALIZE_START__', '', $this->pathToParse);
        }

        return $this->pathToParse;
    }
}
