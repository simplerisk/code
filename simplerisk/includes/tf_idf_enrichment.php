<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// --- TF-IDF Implementation ---
/**
 * Calculate Term Frequency (TF)
 *
 * @param string $term The term to calculate frequency for
 * @param string $document The document text
 * @return float The term frequency
 */
function calculateTF($term, $document) {
    $termCount = substr_count(strtolower($document), strtolower($term));
    $words = str_word_count(strtolower($document), 1);
    return $termCount / max(1, count($words));
}

/**
 * Calculate Inverse Document Frequency (IDF)
 *
 * @param string $term The term
 * @param array $documents Array of document texts
 * @return float The IDF value
 */
function calculateIDF($term, $documents) {
    $docsWithTerm = 0;

    foreach ($documents as $document) {
        if (stripos($document, $term) !== false) {
            $docsWithTerm++;
        }
    }

    // Adding 1 to avoid division by zero
    return log((count($documents) + 1) / ($docsWithTerm + 1)) + 1;
}

/**
 * Extract significant terms from the policy document
 *
 * @param string $policyText The policy document text
 * @param int $maxTerms Maximum number of terms to extract
 * @return array The extracted terms
 */
function extractSignificantTerms($policyText, $maxTerms = 100) {
    // Remove common words and special characters
    $stopWords = [
        'the', 'and', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'of', 'with',
        'by', 'as', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have',
        'has', 'had', 'do', 'does', 'did', 'but', 'or', 'if', 'because', 'as',
        'until', 'while', 'that', 'which', 'who', 'whom', 'this', 'these', 'those',
        'shall', 'should', 'may', 'might', 'must', 'can', 'could', 'would'
    ];

    // Clean the text
    $text = strtolower($policyText ?? '');
    $text = preg_replace('/[^\w\s]/', ' ', $text);

    // Extract words
    $words = str_word_count($text, 1);

    // Remove stop words and short words
    $filteredWords = array_filter($words, function($word) use ($stopWords) {
        return !in_array($word, $stopWords) && strlen($word) > 2;
    });

    // Count word frequencies
    $wordCounts = array_count_values($filteredWords);

    // Sort by frequency
    arsort($wordCounts);

    // Take top terms
    return array_slice(array_keys($wordCounts), 0, $maxTerms);
}

/**
 * Calculate TF-IDF for a document set
 *
 * @param array $documents Array of document texts
 * @param array $terms The terms to calculate TF-IDF for
 * @return array TF-IDF vectors for each document
 */
function calculateTfIdf($documents, $terms) {
    $tfidfVectors = [];

    foreach ($documents as $docIndex => $document) {
        $tfidfVector = [];

        foreach ($terms as $term) {
            $tf = calculateTF($term, $document);
            $idf = calculateIDF($term, $documents);
            $tfidfVector[$term] = $tf * $idf;
        }

        $tfidfVectors[$docIndex] = $tfidfVector;
    }

    return $tfidfVectors;
}

/**
 * Calculate cosine similarity between two vectors
 *
 * @param array $vectorA First vector
 * @param array $vectorB Second vector
 * @return float Similarity score (0-1)
 */
function cosineSimilarity($vectorA, $vectorB) {
    $dotProduct = 0;
    $magnitudeA = 0;
    $magnitudeB = 0;

    // For each term in vector A
    foreach ($vectorA as $term => $weightA) {
        // If term exists in vector B, add to dot product
        if (isset($vectorB[$term])) {
            $dotProduct += $weightA * $vectorB[$term];
        }

        $magnitudeA += $weightA * $weightA;
    }

    // Calculate magnitude of vector B
    foreach ($vectorB as $weightB) {
        $magnitudeB += $weightB * $weightB;
    }

    // Calculate magnitudes
    $magnitudeA = sqrt($magnitudeA);
    $magnitudeB = sqrt($magnitudeB);

    // Avoid division by zero
    if ($magnitudeA == 0 || $magnitudeB == 0) {
        return 0;
    }

    return $dotProduct / ($magnitudeA * $magnitudeB);
}

/**
 * Count the number of times any keyword appears in the given text.
 *
 * @param string $text The text to search in
 * @param array $keywords Keywords to count
 * @return int Total keyword occurrence count
 */
function countKeywordOccurrences($text, $keywords) {
    // Strip HTML tags
    $text = strip_tags($text);

    // Decode HTML entities like &nbsp;, &amp;, etc.
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Normalize the text: lowercase and remove punctuation
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/i', '', $text);

    // Tokenize text into words
    $words = preg_split('/\s+/', $text);

    // Build frequency map of the text
    $wordFrequency = array_count_values($words);

    // Normalize keywords
    $normalizedKeywords = array_map(function($keyword) {
        $keyword = strtolower($keyword);
        $keyword = html_entity_decode($keyword, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/[^a-z0-9\s]/i', '', $keyword);
    }, $keywords);

    // Count matches
    $count = 0;
    foreach ($normalizedKeywords as $keyword) {
        $count += $wordFrequency[$keyword] ?? 0;
    }

    return $count;
}

/**
 * Count the number of times each keyword appears in the given text.
 *
 * @param string $text The text to search in.
 * @param array $keywords Keywords to count.
 * @return array Associative array of keyword => occurrence count (sorted descending by count).
 */
function countKeywordOccurrencesPerKeyword($text, $keywords) {
    // Strip HTML tags
    $text = strip_tags($text ?? '');

    // Decode HTML entities like &nbsp;, &amp;, etc.
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Normalize the text: lowercase and remove punctuation
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/i', '', $text);

    // Tokenize text into words
    $words = preg_split('/\s+/', $text);

    // Build frequency map of the text
    $wordFrequency = array_count_values($words);

    // Normalize keywords
    $normalizedKeywords = array_map(function($keyword) {
        $keyword = strtolower($keyword);
        $keyword = html_entity_decode($keyword, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/[^a-z0-9\s]/i', '', $keyword);
    }, $keywords);

    // Count individual keyword matches
    $counts = [];
    foreach ($normalizedKeywords as $keyword) {
        $count = $wordFrequency[$keyword] ?? 0;
        if ($count > 0) {
            $counts[$keyword] = $count;
        }
    }

    // Sort by descending frequency
    arsort($counts);

    return $counts;
}

/**
 * Count keyword matches between document and control keyword arrays
 *
 * @param array $docKeywords Document keywords array (term => count)
 * @param array $controlKeywords Control keywords array (term => count)
 * @return int Number of matching keywords
 */
function countKeywordMatches($docKeywords, $controlKeywords) {
    // Input validation
    if (!is_array($docKeywords) || !is_array($controlKeywords)) {
        write_debug_log("Error: countKeywordMatches received non-array input");
        return 0;
    }

    // Normalize case for all keys to ensure case-insensitive matching
    $docKeywords = array_change_key_case($docKeywords, CASE_LOWER);
    $controlKeywords = array_change_key_case($controlKeywords, CASE_LOWER);

    // Find intersecting keywords
    $matchingTerms = array_intersect_key($docKeywords, $controlKeywords);
    $matchCount = count($matchingTerms);

    // Optional: Log some details about the matches for debugging
    if ($matchCount > 0) {
        write_debug_log("Matching terms: " . implode(", ", array_keys($matchingTerms)));
    } else {
        write_debug_log("No matching terms found between document and control");
    }

    // Optional: Consider term frequencies for weighted matching
    $weightedMatchScore = 0;
    foreach ($matchingTerms as $term => $docFreq) {
        $controlFreq = $controlKeywords[$term];
        // Use the minimum of the two frequencies as the weight for this term
        $weightedMatchScore += min($docFreq, $controlFreq);
    }

    write_debug_log("Match count: $matchCount, Weighted match score: $weightedMatchScore");

    // You can return either the simple count or the weighted score
    // depending on your preference
    return $matchCount; // or return $weightedMatchScore;
}

?>