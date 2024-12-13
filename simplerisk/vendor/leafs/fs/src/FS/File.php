<?php

namespace Leaf\FS;

/**
 * File operations
 * ----
 * This class provides a set of methods for local file operations
 *
 * @since 3.0.0
 */
class File
{
    protected static $errorsArray = [];

    protected static $fileCreateOptions = [
        'mode' => 0777,
        'rename' => false,
        'recursive' => false,
        'overwrite' => false,
    ];

    /**
     * Check if a file exists
     *
     * @param string $filePath The path of the file to check
     *
     * @return bool
     */
    public static function exists($filePath)
    {
        return file_exists($filePath) && is_file($filePath);
    }

    /**
     * Create a new file
     *
     * @param string $filePath The path of the new file
     * @param mixed $content The content of the new file
     * @param array $options Options for creating the file
     *
     * @return bool
     */
    public static function create($filePath, $content = null, $options = [])
    {
        $path = new Path($filePath);

        $filePath = $path->normalize();
        $options = array_merge(static::$fileCreateOptions, $options);

        if (static::exists($filePath)) {
            if ($options['overwrite']) {
                unlink($filePath);
            } elseif ($options['rename']) {
                $filePath = str_replace(
                    $path->basename(),
                    time() . '_' . uniqid() . '_' . $path->basename(),
                    $filePath
                );
            } else if ($options['recursive']) {
            } else {
                static::$errorsArray['file'] = 'File already exists';
                return false;
            }
        }

        if ($options['recursive'] && !Directory::exists($path->dirname())) {
            mkdir($path->dirname(), $options['mode'], $options['recursive']);
        }

        if (!touch($filePath)) {
            static::$errorsArray['file'] = 'Could not create file';
            return false;
        }

        if ($content) {
            file_put_contents(
                $filePath,
                is_callable($content) ? $content() : $content
            );
        }

        return true;
    }

    /**
     * Read the content of a file
     *
     * @param string $filePath The path of the file to read
     *
     * @return mixed
     */
    public static function read($filePath)
    {
        $path = new Path($filePath);

        $dirName = $path->dirname();
        $fileName = $path->basename();
        $filePath = $path->normalize();

        if (!static::exists($filePath)) {
            static::$errorsArray['file'] = "$fileName not found in $dirName";
            return false;
        }

        return file_get_contents($filePath);
    }

    /**
     * Write content to an existing file
     *
     * @param string $filePath The path of the file to write to
     * @param mixed $content The content to write to the file
     * @param int $mode The mode to write the file in
     *
     * @return bool
     */
    public static function write(string $filePath, $content, int $mode = 0)
    {
        $path = new Path($filePath);
        $filePath = $path->normalize();

        if (!static::exists($filePath)) {
            static::$errorsArray['file'] = 'File does not exist';
            return false;
        }

        if (
            file_put_contents(
                $filePath,
                is_callable($content) ? $content(file_get_contents($filePath)) : $content,
                $mode
            ) === false
        ) {
            static::$errorsArray['file'] = 'Could not write to file';
            return false;
        }

        return true;
    }

    /**
     * Delete a file
     *
     * @param string $filePath The path of the file to delete
     *
     * @return bool
     */
    public static function delete($filePath)
    {
        if (!static::exists($filePath)) {
            static::$errorsArray['file'] = 'File does not exist';
            return false;
        }

        return unlink($filePath);
    }

    /**
     * Check if a file is empty
     *
     * @param string $filePath The path of the file to check
     *
     * @return bool
     */
    public static function isEmpty(string $filePath)
    {
        $path = new Path($filePath);
        $filePath = $path->normalize();

        return static::size($filePath) === 0;
    }

    /**
     * Copy a file
     *
     * @param string $source The path of the file to copy
     * @param string $destination The path to copy the file to
     * @param array $options Options for copying the file
     *
     * @return bool
     */
    public static function copy($source, $destination, $options = [])
    {
        $options = array_merge(static::$fileCreateOptions, $options);

        $sourcePath = new Path($source);
        $source = $sourcePath->normalize();

        $destinationPath = new Path($destination);
        $destination = $destinationPath->normalize();

        if (!static::exists($source)) {
            static::$errorsArray['file'] = 'Source file does not exist';
            return false;
        }

        if (static::exists($destination)) {
            if ($options['overwrite']) {
                unlink($destination);
            } elseif ($options['rename']) {
                $destination = str_replace(
                    $destinationPath->basename(),
                    time() . '_' . uniqid() . '_' . $destinationPath->basename(),
                    $destination
                );
            } else {
                static::$errorsArray['file'] = 'Destination file already exists';

                return false;
            }
        }

        if ($options['recursive'] && !Directory::exists($destinationPath->dirname())) {
            mkdir($destinationPath->dirname(), $options['mode'], $options['recursive']);
        }

        return copy($source, $destination);
    }

    /**
     * Move a file
     *
     * @param string $source The path of the file to move
     * @param string $destination The path to move the file to
     * @param array $options Options for moving the file
     *
     * @return bool
     */
    public static function move($source, $destination, $options = [])
    {
        $options = array_merge(static::$fileCreateOptions, $options);

        $sourcePath = new Path($source);
        $source = $sourcePath->normalize();

        $destinationPath = new Path($destination);
        $destination = $destinationPath->normalize();

        if (!static::exists($source)) {
            static::$errorsArray['file'] = 'Source file does not exist';
            return false;
        }

        if (static::exists($destination)) {
            if ($options['overwrite']) {
                unlink($destination);
            } elseif ($options['rename']) {
                $destination = str_replace(
                    $destinationPath->basename(),
                    time() . '_' . uniqid() . '_' . $destinationPath->basename(),
                    $destination
                );
            } else {
                static::$errorsArray['file'] = 'Destination file already exists';

                return false;
            }
        }

        if ($options['recursive'] && !Directory::exists($destinationPath->dirname())) {
            mkdir($destinationPath->dirname(), $options['mode'], $options['recursive']);
        }

        return rename($source, $destination);
    }

    /**
     * Get a summary of the file information
     *
     * @param string $filePath The path of the file to get the summary of
     *
     * @return array|bool
     */
    public static function info($filePath)
    {
        $path = new Path($filePath);
        $filePath = $path->normalize();

        if (!static::exists($filePath)) {
            static::$errorsArray['file'] = 'File does not exist';
            return false;
        }

        return [
            'path' => $filePath,
            'name' => $path->basename(),
            'dirname' => $path->dirname(),
            'extension' => $path->extension(),
            'size' => static::size($filePath),
            'type' => static::type($filePath),
            'lastModified' => static::lastModified($filePath),
        ];
    }

    /**
     * Get the size of a file
     *
     * @param string $filePath The path of the file to get the size of
     * @param string $unit The unit to return the size in
     *
     * @return number
     */
    public static function size($filePath, $unit = 'byte')
    {
        $path = new Path($filePath);
        $filePath = $path->normalize();

        if (!static::exists($filePath)) {
            static::$errorsArray['file'] = 'File does not exist';
            return false;
        }

        clearstatcache();

        $size = filesize($filePath);

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
     * Get the system file type of a file
     *
     * @param string $filePath The path of the file to get the type of
     *
     * @return string
     */
    public static function systemType($filePath)
    {
        $path = new Path($filePath);
        $filePath = $path->normalize();

        if (!static::exists($filePath)) {
            static::$errorsArray['file'] = 'File does not exist';
            return false;
        }

        return filetype($filePath);
    }

    /**
     * Get the human readable file type of a file
     *
     * @param string $filePath The path of the file to get the type of
     *
     * @return string
     */
    public static function type($filePath)
    {
        $path = new Path($filePath);

        $filePath = $path->normalize();
        $fileExtension = $path->extension();

        if (!static::exists($filePath)) {
            static::$errorsArray['file'] = 'File does not exist';
            return false;
        }

        $extensions = [
            'jpg' => 'image',
            'jpeg' => 'image',
            'png' => 'image',
            'gif' => 'image',
            'webp' => 'image',
            'apng' => 'image',
            'tif' => 'image',
            'tiff' => 'image',
            'svg' => 'image',
            'pjpeg' => 'image',
            'pjp' => 'image',
            'jfif' => 'image',
            'cur' => 'image',
            'ico' => 'image',
            'mp4' => 'video',
            'webm' => 'video',
            'swf' => 'video',
            'flv' => 'video',
            'wav' => 'audio',
            'mp3' => 'audio',
            'ogg' => 'audio',
            'm4a' => 'audio',
            'txt' => 'text',
            'log' => 'text',
            'xml' => 'text',
            'doc' => 'text',
            'docx' => 'text',
            'odt' => 'text',
            'wpd' => 'text',
            'rtf' => 'text',
            'tex' => 'text',
            'pdf' => 'text',
            'md' => 'text',
            'html' => 'text',
            'htm' => 'text',
            'css' => 'text',
            'js' => 'text',
            'php' => 'text',
            'asp' => 'text',
            'aspx' => 'text',
            'cer' => 'text',
            'cfm' => 'text',
            'csr' => 'text',
            'jsp' => 'text',
            'xhtml' => 'text',
            'rss' => 'text',
            'json' => 'text',
            'dll' => 'text',
            'htaccess' => 'text',
            'ppsx' => 'presentation',
            'pptx' => 'presentation',
            'ppt' => 'presentation',
            'pps' => 'presentation',
            'ppsm' => 'presentation',
            'key' => 'presentation',
            'odp' => 'presentation',
            'zip' => 'compressed',
            'rar' => 'compressed',
            'bz' => 'compressed',
            'gz' => 'compressed',
            'iso' => 'compressed',
            'tar.gz' => 'compressed',
            'tgz' => 'compressed',
            'zipx' => 'compressed',
            '7z' => 'compressed',
            'dmg' => 'compressed',
            'ods' => 'spreadsheet',
            'xls' => 'spreadsheet',
            'xlsx' => 'spreadsheet',
            'xlsm' => 'spreadsheet',
            'apk' => 'application',
            'bat' => 'application',
            'cgi' => 'application',
            'pl' => 'application',
            'com' => 'application',
            'exe' => 'application',
            'gadget' => 'application',
            'jar' => 'application',
            'msi' => 'application',
            'py' => 'application',
            'wsf' => 'application',
        ];

        return $extensions[$fileExtension] ?? static::systemType($filePath);
    }

    /**
     * Upload a file
     *
     * @param mixed $file The path of the file to upload
     * @param string $destination The path to upload the file to
     * @param array $options Options for uploading the file
     *
     * @return array|bool
     */
    public static function upload($file, string $destination, array $options = [])
    {
        $defaultUploadOptions = [
            'name' => null,
            'maxSize' => 0,
            'validate' => false,
            'allowedTypes' => [],
            'allowedExtensions' => [],
        ];

        $options = array_merge(static::$fileCreateOptions, $defaultUploadOptions, $options);

        $destinationPath = new Path($destination);
        $destination = $destinationPath->normalize();

        if (!Directory::exists($destination)) {
            mkdir($destination, $options['mode'], true);
        }

        $temp = $file['tmp_name'];
        $name = $options['name'] ?? $file['name'];

        if ($options['maxSize'] > 0 && ($file['size'] > $options['maxSize'])) {
            static::$errorsArray['upload'] = 'File size exceeds maximum size';
            return false;
        }

        if (File::exists($destination . DIRECTORY_SEPARATOR . $name)) {
            if ($options['overwrite']) {
                unlink($destination . DIRECTORY_SEPARATOR . $name);
            } else if ($options['rename']) {
                $name = time() . '_' . uniqid() . '_' . $name;
            } else {
                static::$errorsArray['upload'] = "$name already exists";
                return false;
            }
        }

        if ($options['validate']) {
            $fileType = static::type($temp);
            $fileExtension = (new Path($file['name']))->extension();  // Changed from $temp to $file['name'] to fix extension validation

            if (
                !empty($options['allowedTypes']) &&
                !in_array($fileType, $options['allowedTypes'])
            ) {
                static::$errorsArray['upload'] = "File should be of type: $fileType";
                return false;
            }

            if (
                !empty($options['allowedExtensions']) &&
                !in_array($fileExtension, $options['allowedExtensions'])
            ) {
                static::$errorsArray['upload'] = 'File extension not allowed';
                return false;
            }
        }

        $uploadInfo = [
            'name' => $name,
            'size' => $file['size'],
            'type' => static::type($name),
            'path' => (new Path($destination . DIRECTORY_SEPARATOR . $name))->normalize(),
            'extension' => (new Path($name))->extension(),
        ];

        try {
            if (move_uploaded_file($temp, $destination . DIRECTORY_SEPARATOR . $name)) {
                return $uploadInfo;
            } else {
                self::$errorsArray['upload'] = 'Unable able to upload file';
                return false;
            }
        } catch (\Throwable $th) {
            static::$errorsArray['upload'] = $th->getMessage();
            return false;
        }
    }

    /**
     * Get the mime type of a file
     *
     * @param string $filePath The path of the file to get the mime type of
     *
     * @return string
     */
    public static function mimeType($filePath)
    {
        $path = new Path($filePath);
        $filePath = $path->normalize();

        if (!static::exists($filePath)) {
            static::$errorsArray['file'] = 'File does not exist';
            return false;
        }

        return mime_content_type($filePath);
    }

    /**
     * Get the last modified date of a file
     *
     * @param string $filePath The path of the file to get the last modified date of
     *
     * @return string
     */
    public static function lastModified($filePath)
    {
        $path = new Path($filePath);
        $filePath = $path->normalize();

        if (!static::exists($filePath)) {
            static::$errorsArray['file'] = 'File does not exist';
            return false;
        }

        return filemtime($filePath);
    }

    /**
     * Return all errors that occured during file operations
     * @return array
     */
    public static function errors()
    {
        return static::$errorsArray;
    }
}
