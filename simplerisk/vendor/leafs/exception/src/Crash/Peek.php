<?php

namespace Leaf\Crash;

/**
 * Safe, bounded variable export for dump()-style peeks.
 *
 * Values attached to a capture are exported with hard depth and size
 * limits so a peek can never balloon a report, recurse forever, or
 * serialize something huge. Secrets are masked by the Redactor after
 * export like all other context.
 */
class Peek
{
    public const MAX_DEPTH = 8;
    public const MAX_ITEMS = 25;
    public const MAX_STRING = 500;

    /** Overall node budget per export — bounds total size, not just depth */
    public const MAX_NODES = 400;

    /**
     * @param mixed $value
     * @param int|null $budget Remaining node budget (managed internally)
     * @return mixed A json-safe, size-bounded representation
     */
    public static function export($value, int $depth = self::MAX_DEPTH, ?int &$budget = null)
    {
        $budget ??= self::MAX_NODES;

        if (--$budget < 0) {
            return '(truncated)';
        }
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return mb_strlen($value) > self::MAX_STRING
                ? mb_substr($value, 0, self::MAX_STRING) . '… (' . mb_strlen($value) . ' chars)'
                : $value;
        }

        if (is_array($value)) {
            if ($depth <= 0) {
                return '[array:' . count($value) . ']';
            }

            $exported = [];
            $count = 0;

            foreach ($value as $key => $item) {
                if (++$count > self::MAX_ITEMS) {
                    $exported['…'] = '(' . (count($value) - self::MAX_ITEMS) . ' more)';

                    break;
                }

                $exported[$key] = static::export($item, $depth - 1, $budget);
            }

            return $exported;
        }

        if ($value instanceof \Closure) {
            return '(closure)';
        }

        if (is_object($value)) {
            if ($depth <= 0) {
                return '(object ' . get_class($value) . ')';
            }

            $exported = ['__class' => get_class($value)];

            foreach (get_object_vars($value) as $key => $item) {
                if (count($exported) > self::MAX_ITEMS) {
                    $exported['…'] = '(more)';

                    break;
                }

                $exported[$key] = static::export($item, $depth - 1, $budget);
            }

            return $exported;
        }

        if (is_resource($value)) {
            return '(resource:' . get_resource_type($value) . ')';
        }

        return '(' . gettype($value) . ')';
    }
}
