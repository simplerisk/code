<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace SimpleRisk\DocumentHandlers;

// Include required files
require_once (realpath(__DIR__ . '/../../vendor/autoload.php'));

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use RuntimeException;

class SpreadsheetHandler
{
    /**
     * Extract text from a spreadsheet binary.
     *
     * @param string $binaryContent XLSX or XLS content as string
     * @return string
     * @throws RuntimeException
     */
    public static function extractTextFromSpreadsheet(string $binaryContent): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sheet_');

        if ($tmpFile === false) {
            throw new RuntimeException("Failed to create temporary file for spreadsheet processing.");
        }

        file_put_contents($tmpFile, $binaryContent);

        try {
            // Auto-detect reader
            $reader = IOFactory::createReaderForFile($tmpFile);
            $spreadsheet = $reader->load($tmpFile);

            $textOutput = [];

            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $sheetTitle = $sheet->getTitle();
                $textOutput[] = "---- SHEET: {$sheetTitle} ----";

                foreach ($sheet->getRowIterator() as $row) {
                    $cells = [];
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false); // include empty cells

                    foreach ($cellIterator as $cell) {
                        $cells[] = trim((string)$cell->getValue());
                    }

                    $textOutput[] = implode("\t", $cells);
                }

                $textOutput[] = ""; // blank line between sheets
            }

            return implode("\n", $textOutput);

        } catch (ReaderException $e) {
            throw new RuntimeException("Failed to read spreadsheet: " . $e->getMessage(), previous: $e);
        } finally {
            @unlink($tmpFile);
        }
    }
}

?>