<?php

namespace Leaf\FS;

/**
 * Directory operations
 * ----
 * This class provides a set of methods for local directory operations
 *
 * @since 3.0.0
 */
class Directory
{
    protected static $errorsArray = [];

    protected static $dirCreateOptions = [
        'mode' => 0777,
        'rename' => false,
        'recursive' => false,
        'overwrite' => false,
    ];

    /**
     * Check if a directory exists
     *
     * @param string $dirPath The path of the directory to check
     *
     * @return bool
     */
    public static function exists($filePath)
    {
        return file_exists($filePath) && is_dir($filePath);
    }

    /**
     * Create a new directory
     *
     * @param string $dirPath The path of the new directory
     * @param array $options Options for creating the directory
     *
     * @return bool
     */
    public static function create($dirPath, $options = [])
    {
        $path = new Path($dirPath);

        $dirPath = $path->normalize();
        $options = array_merge(static::$dirCreateOptions, $options);

        if (static::exists($dirPath)) {
            if ($options['rename']) {
                $dirPath = str_replace(
                    $path->basename(),
                    time() . '_' . uniqid() . '_' . $path->basename(),
                    $dirPath
                );
            } elseif ($options['overwrite']) {
                unlink($dirPath);
            } else {
                static::$errorsArray['directory'] = 'Directory already exists';

                return false;
            }
        }

        return mkdir($dirPath, $options['mode'], $options['recursive']);
    }

    /**
     * List all files and folders in a directory
     *
     * @param string $dirname the name of the directory to list
     * @param string|callable|null $pattern
     *
     * @return array|false
     */
    public static function read(string $dirPath, $pattern = null)
    {
        $path = new Path($dirPath);
        $dirPath = $path->normalize();

        if (!static::exists($dirPath)) {
            static::$errorsArray['directory'] = 'Directory does not exist';

            return false;
        }

        $parsedFiles = [];
        $files = scandir($dirPath);

        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $parsedFiles[] = $file;
            }
        }

        if ($pattern) {
            if (is_callable($pattern)) {
                $parsedFiles = array_filter($parsedFiles, $pattern);
            } else {
                $regex = '$#^' . str_replace(['*', '/'], ['.*', '\/'], $pattern) . '$#i';
                $parsedFiles = preg_grep($regex, $parsedFiles);
            }
        }

        return $parsedFiles;
    }

    /**
     * Check if a directory is empty
     *
     * @param string $dirPath The path of the directory to check
     *
     * @return bool
     */
    public static function isEmpty(string $dirPath)
    {
        $path = new Path($dirPath);
        $dirPath = $path->normalize();

        return count(static::read($dirPath)) === 0;
    }

    /**
     * Get all directories in a directory
     *
     * @param string $dirPath The path of the dir to get the directories from
     *
     * @return array|false
     */
    public static function dirs($dirPath)
    {
        $glob = static::read($dirPath);

        if (!$glob) {
            return false;
        }

        $dirs = [];

        foreach ($glob as $file) {
            if (is_dir($dirPath . DIRECTORY_SEPARATOR . $file)) {
                $dirs[] = $file;
            }
        }

        return $dirs;
    }

    /**
     * Get all files in a directory
     *
     * @param string $dirPath The path of the dir to get the files from
     *
     * @return array|false
     */
    public static function files($dirPath)
    {
        $glob = static::read($dirPath);

        if (!$glob) {
            return false;
        }

        $files = [];

        foreach ($glob as $file) {
            if (is_file($dirPath . DIRECTORY_SEPARATOR . $file)) {
                $files[] = $file;
            }
        }

        return $files;
    }

    // {
    //     $dirPath = (new Path($dirPath))->normalize();

    //     if (!file_exists($dirPath)) {
    //         static::$errorsArray['directory'] = "$dirPath does not exist";
    //         return false;
    //     }

    //     $resolvedList = [];

    //     $glob = glob($dir, (\defined('GLOB_BRACE') ? \GLOB_BRACE : 0) | \GLOB_ONLYDIR | \GLOB_NOSORT);

    //     foreach ((array) $dirs as $dir) {
    //         if (is_dir($dir)) {
    //             $resolvedDirs[] = [$this->normalizeDir($dir)];
    //         } elseif () {
    //             sort($glob);
    //             $resolvedDirs[] = array_map($this->normalizeDir(...), $glob);
    //         } else {
    //             throw new DirectoryNotFoundException(sprintf('The "%s" directory does not exist.', $dir));
    //         }
    //     }

    //     $this->dirs = array_merge($this->dirs, ...$resolvedDirs);

    //     return $this;

    //     return dir_get_contents($dirPath);
    // }

    /**
     * Delete a directory
     *
     * @param string $dirPath The path of the directory to delete
     *
     * @return bool
     */
    public static function delete($dirPath, $options = [])
    {
        $dirPath = (new Path($dirPath))->normalize();
        $options = array_merge(static::$dirCreateOptions, $options);

        if (!static::exists($dirPath)) {
            static::$errorsArray['directory'] = 'Directory does not exist';

            return false;
        }

        if ($options['recursive']) {
            $files = array_diff(scandir($dirPath), ['.', '..']);

            foreach ($files as $file) {
                (is_dir("$dirPath/$file"))
                    ? static::delete("$dirPath/$file", $options)
                    : File::delete("$dirPath/$file");
            }
        }

        return rmdir($dirPath);
    }

    /**
     * Copy a directory
     *
     * @param string $source The path of the dir to copy
     * @param string $destination The path to copy the dir to
     * @param array $options Options for copying the dir
     *
     * @return bool
     */
    public static function copy($source, $destination, $options = [])
    {
        $options = array_merge(static::$dirCreateOptions, $options);

        $sourcePath = new Path($source);
        $source = $sourcePath->normalize();

        $destinationPath = new Path($destination);
        $destination = $destinationPath->normalize();

        if (!static::exists($source)) {
            static::$errorsArray['directory'] = 'Source directory does not exist';
            return false;
        }

        if (static::exists($destination)) {
            if ($options['overwrite']) {
                static::delete($destination, [
                    'recursive' => true,
                ]);
            } elseif ($options['rename']) {
                $destination = str_replace(
                    $destinationPath->basename(),
                    time() . '_' . uniqid() . '_' . $destinationPath->basename(),
                    $destination
                );
            } elseif ($options['recursive']) {
            } else {
                static::$errorsArray['directory'] = 'Destination directory already exists';

                return false;
            }
        }

        if (!$options['recursive']) {
            mkdir($destination, $options['mode']);

            foreach (static::read($source) as $file) {
                File::copy($source . DIRECTORY_SEPARATOR . $file, $destination . DIRECTORY_SEPARATOR . $file);
            }

            return true;
        }

        if (!file_exists($destination)) {
            mkdir($destination, $options['mode'], $options['recursive']);
        }

        $glob = static::read($source);

        foreach ($glob as $file) {
            if (is_dir($source . DIRECTORY_SEPARATOR . $file)) {
                static::copy($source . DIRECTORY_SEPARATOR . $file, $destination . DIRECTORY_SEPARATOR . $file, $options);
            } else {
                File::copy($source . DIRECTORY_SEPARATOR . $file, $destination . DIRECTORY_SEPARATOR . $file);
            }
        }

        return true;
    }

    /**
     * Move a directory to a new location
     *
     * @param string $source The path of the dir to move
     * @param string $destination The path to move the dir to
     * @param array $options Options for moving the dir
     *
     * @return bool
     */
    public static function move($source, $destination, $options = [])
    {
        if (static::copy($source, $destination, $options)) {
            return static::delete($source, $options);
        }

        return false;
    }

    /**
     * Get a summary of the dir information
     *
     * @param string $dirPath The path of the dir to get the summary of
     *
     * @return array|bool
     */
    public static function info($dirPath)
    {
        $path = new Path($dirPath);
        $dirPath = $path->normalize();

        if (!static::exists($dirPath)) {
            static::$errorsArray['directory'] = 'Directory does not exist';

            return false;
        }

        return [
            'path' => $dirPath,
            'name' => $path->basename(),
            'dirname' => $path->dirname(),
            'size' => static::size($dirPath),
        ];
    }

    /**
     * Get the total size of a directory
     *
     * @param string $dirPath The path of the directory to get the size of
     * @param string $unit The unit to return the size in
     *
     * @return number
     */
    public static function size($dirPath, $unit = 'byte')
    {
        $path = new Path($dirPath);
        $dirPath = $path->normalize();

        if (!static::exists($dirPath)) {
            static::$errorsArray['directory'] = 'Directory does not exist';

            return false;
        }

        $size = 0;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS)) as $object) {
            try {
                $size += $object->getSize();
            } catch (\Throwable $th) {
                continue;
            }
        }

        switch ($unit) {
            case 'byte':
                return $size;
            case 'kb':
                return $size / 1024;
            case 'mb':
                return $size / 1024 / 1024;
            case 'gb':
                return $size / 1024 / 1024 / 1024;
            case 'tb':
                return $size / 1024 / 1024 / 1024 / 1024;
            default:
                return $size;
        }
    }

    /**
     * Return all errors that occurred during dir operations
     * @return array
     */
    public static function errors()
    {
        return static::$errorsArray;
    }
}
