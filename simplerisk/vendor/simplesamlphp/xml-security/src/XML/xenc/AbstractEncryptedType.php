<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc;

use DOMElement;
use SimpleSAML\XMLSchema\Type\AnyURIValue;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\StringValue;
use SimpleSAML\XMLSecurity\XML\ds\KeyInfo;

use function strval;

/**
 * Abstract class representing encrypted data.
 *
 * @package simplesamlphp/xml-security
 */
abstract class AbstractEncryptedType extends AbstractXencElement
{
    /**
     * EncryptedData constructor.
     *
     * @param \SimpleSAML\XMLSecurity\XML\xenc\CipherData $cipherData The CipherData object of this EncryptedData.
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id The Id attribute of this object. Optional.
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue|null $type The Type attribute of this object. Optional.
     * @param \SimpleSAML\XMLSchema\Type\StringValue|null $mimeType
     *   The MimeType attribute of this object. Optional.
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue|null $encoding
     *   The Encoding attribute of this object. Optional.
     * @param \SimpleSAML\XMLSecurity\XML\xenc\EncryptionMethod|null $encryptionMethod
     *   The EncryptionMethod object of this EncryptedData. Optional.
     * @param \SimpleSAML\XMLSecurity\XML\ds\KeyInfo|null $keyInfo The KeyInfo object of this EncryptedData. Optional.
     */
    public function __construct(
        protected CipherData $cipherData,
        protected ?IDValue $id = null,
        protected ?AnyURIValue $type = null,
        protected ?StringValue $mimeType = null,
        protected ?AnyURIValue $encoding = null,
        protected ?EncryptionMethod $encryptionMethod = null,
        protected ?KeyInfo $keyInfo = null,
    ) {
    }


    /**
     * Get the CipherData object.
     *
     * @return \SimpleSAML\XMLSecurity\XML\xenc\CipherData
     */
    public function getCipherData(): CipherData
    {
        return $this->cipherData;
    }


    /**
     * Get the value of the Encoding attribute.
     *
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue|null
     */
    public function getEncoding(): ?AnyURIValue
    {
        return $this->encoding;
    }


    /**
     * Get the EncryptionMethod object.
     *
     * @return \SimpleSAML\XMLSecurity\XML\xenc\EncryptionMethod|null
     */
    public function getEncryptionMethod(): ?EncryptionMethod
    {
        return $this->encryptionMethod;
    }


    /**
     * Get the value of the Id attribute.
     *
     * @return \SimpleSAML\XMLSchema\Type\IDValue
     */
    public function getID(): ?IDValue
    {
        return $this->id;
    }


    /**
     * Get the KeyInfo object.
     *
     * @return \SimpleSAML\XMLSecurity\XML\ds\KeyInfo|null
     */
    public function getKeyInfo(): ?KeyInfo
    {
        return $this->keyInfo;
    }


    /**
     * Get the value of the MimeType attribute.
     *
     * @return \SimpleSAML\XMLSchema\Type\StringValue
     */
    public function getMimeType(): ?StringValue
    {
        return $this->mimeType;
    }


    /**
     * Get the value of the Type attribute.
     *
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue|null
     */
    public function getType(): ?AnyURIValue
    {
        return $this->type;
    }


    /**
     * @inheritDoc
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);

        $id = $this->getId();
        if ($id !== null) {
            $e->setAttribute('Id', strval($id));
        }

        $type = $this->getType();
        if ($type !== null) {
            $e->setAttribute('Type', strval($type));
        }

        $mimeType = $this->getMimeType();
        if ($mimeType !== null) {
            $e->setAttribute('MimeType', strval($mimeType));
        }

        $encoding = $this->getEncoding();
        if ($encoding !== null) {
            $e->setAttribute('Encoding', strval($encoding));
        }

        $this->getEncryptionMethod()?->toXML($e);
        $this->getKeyInfo()?->toXML($e);
        $this->getCipherData()->toXML($e);

        return $e;
    }
}
