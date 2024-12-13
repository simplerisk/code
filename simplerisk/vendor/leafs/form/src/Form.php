<?php

declare(strict_types=1);

namespace Leaf;

/**
 * Leaf Form
 * ----
 * Leaf's form validation library
 *
 * @version 3.0.0
 * @since 1.0.0
 */
class Form
{
    /**
     * Validation errors
     */
    protected $errors = [];

    /**
     * Validation rules
     */
    protected $rules = [
        'optional' => '/^.*$/',
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
        'truefalse' => '/^(true|false)$/',
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
     * Validation error messages
     */
    protected $messages = [
        'required' => '{Field} is required',
        'email' => '{Field} must be a valid email address',
        'alpha' => '{Field} must contain only alphabets and spaces',
        'text' => '{Field} must contain only alphabets and spaces',
        'string' => '{Field} must contain only alphabets and spaces',
        'textonly' => '{Field} must contain only alphabets',
        'alphanum' => '{Field} must contain only alphabets and numbers',
        'alphadash' => '{Field} must contain only alphabets, numbers, dashes and underscores',
        'username' => '{Field} must contain only alphabets, numbers and underscores',
        'number' => '{Field} must contain only numbers',
        'numeric' => '{Field} must be numeric',
        'float' => '{Field} must contain only floating point numbers',
        'hardfloat' => '{Field} must contain only floating point numbers',
        'date' => '{Field} must be a valid date',
        'min' => '{Field} must be at least %s characters long',
        'max' => '{Field} must not exceed %s characters',
        'between' => '{Field} must be between %s and %s characters long',
        'match' => '{Field} must match the %s field',
        'matchesvalueof' => '{Field} must match the value of %s',
        'contains' => '{Field} must contain %s',
        'boolean' => '{Field} must be a boolean',
        'truefalse' => '{Field} must be a boolean',
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

    public function __construct()
    {
        $this->rules['array'] = function ($value, $internalRules = null, $fieldName = null) {
            $isArray = is_array($value);

            if ($isArray) {
                foreach ($value as $valueItem) {
                    if ($internalRules) {
                        if (!$this->test($internalRules, $valueItem, $fieldName)) {
                            // we're tricking leaf into not adding the second error message by returning true here
                            // this is because we're already adding the error message in the test method
                            return true;
                        }
                    }
                }
            }

            return $isArray;
        };

        $this->rules['string'] = function ($value) {
            return is_string($value);
        };

        $this->rules['hardfloat'] = function ($value) {
            return is_float($value);
        };

        $this->rules['numeric'] = function ($value) {
            return is_numeric($value);
        };

        $this->rules['in'] = function ($value, $param) {
            return in_array($value, $param);
        };

        $this->rules['matchesvalueof'] = function ($value, $param) {
            return \Leaf\Http\Request::get($param) === $value;
        };
    }

    protected function test($rule, $valueToTest, $fieldName = 'item'): bool
    {
        $expandedErrors = false;

        if (is_string($rule)) {
            $rule = preg_match_all('/[^|<>]+(?:<[^>]+>)?/', $rule, $matches);
            $rule = $matches[0];
        }

        if (in_array('optional', $rule) && empty($valueToTest)) {
            return true;
        }

        if (in_array('expanded', $rule)) {
            $expandedErrors = true;
        }

        foreach ($rule as $currentRule) {
            $param = [];

            $currentRule = strtolower($currentRule);

            if ($currentRule === 'optional') {
                continue;
            }

            if ($currentRule === 'expanded') {
                continue;
            }

            if (preg_match('/^[a-zA-Z]+<(.*(\|.*)*)>$/', $currentRule)) {
                $ruleParts = explode('<', $currentRule);
                $ruleParams = str_replace('>', '', $ruleParts[1]);

                $currentRule = $ruleParts[0];
                $param = $ruleParams;
            }


            if (strpos($currentRule, ':') !== false && strpos($currentRule, '|') === false) {
                $ruleParts = explode(':', $currentRule);

                $currentRule = trim($ruleParts[0]);
                $param = $ruleParts[1] ? trim($ruleParts[1]) : null;
            }

            if (is_string($param) && preg_match('/\[(.*)\]/', $param, $matches) && strpos($param, '|') === false) {
                $param = explode(',', $matches[1]);
            }

            if (!isset($this->rules[$currentRule])) {
                throw new \Exception("Rule $currentRule does not exist");
            }

            if (!$valueToTest) {
                if ($expandedErrors) {
                    $this->addError($fieldName, str_replace(
                        ['{field}', '{Field}', '{value}'],
                        [$fieldName, ucfirst($fieldName), is_array($valueToTest) ? json_encode($valueToTest) : $valueToTest],
                        $this->messages['required'] ?? '{Field} is invalid!'
                    ));
                } else {
                    $this->errors[$fieldName] = str_replace(
                        ['{field}', '{Field}', '{value}'],
                        [$fieldName, ucfirst($fieldName), is_array($valueToTest) ? json_encode($valueToTest) : $valueToTest],
                        $this->messages['required'] ?? '{Field} is invalid!'
                    );
                }

                return false;
            }

            if (is_callable($this->rules[$currentRule])) {
                if (!call_user_func($this->rules[$currentRule], $valueToTest, $param, $fieldName)) {
                    if (empty($param)) {
                        $param = ['Item'];
                    }

                    if (!is_array($param)) {
                        $param = [$param];
                    }

                    if ($expandedErrors) {
                        $this->addError($fieldName, sprintf(
                            str_replace(
                                ['{field}', '{Field}', '{value}'],
                                [$fieldName, ucfirst($fieldName), is_array($valueToTest) ? json_encode($valueToTest) : $valueToTest],
                                $this->messages[$currentRule] ?? '{Field} is invalid!'
                            ),
                            ...$param,
                        ));
                    } else {
                        $this->errors[$fieldName] = sprintf(
                            str_replace(
                                ['{field}', '{Field}', '{value}'],
                                [$fieldName, ucfirst($fieldName), is_array($valueToTest) ? json_encode($valueToTest) : $valueToTest],
                                $this->messages[$currentRule] ?? '{Field} is invalid!'
                            ),
                            ...$param,
                        );
                    }
                }

                continue;
            }

            if (!is_array($param)) {
                $param = [$param];
            }

            if (is_float($valueToTest)) {
                $valueToTest = json_encode($valueToTest, JSON_PRESERVE_ZERO_FRACTION);
            }

            if (
                !filter_var(
                    preg_match(sprintf($this->rules[$currentRule], ...$param), (string) $valueToTest),
                    FILTER_VALIDATE_BOOLEAN
                )
            ) {
                if ($expandedErrors) {
                    $this->addError($fieldName, sprintf(
                        str_replace(
                            ['{field}', '{Field}', '{value}'],
                            [$fieldName, ucfirst($fieldName), is_array($valueToTest) ? json_encode($valueToTest) : $valueToTest],
                            $this->messages[$currentRule] ?? '{Field} is invalid!'
                        ),
                        ...$param,
                    ));
                } else {
                    $this->errors[$fieldName] = sprintf(
                        str_replace(
                            ['{field}', '{Field}', '{value}'],
                            [$fieldName, ucfirst($fieldName), is_array($valueToTest) ? json_encode($valueToTest) : $valueToTest],
                            $this->messages[$currentRule] ?? '{Field} is invalid!'
                        ),
                        ...$param,
                    );
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Validate a single rule
     *
     * @param string|array $rule The rule(s) to validate against
     * @param mixed $valueToTest The value to validate
     * @param mixed $fieldName The rule parameter
     *
     * @return bool
     */
    public function validateRule($rule, $valueToTest, $fieldName = 'item'): bool
    {
        $this->errors = [];

        return $this->test($rule, $valueToTest, $fieldName);
    }

    /**
     * Validate form data
     *
     * @param array $dataSource The data to validate
     * @param array $validationSet The rules to validate against
     *
     * @return false|array Returns false if validation fails, otherwise returns the validated data
     */
    public function validate(array $dataSource, array $validationSet)
    {
        // clear previous errors
        $this->errors = [];

        $output = [];

        foreach ($validationSet as $itemToValidate => $userRules) {
            if (empty($userRules)) {
                $output[$itemToValidate] = Anchor::deepGetDot($dataSource, $itemToValidate);

                continue;
            }

            $endsWithWildcard = substr($itemToValidate, -1) === '*';
            $itemToValidate = $endsWithWildcard ? substr($itemToValidate, 0, -1) : $itemToValidate;

            $value = Anchor::deepGetDot($dataSource, $itemToValidate);

            if (!$this->test($userRules, $value, $itemToValidate)) {
                $output = false;
            } elseif ($output !== false && !$endsWithWildcard) {
                if (
                    (is_array($userRules) && in_array('optional', $userRules))
                    || (is_string($userRules) && strpos($userRules, 'optional') !== false)
                ) {
                    if (Anchor::deepGetDot($dataSource, $itemToValidate) !== null) {
                        $output = Anchor::deepSetDot($output, $itemToValidate, $value);
                    }

                    continue;
                }

                $output = Anchor::deepSetDot($output, $itemToValidate, $value);
            }
        }

        return $output;
    }

    /**
     * Add custom validation rule
     * @param string $name The name of the rule
     * @param string|callable $handler The rule handler
     * @param string|null $message The error message
     */
    public function addRule(string $name, $handler, ?string $message = null)
    {
        $this->rules[strtolower($name)] = $handler;
        $this->messages[strtolower($name)] = $message ?? '%s is invalid!';
    }

    /**
     * Alias for addRule
     * @param string $name The name of the rule
     * @param string|callable $handler The rule handler
     * @param string|null $message The error message
     */
    public function rule(string $name, $handler, ?string $message = null)
    {
        $this->addRule($name, $handler, $message);
    }

    /**
     * Add validation error message
     * @param string|array $field The field to add the message to
     * @param string|null $message The error message if $field is a string
     */
    public function addErrorMessage($field, ?string $message = null)
    {
        return $this->addMessage($field, $message);
    }

    /**
     * Add validation error message
     * @param string|array $field The field to add the message to
     * @param string|null $message The error message if $field is a string
     */
    public function addMessage($field, ?string $message = null)
    {
        if (is_array($field)) {
            foreach ($field as $key => $value) {
                $this->messages[$key] = $value;
            }

            return;
        }

        if (!$message) {
            throw new \Exception('Message cannot be empty');
        }

        $this->messages[$field] = $message;
    }

    /**
     * Directly 'submit' a form without having to work with any mark-up
     */
    public function submit(string $method, string $action, array $fields)
    {
        $form_fields = '';

        foreach ($fields as $key => $value) {
            $form_fields = $form_fields . "<input type=\"hidden\" name=\"$key\" value=" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '>';
        }

        echo "
			<form action=\"$action\" method=\"$method\" id=\"67yeg76tug216tdg267tgd21tuygu\">$form_fields</form>
			<script>document.getElementById(\"67yeg76tug216tdg267tgd21tuygu\").submit();</script>
		";
    }

    public function isEmail($value): bool
    {
        return !!filter_var($value, 274);
    }

    /**
     * Alias for addMessage
     * @param string|array $field The field to add the message to
     * @param string|null $message The error message if $field is a string
     */
    public function message($field, ?string $message = null)
    {
        $this->addMessage($field, $message);
    }

    /**
     * Add validation error
     * @param string $field The field that has an error
     * @param string $error The error message
     */
    public function addError(string $field, string $error)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        array_push($this->errors[$field], $error);
    }

    /**
     * Get a list of all supported rules.
     * @return array
     */
    public function supportedRules(): array
    {
        return array_keys($this->rules);
    }

    /**
     * Get validation errors
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
