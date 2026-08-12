<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML\Constants;

final class NS
{
    public const string ANY = '##any';

    public const string LOCAL = '##local';

    public const string OTHER = '##other';

    public const string TARGETNAMESPACE = '##targetNamespace';


    /** @var string[] */
    public static array $PREDEFINED = [
        self::ANY,
        self::LOCAL,
        self::OTHER,
        self::TARGETNAMESPACE,
    ];
}
