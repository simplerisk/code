<?php

namespace Leaf\Crash;

/**
 * Turns a crash report into something an AI assistant can act on.
 *
 * The prompt combines three layers: the project's own context file
 * (.leaf/CONTEXT.md — what the app is and how it's structured), the
 * user journey (breadcrumbs), and the crash itself with code excerpts.
 * That's the difference between "explain this stack trace" and "you
 * know this app, here's what the user did, here's where it broke".
 */
class Ai
{
    /**
     * Build a debugging prompt for the report.
     *
     * @param string|null $projectContext Contents of the app's .leaf/CONTEXT.md (or any project brief)
     */
    public static function prompt(Report $report, ?string $projectContext = null): string
    {
        $sections = [];

        $sections[] = 'You are debugging an error in a Leaf PHP application. '
            . 'Use the project context, the user journey, and the crash report below '
            . 'to find the likely root cause and suggest a concrete fix with code.';

        if ($projectContext) {
            $sections[] = "## Project context\n\n" . trim($projectContext);
        }

        $sections[] = $report->toMarkdown();

        $sections[] = 'Respond with: (1) the most likely root cause, '
            . '(2) the fix as a code change, (3) anything else in the journey that looks suspicious.';

        return implode("\n\n---\n\n", $sections);
    }

    /**
     * Read the app's context file if it exists.
     */
    public static function projectContext(?string $appRoot = null): ?string
    {
        $appRoot = $appRoot ?? getcwd();

        foreach (['.leaf/CONTEXT.md', '.leaf/context.md'] as $candidate) {
            $path = $appRoot . DIRECTORY_SEPARATOR . $candidate;

            if (is_file($path)) {
                return file_get_contents($path) ?: null;
            }
        }

        return null;
    }

    /**
     * A link that opens Claude with the debugging prompt prefilled.
     */
    public static function claudeUrl(string $prompt): string
    {
        return 'https://claude.ai/new?q=' . rawurlencode($prompt);
    }

    /**
     * A link that opens ChatGPT with the debugging prompt prefilled.
     */
    public static function chatgptUrl(string $prompt): string
    {
        return 'https://chatgpt.com/?q=' . rawurlencode($prompt);
    }
}
