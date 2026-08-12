<?php

declare(strict_types=1);

namespace SimpleSAML\XPath;

/**
 * Various XML constants.
 *
 * @package simplesamlphp/xml-common
 */
class Constants extends \SimpleSAML\XML\Constants
{
    /**
     * The namespace for the XML Path Language 1.0
     */
    public const string XPATH10_URI = 'http://www.w3.org/TR/1999/REC-xpath-19991116';

    /** @var array<string> */
    public const array ALL_AXES = [
        'ancestor',
        'ancestor-or-self',
        'attribute',
        'child',
        'descendant',
        'descendant-or-self',
        'following',
        'following-sibling',
        'namespace',
        'parent',
        'preceding',
        'preceding-sibling',
        'self',
    ];

    /** @var array<string> */
    public const array DEFAULT_ALLOWED_AXES = [
        'ancestor',
        'ancestor-or-self',
        'attribute',
        'child',
        'descendant',
        'descendant-or-self',
        'following',
        'following-sibling',
        // 'namespace', // By default, we do not allow using the namespace axis
        'parent',
        'preceding',
        'preceding-sibling',
        'self',
    ];

    /** @var array<string> */
    public const array ALL_FUNCTIONS = [
        'boolean',
        'ceiling',
        'concat',
        'contains',
        'count',
        'false',
        'floor',
        'id',
        'lang',
        'last',
        'local-name',
        'name',
        'namespace-uri',
        'normalize-space',
        'not',
        'number',
        'position',
        'round',
        'starts-with',
        'string',
        'string-length',
        'substring',
        'substring-after',
        'substring-before',
        'sum',
        'text',
        'translate',
        'true',
    ];

    /** @var array<string> */
    public const array DEFAULT_ALLOWED_FUNCTIONS = [
        // 'boolean',
        // 'ceiling',
        // 'concat',
        // 'contains',
        // 'count',
        // 'false',
        // 'floor',
        // 'id',
        // 'lang',
        // 'last',
        // 'local-name',
        // 'name',
        // 'namespace-uri',
        // 'normalize-space',
        'not',
        // 'number',
        // 'position',
        // 'round',
        // 'starts-with',
        // 'string',
        // 'string-length',
        // 'substring',
        // 'substring-after',
        // 'substring-before',
        // 'sum',
        // 'text',
        // 'translate',
        // 'true',
    ];

    public const int XPATH_FILTER_MAX_LENGTH = 100;
}
