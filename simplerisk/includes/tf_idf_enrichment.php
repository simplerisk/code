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
 * Extract significant terms from text
 *
 * @param string $text The text to extract terms from
 * @return array The extracted terms and their term counts
 */
function extractSignificantTerms($text) {
    // Remove common words and special characters
    $stopWords = [
        'the', 'and', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'of', 'with',
        'by', 'as', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have',
        'has', 'had', 'do', 'does', 'did', 'but', 'or', 'if', 'because', 'as',
        'until', 'while', 'that', 'which', 'who', 'whom', 'this', 'these', 'those',
        'shall', 'should', 'may', 'might', 'must', 'can', 'could', 'would', 'nbsp'
    ];

    // Clean the text
    $text = strtolower($text ?? '');
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

    // Return all terms with their counts
    return $wordCounts;
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
            $counts[$keyword] = (int)$count;
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

/**************************************************************
 * FUNCTION: COMPUTE DOCUMENT CONTROL SCORES                  *
 * Call with a single document (new/updated) and all controls *
 * OR                                                         *
 * Call with a single control (new/updated) and all documents *
 **************************************************************/
function compute_document_control_scores($documentIds = [], $controlIds = []) {
    $db = db_open();

    write_debug_log(
        "Starting compute_document_control_scores. Document IDs: " . json_encode($documentIds) .
        ", Control IDs: " . json_encode($controlIds),
        "debug"
    );

    // 1. Compute global document frequency (IDF)
    $allDocuments = $db->query("SELECT `keywords` FROM `compliance_files`")->fetchAll();
    $numDocuments = count($allDocuments);
    write_debug_log("Loaded $numDocuments documents for IDF calculation.", "debug");

    $documentFrequency = [];
    foreach ($allDocuments as $doc) {
        $keywords = json_decode($doc['keywords'], true) ?: [];
        foreach ($keywords as $term => $count) {
            $term = strtolower(trim($term));
            $documentFrequency[$term] = ($documentFrequency[$term] ?? 0) + 1;
        }
    }
    write_debug_log("Computed document frequency for " . count($documentFrequency) . " terms.", "debug");

    // 2. Load documents
    $documentQuery = "SELECT `ref_id` AS `id`, `keywords` FROM `compliance_files`";
    if (!empty($documentIds)) {
        $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
        $documentQuery .= " WHERE ref_id IN ($placeholders)";
    }
    $stmt = $db->prepare($documentQuery);
    $stmt->execute($documentIds);
    $documents = $stmt->fetchAll();
    write_debug_log("Loaded " . count($documents) . " documents to process.", "debug");

    // 3. Load controls
    $controlQuery = "SELECT `id`, `keywords` FROM `framework_controls`";
    if (!empty($controlIds)) {
        $placeholders = implode(',', array_fill(0, count($controlIds), '?'));
        $controlQuery .= " WHERE id IN ($placeholders)";
    }
    $stmt = $db->prepare($controlQuery);
    $stmt->execute($controlIds);
    $controls = $stmt->fetchAll();
    write_debug_log("Loaded " . count($controls) . " controls to process.", "debug");

    // 4. Precompute normalized TF-IDF vectors
    $docVectors = [];
    foreach ($documents as &$doc) {
        $keywords = json_decode($doc['keywords'], true) ?: [];
        $normalizedKeywords = [];
        foreach ($keywords as $term => $count) $normalizedKeywords[strtolower(trim($term))] = $count;

        $doc['_keywords_array'] = array_keys($normalizedKeywords);
        $totalCount = array_sum($normalizedKeywords) ?: 1;

        $vector = [];
        foreach ($normalizedKeywords as $term => $count) {
            $tf = $count / $totalCount;
            $idf = log(1 + $numDocuments / ($documentFrequency[$term] ?? 0.5));
            $vector[$term] = $tf * $idf;
        }
        $docVectors[$doc['id']] = normalizeVector($vector);
    }
    unset($doc);

    $controlVectors = [];
    foreach ($controls as &$control) {
        $keywords = json_decode($control['keywords'], true) ?: [];
        $normalizedKeywords = [];
        foreach ($keywords as $term => $count) $normalizedKeywords[strtolower(trim($term))] = $count;

        $control['_keywords_array'] = array_keys($normalizedKeywords);
        $totalCount = array_sum($normalizedKeywords) ?: 1;

        $vector = [];
        foreach ($normalizedKeywords as $term => $count) {
            $tf = $count / $totalCount;
            $idf = log(1 + $numDocuments / ($documentFrequency[$term] ?? 0.5));
            $vector[$term] = $tf * $idf;
        }
        $controlVectors[$control['id']] = normalizeVector($vector);
    }
    unset($control);

    // 5. Compute scores and store in memory
    $scoreMap = [];
    $allScores = [];
    foreach ($documents as $doc) {
        $docId = $doc['id'];
        $docVector = $docVectors[$docId];
        $docKeywords = $doc['_keywords_array'] ?? [];

        foreach ($controls as $control) {
            $controlId = $control['id'];
            $controlVector = $controlVectors[$controlId];
            $controlKeywords = $control['_keywords_array'] ?? [];

            $tfidf_similarity = cosineSimilarity($docVector, $controlVector);
            $keyword_match_count = count(array_intersect($docKeywords, $controlKeywords));
            $normalized_keyword_score = $keyword_match_count / (min(count($docKeywords), count($controlKeywords)) ?: 1);
            $final_score = 0.8 * $tfidf_similarity + 0.2 * $normalized_keyword_score;

            if ($keyword_match_count < 2) {
                $final_score = 0; // ignore matches with too few overlapping keywords
            }

            $scoreMap["$docId-$controlId"] = [
                'final_score' => $final_score,
                'tfidf_similarity' => $tfidf_similarity,
                'keyword_match' => $keyword_match_count
            ];
            $allScores[] = $final_score;
        }
    }

    // 6. Adaptive threshold
    // Higher standard deviation multiplier above the mean means fewer matching results
    $std_dev_multiplier = 2.0;
    $mean = array_sum($allScores) / count($allScores);
    $stdDev = sqrt(array_sum(array_map(fn($s) => pow($s - $mean, 2), $allScores)) / count($allScores));
    $adaptiveThreshold = min(1.0, $mean + $std_dev_multiplier * $stdDev);
    write_debug_log("Adaptive threshold: $adaptiveThreshold (mean: $mean, stdDev: $stdDev)", "debug");

    // 7. Insert results into DB & collect matched pairs
    $stmt = $db->prepare("
        INSERT INTO document_control_mappings
            (document_id, control_id, score, tfidf_similarity, keyword_match, tfidf_match)
        VALUES
            (:document_id, :control_id, :score, :tfidf_similarity, :keyword_match, :tfidf_match)
        ON DUPLICATE KEY UPDATE
            score = :score,
            tfidf_similarity = :tfidf_similarity,
            keyword_match = :keyword_match,
            tfidf_match = :tfidf_match,
            timestamp = NOW()
    ");

    $matchedPairs = [];
    $pairsProcessed = 0;
    foreach ($scoreMap as $key => $data) {
        [$docId, $controlId] = explode('-', $key);
        $tfidf_match = $data['final_score'] >= $adaptiveThreshold ? 1 : 0;

        $stmt->execute([
            ':document_id' => $docId,
            ':control_id' => $controlId,
            ':score' => $data['final_score'],
            ':tfidf_similarity' => $data['tfidf_similarity'],
            ':keyword_match' => $data['keyword_match'],
            ':tfidf_match' => $tfidf_match
        ]);

        if ($tfidf_match) {
            $matchedPairs[] = ['document_id' => $docId, 'control_id' => $controlId];
        }

        $pairsProcessed++;
    }

    write_debug_log("Completed compute_document_control_scores. Total pairs processed: $pairsProcessed", "info");
    db_close($db);

    return $matchedPairs; // <-- return only matched pairs
}

/**
 * Compute cosine similarity for normalized sparse vectors
 */
function cosineSimilarity($vecA, $vecB) {
    $dot = 0.0;
    if (count($vecA) > count($vecB)) [$vecA, $vecB] = [$vecB, $vecA];
    foreach ($vecA as $term => $val) {
        if (isset($vecB[$term])) $dot += $val * $vecB[$term];
    }
    return $dot; // normalized vectors
}

/**
 * Normalize a vector to unit length
 */
function normalizeVector($vec) {
    $mag = sqrt(array_sum(array_map(fn($v) => $v * $v, $vec)));
    if ($mag == 0.0) return $vec;
    foreach ($vec as $k => $v) $vec[$k] = $v / $mag;
    return $vec;
}

?>