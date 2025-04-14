<?php

namespace Leaf\Http;

/**
 * Leaf HTTP Request
 * --------
 *
 * This class provides an object-oriented way to interact with the current
 * HTTP request being handled by your application as well as retrieve the input,
 * cookies, and files that were submitted with the request.
 *
 * @author Michael Darko
 * @since 1.0.0
 * @version 2.3.0
 */
class Request
{
    public const METHOD_HEAD = 'HEAD';
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_OPTIONS = 'OPTIONS';
    public const METHOD_OVERRIDE = '_METHOD';

    protected static $errors = [];
    protected static $formDataMediaTypes = ['application/x-www-form-urlencoded'];

    /**
     * Internal instance of Leaf Form
     * @var \Leaf\Form
     */
    protected static $validator;

    public function __construct()
    {
        static::$validator = new \Leaf\Form;
    }

    /**
     * Get HTTP method
     * @return string
     */
    public static function getMethod(): string
    {
        return static::methodOverride() ?? static::getOriginalMethod();
    }

    /**
     * Get original method
     */
    public static function getOriginalMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Is this a method override request?
     * @return string|null
     */
    public static function methodOverride()
    {
        if ($method = static::headers('X-Http-Method-Override')) {
            return strtoupper($method);
        }

        if (static::getOriginalMethod() === 'POST' && $method = static::get(static::METHOD_OVERRIDE)) {
            return strtoupper($method);
        }

        return null;
    }

    /**
     * Check for request method type
     *
     * @param string $type The type of request to check for
     * @return bool
     */
    public static function typeIs(string $type): bool
    {
        return static::getMethod() === strtoupper($type);
    }

    /**
     * Find if request has a particular header
     *
     * @param string $header Header to check for
     * @return bool
     */
    public static function hasHeader(string $header): bool
    {
        return !!Headers::get($header);
    }

    /**
     * Is this an AJAX request?
     * @return bool
     */
    public static function isAjax(): bool
    {
        return !!static::params('isajax') || Headers::get('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Is this an XHR request? (alias of Leaf_Http_Request::isAjax)
     * @return bool
     */
    public static function isXhr(): bool
    {
        return static::isAjax();
    }

    /**
     * Access stream that allows you to read raw data from the request body. **This is not for form data**
     *
     * @param bool $safeData Sanitize data?
     */
    public static function input(bool $safeData = true)
    {
        $handler = fopen('php://input', 'r');
        $data = stream_get_contents($handler);
        $contentType = Headers::get('Content-Type') ?? '';

        if ($contentType === 'application/x-www-form-urlencoded') {
            $d = $data;
            $data = [];

            if ($d) {
                foreach (explode('&', $d) as $chunk) {
                    $param = explode('=', $chunk);
                    $data[$param[0]] = urldecode($param[1]);
                }
            }
        } else if (strpos($contentType, 'application/json') !== 0 && strpos($contentType, 'multipart/form-data') !== 0) {
            $safeData = false;
            $data = [$data];
        } else {
            if (!$data) {
                $data = json_encode([]);
            }

            $parsedData = json_decode($data, true);
            $data = is_array($parsedData) ? $parsedData : [$parsedData];
        }

        return $safeData ? \Leaf\Anchor::sanitize($data) : $data;
    }

    /**
     * Attempt to retrieve data from the request.
     *
     * Data which is not found in the request parameters will
     * be completely removed instead of returning null. Use `get`
     * if you want to return null or `params` if you want to set
     * a default value.
     *
     * @param array $params The parameters to return
     * @param bool $safeData Sanitize output?
     * @param bool $noEmptyString Remove empty strings from return data?
     */
    public static function try(array $params, bool $safeData = true, bool $noEmptyString = false)
    {
        $data = static::get($params, $safeData);
        $dataKeys = array_keys($data);

        foreach ($dataKeys as $key) {
            if (!isset($data[$key])) {
                unset($data[$key]);
                continue;
            }

            if ($noEmptyString && !strlen($data[$key])) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Get raw request data
     *
     * @param string|array $item The items to output
     * @param mixed $default The default value to return if no data is available
     */
    public static function rawData($item = null, $default = null)
    {
        return \Leaf\Anchor::deepGet(static::input(false), $item) ?? $default;
    }

    /**
     * Return only get request data
     *
     * @param string|array $item The items to output
     * @param mixed $default The default value to return if no data is available
     */
    public static function urlData($item = null, $default = null)
    {
        return \Leaf\Anchor::deepGet($_GET, $item) ?? $default;
    }

    /**
     * Return only get request data
     *
     * @param string|array $item The items to output
     * @param mixed $default The default value to return if no data is available
     */
    public static function query($item = null, $default = null)
    {
        return \Leaf\Anchor::deepGet($_GET, $item) ?? $default;
    }

    /**
     * Return only get request data
     *
     * @param string|array $item The items to output
     * @param mixed $default The default value to return if no data is available
     */
    public static function postData($item = null, $default = null)
    {
        return \Leaf\Anchor::deepGet($_POST, $item) ?? $default;
    }

    /**
     * Returns request data
     *
     * This method returns get, post, put patch, delete or raw form data or NULL
     * if the data isn't found.
     *
     * @param array|string $params The parameter(s) to return
     * @param bool $safeData Sanitize output
     */
    public static function get($params, bool $safeData = true)
    {
        if (is_string($params)) {
            return static::body($safeData)[$params] ?? null;
        }

        $data = [];

        foreach ($params as $param) {
            $data[$param] = static::get($param, $safeData);
        }

        return $data;
    }

    /**
     * Returns request data
     *
     * This method returns get, post, put patch, delete or raw form data or NULL
     * if the data isn't found.
     *
     * @param string|null $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public static function params(?string $key = null, $default = null)
    {
        return static::get($key) ?? $default;
    }

    /**
     * Returns request data
     *
     * This method returns get, post, put patch, delete or raw form data or NULL
     * if the data isn't found.
     *
     * @param string|null $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public static function getOrDefault(?string $key = null, $default = null)
    {
        return static::get($key) ?? $default;
    }

    /**
     * Get all the request data as an associative array
     *
     * @param bool $safeData Sanitize output
     */
    public static function body(bool $safeData = true)
    {
        $finalData = array_merge(static::urlData(), $_FILES, static::postData(), static::input(false));

        return $safeData ?
            \Leaf\Anchor::sanitize($finalData) :
            $finalData;
    }

    /**
     * Get all files passed into the request.
     *
     * @param array|string|null $filenames The file(s) you want to get
     */
    public static function files($filenames = null)
    {
        if ($filenames == null) {
            return $_FILES;
        }

        if (is_string($filenames)) {
            return $_FILES[$filenames] ?? null;
        }

        $files = [];
        foreach ($filenames as $filename) {
            $files[$filename] = $_FILES[$filename] ?? null;
        }
        return $files;
    }

    /**
     * Fetch COOKIE data
     *
     * This method returns a key-value array of Cookie data sent in the HTTP request, or
     * the value of an array key if requested. If the array key does not exist, NULL is returned.
     *
     * @param string|null $key
     * @return array|string|null
     */
    public static function cookies(?string $key = null)
    {
        return $key === null ?
            Cookie::all() :
            Cookie::get($key);
    }

    /**
     * Display a flash message from the previous request
     * 
     * @param string|null $key The key to get from the flash data
     * @return array|string|null
     */
    public static function flash(?string $key = null)
    {
        return \Leaf\Flash::display($key);
    }

    /**
     * Does the Request body contain parsed form data?
     * @return bool
     */
    public static function isFormData(): bool
    {
        $method = static::getMethod();

        return ($method === static::METHOD_POST && static::getContentType() === null) || in_array(static::getMediaType(), static::$formDataMediaTypes);
    }

    /**
     * Get Headers
     *
     * This method returns a key-value array of headers sent in the HTTP request, or
     * the value of a hash key if requested. If the array key does not exist, NULL is returned.
     *
     * @param array|string|null $key The header(s) to return
     * @param bool $safeData Attempt to sanitize headers
     *
     * @return array|string|null
     */
    public static function headers($key = null, bool $safeData = true)
    {
        return ($key === null) ?
            Headers::all($safeData) :
            Headers::get($key, $safeData);
    }

    /**
     * Validate the request data
     *
     * @param array $rules The rules to validate against
     * @param boolean $returnFullData Return the full data or just the validated data?
     *
     * @return false|array Returns false if validation fails, or the validated data if validation passes
     */
    public static function validate(array $rules, bool $returnFullData = false)
    {
        if (!static::$validator) {
            static::$validator = new \Leaf\Form;
        }

        $data = static::$validator->validate(static::body(false), $rules);

        if ($data === false) {
            return false;
        }

        return $returnFullData ? static::body() : $data;
    }

    /**
     * Return validator instance
     * @return \Leaf\Form
     */
    public static function validator()
    {
        if (!static::$validator) {
            static::$validator = new \Leaf\Form;
        }

        return static::$validator;
    }

    /**
     * Return the auth instance
     * @return \Leaf\Auth
     */
    protected static function auth()
    {
        if (!class_exists('\Leaf\Auth')) {
            throw new \Exception('You need to install the leafs/auth module to use the auth helper');
        }

        if (!(\Leaf\Config::getStatic('auth'))) {
            \Leaf\Config::singleton('auth', function () {
                return new \Leaf\Auth;
            });
        }

        return \Leaf\Config::get('auth');
    }

    /**
     * Get the authenticated user
     */
    public static function user()
    {
        return static::auth()->user();
    }

    /**
     * Get data passed from the previous middleware
     *
     * @param string|null $key The key to get from the middleware data
     */
    public static function next($key = null)
    {
        $middlewareData = \Leaf\Config::getStatic('middleware.data');

        return $key ? $middlewareData[$key] : $middlewareData;
    }

    /**
     * Store a file from the request.
     *
     * @param string $key The name of the file input the request.
     * @param string $destination The directory where the file should be stored.
     * @param array $config Optional configurations: max_file_size, file_type, extensions
     *
     * @return array|false An array containing the status or false for a failure.
     */
    public static function upload(string $key, string $destination, array $config = [])
    {
        $file = static::files($key);

        if (!$file) {
            static::$errors['upload'] = 'No file was uploaded.';
            return false;
        }

        if (isset($config['extensions'])) {
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);

            if (!in_array($fileExtension, $config['extensions'])) {
                static::$errors['upload'] = 'Invalid file extension.';
                return false;
            }
        }

        $fileSystem = new \Leaf\FS\File;

        if (!isset($config['rename']) || !$config['rename']) {
            $config['unique'] = true;
        }

        $uploadedFile = $fileSystem->upload(
            $file,
            preg_replace(
                '/\/$/',
                '',
                $destination
            ) . '/',
            $config
        );

        return $uploadedFile;
    }

    /**
     * Store a file from the request with a given name
     *
     * @param string $key The name of the file input the request.
     * @param string $destination The directory where the file should be stored.
     * @param string $name The name to store the file as.
     * @param array $config Optional configurations: max_file_size, file_type, extensions
     */
    public static function uploadAs(string $key, string $destination, string $name, array $config = [])
    {
        $fileExtension = pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION);

        $config['rename'] = true;
        $config['name'] = $name . '.' . $fileExtension;

        return static::upload($key, $destination, $config);
    }

    /**
     * Get Content Type
     * @return string|null
     */
    public static function getContentType(): ?string
    {
        return Headers::get('Content-Type');
    }

    /**
     * Get Media Type (type/subtype within Content Type header)
     * @return string|null
     */
    public static function getMediaType(): ?string
    {
        $contentType = static::getContentType();

        if ($contentType) {
            $contentTypeParts = preg_split('/\s*[;,]\s*/', $contentType);

            return strtolower($contentTypeParts[0]);
        }

        return null;
    }

    /**
     * Get Media Type Params
     * @return array
     */
    public static function getMediaTypeParams(): array
    {
        $contentTypeParams = [];
        $contentType = static::getContentType();

        if ($contentType) {
            $contentTypeParts = preg_split('/\s*[;,]\s*/', $contentType);
            $contentTypePartsLength = count($contentTypeParts);

            for ($i = 1; $i < $contentTypePartsLength; $i++) {
                $paramParts = explode('=', $contentTypeParts[$i]);
                $contentTypeParams[strtolower($paramParts[0])] = $paramParts[1];
            }
        }

        return $contentTypeParams;
    }

    /**
     * Get Content Charset
     * @return string|null
     */
    public static function getContentCharset(): ?string
    {
        $mediaTypeParams = static::getMediaTypeParams();

        if (isset($mediaTypeParams['charset'])) {
            return $mediaTypeParams['charset'];
        }

        return null;
    }

    /**
     * Get Content-Length
     * @return int
     */
    public static function getContentLength(): int
    {
        return Headers::get('CONTENT_LENGTH') ?? 0;
    }

    /**
     * Get Host
     * @return string
     */
    public static function getHost(): string
    {
        if (isset($_SERVER['HTTP_HOST'])) {
            if (preg_match('/^(\[[a-fA-F0-9:.]+\])(:\d+)?\z/', $_SERVER['HTTP_HOST'], $matches)) {
                return $matches[1];
            } else if (strpos($_SERVER['HTTP_HOST'], ':') !== false) {
                $hostParts = explode(':', $_SERVER['HTTP_HOST']);
                return $hostParts[0];
            }

            return $_SERVER['HTTP_HOST'];
        }

        return $_SERVER['SERVER_NAME'];
    }

    /**
     * Get Host with Port
     * @return string
     */
    public static function getHostWithPort(): string
    {
        return sprintf('%s:%s', static::getHost(), static::getPort());
    }

    /**
     * Get Port
     * @return int
     */
    public static function getPort(): int
    {
        return (int) $_SERVER['SERVER_PORT'] ?? 80;
    }

    /**
     * Get Scheme (https or http)
     * @return string
     */
    public static function getScheme(): string
    {
        return empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? 'http' : 'https';
    }

    /**
     * Get Script Name
     * @return string
     */
    public static function getScriptName(): string
    {
        return $_SERVER['SCRIPT_NAME'];
    }

    /**
     * Get physical path
     * @return string
     */
    public static function getPhysicalPath(): string
    {
        return dirname($_SERVER['SCRIPT_NAME']);
    }

    /**
     * Get Path
     * @return string
     */
    public static function getPath(): string
    {
        $physicalPath = static::getPhysicalPath();

        return substr(
            parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
            $physicalPath == '/' ? 0 : strlen($physicalPath)
        );
    }

    /**
     * Get Path Info (virtual path)
     * @return string|null
     */
    public static function getPathInfo(): ?string
    {
        return $_SERVER['REQUEST_URI'] ?? null;
    }

    /**
     * Get query string
     * @return string|null
     */
    public static function getQueryString(): ?string
    {
        return $_SERVER['QUERY_STRING'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? null;
    }

    /**
     * Get URL (scheme + host [ + port if non-standard ])
     * @return string
     */
    public static function getUrl(): string
    {
        $url = static::getScheme() . '://' . static::getHost();

        if ((static::getScheme() === 'https' && static::getPort() !== 443) || (static::getScheme() === 'http' && static::getPort() !== 80)) {
            $url .= ':' . static::getPort();
        }

        return $url;
    }

    /**
     * Get Full URL (scheme + host [ + port if non-standard ] + path + query string)
     * @return string
     */
    public static function getFullUrl(): string
    {
        return static::getUrl() . static::getPathInfo() . (static::getQueryString() ? '?' . static::getQueryString() : '');
    }

    /**
     * Get IP
     * @return string
     */
    public static function getIp(): string
    {
        $keys = ['X_FORWARDED_FOR', 'HTTP_X_FORWARDED_FOR', 'CLIENT_IP', 'REMOTE_ADDR'];

        foreach ($keys as $key) {
            if (isset($_SERVER[$key])) {
                return $_SERVER[$key];
            }
        }

        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Get Referrer
     * @return string|null
     */
    public static function getReferrer(): ?string
    {
        return Headers::get('HTTP_REFERER');
    }

    /**
     * Get Referer (for those who can't spell)
     * @return string|null
     */
    public static function getReferer(): ?string
    {
        return static::getReferrer();
    }

    /**
     * Get User Agent
     * @return string|null
     */
    public static function getUserAgent(): ?string
    {
        return Headers::get('USER-AGENT');
    }

    /**
     * Handle errors from validation/auth/upload
     * @return array
     */
    public static function errors()
    {
        return array_merge(
            static::$errors,
            static::$validator ? static::$validator->errors() : [],
            class_exists('\Leaf\Auth') ? static::auth()->errors() : []
        );
    }
}
