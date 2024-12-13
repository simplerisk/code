<?php

declare(strict_types=1);

namespace SimpleSAML\XML\TestUtils;

use DOMDocument;
use Exception;
use LibXMLError; // Officially spelled with a lower-case `l`, but that breaks composer-require-checker
use PHPUnit\Framework\Attributes\Depends;
use SimpleSAML\Assert\Assert;
use SimpleSAML\XML\Exception\SchemaViolationException;
use XMLReader;

use function array_unique;
use function class_exists;
use function implode;
use function libxml_get_last_error;
use function libxml_use_internal_errors;
use function trim;

/**
 * Test for AbstractElement classes to perform schema validation tests.
 *
 * @package simplesamlphp\xml-common
 */
trait SchemaValidationTestTrait
{
    /** @var class-string */
    protected static string $testedClass;

    /** @var string */
    protected static string $schemaFile;

    /** @var \DOMDocument */
    protected static DOMDocument $xmlRepresentation;


    /**
     * Test schema validation.
     */
    #[Depends('testSerialization')]
    public function testSchemaValidation(): void
    {
        if (!class_exists(self::$testedClass)) {
            $this->markTestSkipped(
                'Unable to run ' . self::class . '::testSchemaValidation(). Please set ' . self::class
                . ':$testedClass to a class-string representing the XML-class being tested',
            );
        } elseif (empty(self::$schemaFile)) {
            $this->markTestSkipped(
                'Unable to run ' . self::class . '::testSchemaValidation(). Please set ' . self::class
                . ':$schema to point to a schema file',
            );
        } elseif (empty(self::$xmlRepresentation)) {
            $this->markTestSkipped(
                'Unable to run ' . self::class . '::testSchemaValidation(). Please set ' . self::class
                . ':$xmlRepresentation to a DOMDocument representing the XML-class being tested',
            );
        } else {
            $predoc = XMLReader::XML(self::$xmlRepresentation->saveXML());
            Assert::notFalse($predoc);

            $pre = $this->validateDocument($predoc);
            $this->assertTrue($pre);

            $class = self::$testedClass::fromXML(self::$xmlRepresentation->documentElement);
            $serializedClass = $class->toXML();

            $postdoc = XMLReader::XML($serializedClass->ownerDocument->saveXML());
            Assert::notFalse($postdoc);
            $post = $this->validateDocument($postdoc);
            $this->assertTrue($post);
        }
    }


    /**
     * @param \XMLReader $doc
     * @return boolean
     */
    private function validateDocument(XMLReader $xmlReader): bool
    {
        libxml_use_internal_errors(true);

        try {
            $xmlReader->setSchema(self::$schemaFile);
        } catch (Exception) {
            $err = libxml_get_last_error();
            throw new SchemaViolationException(trim($err->message) . ' on line ' . $err->line);
        }

        $msgs = [];
        while ($xmlReader->read()) {
            if (!$xmlReader->isValid()) {
                $err = libxml_get_last_error();
                if ($err instanceof LibXMLError) {
                    $msgs[] = trim($err->message) . ' on line ' . $err->line;
                }
            }
        }

        if ($msgs) {
            throw new SchemaViolationException(sprintf(
                "XML schema validation errors:\n - %s",
                implode("\n - ", array_unique($msgs)),
            ));
        }

        return true;
    }


    abstract public function testSerialization(): void;
}
