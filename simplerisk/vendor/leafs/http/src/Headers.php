<?php

namespace Leaf\Http;

/**
 * HTTP Headers
 * ---------------------
 * Response header management made simple with Leaf
 *
 * @author Michael Darko
 * @since 2.0.0
 */
class Headers
{
    /**
     * @var int
     */
    protected static $httpCode = 200;

    /**
     * Get or Set an HTTP code for response
     *
     * @param int|null $httpCode The current response code.
     */
    public static function status(int $httpCode = null)
    {
        if ($httpCode === null) return self::$httpCode;
        self::$httpCode = $httpCode;
    }

    /**
     * Force an HTTP code for response using PHP's `http_response_code`
     * 
     * @param int $httpCode The response code to set
     */
    public static function resetStatus($httpCode = 200)
    {
        return http_response_code($httpCode);
    }

    /**
     * Get all headers passed into application
     *
     * @param bool $safeOutput Try to sanitize header data
     */
    public static function all(bool $safeOutput = false): array
    {
        if (class_exists('Leaf\Eien\Server') && PHP_SAPI === 'cli') {
            return \Leaf\Config::get('request.headers');
        }

        return ($safeOutput === false) ?
            self::findHeaders() :
            \Leaf\Anchor::sanitize(self::findHeaders());
    }

    /**
     * Return a particular header passed into app
     *
     * @param array|string $params The header(s) to return
     * @param bool $safeOutput Try to sanitize header data
     *
     * @return array|string|null
     */
    public static function get($params, bool $safeOutput = false)
    {
        if (is_string($params)) return array_change_key_case(self::all($safeOutput), CASE_LOWER)[strtolower($params)] ?? null;

        $data = [];
        foreach ($params as $param) {
            $data[$param] = self::get($param, $safeOutput);
        }
        return $data;
    }

    /**
     * Set a new header
     */
    public static function set($key, string $value = "", $replace = true, int $httpCode = 200): void
    {
        if (!is_array($key)) {
            $code = $httpCode ?? self::$httpCode;

            if (!$code) {
                header("$key: $value", $replace);
            } else {
                header("$key: $value", $replace, $code);
            }
        } else {
            foreach ($key as $header => $headerValue) {
                self::set($header, $headerValue, $replace, $httpCode);
            }
        }
    }

    /**
     * Remove a header
     */
    public static function remove($keys)
    {
        if (!is_array($keys)) {
            header_remove($keys);
        } else {
            foreach ($keys as $key) {
                self::remove($key);
            }
        }
    }

    /**
     * Check if a header is present
     * 
     * @param string $header The header to check
     */
    public static function has(string $header)
    {
        return in_array($header, static::all());
    }

    /**
     * Set the content-type to plain text
     */
    public static function contentPlain($code = 200): void
    {
        self::set("Content-Type", "text/plain", true, $code ?? self::$httpCode);
    }

    /**
     * Set the content-type to html
     */
    public static function contentHtml($code = 200): void
    {
        self::set("Content-Type", "text/html", true, $code ?? self::$httpCode);
    }

    /**
     * Set the content-type to xml
     */
    public static function contentXml($code = 200): void
    {
        self::set("Content-Type", "application/xml", true, $code ?? self::$httpCode);
    }

    /**
     * Set the content-type to json
     */
    public static function contentJSON($code = 200): void
    {
        self::set("Content-Type", "application/json", true, $code ?? self::$httpCode);
    }

    /**
     * Quickly set an access control header
     */
    public static function accessControl($key, $value = "", $code = 200)
    {
        if (is_string($key)) {
            self::set("Access-Control-$key", $value, true, $code ?? self::$httpCode);
        } else {
            foreach ($key as $header => $headerValue) {
                self::accessControl($header, $headerValue, $code);
            }
        }
    }

    protected static function findHeaders()
    {
        if (function_exists("getallheaders") && \getallheaders()) {
            return \getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
                $headers[str_replace([' ', 'Http'], ['-', 'HTTP'], ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}
