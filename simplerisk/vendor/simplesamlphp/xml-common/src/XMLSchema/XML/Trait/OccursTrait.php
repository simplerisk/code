<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML\Trait;

/**
 * Trait grouping common functionality for elements that represent the occurs group.
 *
 * @package simplesamlphp/xml-common
 */
trait OccursTrait
{
    use MaxOccursTrait;
    use MinOccursTrait;
}
