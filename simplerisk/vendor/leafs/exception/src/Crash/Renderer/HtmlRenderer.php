<?php

namespace Leaf\Crash\Renderer;

use Leaf\Crash\Ai;
use Leaf\Crash\Frame;
use Leaf\Crash\Report;

/**
 * The crash page: renders a Report as a self-contained HTML document.
 *
 * No external assets, no frameworks — everything the page needs ships
 * inline, so it renders even when the app (or the internet) is broken.
 */
class HtmlRenderer
{
    /**
     * @param array{
     *     solution?: string,
     *     projectContext?: string|null,
     * } $options
     */
    public function render(Report $report, array $options = []): string
    {
        $frames = $report->frames;
        $activeIndex = 0;

        foreach ($frames as $index => $frame) {
            if ($frame->isApplication) {
                $activeIndex = $index;

                break;
            }
        }

        $prompt = Ai::prompt($report, $options['projectContext'] ?? null);
        $markdown = $report->toMarkdown();

        $e = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        ob_start();
        include __DIR__ . '/views/crash.html.php';

        return ob_get_clean();
    }

    /**
     * Group consecutive vendor frames so the frame list reads like
     * Flare's: app frames always visible, vendor runs collapsed.
     *
     * @param Frame[] $frames
     * @return array<int, array{type: string, frames: array<int, array{index: int, frame: Frame}>}>
     */
    public static function groupFrames(array $frames): array
    {
        $groups = [];
        $current = null;

        foreach ($frames as $index => $frame) {
            $type = $frame->isApplication ? 'app' : 'vendor';

            if ($current === null || $current['type'] !== $type) {
                if ($current !== null) {
                    $groups[] = $current;
                }

                $current = ['type' => $type, 'frames' => []];
            }

            $current['frames'][] = ['index' => $index, 'frame' => $frame];
        }

        if ($current !== null) {
            $groups[] = $current;
        }

        return $groups;
    }
}
