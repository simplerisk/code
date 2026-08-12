<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Container;

use SimpleSAML\XML\Assert\Assert;

/**
 * Class \SimpleSAML\XML\Container\TestContainerSingleton
 */
final class TestContainerSingleton
{
    protected static AbstractTestContainer $testContainer;


    /** Set a container to use. */
    public static function setContainer(AbstractTestContainer $testContainer): void
    {
        self::$testContainer = $testContainer;
    }


    public static function getContainer(): AbstractTestContainer
    {
        Assert::notNull(self::$testContainer, 'No container set.');
        return self::$testContainer;
    }
}
