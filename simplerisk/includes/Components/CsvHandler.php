<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace SimpleRisk\DocumentHandlers;

class CsvHandler
{
    /**
     * Extract text from CSV content
     *
     * @param string $content CSV content as string
     * @return string Flattened plain text with rows separated by newlines and columns by tabs
     */
    public static function extractTextFromCsv(string $content): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $content);

        // Detect separator from first non-empty line
        $separator = self::detectSeparator($lines);

        $output = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $cells = str_getcsv($line, $separator);
            $output[] = implode("\t", $cells);
        }

        return implode("\n", $output);
    }

    /**
     * Detect CSV separator (comma, semicolon, tab)
     */
    private static function detectSeparator(array $lines): string
    {
        foreach ($lines as $line) {
            if (trim($line) === '') continue;

            $commaCount = substr_count($line, ',');
            $semicolonCount = substr_count($line, ';');
            $tabCount = substr_count($line, "\t");

            $counts = [
                ',' => $commaCount,
                ';' => $semicolonCount,
                "\t" => $tabCount
            ];

            // Choose the separator with the most occurrences
            arsort($counts);
            return key($counts);
        }

        // Fallback to comma
        return ',';
    }
}

?>