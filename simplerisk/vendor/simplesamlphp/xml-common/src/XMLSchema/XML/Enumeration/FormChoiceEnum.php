<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML\Enumeration;

enum FormChoiceEnum: string
{
    case Qualified = 'qualified';
    case Unqualified = 'unqualified';
}
