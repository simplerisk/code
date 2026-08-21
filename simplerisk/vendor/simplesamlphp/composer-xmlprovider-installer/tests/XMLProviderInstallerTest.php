<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Composer;

use Composer\IO\NullIO;
use Composer\Package\PackageInterface;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Config;
use Composer\PartialComposer;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\InstalledRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositoryManager;
use Composer\Util\HttpDownloader;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Composer\XMLProvider\XMLProviderInstaller;

/**
 */
#[CoversClass(XMLProviderInstaller::class)]
class XMLProviderInstallerTest extends TestCase
{
    /** @var \SimpleSAML\Composer\XMLProvider\XMLProviderInstaller */
    private XMLProviderInstaller $xmlProviderInstaller;


    /**
     */
    public function setUp(): void
    {
        $partialComposer = new PartialComposer();
        $partialComposer->setConfig(new Config());
        $partialComposer->setRepositoryManager(
            new RepositoryManager(new NullIO(), new Config(), new HttpDownloader(new NullIO(), new Config()))
        );
        $partialComposer->getRepositoryManager()->setLocalRepository(new InstalledArrayRepository());
        $partialComposer->setPackage(new RootPackage('simplesamlphp/simplesamlphp', '0.0.1', 'v0.0.1'));

        $this->xmlProviderInstaller = new XMLProviderInstaller(new NullIO(), $partialComposer);
    }


    #[DataProvider('packageTypeProvider')]
    public function testSupports(bool $shouldPass, string $packageType): void
    {
        $this->assertEquals(
            $this->xmlProviderInstaller->supports($packageType),
            $shouldPass
        );
    }


    /**
     * @return array
     */
    public static function packageTypeProvider(): array
    {
        return [
            'simplesamlphp-xmlprovider' => [true, 'simplesamlphp-xmlprovider'],
            'simplesamlphp-anyother' => [false, 'simplesamlphp-anyother'],
        ];
    }
}
