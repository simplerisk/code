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
    public static function status(?int $httpCode = null)
    {
        if ($httpCode === null) {
            return self::$httpCode;
        }
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
            return \Leaf\Config::getStatic('request.headers');
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
        if (is_string($params)) {
            return array_change_key_case(self::all($safeOutput), CASE_LOWER)[strtolower($params)] ?? null;
        }

        $data = [];
        foreach ($params as $param) {
            $data[$param] = self::get($param, $safeOutput);
        }

        return $data;
    }

    /**
     * Set a new header
     */
    public static function set($key, string $value = '', $replace = true, ?int $httpCode = null): void
    {
        if (!is_array($key)) {
            // only touch the response status when a code is explicitly
            // passed in — setting a header should not reset the status
            if (!$httpCode) {
                header("$key: $value", $replace);
            } else {
                header("$key: $value", $replace, $httpCode);
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
        return array_key_exists(
            strtolower($header),
            array_change_key_case(static::all(), CASE_LOWER)
        );
    }

    /**
     * Set the content-type to plain text
     */
    public static function contentPlain($code = 200): void
    {
        self::set('Content-Type', 'text/plain', true, $code ?? self::$httpCode);
    }

    /**
     * Set the content-type to html
     */
    public static function contentHtml($code = 200): void
    {
        self::set('Content-Type', 'text/html', true, $code ?? self::$httpCode);
    }

    /**
     * Set the content-type to xml
     */
    public static function contentXml($code = 200): void
    {
        self::set('Content-Type', 'application/xml', true, $code ?? self::$httpCode);
    }

    /**
     * Set the content-type to json
     */
    public static function contentJSON($code = 200): void
    {
        self::set('Content-Type', 'application/json', true, $code ?? self::$httpCode);
    }

    /**
     * Quickly set an access control header
     */
    public static function accessControl($key, $value = '', $code = 200)
    {
        if (is_string($key)) {
            self::set("Access-Control-$key", $value, true, $code ?? self::$httpCode);
        } else {
            foreach ($key as $header => $headerValue) {
                self::accessControl($header, $headerValue, $code);
            }
        }
    }

    /**
     * Set common security headers in one call
     *
     * Pass `true` for the sensible defaults, or an array to pick your own
     * values. Any option set to `false` is skipped.
     *
     * ```php
     * Headers::security();
     * Headers::security(['frameOptions' => 'SAMEORIGIN', 'hsts' => false]);
     * ```
     *
     * @param array|bool $options Headers to set
     */
    public static function security($options = true): void
    {
        $defaults = [
            'frameOptions' => 'DENY',
            'contentTypeOptions' => 'nosniff',
            'referrerPolicy' => 'no-referrer-when-downgrade',
            'permissionsPolicy' => false,
            'csp' => false,
            'hsts' => false,
        ];

        $options = array_merge($defaults, is_array($options) ? $options : []);

        $headers = [
            'frameOptions' => 'X-Frame-Options',
            'contentTypeOptions' => 'X-Content-Type-Options',
            'referrerPolicy' => 'Referrer-Policy',
            'permissionsPolicy' => 'Permissions-Policy',
            'csp' => 'Content-Security-Policy',
        ];

        foreach ($headers as $option => $header) {
            if ($options[$option] === false || $options[$option] === null) {
                continue;
            }

            self::set($header, self::policyValue($options[$option]));
        }

        if ($options['hsts'] !== false && $options['hsts'] !== null) {
            // browsers ignore HSTS over plain http, and sending it there is
            // how you lock yourself out of a domain you can't serve securely
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                self::set('Strict-Transport-Security', is_string($options['hsts'])
                    ? $options['hsts']
                    : 'max-age=31536000; includeSubDomains');
            }
        }
    }

    /**
     * Build a policy header value from a string or a directive map
     *
     * @param string|array $value The value or directives to build from
     */
    protected static function policyValue($value): string
    {
        if (!is_array($value)) {
            return (string) $value;
        }

        $directives = [];

        foreach ($value as $directive => $sources) {
            $directives[] = is_int($directive)
                ? $sources
                : trim($directive . ' ' . (is_array($sources) ? implode(' ', $sources) : $sources));
        }

        return implode('; ', $directives);
    }

    protected static function findHeaders()
    {
        if (function_exists('getallheaders') && \getallheaders()) {
            return \getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $name = substr($name, 5);
            } elseif ($name !== 'CONTENT_TYPE' && $name !== 'CONTENT_LENGTH') {
                continue;
            }

            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))))] = $value;
        }

        return $headers;
    }
}
