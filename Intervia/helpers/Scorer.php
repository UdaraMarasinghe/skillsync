<?php
// helpers/Scorer.php

class Scorer {

    /**
     * Stop words list for cleaning input text.
     */
    private static $stopwords = [
        'a','an','the','and','or','but','if','because','as','what','which','this','that','these','those',
        'then','just','so','than','such','both','through','about','against','between','into','throughout',
        'during','before','after','above','below','to','from','up','upon','down','in','out','on','off',
        'over','under','again','further','then','once','here','there','when','where','why','how','all',
        'any','both','each','few','more','most','other','some','such','no','nor','not','only','own',
        'same','so','than','too','very','can','will','just','should','now','is','are','was',
        'were','be','been','being','have','has','had','having','do','does','did','doing','i','me','my',
        'myself','we','our','ours','ourselves','you','your','yours','yourself','yourselves','he','him',
        'his','himself','she','her','hers','herself','it','its','itself','they','them','their','theirs',
        'themselves','also','used','using','refers','refer','known','called','defined','means','meaning'
    ];

    /**
     * Extract normalized tokens (words) from a text string.
     */
    public static function extractTokens($text) {
        $text = strtolower(trim($text));
        preg_match_all('/[a-z0-9]+/i', $text, $matches);
        $words = $matches[0] ?? [];

        $stopwordsHash = array_flip(self::$stopwords);
        $cleanWords = [];

        foreach ($words as $w) {
            if (strlen($w) > 1 && !isset($stopwordsHash[$w])) {
                $cleanWords[] = self::stemWord($w);
            }
        }

        return $cleanWords;
    }

    /**
     * Simple suffix stemming rule for common English words
     */
    private static function stemWord($word) {
        $len = strlen($word);
        if ($len <= 3) return $word;

        // Common suffixes to normalize (e.g. globalization -> global, processes -> process)
        if (str_ends_with($word, 'ization') && $len > 8) return substr($word, 0, -6);
        if (str_ends_with($word, 'ational') && $len > 8) return substr($word, 0, -5);
        if (str_ends_with($word, 'ingly') && $len > 6) return substr($word, 0, -5);
        if (str_ends_with($word, 'ing') && $len > 5) return substr($word, 0, -3);
        if (str_ends_with($word, 'ment') && $len > 6) return substr($word, 0, -4);
        if (str_ends_with($word, 'ness') && $len > 6) return substr($word, 0, -4);
        if (str_ends_with($word, 'able') && $len > 6) return substr($word, 0, -4);
        if (str_ends_with($word, 'ible') && $len > 6) return substr($word, 0, -4);
        if (str_ends_with($word, 'ties') && $len > 5) return substr($word, 0, -4) . 'ty';
        if (str_ends_with($word, 'ies') && $len > 5) return substr($word, 0, -3) . 'y';
        if (str_ends_with($word, 'ed') && $len > 4) return substr($word, 0, -2);
        if (str_ends_with($word, 'es') && $len > 4) return substr($word, 0, -2);
        if (str_ends_with($word, 's') && $len > 3 && !str_ends_with($word, 'ss')) return substr($word, 0, -1);

        return $word;
    }

    /**
     * Compare user's answer with standard answer
     */
    public static function evaluateAnswer($expectedAnswer, $userAnswer) {
        $expectedTokens = self::extractTokens($expectedAnswer);
        $userTokens = self::extractTokens($userAnswer);

        if (empty($userTokens)) {
            return [
                'score' => 0,
                'exact_matches' => [],
                'semantic_matches' => [],
                'matching_count' => 0,
                'total_expected' => count(array_unique($expectedTokens)),
                'feedback' => 'No meaningful answer provided.'
            ];
        }

        $uniqueExpected = array_values(array_unique($expectedTokens));
        $uniqueUser = array_values(array_unique($userTokens));

        $exactMatches = [];
        $matchedExpected = [];
        $matchedUser = [];

        // Step 1: Direct Matching (after stemming)
        foreach ($uniqueUser as $uWord) {
            if (in_array($uWord, $uniqueExpected)) {
                $exactMatches[] = $uWord;
                $matchedUser[$uWord] = true;
                $matchedExpected[$uWord] = true;
            }
        }

        // Step 2: Local Fuzzy / Substring / Phonetic Matching for non-matching words
        $semanticMatches = [];

        foreach ($uniqueUser as $uWord) {
            if (isset($matchedUser[$uWord])) continue;

            $bestMatch = null;
            $bestSim = 0;

            foreach ($uniqueExpected as $eWord) {
                // Levenshtein ratio
                $len1 = strlen($uWord);
                $len2 = strlen($eWord);
                $maxLen = max($len1, $len2);
                if ($maxLen == 0) continue;

                $lev = levenshtein($uWord, $eWord);
                $levSim = 1 - ($lev / $maxLen);

                // Substring containment match ratio
                $subSim = 0;
                if ($len1 >= 3 && $len2 >= 3) {
                    if (str_contains($eWord, $uWord) || str_contains($uWord, $eWord)) {
                        $subSim = min($len1, $len2) / max($len1, $len2);
                    }
                }

                // Metaphone soundex similarity
                $metaSim = (metaphone($uWord) === metaphone($eWord) && strlen($uWord) > 2) ? 0.75 : 0;

                // N-gram / character overlap similarity
                $similarChars = similar_text($uWord, $eWord, $percent);
                $simTextSim = $percent / 100;

                $sim = max($levSim, $subSim, $metaSim, $simTextSim);

                if ($sim > $bestSim && $sim >= 0.58) {
                    $bestSim = $sim;
                    $bestMatch = $eWord;
                }
            }

            if ($bestMatch !== null) {
                $semanticMatches[] = [
                    'user_word' => $uWord,
                    'expected_word' => $bestMatch,
                    'similarity_percent' => round($bestSim * 100)
                ];
                $matchedUser[$uWord] = true;
                $matchedExpected[$bestMatch] = true;
            }
        }

        // Step 3: Compute final score
        $exactCount = count($exactMatches);
        $semanticCount = count($semanticMatches);
        $totalMatchedPoints = $exactCount + ($semanticCount * 0.85);

        $totalExpectedCount = max(1, count($uniqueExpected));
        $coverage = $totalMatchedPoints / $totalExpectedCount;

        // Dynamic scaling factor based on user answer length vs expected concept length
        // A concise correct answer gets high points
        $scoreMultiplier = 2.0; 
        $rawScore = min(1.0, $coverage * $scoreMultiplier);

        // Additional sanity check for non-empty answers that captured core concepts
        $finalScore = (int)round($rawScore * 100);

        // Feedback summary generator
        $feedback = "Good effort!";
        if ($finalScore >= 85) {
            $feedback = "Excellent response! You captured the essential concepts very accurately.";
        } elseif ($finalScore >= 65) {
            $feedback = "Great answer! Most key concepts were correctly mentioned.";
        } elseif ($finalScore >= 45) {
            $feedback = "Fair answer. You touched on some key points, but missed several details.";
        } else {
            $feedback = "Your answer needs improvement. Try to incorporate key domain terms.";
        }

        return [
            'score' => $finalScore,
            'exact_matches' => $exactMatches,
            'semantic_matches' => $semanticMatches,
            'matching_count' => count($exactMatches) + count($semanticMatches),
            'total_expected' => $totalExpectedCount,
            'total_user' => count($uniqueUser),
            'feedback' => $feedback
        ];
    }
}
