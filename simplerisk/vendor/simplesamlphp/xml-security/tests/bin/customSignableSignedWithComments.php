#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once(dirname(__FILE__, 3) . '/vendor/autoload.php');

use SimpleSAML\XML\DOMDocumentFactory;
use SimpleSAML\XMLSchema\Type\Base64BinaryValue;
use SimpleSAML\XMLSecurity\Alg\Signature\SignatureAlgorithmFactory;
use SimpleSAML\XMLSecurity\Constants as C;
use SimpleSAML\XMLSecurity\Test\XML\CustomSignable;
use SimpleSAML\XMLSecurity\TestUtils\PEMCertificatesMock;
use SimpleSAML\XMLSecurity\XML\ds\KeyInfo;
use SimpleSAML\XMLSecurity\XML\ds\X509Certificate;
use SimpleSAML\XMLSecurity\XML\ds\X509Data;

$document = DOMDocumentFactory::fromFile(dirname(__FILE__, 2) . '/resources/xml/custom_CustomSignable.xml');

$chunks = $document->documentElement->getElementsByTagNameNS("urn:x-simplesamlphp:namespace", "Chunk");
$chunk = $chunks->item(0);
$chunkContent = $chunk->firstChild;
$chunk->insertBefore(new DOMComment('comment'), $chunkContent);
$chunk->appendChild(new DOMComment('comment'));

$signer = (new SignatureAlgorithmFactory())->getAlgorithm(
    C::SIG_RSA_SHA256,
    PEMCertificatesMock::getPrivateKey(PEMCertificatesMock::SELFSIGNED_PRIVATE_KEY),
);

$keyInfo = new KeyInfo([
    new X509Data([
         new X509Certificate(
             Base64BinaryValue::fromString(
                 PEMCertificatesMock::getPlainCertificateContents(
                     PEMCertificatesMock::SELFSIGNED_CERTIFICATE,
                 ),
             ),
         ),
    ]),
]);

$unsignedElement = CustomSignable::fromXML($document->documentElement);
$unsignedElement->sign($signer, C::C14N_EXCLUSIVE_WITH_COMMENTS, $keyInfo);

echo $unsignedElement->toXML()->ownerDocument->saveXML();
