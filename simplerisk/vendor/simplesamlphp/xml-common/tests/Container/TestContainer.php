<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Container;

use SimpleSAML\XML\Container\AbstractTestContainer;
use SimpleSAML\XML\Container\XMLElementsTrait;
use SimpleSAML\XML\Container\XMLSchemaElementsTrait;

/**
 * Class \SimpleSAML\Test\Container\TestContainer
 */
class TestContainer extends AbstractTestContainer
{
    use XMLElementsTrait;
    use XMLSchemaElementsTrait;
}
