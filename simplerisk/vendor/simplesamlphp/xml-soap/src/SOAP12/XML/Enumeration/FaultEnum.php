<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP12\XML;

/**
 * The fault codes defined by the specification
 *
 * @package simplesamlphp/xml-soap
 */
enum FaultEnum: string
{
    case VERSION_MISMATCH = 'VersionMismatch';
    case MUST_UNDERSTAND = 'MustUnderstand';
    case SENDER = 'Sender';
    case RESPONDER = 'Responder';
    case DATA_ENCODING_UNKNOWN = 'DataEncodingUnknown';
}
