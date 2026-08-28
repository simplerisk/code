<?php

namespace Leaf\Crash;

/**
 * Turns a Throwable into structured frames.
 *
 * The inspector is deliberately dumb: it walks the trace, builds Frame
 * objects with code excerpts, and marks which frames are application
 * code. Everything smarter (grouping, redaction, serialization) lives
 * in Report.
 */
class Inspector
{
    protected \Throwable $throwable;
    protected ?string $appRoot;

    /** @var string[] Path fragments that mark a frame as non-application code */
    protected array $vendorMarkers = [
        DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
    ];

    public function __construct(\Throwable $throwable, ?string $appRoot = null)
    {
        $this->throwable = $throwable;
        $this->appRoot = $appRoot ? rtrim($appRoot, DIRECTORY_SEPARATOR) : null;
    }

    /**
     * Build the frame list, starting from where the throwable was thrown.
     *
     * @return Frame[]
     */
    public function frames(): array
    {
        $frames = [
            new Frame($this->throwable->getFile(), $this->throwable->getLine()),
        ];

        foreach ($this->throwable->getTrace() as $entry) {
            $frames[] = new Frame(
                $entry['file'] ?? null,
                $entry['line'] ?? null,
                $entry['class'] ?? null,
                $entry['function'] ?? null
            );
        }

        foreach ($frames as $frame) {
            $frame->isApplication = $this->isApplicationFile($frame->file);
            $frame->loadExcerpt();
        }

        return $frames;
    }

    /**
     * The first frame that belongs to application code, if any.
     */
    public function applicationFrame(): ?Frame
    {
        foreach ($this->frames() as $frame) {
            if ($frame->isApplication) {
                return $frame;
            }
        }

        return null;
    }

    protected function isApplicationFile(?string $file): bool
    {
        if (!$file || !is_file($file)) {
            return false;
        }

        foreach ($this->vendorMarkers as $marker) {
            if (strpos($file, $marker) !== false) {
                return false;
            }
        }

        if ($this->appRoot) {
            return strpos($file, $this->appRoot . DIRECTORY_SEPARATOR) === 0;
        }

        return true;
    }
}
