<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML\Enumeration;

enum WhiteSpaceEnum: string
{
    case Collapse = 'collapse';
    case Preserve = 'preserve';
    case Replace = 'replace';
}
