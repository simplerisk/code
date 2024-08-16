<?php

namespace Leaf\Http;

/**
 * Leaf HTTP Response
 * -----------
 * This is a simple abstraction over top an HTTP response. This
 * provides methods to set the HTTP status, the HTTP headers,
 * and the HTTP body.
 *
 * @author Michael Darko
 * @since 1.0.0
 * @version 2.0
 */
class Response
{
    /**
     * @var array
     */
    public $headers = [];

    /**
     * @var array
     */
    public $cookies = [];

    /**
     * @var string
     */
    protected $content = '';

    /**
     * @var int HTTP status code
     */
    protected $status = 200;

    /**
     * @var string HTTP Version
     */
    protected $version;

    /**
     * Get/Set Http Version
     */
    public function httpVersion(?string $version = null)
    {
        if (!$version || (is_string($version) && strlen($version) === 0)) {
            return $this->version ?? $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        }

        $this->version = 'HTTP/' . str_replace('HTTP/', '', $version);

        return $this;
    }

    /**
     * Output plain text
     *
     * @param mixed $data The data to output
     * @param int $code The response status code
     */
    public function plain($data, int $code = 200)
    {
        $this->status = $code;
        $this->headers['Content-Type'] = 'text/plain';
        $this->content = $data;

        $this->send();
    }

    /**
     * Output xml text
     *
     * @param string $data The data to output
     * @param int $code The response status code
     */
    public function xml(string $data, int $code = 200)
    {
        $this->status = $code;
        $this->headers['Content-Type'] = 'application/xml';
        $this->content = $data;

        $this->send();
    }

    /**
     * Output json encoded data with an HTTP code/message
     *
     * @param mixed $data The data to output
     * @param int $code The response status code
     * @param bool $showCode Show response code in body?
     */
    public function json($data, int $code = 200, bool $showCode = false)
    {
        $this->status = $code;

        if ($showCode) {
            $dataToPrint = [
                'data' => $data,
                'status' => [
                    'code' => $code,
                    'message' => Status::$statusTexts[$code] ?? 'unknown status',
                ],
            ];
        } else {
            $dataToPrint = $data;
        }

        $this->headers['Content-Type'] = 'application/json';
        $this->content = json_encode($dataToPrint);

        $this->send();
    }

    /**
     * Output data from an HTML or PHP file
     *
     * @param string $file The file to output
     * @param int $code The http status code
     */
    public function page(string $file, int $code = 200)
    {
        $this->status = $code;
        $this->headers['Content-Type'] = 'text/html';

        \ob_start();
        require $file;
        $this->content = ob_get_contents();
        ob_end_clean();

        $this->send();
    }

    /**
     * Output some html/PHP
     *
     * @param string $markup The data to output
     * @param int $code The http status code
     */
    public function markup(string $markup, int $code = 200)
    {
        $this->status = $code;
        $this->headers['Content-Type'] = 'text/html';
        $this->content = <<<EOT
$markup
EOT;

        $this->send();
    }

    /**
     * Output plain text
     *
     * @param string $file Path to the file to download
     * @param string|null $name The of the file as shown to user
     * @param int $code The response status code
     */
    public function download(string $file, string $name = null, int $code = 200)
    {
        $this->status = $code;

        if (!file_exists($file)) {
            Headers::contentHtml();
            trigger_error("$file not found. Confirm your file path.");
        }

        $this->headers = array_merge($this->headers, [
            'Expires' => '0',
            'Pragma' => 'public',
            'Content-Length' => filesize($file),
            'Cache-Control' => 'must-revalidate',
            'Content-Description' => 'File Transfer',
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $name ?? basename($file) . '"',
        ]);

        $this->content = $file;

        $this->send();
    }

    /**
     * The HTTP 204 No Content success status response code indicates
     * that a request has succeeded, but that the client doesn't
     * need to navigate away from its current page.
     */
    public function noContent()
    {
        $this->status = 204;
        $this->send();
    }

    /**
     * Output some data and break the application
     *
     * @param mixed $data The data to output
     * @param int $code The Http status code
     */
    public function exit($data, int $code = 500)
    {
        $this->status = $code;

        if (is_array($data)) {
            $this->headers['Content-Type'] = 'application/json';
            $this->content = json_encode($data);
        } else {
            $this->content = $data;
        }

        $this->send();

        exit();
    }

    /**
     * Redirect
     *
     * This method prepares this response to return an HTTP Redirect response
     * to the HTTP client.
     *
     * @param string $url The redirect destination
     * @param int $status The redirect HTTP status code
     */
    public function redirect(string $url, int $status = 302)
    {
        if (class_exists('Leaf\Eien\Server') && PHP_SAPI === 'cli') {
            \Leaf\Config::set('response.redirect', [$url, $status]);
            return;
        }

        Headers::status($status);
        Headers::set('Location', $url, true, $status);
    }

    /**
     * Force set HTTP status code
     *
     * @param int|null code The response code to set
     */
    public function status(?int $code = null): Response
    {
        $this->status = $code;
        Headers::status($code);

        return $this;
    }

    /**
     * set header
     *
     * @param string|array $name Header name
     * @param string|null $value Header value
     * @param boolean $replace Replace existing header
     * @param int $httpCode The HTTP status code
     */
    public function withHeader($name, ?string $value = '', bool $replace = true, int $httpCode = 200): Response
    {
        if (class_exists('Leaf\Eien\Server') && PHP_SAPI === 'cli') {
            $this->headers = array_merge(
                $this->headers,
                is_array($name) ? $name : [$name => $value]
            );

            \Leaf\Config::set('response.headers', $this->headers);

            return $this;
        }

        $this->status = $httpCode;
        Headers::status($httpCode);

        if (is_array($name)) {
            $this->headers = array_merge($this->headers, $name);
            return $this;
        }

        if ($replace === false || $httpCode !== 200) {
            Headers::set($name, $value, $replace, $httpCode);
        } else {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    /**
     * Shorthand method of setting a cookie + value + expire time
     *
     * @param string $name The name of the cookie
     * @param string $value The value of cookie
     * @param int|null $expire When the cookie expires. Default: 7 days
     *
     * @return Response
     */
    public function withCookie(string $name, string $value, int $expire = null): Response
    {
        $this->cookies[$name] = [$value, $expire ?? (time() + 604800)];

        if (class_exists('Leaf\Eien\Server') && PHP_SAPI === 'cli') {
            \Leaf\Config::set('response.cookies', $this->cookies);
        }

        return $this;
    }

    /**
     * Delete cookie
     *
     * @param mixed $name The name of the cookie
     */
    public function withoutCookie($name): Response
    {
        $this->cookies[$name] = ['', -1];

        if (class_exists('Leaf\Eien\Server') && PHP_SAPI === 'cli') {
            \Leaf\Config::set('response.cookies', $this->cookies);
        }

        return $this;
    }

    /**
     * Flash a piece of data to the session.
     *
     * @param string|array key The key of the item to set
     * @param string $value The value of flash item
     */
    public function withFlash($key, string $value): Response
    {
        if (!class_exists('Leaf\Http\Session')) {
            Headers::contentHtml();
            trigger_error('Leaf session not found. Run `leaf install session` or `composer require leafs/session`');
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->withFlash($k, $v);
            }
        }

        \Leaf\Flash::set($key, $value);

        return $this;
    }

    /**
     * Get message for HTTP status code
     *
     * @param int $status
     * @return string|null
     */
    public static function getMessageForCode(int $status): ?string
    {
        return Status::$statusTexts[$status] ?? 'unknown status';
    }

    /**
     * Sends HTTP headers.
     *
     * @return $this
     */
    public function sendHeaders(): Response
    {
        if (class_exists('Leaf\Eien\Server') && PHP_SAPI === 'cli') {
            \Leaf\Config::set('response.headers', $this->headers);
            return $this;
        }

        // headers have already been sent by the developer
        if (headers_sent()) {
            return $this;
        }

        Headers::set($this->headers);

        // status
        header(sprintf('%s %s %s', $this->httpVersion(), $this->status, Status::$statusTexts[$this->status]), true, $this->status);

        return $this;
    }

    /**
     * Send cookies
     */
    public function sendCookies(): Response
    {
        if (class_exists('Leaf\Eien\Server') && PHP_SAPI === 'cli') {
            \Leaf\Config::set('response.cookies', $this->cookies);
            return $this;
        }

        if (class_exists('Leaf\Http\Cookie')) {
            foreach ($this->cookies as $key => $value) {
                Cookie::set($key, $value[0], ['expire' => $value[1]]);
            }
        }

        return $this;
    }

    /**
     * Sends content for the current web response.
     *
     * @return $this
     */
    public function sendContent(): Response
    {
        if (strpos($this->headers['Content-Disposition'] ?? '', 'attachment') !== false) {
            readfile($this->content);
        } else {
            echo $this->content;
        }

        return $this;
    }

    /**
     * Send the Http headers and content
     *
     * @return $this
     */
    public function send(): Response
    {
        $this->sendHeaders()->sendCookies()->sendContent();

        if (\function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (\function_exists('litespeed_finish_request')) {
            \litespeed_finish_request();
        } elseif (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            $this->closeOutputBuffers(0, true);
        }

        return $this;
    }

    /**
     * Cleans or flushes output buffers up to target level.
     *
     * Resulting level can be greater than target level if a non-removable buffer has been encountered.
     *
     * @final
     */
    public static function closeOutputBuffers(int $targetLevel, bool $flush): void
    {
        $status = ob_get_status(true);
        $level = \count($status);
        $flags = \PHP_OUTPUT_HANDLER_REMOVABLE | ($flush ? \PHP_OUTPUT_HANDLER_FLUSHABLE : \PHP_OUTPUT_HANDLER_CLEANABLE);

        while ($level-- > $targetLevel && ($s = $status[$level]) && (!isset($s['del']) ? !isset($s['flags']) || ($s['flags'] & $flags) === $flags : $s['del'])) {
            if ($flush) {
                ob_end_flush();
            } else {
                ob_end_clean();
            }
        }
    }
}
