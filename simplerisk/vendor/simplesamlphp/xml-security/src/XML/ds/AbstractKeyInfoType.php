<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use DOMElement;
use SimpleSAML\XML\ExtendableElementTrait;
use SimpleSAML\XML\SerializableElementInterface;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;
use SimpleSAML\XMLSecurity\Assert\Assert;
use SimpleSAML\XMLSecurity\Constants as C;
use SimpleSAML\XMLSecurity\Exception\InvalidArgumentException;
use SimpleSAML\XMLSecurity\XML\dsig11\AbstractDsig11Element;
use SimpleSAML\XMLSecurity\XML\dsig11\DEREncodedKeyValue;

use function strval;

/**
 * Abstract class representing the KeyInfoType.
 *
 * @package simplesamlphp/xml-security
 */
abstract class AbstractKeyInfoType extends AbstractDsElement
{
    use ExtendableElementTrait;


    public const string XS_ANY_ELT_NAMESPACE = NS::OTHER;


    /**
     * Initialize a KeyInfo element.
     *
     * @param (
     *     \SimpleSAML\XMLSecurity\XML\ds\KeyName|
     *     \SimpleSAML\XMLSecurity\XML\ds\KeyValue|
     *     \SimpleSAML\XMLSecurity\XML\ds\RetrievalMethod|
     *     \SimpleSAML\XMLSecurity\XML\ds\X509Data|
     *     \SimpleSAML\XMLSecurity\XML\ds\PGPData|
     *     \SimpleSAML\XMLSecurity\XML\ds\SPKIData|
     *     \SimpleSAML\XMLSecurity\XML\ds\MgmtData|
     *     \SimpleSAML\XMLSecurity\XML\dsig11\DEREncodedKeyValue|
     *     \SimpleSAML\XML\SerializableElementInterface
     * )[] $info
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $Id
     */
    final public function __construct(
        protected array $info,
        protected ?IDValue $Id = null,
    ) {
        Assert::notEmpty(
            $info,
            sprintf(
                '%s:%s cannot be empty',
                static::getNamespacePrefix(),
                static::getLocalName(),
            ),
            InvalidArgumentException::class,
        );
        Assert::maxCount($info, C::UNBOUNDED_LIMIT);
        Assert::allIsInstanceOf(
            $info,
            SerializableElementInterface::class,
            InvalidArgumentException::class,
        );

        foreach ($info as $item) {
            if ($item instanceof AbstractDsElement) {
                Assert::isInstanceOfAny(
                    $item,
                    [
                        KeyName::class,
                        KeyValue::class,
                        RetrievalMethod::class,
                        X509Data::class,
                        PGPData::class,
                        SPKIData::class,
                        MgmtData::class,
                    ],
                    SchemaViolationException::class,
                );
            } elseif ($item instanceof AbstractDsig11Element) {
                Assert::isInstanceOfAny(
                    $item,
                    [
                        DEREncodedKeyValue::class,
                    ],
                    SchemaViolationException::class,
                );
            }
        }
    }


    /**
     * Collect the value of the Id-property
     *
     * @return \SimpleSAML\XMLSchema\Type\IDValue|null
     */
    public function getId(): ?IDValue
    {
        return $this->Id;
    }


    /**
     * Collect the value of the info-property
     *
     * @return list<\SimpleSAML\XML\SerializableElementInterface>
     */
    public function getInfo(): array
    {
        return $this->info;
    }


    /**
     * Convert this KeyInfo to XML.
     *
     * @param \DOMElement|null $parent The element we should append this KeyInfo to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);

        if ($this->getId() !== null) {
            $e->setAttribute('Id', strval($this->getId()));
        }

        foreach ($this->getInfo() as $elt) {
            $elt->toXML($e);
        }

        return $e;
    }
}
