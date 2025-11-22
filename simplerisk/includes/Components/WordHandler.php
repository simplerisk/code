<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

declare(strict_types=1);

namespace SimpleRisk\DocumentHandlers;

// Include required files
require_once (realpath(__DIR__ . '/../../vendor/autoload.php'));
require_once(realpath(__DIR__ . '/../functions.php'));

use DOMDocument;
use DOMNode;
use PDO;
use ZipArchive;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Settings;

class WordHandler
{
    private PDO $db;
    private int $user;

    public function __construct(PDO $db=null, int $user=0)
    {
        // If the database is not initialized
        if (is_null($db))
        {
            // Initialize the database
            $this->db = db_open();
        }
        // Otherwise, use the provided database
        else $this->db = $db;

        // Use the provided user or default to 0
        $this->user = $user;

        // Set PHPWord to use more compatible settings
        Settings::setOutputEscapingEnabled(true);
    }

    /**
     * Converts DOCX (from tmp_files) → Markdown
     */
    public function convertTmpDocxToMarkdown(string $unique_name): string
    {
        $file = load_tmp_file($this->db, $unique_name);
        $content = $file['content'];
        $mimeType = $file['type'];
        $fileName = $file['name'];
        if (!$file) {
            throw new Exception("Temporary file not found: $unique_name");
        }
        return self::convertDocxToMarkdown($content);
    }

    public static function convertDocxToMarkdown(string $docxBinary): string
    {
        $zip = new ZipArchive();
        $tmp = tmpfile();
        $meta = stream_get_meta_data($tmp);
        fwrite($tmp, $docxBinary);
        rewind($tmp);

        if ($zip->open($meta['uri']) !== true) {
            fclose($tmp);
            throw new Exception("Failed to open DOCX as ZIP archive");
        }

        $md = '';
        if (($index = $zip->locateName('word/document.xml')) !== false) {
            $xml = $zip->getFromIndex($index);
            $dom = new DOMDocument();
            @$dom->loadXML($xml);
            $body = $dom->getElementsByTagName('body')->item(0);
            if ($body) {
                $md = self::parseWordXmlToMarkdown($body);
            }
        }

        $zip->close();
        fclose($tmp);
        return $md;
    }

    private static function parseWordXmlToMarkdown(DOMNode $node): string
    {
        $md = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === 'w:p') {
                // Check for heading styles
                $pPr = $child->getElementsByTagName('pPr')->item(0);
                $isHeading = false;
                $headingLevel = 0;

                if ($pPr) {
                    $pStyle = $pPr->getElementsByTagName('pStyle')->item(0);
                    if ($pStyle) {
                        $styleName = $pStyle->getAttribute('w:val');
                        if (preg_match('/heading(\d)/i', $styleName, $matches)) {
                            $isHeading = true;
                            $headingLevel = (int)$matches[1];
                        }
                    }
                }

                $text = '';
                foreach ($child->getElementsByTagName('t') as $tNode) {
                    $text .= $tNode->nodeValue;
                }

                if ($text = trim($text)) {
                    if ($isHeading && $headingLevel > 0) {
                        $md .= str_repeat('#', $headingLevel) . ' ' . $text . "\n\n";
                    } else {
                        $md .= $text . "\n\n";
                    }
                }
            } elseif ($child->nodeName === 'w:tbl') {
                // Basic table support
                $md .= "[TABLE]\n\n";
            } else {
                $md .= self::parseWordXmlToMarkdown($child);
            }
        }
        return $md;
    }

    /**
     * IMPROVED: Create DOCX from HTML with better compatibility
     */
    public function createTmpDocxFromHtml(string $html, string $name = 'AI_Generated.docx'): string
    {
        write_debug_log("Starting DOCX creation from HTML for: $name", "debug");

        // 1. Sanitize HTML with improved logic
        $html = self::sanitizeHtmlForDocx($html);

        // 2. Create fresh PhpWord instance
        $phpWord = new PhpWord();

        // 3. Set compatibility options for LibreOffice
        $phpWord->getCompatibility()->setOoxmlVersion(15); // Office 2013 format

        // 4. Setup comprehensive styles
        $this->setupDocumentStyles($phpWord);

        // 5. Add section with proper margins
        $section = $phpWord->addSection([
            'marginTop' => 1440,    // 1 inch
            'marginBottom' => 1440,
            'marginLeft' => 1440,
            'marginRight' => 1440,
        ]);

        // 6. Parse and add HTML content
        try {
            // Use PHPWord's built-in HTML parser with error handling
            \PhpOffice\PhpWord\Shared\Html::addHtml($section, $html, false, false);
            write_debug_log("Successfully added HTML to DOCX for: $name", "debug");
        } catch (\Exception $e) {
            write_debug_log("PHPWord HTML parsing failed for $name: " . $e->getMessage(), "error");

            // Fallback: try manual parsing
            $this->addHtmlManually($section, $html);
        }

        // 7. Save to temporary file with proper settings
        $tmp = tmpfile();
        $meta = stream_get_meta_data($tmp);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($meta['uri']);

        $binary = file_get_contents($meta['uri']);
        fclose($tmp);

        // 8. Validate the generated DOCX
        if (!self::validateDocxStructure($binary)) {
            write_debug_log("Warning: Generated DOCX may have structural issues for: $name", "warning");
        }

        return save_tmp_file(
            $this->db,
            $name,
            $binary,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'docx',
            $this->user
        );
    }

    /**
     * Setup comprehensive document styles for better compatibility
     */
    private function setupDocumentStyles(PhpWord $phpWord): void
    {
        // Normal paragraph style
        $phpWord->addParagraphStyle('Normal', [
            'alignment' => Jc::LEFT,
            'spaceAfter' => 120,
            'spacing' => 120,
        ]);

        // Heading styles
        $phpWord->addTitleStyle(1, [
            'bold' => true,
            'size' => 16,
            'name' => 'Arial'
        ], [
            'spaceAfter' => 240,
            'spaceBefore' => 240,
        ]);

        $phpWord->addTitleStyle(2, [
            'bold' => true,
            'size' => 14,
            'name' => 'Arial'
        ], [
            'spaceAfter' => 180,
            'spaceBefore' => 180,
        ]);

        $phpWord->addTitleStyle(3, [
            'bold' => true,
            'size' => 12,
            'name' => 'Arial'
        ], [
            'spaceAfter' => 120,
            'spaceBefore' => 120,
        ]);

        // Font styles
        $phpWord->addFontStyle('Bold', ['bold' => true]);
        $phpWord->addFontStyle('Italic', ['italic' => true]);
        $phpWord->addFontStyle('Underline', ['underline' => 'single']);
    }

    /**
     * FUNCTION: SANITIZE HTML FOR DOCX
     * Sanitize HTML for DOCX conversion
     * More conservative approach that preserves structure
     */
    public static function sanitizeHtmlForDocx(string $html): string
    {
        if (empty($html)) {
            write_debug_log("sanitizeHtmlForDocx: Input HTML is empty", "warning");
            return '';
        }

        write_debug_log("Starting HTML sanitization, input length: " . strlen($html), "debug");

        // Ensure UTF-8
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');

        // 1. Remove dangerous tags entirely
        $html = preg_replace('#<(script|style|iframe|object|embed|form)[^>]*>.*?</\1>#is', '', $html);

        // 2. Remove event handlers and javascript URLs
        $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*["\']?\s*(javascript|data):[^"\'>\s]*/i', '', $html);

        // 3. Convert smart quotes and special chars to standard ones
        $replacements = [
            "\xE2\x80\x9C" => '"',  // left double quote
            "\xE2\x80\x9D" => '"',  // right double quote
            "\xE2\x80\x98" => "'",  // left single quote
            "\xE2\x80\x99" => "'",  // right single quote
            "\xE2\x80\x94" => '-',  // em dash
            "\xE2\x80\x93" => '-',  // en dash
            "\xE2\x80\xA6" => '...', // ellipsis
            "\xC2\xA0" => ' ',      // non-breaking space
        ];
        $html = strtr($html, $replacements);

        // 4. Load into DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);

        // Wrap in proper HTML structure
        $success = $dom->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $errors = libxml_get_errors();
        if (!empty($errors)) {
            write_debug_log("libxml errors during HTML parsing: " . print_r($errors, true), "debug");
        }
        libxml_clear_errors();

        if (!$success) {
            write_debug_log("Failed to parse HTML, returning original", "error");
            return $html;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            write_debug_log("No body tag found, using entire document", "debug");
            $body = $dom->documentElement;
        }

        // 5. Allowed tags
        $allowedTags = ['p','h1','h2','h3','h4','h5','h6','ul','ol','li','b','strong','i','em','u','table','thead','tbody','tr','td','th','br','a'];

        // Recursive processing
        $cleanedHtml = self::processDomNodeForDocx($body, $allowedTags);

        $cleanedHtml = trim($cleanedHtml);
        write_debug_log("Sanitization complete, output length: " . strlen($cleanedHtml), "debug");

        return $cleanedHtml;
    }

    /**
     * FUNCTION: PROCESS DOM NODE FOR DOCX
     * Recursive DOM processor that preserves allowed HTML for PHPWord
     */
    private static function processDomNodeForDocx(DOMNode $node, array $allowedTags): string
    {
        $output = '';

        foreach ($node->childNodes as $child) {
            switch ($child->nodeType) {
                case XML_ELEMENT_NODE:
                    $tagName = strtolower($child->nodeName);

                    if (in_array($tagName, $allowedTags)) {
                        $output .= "<$tagName>";
                        if ($child->hasChildNodes()) {
                            $output .= self::processDomNodeForDocx($child, $allowedTags);
                        }
                        $output .= "</$tagName>";
                    } else {
                        // Skip tag but keep children
                        if ($child->hasChildNodes()) {
                            $output .= self::processDomNodeForDocx($child, $allowedTags);
                        }
                    }
                    break;

                case XML_TEXT_NODE:
                case XML_CDATA_SECTION_NODE:
                    $text = $child->nodeValue;
                    if (!empty(trim($text))) {
                        $output .= $text; // leave raw text for PHPWord
                    }
                    break;
            }
        }

        return $output;
    }

    /**
     * Validate DOCX structure by checking if it's a valid ZIP with required files
     */
    private static function validateDocxStructure(string $binary): bool
    {
        $tmp = tmpfile();
        $meta = stream_get_meta_data($tmp);
        fwrite($tmp, $binary);
        rewind($tmp);

        $zip = new ZipArchive();
        $isValid = false;

        if ($zip->open($meta['uri']) === true) {
            // Check for required files
            $requiredFiles = [
                '[Content_Types].xml',
                'word/document.xml',
                '_rels/.rels',
                'word/_rels/document.xml.rels'
            ];

            $isValid = true;
            foreach ($requiredFiles as $file) {
                if ($zip->locateName($file) === false) {
                    write_debug_log("Missing required file in DOCX: $file", "warning");
                    $isValid = false;
                }
            }

            $zip->close();
        }

        fclose($tmp);
        return $isValid;
    }

    /**
     * Fallback manual HTML parsing if PHPWord's parser fails
     */
    private function addHtmlManually($section, string $html): void
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $this->processNodeManually($section, $body);
        }
    }

    /**
     * Recursive processing of DOM nodes for manual DOCX creation
     * Fully supports headings, paragraphs, nested lists, and tables
     */
    private function processNodeManually($section, DOMNode $node): void
    {
        foreach ($node->childNodes as $child) {
            $tag = strtolower($child->nodeName);

            switch ($tag) {
                case 'h1':
                    $section->addTitle(trim($child->textContent), 1);
                    break;
                case 'h2':
                    $section->addTitle(trim($child->textContent), 2);
                    break;
                case 'h3':
                    $section->addTitle(trim($child->textContent), 3);
                    break;
                case 'h4':
                    $section->addTitle(trim($child->textContent), 3); // PhpWord has 1-3 default, map h4+ to 3
                    break;
                case 'h5':
                case 'h6':
                    $section->addTitle(trim($child->textContent), 3);
                    break;
                case 'p':
                    $text = trim($child->textContent);
                    if ($text !== '') {
                        $section->addText($text, null, ['alignment' => Jc::LEFT]);
                    }
                    break;
                case 'ul':
                case 'ol':
                    $isOrdered = $tag === 'ol';
                    $this->processListManually($section, $child, $isOrdered);
                    break;
                case 'table':
                    $this->processTableManually($section, $child);
                    break;
                default:
                    if ($child->hasChildNodes()) {
                        $this->processNodeManually($section, $child);
                    }
            }
        }
    }

    /**
     * Recursive list processing (supports nested lists)
     */
    private function processListManually($section, DOMNode $listNode, bool $isOrdered, int $level = 0): void
    {
        foreach ($listNode->childNodes as $li) {
            if (strtolower($li->nodeName) === 'li') {
                $text = trim($li->textContent);
                if ($text !== '') {
                    $section->addListItem($text, $level, null, $isOrdered ? 'decimal' : 'bullet');
                }

                // Handle nested lists inside this li
                foreach ($li->childNodes as $child) {
                    if (in_array(strtolower($child->nodeName), ['ul', 'ol'])) {
                        $this->processListManually($section, $child, strtolower($child->nodeName) === 'ol', $level + 1);
                    }
                }
            }
        }
    }

    /**
     * Table processing for DOCX
     */
    private function processTableManually($section, DOMNode $tableNode): void
    {
        $table = $section->addTable();

        foreach ($tableNode->getElementsByTagName('tr') as $tr) {
            $table->addRow();
            foreach ($tr->getElementsByTagName('td') as $td) {
                $text = trim($td->textContent);
                $table->addCell(1750)->addText($text, null, ['alignment' => Jc::LEFT]);
            }
            // Optionally handle <th>
            foreach ($tr->getElementsByTagName('th') as $th) {
                $text = trim($th->textContent);
                $table->addCell(1750)->addText($text, ['bold' => true], ['alignment' => Jc::CENTER]);
            }
        }
    }

    /**
     * Extract text from DOCX binary (fixed typo)
     */
    public static function extractTextFromDocx(string $binaryContent): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'docx_');
        file_put_contents($tmpFile, $binaryContent);

        $zip = new ZipArchive;
        $text = '';

        if ($zip->open($tmpFile) === true) {
            if (($index = $zip->locateName('word/document.xml')) !== false) {
                $xml = $zip->getFromIndex($index);
                $dom = new DOMDocument();
                $dom->preserveWhiteSpace = false;
                $dom->formatOutput = false;
                @$dom->loadXML($xml);

                $text = strip_tags($dom->saveXML());
            }
            $zip->close();
        }

        unlink($tmpFile);
        $text = preg_replace('/\s+/', ' ', trim($text));
        return $text;
    }
}

?>