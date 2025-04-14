<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

class WordDocumentHandler {
    private $phpWord;
    private $section;

    public function __construct() {
        $this->phpWord = new PhpWord();
        $this->setupDefaultStyles();
    }

    private function setupDefaultStyles() {
        $this->phpWord->addParagraphStyle('Normal', [
            'spaceAfter' => 100,
            'spacing' => 120,
            'alignment' => Jc::LEFT
        ]);

        $this->phpWord->addTitleStyle(1, [
            'bold' => true,
            'size' => 16,
            'spaceAfter' => 200,
            'spaceBefore' => 200,
        ]);

        $this->phpWord->addTitleStyle(2, [
            'bold' => true,
            'size' => 14,
            'spaceAfter' => 150,
            'spaceBefore' => 150,
        ]);

        $this->phpWord->addNumberingStyle(
            'multilevel',
            [
                'type' => 'multilevel',
                'levels' => [
                    ['format' => 'decimal', 'text' => '%1.', 'left' => 360, 'hanging' => 360, 'tabPos' => 360],
                    ['format' => 'decimal', 'text' => '%1.%2.', 'left' => 720, 'hanging' => 360, 'tabPos' => 720],
                ]
            ]
        );

        $this->phpWord->addNumberingStyle(
            'singleLevelBullet',
            [
                'type' => 'singleLevel',
                'levels' => [
                    ['format' => 'bullet', 'text' => '•', 'left' => 360, 'hanging' => 360, 'tabPos' => 360],
                ]
            ]
        );
    }

    public function processDocument($content, $fileName) {
        try {
            $this->section = $this->phpWord->addSection([
                'marginLeft' => 1440,
                'marginRight' => 1440,
                'marginTop' => 1440,
                'marginBottom' => 1440
            ]);

            $parsedown = new Parsedown();
            $htmlContent = $parsedown->text($content);

            $this->convertHtmlToWord($htmlContent);

            return $this->saveDocument($fileName);

        } catch (\Exception $e) {
            error_log("Error processing document: " . $e->getMessage());
            throw $e;
        }
    }

    private function convertHtmlToWord($htmlContent) {
        $dom = new \DOMDocument();
        @$dom->loadHTML($htmlContent);

        $this->parseHtmlElement($dom->documentElement);
    }

    private function parseHtmlElement($element, $parentTextRun = null) {
        foreach ($element->childNodes as $child) {
            if ($child->nodeType == XML_TEXT_NODE) {
                $text = preg_replace('/\s+/', ' ', $child->nodeValue); // Normalize whitespace

                if (!empty(trim($text))) {
                    if ($parentTextRun) {
                        $parentTextRun->addText($text);
                    } else {
                        $this->section->addText($text);
                    }
                }
            } elseif ($child->nodeType == XML_ELEMENT_NODE) {
                switch ($child->nodeName) {
                    case 'strong':
                    case 'b':
                        $textRun = $parentTextRun ?: $this->section->addTextRun();
                        $textRun->addText(trim($child->textContent), ['bold' => true]);
                        break;
                    case 'em':
                    case 'i':
                        $textRun = $parentTextRun ?: $this->section->addTextRun();
                        $textRun->addText(trim($child->textContent), ['italic' => true]);
                        break;
                    case 'u':
                        $textRun = $parentTextRun ?: $this->section->addTextRun();
                        $textRun->addText(trim($child->textContent), ['underline' => 'single']);
                        break;
                    case 'h1':
                        $this->section->addTitle(trim($child->textContent), 1);
                        break;
                    case 'h2':
                        $this->section->addTitle(trim($child->textContent), 2);
                        break;
                    case 'ul':
                    case 'ol':
                        $listLevel = 0; // Default list level
                        $listStyle = ($child->nodeName === 'ul') ? \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED : \PhpOffice\PhpWord\Style\ListItem::TYPE_NUMBER;

                        foreach ($child->childNodes as $li) {
                            if ($li->nodeName === 'li') {
                                // Create a TextRun inside the section instead
                                $textRun = $this->section->addTextRun();

                                // Add bullet manually (for unordered lists)
                                if ($child->nodeName === 'ul') {
                                    $textRun->addText("• ", ['bold' => true]); // Adds a bullet
                                }

                                // Parse <li> contents and apply formatting inside the TextRun
                                $this->parseHtmlElement($li, $textRun);
                            }
                        }
                        break;
                    case 'p':
                        $this->section->addText(trim($child->textContent));
                        break;
                    default:
                        $this->parseHtmlElement($child, $parentTextRun);
                        break;
                }
            }
        }
    }

    public function saveDocument($fileName) {
        try {
            $tempFilePath = sys_get_temp_dir() . '/' . $fileName;

            $objWriter = IOFactory::createWriter($this->phpWord, 'Word2007');
            $objWriter->save($tempFilePath);

            unset($objWriter);

            if (!file_exists($tempFilePath)) {
                throw new Exception("File was not created: " . $tempFilePath);
            }

            return $tempFilePath;
        } catch (\Exception $e) {
            error_log("Error saving document: " . $e->getMessage());
            throw $e;
        }
    }
}

function extract_text_content($phpWord) {
    $textContent = '';

    foreach ($phpWord->getSections() as $section) {
        foreach ($section->getElements() as $element) {
            $extractedText = extract_text_from_element($element);
            if (!empty($extractedText) && is_string($extractedText)) {
                $textContent .= $extractedText . "\n";
            }
        }
    }

    return trim($textContent);
}

function extract_text_from_element($element) {
    if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
        return $element->getText(); // ✅ Extracts plain text
    }

    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
        $textRunContent = [];
        foreach ($element->getElements() as $textElement) {
            $textRunContent[] = extract_text_from_element($textElement);
        }
        return implode(" ", array_filter($textRunContent)); // ✅ Safely join text parts
    }

    if ($element instanceof \PhpOffice\PhpWord\Element\ListItem) {
        return "- " . $element->getText(); // ✅ Handles bullet points
    }

    if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
        $tableContent = [];
        foreach ($element->getRows() as $row) {
            $rowContent = [];
            foreach ($row->getCells() as $cell) {
                foreach ($cell->getElements() as $cellElement) {
                    $rowContent[] = extract_text_from_element($cellElement);
                }
            }
            $tableContent[] = implode(" | ", array_filter($rowContent)); // ✅ Format table rows
        }
        return implode("\n", array_filter($tableContent));
    }

    if ($element instanceof \PhpOffice\PhpWord\Element\TextBreak) {
        return "\n"; // ✅ Handles line breaks
    }

    if ($element instanceof \PhpOffice\PhpWord\Element\PageBreak) {
        return "\n---- Page Break ----\n"; // ✅ Handles page breaks
    }

    if (method_exists($element, 'getText')) {
        return $element->getText();
    }

    if (method_exists($element, 'getElements')) {
        $nestedContent = [];
        foreach ($element->getElements() as $childElement) {
            $nestedContent[] = extract_text_from_element($childElement);
        }
        return implode(" ", array_filter($nestedContent));
    }

    // 🛑 If an unknown object is encountered, log it for debugging.
    error_log("Unhandled element type: " . get_class($element));
    return '';
}

?>