<?php

namespace Leaf\Crash;

/**
 * A single stack frame of a crash report.
 *
 * Frames are plain serializable data: file, line, the callable that was
 * executing, a small excerpt of the surrounding code, and whether the
 * frame belongs to application code or a dependency.
 */
class Frame
{
    /** How many lines of code to capture around the frame line */
    public const EXCERPT_RADIUS = 8;

    public ?string $file;
    public ?int $line;
    public ?string $class;
    public ?string $function;

    /** @var array<int, string> Source lines keyed by line number */
    public array $excerpt = [];

    /** True when the frame is your code, false for vendor/internal frames */
    public bool $isApplication = false;

    public function __construct(?string $file, ?int $line, ?string $class = null, ?string $function = null)
    {
        $this->file = $file;
        $this->line = $line;
        $this->class = $class;
        $this->function = $function;
    }

    /**
     * The callable this frame was executing, eg App\Controllers\HomeController::index
     */
    public function callable(): ?string
    {
        if (!$this->function) {
            return null;
        }

        return $this->class ? "{$this->class}::{$this->function}" : $this->function;
    }

    /**
     * Load the code excerpt around this frame's line from disk.
     */
    public function loadExcerpt(): self
    {
        if (!$this->file || !$this->line || !is_file($this->file)) {
            return $this;
        }

        $lines = @file($this->file);

        if ($lines === false) {
            return $this;
        }

        $start = max(1, $this->line - self::EXCERPT_RADIUS);
        $end = min(count($lines), $this->line + self::EXCERPT_RADIUS);

        for ($number = $start; $number <= $end; $number++) {
            $this->excerpt[$number] = rtrim($lines[$number - 1], "\n\r");
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'line' => $this->line,
            'callable' => $this->callable(),
            'isApplication' => $this->isApplication,
            'excerpt' => $this->excerpt,
        ];
    }
}
