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

/**
 * Exception thrown when a document type is not supported for text extraction
 */
class UnsupportedDocumentException extends \RuntimeException {}

/**
 * Exception thrown when a document exceeds the safe pre-parse size limit for
 * its type. Like an unsupported type, this is a permanent skip — retrying the
 * same oversized file only repeats the blowup.
 */
class DocumentTooLargeException extends \RuntimeException {}

/**
 * Pre-parse size guard for document text extraction.
 *
 * Parsers expand a file far beyond its on-disk size — PhpSpreadsheet inflates
 * an XLSX 10-50x — and, critically, XLSX/DOCX parse zipped XML through
 * libxml/ZipArchive, which allocate C memory OUTSIDE the Zend allocator that
 * PHP's `memory_limit` accounts for. A modest file can therefore grow the
 * worker process to multiple GB and get OS-OOM-killed mid-parse, which
 * `memory_limit` cannot prevent. Refusing an oversized file up front is the
 * cheap first line of defense.
 *
 * NOTE: file size is an imperfect predictor (blowup tracks internal cell /
 * shared-string count, not bytes), so this guard has both false positives and
 * false negatives. The durable safeguard is a memory-bounded extraction
 * subprocess (ulimit -v / cgroup) so a pathological file kills only the child;
 * that is a tracked follow-up. This guard meaningfully reduces the blast radius
 * in the meantime without being a complete solution.
 *
 * Limits are per-type because the expansion factor differs sharply. Only the
 * supported, parse-heavy types are bounded; unrecognized types are left to the
 * UnsupportedDocumentException path.
 */
class DocumentSizeGuard
{
    private const MB = 1024 * 1024;

    /**
     * Per-type maximum input bytes (in MB) eligible for extraction. Tuned
     * conservative for the worst expanders (xlsx/xls strictest, observed to
     * exceed 4 GB at ~9.4 MB on a production instance while ~7.8 MB stayed
     * under 1 GB).
     */
    private const DEFAULT_LIMITS_MB = [
        'xlsx' => 8,
        'xls'  => 8,
        'docx' => 16,
        'pdf'  => 24,
        'csv'  => 32,
        'txt'  => 32,
    ];

    /**
     * Maximum eligible bytes for a document type, or 0 if the type is not
     * bounded by this guard (unknown/unsupported types).
     *
     * @param array<string,int> $overridesMb Optional per-type overrides in MB.
     */
    public static function maxBytes(string $docType, array $overridesMb = []): int
    {
        $limits = $overridesMb + self::DEFAULT_LIMITS_MB;
        $type = strtolower($docType);
        if (!isset($limits[$type]) || (int)$limits[$type] <= 0) {
            return 0;
        }
        return (int)$limits[$type] * self::MB;
    }

    /**
     * True when a document of $docType and $sizeBytes is too large to extract
     * safely. Unbounded types (maxBytes 0) never exceed.
     *
     * @param array<string,int> $overridesMb Optional per-type overrides in MB.
     */
    public static function exceedsLimit(int $sizeBytes, string $docType, array $overridesMb = []): bool
    {
        $max = self::maxBytes($docType, $overridesMb);
        return $max > 0 && $sizeBytes > $max;
    }
}

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
     * @throws UnsupportedDocumentException
     */
    public static function extractText(
        string $content,
        ?string $mimeType = null,
        ?string $fileName = null,
        array $options = []
    ): string {
        $docType = self::determineDocumentType($mimeType, $fileName, $content);

        // Pre-parse size guard: refuse files whose type+size risk an
        // out-of-memory blowup (esp. XLSX, which allocates outside PHP's
        // memory_limit). Unbounded/unknown types fall through to the
        // UnsupportedDocumentException path below. See DocumentSizeGuard.
        $sizeBytes = strlen($content);
        if (DocumentSizeGuard::exceedsLimit($sizeBytes, $docType)) {
            throw new DocumentTooLargeException(
                "Document too large for safe extraction. [Type = {$docType}, Bytes = {$sizeBytes}, File Name = {$fileName}]"
            );
        }

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
                throw new UnsupportedDocumentException("Unsupported document. Unable to extract text. [Mime Type = {$mimeType}, File Name = [{$fileName}]");
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