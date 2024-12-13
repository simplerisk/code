<?php

namespace Leaf\FS;

class Storage
{
    protected static $errorsArray = [];

    /**
     * Create a new file
     *
     * @param string $filePath The path of the new file
     * @param mixed $content The content of the new file
     * @param array $options Options for creating the file
     *
     * @return bool
     */
    public static function createFile(string $filePath, $content = null, array $options = [])
    {
        return File::create($filePath, $content, $options);
    }

    /**
     * Read the content of a file
     *
     * @param string $filePath The path of the file to read
     *
     * @return mixed
     */
    public static function read(string $filePath)
    {
        return File::read($filePath);
    }

    /**
     * Write content to a file
     *
     * @param string $filePath The path of the file to write to
     * @param mixed $content The content to write to the file
     * @param int $mode The mode to write the file in
     *
     * @return bool
     */
    public static function writeFile(string $filePath, $content, int $mode = 0)
    {
        return File::write($filePath, $content, $mode);
    }

    /**
     * Get a summary of the file/directory information
     *
     * @param string $filePath The path of the file/directory to get the summary of
     *
     * @return array|bool
     */
    public static function info(string $filePath)
    {
        if (is_dir($filePath)) {
            return Directory::info($filePath);
        }

        return File::info($filePath);
    }

    /**
     * Get the size of a file/folder
     *
     * @param string $filePath The path of the file/folder to get the size of
     * @param string $unit The unit to return the size in
     *
     * @return number
     */
    public static function size(string $filePath, string $unit = 'byte')
    {
        return is_dir($filePath)
            ? Directory::size($filePath, $unit)
            : File::size($filePath, $unit);
    }

    /**
     * Get the human readable file type of a file
     *
     * @param string $filePath The path of the file to get the type of
     *
     * @return string
     */
    public static function type(string $filePath)
    {
        return File::type($filePath);
    }

    /**
     * Get the last modified date of a file
     *
     * @param string $filePath The path of the file to get the last modified date of
     *
     * @return string
     */
    public static function lastModified(string $filePath)
    {
        return File::lastModified($filePath);
    }

    /**
     * Return the extension of the file
     *
     * @param string $filePath The path of the file to get the extension of
     *
     * @return string
     */
    public static function extension(string $filePath)
    {
        return (new Path($filePath))->extension();
    }

    /**
     * Return the last part of the path
     *
     * @param string $filePath The path of the file to get the basename of
     *
     * @return string
     */
    public static function basename(string $filePath)
    {
        return (new Path($filePath))->basename();
    }

    /**
     * Return the parent directory of the path
     *
     * @param string $filePath The path of the file to get the dirname of
     *
     * @return string
     */
    public static function dirname(string $filePath)
    {
        return (new Path($filePath))->dirname();
    }

    /**
     * Check if a file/directory exists
     *
     * @param string $filePath The path of the file/directory to check
     *
     * @return bool
     */
    public static function exists(string $filePath)
    {
        return File::exists($filePath) || Directory::exists($filePath);
    }

    /**
     * Upload a file
     *
     * @param mixed $filePath The path of the file to upload
     * @param string $destination The path to upload the file to
     * @param array $options Options for uploading the file
     *
     * @return bool
     */
    public static function upload($filePath, string $destination, array $options = [])
    {
        return File::upload($filePath, $destination, $options);
    }

    /**
     * Create a new directory
     *
     * @param string $dirPath The path of the new directory
     * @param array $options Options for creating the directory
     *
     * @return bool
     */
    public static function createFolder(string $dirPath, array $options = [])
    {
        return Directory::create($dirPath, $options);
    }

    /**
     * Check if a file or directory is empty
     *
     * @param string $filePath The path of the file or directory to check
     *
     * @return bool
     */
    public static function isEmpty(string $path)
    {
        if (is_dir($path)) {
            return Directory::isEmpty($path);
        }

        return File::isEmpty($path);
    }

    public static function list(string $dirPath, $filter = null)
    {
        return Directory::read($dirPath, $filter);
    }

    /**
     * Move a file or directory
     *
     * @param string $source The path of the file or directory to move
     * @param string $destination The path to move the file or directory to
     * @param array $options Options for moving the file or directory
     *
     * @return bool
     */
    public static function move(string $source, string $destination, array $options = [])
    {
        if (is_dir($source)) {
            return Directory::move($source, $destination, $options);
        }

        return File::move($source, $destination, $options);
    }

    /**
     * Copy a file or directory
     *
     * @param string $source The path of the file or directory to copy
     * @param string $destination The path to copy the file or directory to
     * @param array $options Options for copying the file or directory
     *
     * @return bool
     */
    public static function copy(string $source, string $destination, array $options = [])
    {
        if (is_dir($source)) {
            return Directory::copy($source, $destination, $options);
        }

        return File::copy($source, $destination, $options);
    }

    /**
     * Rename a file or directory
     *
     * @param string $name The path of the file/directory to rename
     * @param string $newName The path to rename the file/directory to
     *
     * @return bool
     */
    public static function rename(string $name, string $newName)
    {
        if (is_dir($name)) {
            return Directory::move($name, $newName);
        }

        return File::move($name, $newName);
    }

    /**
     * Delete a file or directory
     *
     * @param string $filePath The path of the file or directory to delete
     *
     * @return bool
     */
    public static function delete(string $source)
    {
        if (is_dir($source)) {
            return Directory::delete($source, [
                'recursive' => true
            ]);
        }

        return File::delete($source);
    }

    /**
     * Get or set UNIX mode of a file or directory.
     *
     * @param  string  $path
     * @param  int|null  $mode
     * @return mixed
     */
    public static function chmod($path, $mode = null)
    {
        if ($mode) {
            return chmod($path, $mode);
        }

        return substr(sprintf('%o', fileperms($path)), -4);
    }

    /**
     * Create a symlink to the target file or directory. On Windows, a hard link is created if the target is a file.
     *
     * @param  string  $target
     * @param  string  $link
     * @return string|bool
     */
    public static function link($target, $link)
    {
        if (!windows_os()) {
            return symlink($target, $link);
        }

        $mode = is_dir($target) ? 'J' : 'H';

        return exec("mklink /{$mode} " . escapeshellarg($link) . ' ' . escapeshellarg($target));
    }

    /**
     * Check if a path is a file
     *
     * @param string $filePath The path of the file to check
     *
     * @return bool
     */
    public static function isFile(string $filePath)
    {
        return File::exists($filePath);
    }

    /**
     * Check if a path is a directory
     *
     * @param string $dirPath The path of the file to check
     *
     * @return bool
     */
    public static function isDir(string $dirPath)
    {
        return Directory::exists($dirPath);
    }

    /**
     * Return all errors that occurred
     * @return array
     */
    public static function errors()
    {
        return array_merge(static::$errorsArray, File::errors(), Directory::errors());
    }
}
