<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace SimpleRisk\DocumentHandlers;

require_once(realpath(__DIR__ . '/CsvHandler.php')); // CSV Wrapper
require_once(realpath(__DIR__ . '/PdfHandler.php')); // PDF Wrapper
require_once(realpath(__DIR__ . '/SpreadsheetHandler.php')); // PhpSpreadsheet wrapper
require_once(realpath(__DIR__ . '/WordHandler.php')); // Word wrapper

use SimpleRisk\DocumentHandlers\DocumentTextExtractor;
use SimpleRisk\DocumentHandlers\CsvHandler;
use SimpleRisk\DocumentHandlers\PdfHandler;
use SimpleRisk\DocumentHandlers\SpreadsheetHandler;
use SimpleRisk\DocumentHandlers\TextHandler;
use SimpleRisk\DocumentHandlers\WordHandler;

class DocumentTextExtractor
{
    /**
     * Extract text from any supported document type.
     *
     * @param string $content
     * @param string|null $mimeType
     * @param string|null $fileName
     * @param array $options Optional flags (e.g., ['preserveLayout' => true] for PDFs)
     * @return string
     * @throws \RuntimeException
     */
    public static function extractText(
        string $content,
        ?string $mimeType = null,
        ?string $fileName = null,
        array $options = []
    ): string {
        $docType = self::determineDocumentType($mimeType, $fileName, $content);

        switch ($docType) {
            case 'csv':
                return CsvHandler::extractTextFromCsv($content);
            case 'docx':
                return WordHandler::extractTextFromDocx($content);

            case 'txt':
                return TextHandler::extractTextFromPlainText($content);

            case 'pdf':
                $preserveLayout = $options['preserveLayout'] ?? false;
                $chunked = $options['chunkedProcessing'] ?? false;
                $isBase64 = $options['isBase64'] ?? false;

                return PdfHandler::extractTextFromPdf(
                    $content,
                    $isBase64,
                    $preserveLayout,
                    $chunked
                );

            case 'xlsx':
            case 'xls':
                return SpreadsheetHandler::extractTextFromSpreadsheet($content);

            default:
                throw new \RuntimeException("Unsupported document. Unable to extract text. [Mime Type = {$mimeType}, File Name = [$fileName}]");
        }
    }

    /**
     * Determine the document type using MIME, extension, or magic headers.
     *
     * @param string|null $mimeType
     * @param string|null $fileName
     * @param string $content
     * @return string
     */
    private static function determineDocumentType(?string $mimeType, ?string $fileName, string $content): string
    {
        // 1. MIME type detection
        if ($mimeType) {
            $map = [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/pdf' => 'pdf',
                'text/plain' => 'txt',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.ms-excel' => 'xls', // sometimes XLS or CSV
                'text/csv' => 'csv',
                'application/csv' => 'csv',
            ];

            if (isset($map[$mimeType])) return $map[$mimeType];
        }

        // 2. File extension detection
        if ($fileName) {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $validExtensions = ['csv', 'docx', 'pdf', 'txt', 'xlsx', 'xls'];
            if (in_array($ext, $validExtensions)) return $ext;
        }

        // 3. Magic header / content detection
        if (strncmp($content, "%PDF", 4) === 0) return 'pdf';
        if (substr($content, 0, 2) === "PK") {
            if (strpos($content, 'word/document.xml') !== false) return 'docx';
            if (strpos($content, 'xl/worksheets') !== false) return 'xlsx';
        }

        // 4. Heuristic for CSV or plain text
        if (strpos($content, "\0") === false) {
            $lines = preg_split("/\r\n|\n|\r/", $content);

            if (count($lines) > 1) {
                $firstLine = $lines[0];

                // Check for common CSV separators
                if (strpos($firstLine, ',') !== false || strpos($firstLine, ';') !== false || strpos($firstLine, "\t") !== false) {
                    return 'csv';
                }
            }

            return 'txt';
        }

        return 'unknown';
    }
}

/**
 * Simple text handler.
 */
class TextHandler
{
    public static function extractTextFromPlainText(string $content): string
    {
        return $content;
    }
}

?>