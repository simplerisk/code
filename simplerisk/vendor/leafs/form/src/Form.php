<?php

declare(strict_types=1);

namespace Leaf;

/**
 * Leaf Form
 * ----
 * Leaf's form validation library
 *
 * @since 1.0.0
 */
class Form
{
    /**
     * Validation errors
     */
    protected static $errors = [];

    /**
     * Validation rules
     */
    protected static $rules = [
        'required' => '/.+/',
        'email' => '/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/',
        'alpha' => '/^[a-zA-Z\s]+$/',
        'text' => '/^[a-zA-Z\s]+$/',
        'textonly' => '/^[a-zA-Z]+$/',
        'alphanum' => '/^[a-zA-Z0-9\s]+$/',
        'alphadash' => '/^[a-zA-Z0-9-_]+$/',
        'username' => '/^[a-zA-Z0-9_]+$/',
        'number' => '/^[0-9]+$/',
        'float' => '/^[0-9]+(\.[0-9]+)$/',
        'date' => '/^\d{4}-\d{2}-\d{2}$/',
        'min' => '/^.{%s,}$/',
        'max' => '/^.{0,%s}$/',
        'between' => '/^.{%s,%s}$/',
        'match' => '/^%s$/',
        'contains' => '/%s/',
        'boolean' => '/^(true|false|1|0)$/',
        'in' => '/^(%s)$/',
        'ip' => '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/',
        'ipv4' => '/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/',
        'ipv6' => '/^([a-fA-F0-9]{1,4}:){7}[a-fA-F0-9]{1,4}$/',
        'url' => '/^(https?|ftp):\/\/(-\.)?([^\s\/?\.#-]+\.?)+(\/[^\s]*)?$/i',
        'domain' => '/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i',
        'creditcard' => '/^([0-9]{4}-){3}[0-9]{4}$/',
        'phone' => '/^\+?(\d.*){3,}$/',
        'uuid' => '/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i',
        'slug' => '/^[a-z0-9]+(-[a-z0-9]+)*$/i',
        'json' => '/^[\w\s\-\{\}\[\]\"]+$/',
        'regex' => '/%s/',
    ];

    /**
     * Special validation rules
     */
    protected static $specialRules = [
        'array',
    ];

    /**
     * Validation error messages
     */
    protected static $messages = [
        'required' => '{Field} is required',
        'email' => '{Field} must be a valid email address',
        'alpha' => '{Field} must contain only alphabets and spaces',
        'text' => '{Field} must contain only alphabets and spaces',
        'textonly' => '{Field} must contain only alphabets',
        'alphanum' => '{Field} must contain only alphabets and numbers',
        'alphadash' => '{Field} must contain only alphabets, numbers, dashes and underscores',
        'username' => '{Field} must contain only alphabets, numbers and underscores',
        'number' => '{Field} must contain only numbers',
        'float' => '{Field} must contain only numbers',
        'date' => '{Field} must be a valid date',
        'min' => '{Field} must be at least %s characters long',
        'max' => '{Field} must not exceed %s characters',
        'between' => '{Field} must be between %s and %s characters long',
        'match' => '{Field} must match the %s field',
        'contains' => '{Field} must contain %s',
        'boolean' => '{Field} must be a boolean',
        'in' => '{Field} must be one of the following: %s',
        'notin' => '{Field} must not be one of the following: %s',
        'ip' => '{Field} must be a valid IP address',
        'ipv4' => '{Field} must be a valid IPv4 address',
        'ipv6' => '{Field} must be a valid IPv6 address',
        'url' => '{Field} must be a valid URL',
        'domain' => '{Field} must be a valid domain',
        'creditcard' => '{Field} must be a valid credit card number',
        'phone' => '{Field} must be a valid phone number',
        'uuid' => '{Field} must be a valid UUID',
        'slug' => '{Field} must be a valid slug',
        'json' => '{Field} must be a valid JSON string',
        'regex' => '{Field} must match the pattern %s',
        'array' => '{field} must be an array',
    ];

    /**
     * Validate a single rule
     *
     * @param string $rule The rule to validate against
     * @param mixed $value The value to validate
     * @param mixed $param The rule parameter
     *
     * @return bool
     */
    public static function test(string $rule, $value, $param = null, $field = null): bool
    {
        $rule = strtolower($rule);

        if (strpos($rule, '(') !== false) {
            $ruleData = explode('(', $rule);
            $rule = $ruleData[0];

            if (in_array($rule, static::$specialRules)) {
                $params = array_filter(explode('|', str_replace(['(', ')'], '', $ruleData[1])));

                if ($rule === 'array') {
                    if (!is_array($value)) {
                        return false;
                    }

                    $isValid = true;

                    if (count($params) > 0) {
                        foreach ($params as $paramValue) {
                            foreach ($value as $valueArrayItem) {
                                if (!static::test($paramValue, $valueArrayItem)) {
                                    $isValid = false;
                                }
                            }
                        }
                    }

                    return $isValid;
                }
            }
        }

        if (!isset(static::$rules[$rule])) {
            throw new \Exception("Rule $rule does not exist");
        }

        $param = is_string($param) ? trim($param, '()') : $param;
        $param = eval("return $param;");

        if (is_callable(static::$rules[$rule])) {
            return call_user_func(static::$rules[$rule], $value, $param, $field);
        }

        if (!is_array($param)) {
            $param = [$param];
        }

        return filter_var(
            preg_match(sprintf(static::$rules[$rule], ...$param), "$value"),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Validate form data
     *
     * @param array $data The data to validate
     * @param array $rules The rules to validate against
     *
     * @return false|array Returns false if validation fails, otherwise returns the validated data
     */
    public static function validate(array $data, array $rules)
    {
        $output = $data;

        foreach ($rules as $field => $userRules) {
            if (is_string($userRules)) {
                if (strpos($userRules, '(') !== false) {
                    $pattern = '/(array\([^)]+\))\|(\w+)/';

                    if (preg_match($pattern, $userRules, $matches)) {
                        $userRules = [$matches[1], ...explode('|', strtolower($matches[2]))];
                    } else {
                        $userRules = explode('|', strtolower($userRules));
                    }
                } else {
                    $userRules = explode('|', strtolower($userRules));
                }
            }

            if (in_array('optional', $userRules)) {
                $userRules = array_filter($userRules, function ($rule) {
                    return $rule !== 'optional';
                });

                if (!isset($data[$field])) {
                    continue;
                }
            }

            foreach ($userRules as $rule) {
                if (empty($rule)) {
                    continue;
                }

                $rule = explode(':', $rule);
                $rule[0] = trim($rule[0]);

                if (count($rule) > 1) {
                    $rule[1] = trim($rule[1]);
                }

                $value = Form::getDotNotatedValue($data, $field);

                if (!static::test($rule[0], $value, $rule[1] ?? null, $field)) {
                    $params = is_string($rule[1] ?? null) ? trim($rule[1], '()') : ($rule[1] ?? null);
                    $params = eval("return $params;");

                    if (!is_array($params)) {
                        $params = [$params];
                    }

                    $errorMessage = str_replace(
                        ['{field}', '{Field}', '{value}'],
                        [$field, ucfirst($field), is_array($value) ? json_encode($value) : $value],
                        static::$messages[$rule[0]] ?? static::$messages[explode('(', $rule[0])[0] ?? 'any'] ?? '{Field} is invalid!'
                    );

                    static::addError($field, sprintf($errorMessage, ...$params));

                    $output = false;
                }
            }
        }

        return $output;
    }

    /**
     * Get the value from a nested array by key.
     *
     * @param array $array The array to search in.
     * @param string $key The key to search for.
     * @return mixed|null The value if found, null otherwise.
     */
    public static function getDotNotatedValue($array, $key)
    {
        $keys = explode('.', $key);
        foreach ($keys as $k) {
            if (!isset($array[$k])) {
                return null;
            }
            $array = $array[$k];
        }

        return $array;
    }

    /**
     * Add custom validation rule
     * @param string $name The name of the rule
     * @param string|callable $handler The rule handler
     * @param string|null $message The error message
     */
    public static function addRule(string $name, $handler, ?string $message = null)
    {
        static::$rules[strtolower($name)] = $handler;
        static::$messages[strtolower($name)] = $message ?? "%s is invalid!";
    }

    /**
     * Alias for addRule
     * @param string $name The name of the rule
     * @param string|callable $handler The rule handler
     * @param string|null $message The error message
     */
    public static function rule(string $name, $handler, ?string $message = null)
    {
        static::addRule($name, $handler, $message);
    }

    /**
     * Add validation error message
     * @param string|array $field The field to add the message to
     * @param string|null $message The error message if $field is a string
     */
    public static function addMessage($field, ?string $message = null)
    {
        if (is_array($field)) {
            foreach ($field as $key => $value) {
                static::$messages[$key] = $value;
            }

            return;
        }

        if (!$message) {
            throw new \Exception('Message cannot be empty');
        }

        static::$messages[$field] = $message;
    }

    /**
     * Directly 'submit' a form without having to work with any mark-up
     */
    public static function submit(string $method, string $action, array $fields)
    {
        $form_fields = '';

        foreach ($fields as $key => $value) {
            $form_fields = $form_fields . "<input type=\"hidden\" name=\"$key\" value=" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . ">";
        }

        echo "
			<form action=\"$action\" method=\"$method\" id=\"67yeg76tug216tdg267tgd21tuygu\">$form_fields</form>
			<script>document.getElementById(\"67yeg76tug216tdg267tgd21tuygu\").submit();</script>
		";
    }

    public static function isEmail($value): bool
    {
        return !!filter_var($value, 274);
    }

    /**
     * Alias for addMessage
     * @param string|array $field The field to add the message to
     * @param string|null $message The error message if $field is a string
     */
    public static function message($field, ?string $message = null)
    {
        static::addMessage($field, $message);
    }

    /**
     * Add validation error
     * @param string $field The field that has an error
     * @param string $error The error message
     */
    public static function addError(string $field, string $error)
    {
        if (!isset(static::$errors[$field])) {
            static::$errors[$field] = [];
        }

        array_push(static::$errors[$field], $error);
    }

    /**
     * Get a list of all supported rules.
     * @return array
     */
    public static function supportedRules(): array
    {
        return array_keys(static::$rules);
    }

    /**
     * Get validation errors
     * @return array
     */
    public static function errors(): array
    {
        return static::$errors;
    }
}
