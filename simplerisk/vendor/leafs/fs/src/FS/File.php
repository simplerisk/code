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

    /**
     * Split a `bucket://path` string into its connection name and path
     *
     * Returns null for ordinary local paths, so callers can branch on it.
     *
     * @param string $filePath The path to inspect
     * @return array{0: string, 1: string}|null
     */
    protected static function parseBucketPath($filePath): ?array
    {
        if (!is_string($filePath) || !preg_match('/^([a-zA-Z0-9-_]+):\/\//', $filePath, $matches)) {
            return null;
        }

        if (in_array($matches[1], stream_get_wrappers(), true)) {
            return null;
        }

        $objectKey = (new Path(str_replace($matches[0], '', $filePath)))->normalize();

        return [$matches[1], str_replace('\\', '/', $objectKey)];
    }

    /**
     * Resolve the bucket for a `bucket://path`, or false when the s3 module
     * isn't installed
     *
     * @param string $bucketName The connection name
     * @return \Leaf\FS\Bucket|false
     */
    protected static function bucketFor(string $bucketName)
    {
        if (!class_exists(Bucket::class)) {
            static::$errorsArray['file'] = 'Storage buckets require the leafs/s3 module. Run `composer require leafs/s3` first.';

            return false;
        }

        $connection = Bucket::connection($bucketName);

        if (!$connection) {
            static::$errorsArray['file'] = Bucket::errors();

            return false;
        }

        return $connection;
    }

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
        if ($bucket = static::parseBucketPath($filePath)) {
            $connection = static::bucketFor($bucket[0]);

            return $connection ? $connection->exists($bucket[1]) : false;
        }

        return file_exists($filePath) && is_file($filePath);
    }

    /**
     * Create a new file
     *
     * @param string $filePath The path of the new file
     * @param mixed $content The content of the new file
     * @param array $options Options for creating the file
     *
     * @return string|bool
     */
    public static function create($filePath, $content = null, $options = [])
    {
        $bucketName = null;
        $destinationIsBucket = false;
        $options = array_merge(static::$fileCreateOptions, $options);

        if (preg_match('/^([a-zA-Z0-9-_]+):\/\//', $filePath, $matches)) {
            $destinationIsBucket = true;
            $bucketName = $matches[1];
        }

        if (!$destinationIsBucket) {
            $path = new Path($filePath);
            $filePath = $path->normalize();

            if (static::exists($filePath)) {
                if ($options['overwrite']) {
                    unlink($filePath);
                } elseif ($options['rename']) {
                    $filePath = str_replace(
                        $path->basename(),
                        time() . '_' . uniqid() . '_' . $path->basename(),
                        $filePath
                    );
                } elseif ($options['recursive']) {
                    // recursive create tolerates existing files: generators
                    // re-run over existing trees without erroring
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

            if ($content !== null && $content !== false) {
                file_put_contents(
                    $filePath,
                    is_callable($content) ? $content() : $content
                );
            }
        } else {
            if (!class_exists(Bucket::class)) {
                static::$errorsArray['file'] = 'Storage buckets require the leafs/s3 module. Run `composer require leafs/s3` first.';

                return false;
            }

            $filePath = str_replace($matches[0], '', $filePath);
            $filePath = (new Path($filePath))->normalize();

            if (!($url = Bucket::connection($bucketName)->createFile($filePath, $content, [
                'name' => (new Path($filePath))->basename(),
                'overwrite' => $options['overwrite'],
                'rename' => $options['rename'],
                'visibility' => $options['visibility'] ?? 'public',
            ]))) {
                static::$errorsArray['file'] = Bucket::errors();

                return false;
            }

            return $url;
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
        if ($bucket = static::parseBucketPath($filePath)) {
            $connection = static::bucketFor($bucket[0]);

            return $connection ? $connection->read($bucket[1]) : false;
        }

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
     * Read a byte range from a file without loading the whole file
     *
     * @param string $filePath The path of the file to read
     * @param int $start Byte offset to start from (negative = from the end of the file)
     * @param int|null $length Number of bytes to read (null = to the end of the file)
     *
     * @return string|false
     */
    public static function readRange($filePath, int $start = 0, ?int $length = null)
    {
        $path = new Path($filePath);
        $filePath = $path->normalize();

        if (!static::exists($filePath)) {
            static::$errorsArray['file'] = 'File does not exist';

            return false;
        }

        $size = filesize($filePath);

        if ($start < 0) {
            $start = max(0, $size + $start);
        }

        if ($start > $size) {
            static::$errorsArray['file'] = "Range start ($start) is beyond the end of the file ($size bytes)";

            return false;
        }

        if ($length !== null && $length < 0) {
            static::$errorsArray['file'] = 'Range length cannot be negative';

            return false;
        }

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            static::$errorsArray['file'] = 'Could not open file for reading';

            return false;
        }

        fseek($handle, $start);

        $content = $length === null
            ? stream_get_contents($handle)
            : ($length === 0 ? '' : (fread($handle, $length) ?: ''));

        fclose($handle);

        return $content;
    }

    /**
     * Stream a file in chunks — memory stays flat no matter the file size.
     * Perfect for serving large downloads or HTTP range responses:
     *
     *     foreach (File::chunks('movie.mp4', 1024 * 1024) as $chunk) {
     *         echo $chunk;
     *     }
     *
     * @param string $filePath The path of the file to stream
     * @param int $chunkSize Bytes per chunk (default 1MB)
     * @param int $start Byte offset to start from (negative = from the end of the file)
     * @param int|null $length Total bytes to stream (null = to the end of the file)
     *
     * @return \Generator|false Generator yielding string chunks, false on error
     */
    public static function chunks($filePath, int $chunkSize = 1048576, int $start = 0, ?int $length = null)
    {
        $path = new Path($filePath);
        $filePath = $path->normalize();

        // validate eagerly — a generator would defer errors until iteration
        if (!static::exists($filePath)) {
            static::$errorsArray['file'] = 'File does not exist';

            return false;
        }

        if ($chunkSize < 1) {
            static::$errorsArray['file'] = 'Chunk size must be at least 1 byte';

            return false;
        }

        $size = filesize($filePath);

        if ($start < 0) {
            $start = max(0, $size + $start);
        }

        if ($start > $size) {
            static::$errorsArray['file'] = "Range start ($start) is beyond the end of the file ($size bytes)";

            return false;
        }

        $remaining = $length === null ? ($size - $start) : min($length, $size - $start);

        return (static function () use ($filePath, $chunkSize, $start, $remaining) {
            $handle = fopen($filePath, 'rb');

            if ($handle === false) {
                return;
            }

            try {
                fseek($handle, $start);

                while ($remaining > 0 && !feof($handle)) {
                    $chunk = fread($handle, min($chunkSize, $remaining));

                    if ($chunk === false || $chunk === '') {
                        break;
                    }

                    $remaining -= strlen($chunk);

                    yield $chunk;
                }
            } finally {
                fclose($handle);
            }
        })();
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
        if ($bucket = static::parseBucketPath($filePath)) {
            $connection = static::bucketFor($bucket[0]);

            if (!$connection) {
                return false;
            }

            // callables receive the current contents, same as local writes
            if (is_callable($content)) {
                $content = $content($connection->read($bucket[1]) ?: '');
            }

            return $connection->write($bucket[1], (string) $content);
        }

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
        if ($bucket = static::parseBucketPath($filePath)) {
            $connection = static::bucketFor($bucket[0]);

            return $connection ? $connection->delete($bucket[1]) : false;
        }

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
        if ($bucket = static::parseBucketPath($filePath)) {
            $connection = static::bucketFor($bucket[0]);
            $size = $connection ? $connection->size($bucket[1]) : false;

            if ($size === false) {
                return false;
            }
        } else {
            $path = new Path($filePath);
            $filePath = $path->normalize();

            if (!static::exists($filePath)) {
                static::$errorsArray['file'] = 'File does not exist';

                return false;
            }

            clearstatcache();

            $size = filesize($filePath);
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

        return static::typeFromExtension($fileExtension) ?? static::systemType($filePath);
    }

    /**
     * Map a file extension to a human readable type — no filesystem checks
     *
     * @param string $extension The extension to map (without the dot)
     *
     * @return string|null
     */
    public static function typeFromExtension($extension)
    {
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

        return $extensions[strtolower((string) $extension)] ?? null;
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
        $bucketName = null;
        $destinationIsBucket = false;

        if (preg_match('/^([a-zA-Z0-9-_]+):\/\//', $destination, $matches)) {
            $destinationIsBucket = true;
            $bucketName = $matches[1];
            $destination = str_replace($matches[0], '', $destination);
        }

        $defaultUploadOptions = [
            'name' => null,
            'maxSize' => 0,
            'validate' => false,
            'allowedTypes' => [],
            'allowedExtensions' => [],
        ];

        $options = array_merge(static::$fileCreateOptions, $defaultUploadOptions, $options);

        if ($destinationIsBucket && !class_exists(Bucket::class)) {
            static::$errorsArray['upload'] = 'Storage buckets require the leafs/s3 module. Run `composer require leafs/s3` first.';

            return false;
        }

        if (is_resource($file)) {
            // raw resources carry no name of their own — bucket uploads only
            if (!$destinationIsBucket) {
                static::$errorsArray['upload'] = 'Resource uploads are only supported for storage buckets. Pass an uploaded file array instead.';

                return false;
            }

            $name = $options['name'] ?? basename((string) (stream_get_meta_data($file)['uri'] ?? 'upload_' . uniqid()));
        }

        // a plain path can be uploaded too: the source file is copied, never moved
        $sourceIsPath = is_string($file);

        if ($sourceIsPath) {
            $sourcePath = (new Path($file))->normalize();

            if (!static::exists($sourcePath)) {
                static::$errorsArray['upload'] = "$file does not exist";

                return false;
            }

            $file = [
                'tmp_name' => $sourcePath,
                'name' => basename($sourcePath),
                'size' => filesize($sourcePath),
            ];
        }

        if (!is_resource($file)) {
            $temp = $file['tmp_name'];
            $name = $options['name'] ?? $file['name'];

            if ($options['maxSize'] > 0 && ($file['size'] > $options['maxSize'])) {
                static::$errorsArray['upload'] = 'File size exceeds maximum size';

                return false;
            }

            if (File::exists($destination . DIRECTORY_SEPARATOR . $name)) {
                if ($options['overwrite']) {
                    unlink($destination . DIRECTORY_SEPARATOR . $name);
                } elseif ($options['rename']) {
                    $name = time() . '_' . uniqid() . '_' . $name;
                } else {
                    static::$errorsArray['upload'] = "$name already exists";

                    return false;
                }
            }

            if ($options['validate']) {
                // the tmp file has no extension, so type validation must work
                // from the original upload's name
                $fileExtension = (new Path($file['name']))->extension();
                $fileType = static::typeFromExtension($fileExtension);

                if (
                    !empty($options['allowedTypes']) &&
                    !in_array($fileType, $options['allowedTypes'])
                ) {
                    static::$errorsArray['upload'] = 'File type not allowed (got ' . ($fileType ?? 'unknown') . ', expected: ' . implode(', ', $options['allowedTypes']) . ')';

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

            if (!$destinationIsBucket) {
                $destinationPath = new Path($destination);
                $destination = $destinationPath->normalize();

                if (!Directory::exists($destination)) {
                    mkdir($destination, $options['mode'], true);
                }
            } else {
                $file = fopen($temp, 'r+');
            }
        }

        $uploadInfo = [
            'name' => $name,
            'size' => is_array($file) ? ($file['size'] ?? null) : null,
            'type' => static::typeFromExtension((new Path($name))->extension()) ?? 'file',
            'path' => (new Path($destination . DIRECTORY_SEPARATOR . $name))->normalize(),
            'extension' => (new Path($name))->extension(),
            'url' => (rtrim($_ENV['APP_URL'] ?? '/', '/') . DIRECTORY_SEPARATOR . str_replace('storage/app/public', 'storage', str_replace(
                str_replace(['public/index.php', 'index.php'], '', $_SERVER['SCRIPT_FILENAME'] ?? ''),
                '',
                (new Path($destination . DIRECTORY_SEPARATOR . $name))->normalize()
            ))),
        ];

        if ($destinationIsBucket) {
            $result = Bucket::connection($bucketName)->upload($file, $destination, [
                'name' => $name,
                'overwrite' => $options['overwrite'],
                'rename' => $options['rename'],
                'visibility' => $options['visibility'] ?? 'public',
            ]);

            if (!$result) {
                static::$errorsArray['upload'] = Bucket::errors();

                return false;
            }

            $uploadInfo['url'] = (is_string($result)) ? $result : false;

            return $uploadInfo;
        }

        try {
            // move_uploaded_file only works for real HTTP uploads — fall back
            // to copy for plain path sources (never destroy the user's file)
            // and rename for other tmp-style flows
            if (is_uploaded_file($temp)) {
                $moved = move_uploaded_file($temp, $destination . DIRECTORY_SEPARATOR . $name);
            } elseif ($sourceIsPath) {
                $moved = copy($temp, $destination . DIRECTORY_SEPARATOR . $name);
            } else {
                $moved = rename($temp, $destination . DIRECTORY_SEPARATOR . $name);
            }

            if ($moved) {
                return $uploadInfo;
            }

            static::$errorsArray['upload'] = 'Unable to upload file';

            return false;
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
        if ($bucket = static::parseBucketPath($filePath)) {
            $connection = static::bucketFor($bucket[0]);

            return $connection ? $connection->mimeType($bucket[1]) : false;
        }

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
        if ($bucket = static::parseBucketPath($filePath)) {
            $connection = static::bucketFor($bucket[0]);

            return $connection ? $connection->lastModified($bucket[1]) : false;
        }

        $path = new Path($filePath);
        $filePath = $path->normalize();

        if (!static::exists($filePath)) {
            static::$errorsArray['file'] = 'File does not exist';

            return false;
        }

        return filemtime($filePath);
    }

    /**
     * Create a resource from a file
     *
     * @param string $filePath The path of the file to create a resource from
     * @param string $mode The mode to open the file in
     * @return resource|bool
     */
    public static function toResource($filePath, $mode = 'r')
    {
        $path = new Path($filePath);
        $filePath = $path->normalize();

        if (!static::exists($filePath)) {
            static::$errorsArray['file'] = 'File does not exist';

            return false;
        }

        return fopen($filePath, $mode);
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
