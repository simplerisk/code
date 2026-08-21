<?php

declare(strict_types=1);

namespace SimpleSAML\XML\TestUtils;

use SimpleSAML\XML\Container\AbstractTestContainer;
use SimpleSAML\XML\Container\TestContainerSingleton;

/**
 * Trait for importing the TestContainer into the unit test.
 *
 * @package simplesamlphp\xml-common
 * @phpstan-ignore trait.unused
 */
trait TestContainerTestTrait
{
    protected static AbstractTestContainer $testContainer;


    protected static function instantiateTestContainer(): void
    {
        self::$testContainer = TestContainerSingleton::getContainer();
    }
}
