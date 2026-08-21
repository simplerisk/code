<?php
declare(strict_types = 1);

namespace Gettext\Generator;

use Gettext\Headers;
use Gettext\Translation;
use Gettext\Translations;

final class ArrayGenerator extends Generator
{
    /**
     * @private
     */
    const PRETTY_INDENT = '    ';

    /**
     * @private
     */
    const FORMAT_NORMAL = 'normal';

    /**
     * @private
     */
    const FORMAT_PRETTY = 'pretty';

    /**
     * @private
     */
    const FORMAT_COMPACT = 'compact';

    /**
     * @var bool
     */
    private $includeEmpty;

    /**
     * @var bool
     */
    private $strictTypes;

    /**
     * @var string
     */
    private $format;

    /**
     * Constructs a new ArrayGenerator
     * @param array {
     *   includeEmpty?: bool,                 // Controls whether empty translations should be included (default: false)
     *   strictTypes?: bool,                  // Add declare(strict_types=1) (default: false)
     *   format?: 'normal'|'pretty'|'compact' // How to format the code (default: 'normal')
     *   pretty?: bool,                       // Deprecated: use format instead (default: false)
     * } | null $options
     */
    public function __construct(?array $options = null)
    {
        $this->includeEmpty = (bool) ($options['includeEmpty'] ?? false);
        $this->strictTypes = (bool) ($options['strictTypes'] ?? false);
        if (!isset($options['format'])) {
            $this->format = ($options['pretty'] ?? false) ? self::FORMAT_PRETTY : self::FORMAT_NORMAL;
        } elseif (in_array($options['format'], [self::FORMAT_PRETTY, self::FORMAT_COMPACT], true)) {
            $this->format = $options['format'];
        } else {
            $this->format = self::FORMAT_NORMAL;
        }
    }

    public function generateString(Translations $translations): string
    {
        $array = $this->generateArray($translations);
        $result = '<?php';
        if ($this->strictTypes) {
            switch ($this->format) {
                case self::FORMAT_PRETTY:
                    $result .= "\n\ndeclare(strict_types=1);\n\n";
                    break;
                case self::FORMAT_COMPACT:
                    $result .= ' declare(strict_types=1);';
                    break;
                case self::FORMAT_NORMAL:
                default:
                    $result .= ' declare(strict_types=1); ';
                    break;
            }
        } else {
            $result .= $this->format === self::FORMAT_PRETTY ? "\n\n"  : ' ';
        }
        switch ($this->format) {
            case self::FORMAT_PRETTY:
                $result .= self::format($array, false);
                break;
            case self::FORMAT_COMPACT:
                $result .= self::format($array, true);
                break;
            case self::FORMAT_NORMAL:
            default:
                $result .= 'return ' . var_export($array, true) . ';';
                break;
        }

        return $result;
    }

    public function generateArray(Translations $translations): array
    {
        $pluralForm = $translations->getHeaders()->getPluralForm();
        $pluralSize = is_array($pluralForm) ? ($pluralForm[0] - 1) : null;
        $messages = [];

        foreach ($translations as $translation) {
            if ((!$this->includeEmpty && !$translation->getTranslation()) || $translation->isDisabled()) {
                continue;
            }

            $context = $translation->getContext() ?: '';
            $original = $translation->getOriginal();

            if (!isset($messages[$context])) {
                $messages[$context] = [];
            }

            if (self::hasPluralTranslations($translation)) {
                $messages[$context][$original] = $translation->getPluralTranslations($pluralSize);
                array_unshift($messages[$context][$original], $translation->getTranslation());
            } elseif ($pluralSize !== null && $pluralSize > 1 && (string) $translation->getPlural() !== '') {
                $messages[$context][$original] = array_fill(0, $pluralSize, '');
            } else {
                $messages[$context][$original] = (string) $translation->getTranslation();
            }
        }

        return [
            'domain' => $translations->getDomain(),
            'plural-forms' => $translations->getHeaders()->get(Headers::HEADER_PLURAL),
            'messages' => $messages,
        ];
    }

    private static function hasPluralTranslations(Translation $translation): bool
    {
        return implode('', $translation->getPluralTranslations()) !== '';
    }

    private static function format(array &$array, bool $compact): string
    {
        return 'return ' . self::formatArray($array, $compact, 0) . ($compact ? ';' : ";\n");
    }

    private static function formatArray(array &$array, bool $compact, int $depth): string
    {
        if ($array === []) {
            return '[]';
        }
        $result = '[';
        $isList = self::isList($array);
        foreach ($array as $key => $value) {
            if (!$compact) {
                $result .= "\n" . str_repeat(self::PRETTY_INDENT, $depth + 1);
            }
            if (!$isList) {
                $result .= var_export($key, true) . ($compact ? '=>' : ' => ');
            }
            if (is_array($value)) {
                $result .= self::formatArray($value, $compact, $depth + 1);
            } else {
                $result .= self::formatScalar($value);
            }
            $result .= ',';
        }
        if ($compact) {
            $result = substr($result, 0, -1);
        } else {
            $result .= "\n" . str_repeat(self::PRETTY_INDENT, $depth);
        }

        return $result . ']';
    }

    private static function formatScalar($value): string
    {
        return $value === null ? 'null' : var_export($value, true);
    }

    private static function isList(array &$value): bool
    {
        if ($value === []) {
            return true;
        }
        if (function_exists('array_is_list')) {
            return \array_is_list($value);
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
