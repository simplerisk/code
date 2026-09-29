<?php

declare(strict_types=1);

return [
    'http://schemas.xmlsoap.org/soap/envelope/' => [
        'Body' => '\SimpleSAML\SOAP11\XML\Body',
        'Envelope' => '\SimpleSAML\SOAP11\XML\Envelope',
        'Fault' => '\SimpleSAML\SOAP11\XML\Fault',
        'Header' => '\SimpleSAML\SOAP11\XML\Header',
    ],
    'http://www.w3.org/2003/05/soap-envelope' => [
        'Body' => '\SimpleSAML\SOAP12\XML\Body',
        'Envelope' => '\SimpleSAML\SOAP12\XML\Envelope',
        'Fault' => '\SimpleSAML\SOAP12\XML\Fault',
        'Header' => '\SimpleSAML\SOAP12\XML\Header',
        'NotUnderstood' => '\SimpleSAML\SOAP12\XML\NotUnderstood',
        'Upgrade' => '\SimpleSAML\SOAP12\XML\Upgrade',
    ],
];
