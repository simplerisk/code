<?php

declare(strict_types=1);

namespace Leaf\Crash;

/**
 * Framework status pages — 404, maintenance, CSRF and friends —
 * rendered in Crash's design language. These replace the legacy
 * Leaf\Exception\General pages.
 */
class Pages
{
    /**
     * Render a status page and end the request.
     *
     * @param string $eyebrow The small mono label above the title (e.g. "error 404")
     * @param string $title The page heading
     * @param string $message Plain-text explanation (escaped in the view)
     * @param int $status The HTTP status code to send
     */
    public static function render(string $eyebrow, string $title, string $message, int $status): void
    {
        $actionUrl = '/';
        $actionLabel = 'Back to safety';

        ob_start();
        include __DIR__ . '/Renderer/views/production.html.php';
        $html = ob_get_clean();

        static::send($html, $status);
    }

    /**
     * Default Not Found page
     */
    public static function default404(): void
    {
        static::render('error 404', 'Page not found', 'The page you are looking for could not be found.', 404);
    }

    /**
     * Default maintenance page
     */
    public static function defaultDown(): void
    {
        static::render('maintenance', "We'll be right back", 'The app is down for maintenance. Please check back shortly.', 503);
    }

    /**
     * CSRF failure page
     */
    public static function csrf(?string $error = null): void
    {
        static::render('error 400', 'Invalid request', $error ?: 'This page has expired. Go back and try again.', 400);
    }

    /**
     * Generic error page
     */
    public static function error(string $title, string $message, int $code = 500): void
    {
        static::render("error $code", $title, $message, $code);
    }

    /**
     * Send the page through leaf http when present, raw otherwise,
     * ending the request either way — same contract the legacy
     * pages had, minus the http dependency.
     */
    protected static function send(string $html, int $status): void
    {
        if (class_exists(\Leaf\Http\Response::class)) {
            (new \Leaf\Http\Response())->exit($html, $status);
        }

        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;

        exit(1);
    }
}
