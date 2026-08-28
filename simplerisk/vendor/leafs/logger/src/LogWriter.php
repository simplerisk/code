<?php

namespace Leaf;

/**
 * Log Writer
 *
 * This class is used by Leaf_Log to write log messages to a valid, writable
 * resource handle (e.g. a file or STDERR).
 *
 * @package Leaf
 * @author  Michael Darko
 * @since   2.0.0
 */
class LogWriter
{
    protected $logFile;

    /**
     * Constructor
     * @param string $file File to log to
     * @param bool $createFile Create file if it's not found
     */
    public function __construct(string $file, bool $createFile = false)
    {
        if (!file_exists($file)) {
            if ($createFile) {
                FS\File::create($file, null, ['recursive' => true]);
            } else {
                // php 8.4 deprecates trigger_error with E_USER_ERROR
                throw new \RuntimeException(basename($file) . ' not found in ' . dirname($file));
            }
        }

        $this->logFile = $file;
    }

    /**
     * Write message
     *
     * @param mixed $message
     * @param int $level
     * @return int|bool
     */
    public function write($message, $level = null)
    {
        $style = class_exists('Leaf\Config') ? \Leaf\Config::get('log.style') ?? 'leaf' : 'leaf';

        if ($level !== null) {
            $level = Log::getLevel($level) . ' - ';
        }

        $timestamp = (new \Leaf\Date())->tick()->now();
        $formatted = $style === 'linux'
            ? "[$timestamp] $level$message\n\n"
            : "[$timestamp]\n$level$message\n\n";

        // appending uses the file's own pointer; prepending rewrites the
        // whole file on every line, which is unusable on production logs.
        // `log.mode: prepend` keeps the old newest-first layout by choice.
        $mode = class_exists('Leaf\Config') ? \Leaf\Config::get('log.mode') : null;

        if ($mode === 'prepend') {
            FS\File::write($this->logFile, function ($content) use ($formatted) {
                return $formatted . $content;
            });
        } else {
            FS\File::write($this->logFile, $formatted, FILE_APPEND);
        }

        return 1;
    }
}
