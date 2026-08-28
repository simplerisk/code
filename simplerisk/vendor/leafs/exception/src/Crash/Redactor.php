<?php

namespace Leaf\Crash;

/**
 * Strips secrets out of crash data before it ever reaches a Report.
 *
 * Redaction happens at report construction, not at render time — so no
 * renderer or reporter (including remote ones) can leak a value that
 * was already masked.
 */
class Redactor
{
    public const MASK = '••••••••';

    /** @var string[] Case-insensitive fragments that mark a key as sensitive */
    protected array $patterns = [
        'password',
        'passwd',
        'secret',
        'token',
        'api_key',
        'apikey',
        'auth',
        'cookie',
        'session',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
        'private',
        'signature',
        'dsn',
    ];

    /**
     * @param string[] $extraPatterns Additional key fragments to mask
     */
    public function __construct(array $extraPatterns = [])
    {
        $this->patterns = array_merge($this->patterns, array_map('strtolower', $extraPatterns));
    }

    /**
     * Recursively mask sensitive keys in an array.
     *
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function redact(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $clean[$key] = self::MASK;

                continue;
            }

            $clean[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $clean;
    }

    public function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->patterns as $pattern) {
            if (strpos($key, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
