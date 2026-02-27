<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace SimpleRisk\DocumentHandlers;

// Include required files
require_once (realpath(__DIR__ . '/../../vendor/autoload.php'));
require_once(realpath(__DIR__ . '/../functions.php'));

use Smalot\PdfParser\Parser;
use Smalot\PdfParser\Exception as PdfException;
use RuntimeException;

class PdfHandler
{
    /**
     * Extract text from a PDF. Input may be raw bytes or base64-encoded PDF.
     *
     * @param string      $content  PDF content (raw bytes or base64 string)
     * @param bool        $isBase64 Whether the input is base64-encoded
     * @param bool        $preserveLayout Try to preserve layout when possible
     * @param bool        $chunkedProcessing Use chunked writes to temp file for large PDFs
     * @return string
     *
     * @throws RuntimeException
     */
    public static function extractTextFromPdf(
        string $content,
        bool   $isBase64 = false,
        bool   $preserveLayout = false,
        bool   $chunkedProcessing = false
    ): string {
        // --------------------------------------------
        // Handle base64 input
        // --------------------------------------------
        if ($isBase64) {
            $decoded = base64_decode($content, true);
            if ($decoded === false) {
                throw new RuntimeException("Invalid base64 PDF content.");
            }
            $content = $decoded;
        }

        // --------------------------------------------
        // Prepare temporary file for parsing
        // --------------------------------------------
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
        if (!$tempFile) {
            throw new RuntimeException("Failed to create temporary file for PDF processing.");
        }

        try {
            // --------------------------------------------
            // Chunked writing option for large PDFs
            // --------------------------------------------
            if ($chunkedProcessing) {
                $fp = fopen($tempFile, 'wb');
                if (!$fp) {
                    throw new RuntimeException("Failed to open temp file for chunked write.");
                }

                $chunkSize = 1024 * 512; // 512 KB chunks
                $offset = 0;
                $length = strlen($content);

                while ($offset < $length) {
                    fwrite($fp, substr($content, $offset, $chunkSize));
                    $offset += $chunkSize;
                }

                fclose($fp);
            } else {
                // Standard write
                file_put_contents($tempFile, $content);
            }

            // --------------------------------------------
            // Parse PDF
            // --------------------------------------------
            $parser = new Parser();
            $pdf = $parser->parseFile($tempFile);

            // --------------------------------------------
            // Extract text
            // --------------------------------------------
            if ($preserveLayout) {
                // Smalot does not truly preserve layout, but we can merge text by pages
                $text = [];
                foreach ($pdf->getPages() as $page) {
                    $pageText = trim($page->getText());
                    // Sanitize each page individually
                    $text[] = self::sanitizeUtf8($pageText);
                }
                $output = implode("\n\n---- PAGE BREAK ----\n\n", $text);
            } else {
                // Default extraction
                $output = trim($pdf->getText());
                // Sanitize the entire extracted text
                $output = self::sanitizeUtf8($output);
            }

            return $output;

        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Failed to extract text from PDF: " . $e->getMessage(),
                0,
                $e
            );
        } finally {
            // --------------------------------------------
            // Always remove the temp file
            // --------------------------------------------
            @unlink($tempFile);
        }
    }
}

?>