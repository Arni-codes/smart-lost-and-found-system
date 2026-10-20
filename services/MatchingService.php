<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * MatchingService — 4-Factor Heuristic Smart Matching Engine
 *
 * Implements:
 * 1. 4-factor scoring algorithm:
 *    - Category Match (Weight: 30 pts)
 *    - Campus Location / Proximity (Weight: 25 pts)
 *    - Date Proximity (Weight: 20 pts)
 *    - Title & Description Keyword Overlap via Jaccard similarity (Weight: 25 pts)
 * 2. On-demand match discovery, scoring, and caching in `smart_matches`
 * 3. Prevention of duplicate matching records (uk_match_pair)
 * 4. Privacy preservation: strictly excludes secret identifying_features
 * 5. Explainable JSON score breakdown
 * 6. Integration with NotificationService for match alerts
 *
 * Reference: PROJECT_SPEC.md (FR-05, Section 7.2.3, Section 9.4)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/NotificationService.php';

class MatchingService
{
    /**
     * Default threshold if constant not defined in config
     */
    public const DEFAULT_THRESHOLD = 40.0;

    /**
     * Comprehensive list of English stopwords and conversational noise words
     * to eliminate before computing keyword overlap.
     */
    private const STOPWORDS = [
        'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and', 'any',
        'are', 'arent', 'as', 'at', 'be', 'because', 'been', 'before', 'being', 'below',
        'between', 'both', 'but', 'by', 'can', 'cannot', 'cant', 'could', 'couldnt', 'did',
        'didnt', 'do', 'does', 'doesnt', 'doing', 'dont', 'down', 'during', 'each', 'few',
        'for', 'from', 'further', 'had', 'hadnt', 'has', 'hasnt', 'have', 'havent', 'having',
        'he', 'hed', 'hell', 'hes', 'her', 'here', 'heres', 'hers', 'herself', 'him',
        'himself', 'his', 'how', 'hows', 'i', 'id', 'ill', 'im', 'ive', 'if', 'in',
        'into', 'is', 'isnt', 'it', 'its', 'itself', 'lets', 'me', 'more', 'most',
        'mustnt', 'my', 'myself', 'no', 'nor', 'not', 'of', 'off', 'on', 'once', 'only',
        'or', 'other', 'ought', 'our', 'ours', 'ourselves', 'out', 'over', 'own', 'same',
        'shant', 'she', 'shed', 'shell', 'shes', 'should', 'shouldnt', 'so', 'some',
        'such', 'than', 'that', 'thats', 'the', 'their', 'theirs', 'them', 'themselves',
        'then', 'there', 'theres', 'these', 'they', 'theyd', 'theyll', 'theyre',
        'theyve', 'this', 'those', 'through', 'to', 'too', 'under', 'until', 'up', 'very',
        'was', 'wasnt', 'we', 'wed', 'well', 'were', 'werent', 'weve',
        'what', 'whats', 'when', 'whens', 'where', 'wheres', 'which', 'while', 'who',
        'whos', 'whom', 'why', 'whys', 'with', 'wont', 'would', 'wouldnt', 'you',
        'youd', 'youll', 'youre', 'youve', 'your', 'yours', 'yourself', 'yourselves',
        // Domain specific conversational noise
        'lost', 'found', 'item', 'please', 'thanks', 'thank', 'contact', 'help', 'left',
        'someone', 'anyone', 'around', 'near', 'holding', 'keep'
    ];

    /**
     * Get configured match score threshold.
     */
    public static function getThreshold(): float
    {
        return defined('MATCH_SCORE_THRESHOLD') ? (float) MATCH_SCORE_THRESHOLD : self::DEFAULT_THRESHOLD;
    }

    /**
     * Tokenize text: converts to lowercase, strips punctuation, removes stopwords,
     * and filters out single-character tokens.
     *
     * @param string|null $text Raw text
     * @return string[] Array of unique normalized token strings
     */
    public static function tokenizeText(?string $text): array
    {
        if (empty($text)) {
            return [];
        }

        // 1. Remove HTML tags
        $clean = strip_tags($text);

        // 2. Normalize to lowercase
        $clean = mb_strtolower($clean, 'UTF-8');

        // 3. Remove apostrophes so contractions like don't become dont
        $clean = str_replace(["'", "’"], '', $clean);

        // 4. Replace remaining non-alphanumeric characters with spaces
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $clean) ?? '';

        // 4. Split into word tokens
        $rawTokens = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // 5. Flip stopwords for O(1) isset lookups
        static $stopwordLookup = null;
        if ($stopwordLookup === null) {
            $stopwordLookup = array_fill_keys(self::STOPWORDS, true);
        }

        $filtered = [];
        foreach ($rawTokens as $token) {
            // Filter tokens: length >= 2 and not in stopword list
            if (mb_strlen($token, 'UTF-8') >= 2 && !isset($stopwordLookup[$token])) {
                $filtered[$token] = true;
            }
        }

        return array_keys($filtered);
    }

    /**
     * Factor 1: Category Match (Weight: 30 pts)
     * Exact category match = 30 pts; different = 0 pts.
     *
     * @param int $category1
     * @param int $category2
     * @return float
     */
    public static function calculateCategoryScore(int $category1, int $category2): float
    {
        if ($category1 > 0 && $category1 === $category2) {
            return 30.0;
        }
        return 0.0;
    }

    /**
     * Factor 2: Location Match (Weight: 25 pts)
     * Same building = 25 pts; same campus zone = 15 pts; different = 0 pts.
     *
     * @param string|null $bldg1
     * @param string|null $bldg2
     * @param string|null $zone1
     * @param string|null $zone2
     * @return array ['score' => float, 'type' => string]
     */
    public static function calculateLocationScore(?string $bldg1, ?string $bldg2, ?string $zone1, ?string $zone2): array
    {
        $bldg1Clean = trim(mb_strtolower($bldg1 ?? '', 'UTF-8'));
        $bldg2Clean = trim(mb_strtolower($bldg2 ?? '', 'UTF-8'));
        $zone1Clean = trim(mb_strtolower($zone1 ?? '', 'UTF-8'));
        $zone2Clean = trim(mb_strtolower($zone2 ?? '', 'UTF-8'));

        if ($bldg1Clean !== '' && $bldg1Clean === $bldg2Clean) {
            return [
                'score' => 25.0,
                'type'  => 'same_building'
            ];
        }

        if ($zone1Clean !== '' && $zone1Clean === $zone2Clean) {
            return [
                'score' => 15.0,
                'type'  => 'same_campus_zone'
            ];
        }

        return [
            'score' => 0.0,
            'type'  => 'different_location'
        ];
    }

    /**
     * Factor 3: Date Proximity (Weight: 20 pts)
     * Found date 0–3 days after lost date = 20 pts;
     * 4–7 days after = 12 pts;
     * > 7 days after = 5 pts;
     * found *before* lost date = 0 pts.
     *
     * @param string|null $lostDateStr ISO date (YYYY-MM-DD)
     * @param string|null $foundDateStr ISO date (YYYY-MM-DD)
     * @return array ['score' => float, 'days_diff' => ?int, 'status' => string]
     */
    public static function calculateDateScore(?string $lostDateStr, ?string $foundDateStr): array
    {
        if (empty($lostDateStr) || empty($foundDateStr)) {
            return [
                'score'     => 0.0,
                'days_diff' => null,
                'status'    => 'missing_date'
            ];
        }

        $lostTs = strtotime($lostDateStr);
        $foundTs = strtotime($foundDateStr);

        if ($lostTs === false || $foundTs === false) {
            return [
                'score'     => 0.0,
                'days_diff' => null,
                'status'    => 'invalid_date'
            ];
        }

        // Days elapsed: Found date - Lost date
        $diffSeconds = $foundTs - $lostTs;
        $diffDays = (int) round($diffSeconds / 86400);

        if ($diffDays < 0) {
            // Found before lost is logically contradictory for the same incident
            return [
                'score'     => 0.0,
                'days_diff' => $diffDays,
                'status'    => 'found_before_lost'
            ];
        }

        if ($diffDays <= 3) {
            return [
                'score'     => 20.0,
                'days_diff' => $diffDays,
                'status'    => '0_to_3_days'
            ];
        }

        if ($diffDays <= 7) {
            return [
                'score'     => 12.0,
                'days_diff' => $diffDays,
                'status'    => '4_to_7_days'
            ];
        }

        return [
            'score'     => 5.0,
            'days_diff' => $diffDays,
            'status'    => 'more_than_7_days'
        ];
    }

    /**
     * Factor 4: Keyword / Title Overlap (Weight: 25 pts)
     * Tokenization after stopword filtering:
     * KeywordScore = 25 * (|Tokens_lost ∩ Tokens_found| / |Tokens_lost ∪ Tokens_found|)
     *
     * @param string $textLost
     * @param string $textFound
     * @return array
     */
    public static function calculateKeywordScore(string $textLost, string $textFound): array
    {
        $tokensLost = self::tokenizeText($textLost);
        $tokensFound = self::tokenizeText($textFound);

        if (empty($tokensLost) || empty($tokensFound)) {
            return [
                'score'         => 0.0,
                'jaccard'       => 0.0,
                'common_tokens' => [],
                'tokens_lost'   => $tokensLost,
                'tokens_found'  => $tokensFound
            ];
        }

        $intersection = array_intersect($tokensLost, $tokensFound);
        $union = array_unique(array_merge($tokensLost, $tokensFound));

        $countIntersect = count($intersection);
        $countUnion = count($union);

        if ($countUnion === 0) {
            return [
                'score'         => 0.0,
                'jaccard'       => 0.0,
                'common_tokens' => [],
                'tokens_lost'   => $tokensLost,
                'tokens_found'  => $tokensFound
            ];
        }

        $jaccard = $countIntersect / $countUnion;
        $score = round(25.0 * $jaccard, 2);

        return [
            'score'         => $score,
            'jaccard'       => round($jaccard, 4),
            'common_tokens' => array_values($intersection),
            'tokens_lost'   => $tokensLost,
            'tokens_found'  => $tokensFound
        ];
    }

    /**
     * Compute comprehensive 4-factor heuristic similarity score between a lost item and a found item.
     *
     * @param array $lostItem Associative array containing lost item data
     * @param array $foundItem Associative array containing found item data
     * @return array Structure containing total score and detailed factor breakdown
     */
    public static function calculateScore(array $lostItem, array $foundItem): array
    {
        // 1. Category Score (max 30)
        $catScore = self::calculateCategoryScore(
            (int) ($lostItem['category_id'] ?? 0),
            (int) ($foundItem['category_id'] ?? 0)
        );

        // 2. Location Score (max 25)
        $locResult = self::calculateLocationScore(
            $lostItem['building'] ?? ($lostItem['location_building'] ?? null),
            $foundItem['building'] ?? ($foundItem['location_building'] ?? null),
            $lostItem['campus_zone'] ?? ($lostItem['location_zone'] ?? null),
            $foundItem['campus_zone'] ?? ($foundItem['location_zone'] ?? null)
        );

        // 3. Date Proximity Score (max 20)
        $dateResult = self::calculateDateScore(
            $lostItem['incident_date'] ?? null,
            $foundItem['incident_date'] ?? null
        );

        // 4. Keyword / Title Overlap (max 25)
        // Public title + description only. Identifying features are STRICTLY EXCLUDED.
        $textLost = ($lostItem['title'] ?? '') . ' ' . ($lostItem['description'] ?? '');
        $textFound = ($foundItem['title'] ?? '') . ' ' . ($foundItem['description'] ?? '');
        $kwResult = self::calculateKeywordScore($textLost, $textFound);

        // Total Bounded Score (0.00 to 100.00)
        $totalRaw = $catScore + $locResult['score'] + $dateResult['score'] + $kwResult['score'];
        $totalScore = (float) max(0.0, min(100.0, round($totalRaw, 2)));

        $threshold = self::getThreshold();

        return [
            'total_score'     => $totalScore,
            'threshold'       => $threshold,
            'meets_threshold' => ($totalScore >= $threshold),
            'breakdown'       => [
                'category' => $catScore,
                'location' => $locResult['score'],
                'date'     => $dateResult['score'],
                'keyword'  => $kwResult['score'],
                'total'    => $totalScore,
                'factors'  => [
                    'category_match'       => ($catScore > 0),
                    'location_match_type'  => $locResult['type'],
                    'date_difference_days' => $dateResult['days_diff'],
                    'date_status'          => $dateResult['status'],
                    'common_keywords'      => $kwResult['common_tokens'],
                    'jaccard_similarity'   => $kwResult['jaccard']
                ]
            ]
        ];
    }

    /**
     * Find, score, and persist potential matches for a given item.
     * Evaluates candidates of the opposite item_type having status = 'open'.
     *
     * @param int $itemId The target item ID (lost or found)
     * @param bool $dispatchNotifications Whether to send notifications for new matches
     * @return int Number of matches found meeting or exceeding MATCH_SCORE_THRESHOLD
     */
    public static function findMatchesForItem(int $itemId, bool $dispatchNotifications = true): int
    {
        if ($itemId <= 0) {
            return 0;
        }

        try {
            $pdo = get_db_connection();

            // 1. Retrieve the target item with category & location metadata
            $stmt = $pdo->prepare(
                'SELECT i.*, c.name AS category_name, l.building, l.campus_zone, l.specific_area
                 FROM items i
                 JOIN categories c ON i.category_id = c.id
                 JOIN locations l ON i.location_id = l.id
                 WHERE i.id = :id
                 LIMIT 1'
            );
            $stmt->execute([':id' => $itemId]);
            $targetItem = $stmt->fetch();

            if (!$targetItem || $targetItem['status'] !== 'open') {
                return 0;
            }

            $targetType = $targetItem['item_type'];
            $oppositeType = ($targetType === 'lost') ? 'found' : 'lost';

            // 2. Fetch all eligible open opposite-type items
            $oppStmt = $pdo->prepare(
                'SELECT i.*, c.name AS category_name, l.building, l.campus_zone, l.specific_area
                 FROM items i
                 JOIN categories c ON i.category_id = c.id
                 JOIN locations l ON i.location_id = l.id
                 WHERE i.item_type = :type AND i.status = "open" AND i.id != :target_id
                 ORDER BY i.incident_date DESC'
            );
            $oppStmt->execute([
                ':type'      => $oppositeType,
                ':target_id' => $itemId
            ]);
            $candidates = $oppStmt->fetchAll();

            if (empty($candidates)) {
                return 0;
            }

            // 3. Prepare queries for match lookup, upsert, and deletion
            $checkMatchStmt = $pdo->prepare(
                'SELECT id, match_score, status FROM smart_matches WHERE lost_item_id = :lost_id AND found_item_id = :found_id LIMIT 1'
            );

            $upsertStmt = $pdo->prepare(
                'INSERT INTO smart_matches
                    (lost_item_id, found_item_id, match_score, score_breakdown, status, calculated_at)
                 VALUES
                    (:lost_id, :found_id, :score, :breakdown, :status, NOW())
                 ON DUPLICATE KEY UPDATE
                    match_score     = VALUES(match_score),
                    score_breakdown = VALUES(score_breakdown),
                    calculated_at   = NOW()'
            );

            $deleteStmt = $pdo->prepare(
                'DELETE FROM smart_matches WHERE lost_item_id = :lost_id AND found_item_id = :found_id'
            );

            $threshold = self::getThreshold();
            $qualifyingMatchesCount = 0;
            $newMatchesToNotify = [];

            $pdo->beginTransaction();

            try {
                foreach ($candidates as $candidate) {
                    // Assign canonical orientation: lost_item_id always refers to lost report
                    if ($targetType === 'lost') {
                        $lostItem = $targetItem;
                        $foundItem = $candidate;
                    } else {
                        $lostItem = $candidate;
                        $foundItem = $targetItem;
                    }

                    $lostId = (int) $lostItem['id'];
                    $foundId = (int) $foundItem['id'];

                    // Compute 4-factor score
                    $result = self::calculateScore($lostItem, $foundItem);
                    $score = $result['total_score'];
                    $breakdownJson = json_encode($result['breakdown'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                    // Check existing match record
                    $checkMatchStmt->execute([
                        ':lost_id'  => $lostId,
                        ':found_id' => $foundId
                    ]);
                    $existing = $checkMatchStmt->fetch();

                    if ($score >= $threshold) {
                        $qualifyingMatchesCount++;

                        $currentStatus = $existing ? $existing['status'] : 'suggested';
                        // Keep viewed/confirmed status if already set; otherwise 'suggested'
                        if (!$existing || $currentStatus === 'dismissed') {
                            $currentStatus = 'suggested';
                        }

                        $upsertStmt->execute([
                            ':lost_id'   => $lostId,
                            ':found_id'  => $foundId,
                            ':score'     => $score,
                            ':breakdown' => $breakdownJson,
                            ':status'    => $currentStatus
                        ]);

                        // If brand new match, queue for in-app notification
                        if (!$existing && $dispatchNotifications) {
                            $newMatchesToNotify[] = [
                                'lost_user_id'  => (int) $lostItem['user_id'],
                                'found_user_id' => (int) $foundItem['user_id'],
                                'lost_id'       => $lostId,
                                'found_id'      => $foundId,
                                'lost_title'    => $lostItem['title'],
                                'found_title'   => $foundItem['title'],
                                'score'         => $score
                            ];
                        }
                    } else {
                        // Score below threshold: if record previously existed, remove it so non-matches are clean
                        if ($existing) {
                            $deleteStmt->execute([
                                ':lost_id'  => $lostId,
                                ':found_id' => $foundId
                            ]);
                        }
                    }
                }

                $pdo->commit();

            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            // 4. Dispatch match notifications to both item reporters
            if (!empty($newMatchesToNotify)) {
                foreach ($newMatchesToNotify as $n) {
                    // Alert the owner of the lost item
                    NotificationService::send(
                        $n['lost_user_id'],
                        'Potential Match Found',
                        sprintf(
                            "A found report '%s' may match your lost item '%s' (similarity score: %.1f pts).",
                            mb_substr($n['found_title'], 0, 40),
                            mb_substr($n['lost_title'], 0, 40),
                            $n['score']
                        ),
                        'match_found',
                        $n['found_id'],
                        '/item-details.php?id=' . $n['found_id']
                    );

                    // Alert the reporter of the found item
                    NotificationService::send(
                        $n['found_user_id'],
                        'Potential Match Found',
                        sprintf(
                            "A lost item report '%s' corresponds closely to what you found ('%s') (score: %.1f pts).",
                            mb_substr($n['lost_title'], 0, 40),
                            mb_substr($n['found_title'], 0, 40),
                            $n['score']
                        ),
                        'match_found',
                        $n['lost_id'],
                        '/item-details.php?id=' . $n['found_id']
                    );
                }
            }

            return $qualifyingMatchesCount;

        } catch (Throwable $e) {
            error_log('MatchingService::findMatchesForItem error for item ' . $itemId . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Retrieve cached matches from `smart_matches` for a specific item ($O(1) indexed lookup).
     * Masks private identifying_features and contact information for non-authorized viewers.
     *
     * @param int $itemId Target item ID
     * @param int|null $currentUserId Logged in user ID (optional)
     * @param bool $isAdmin Whether the caller is an administrator
     * @return array List of matched items with scores and breakdowns
     */
    public static function getMatchesForItem(int $itemId, ?int $currentUserId = null, bool $isAdmin = false): array
    {
        if ($itemId <= 0) {
            return [];
        }

        try {
            $pdo = get_db_connection();

            // First get target item type
            $stmt = $pdo->prepare('SELECT item_type FROM items WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $itemId]);
            $itemType = $stmt->fetchColumn();

            if (!$itemType) {
                return [];
            }

            if ($itemType === 'lost') {
                // Return matched found items
                $query = '
                    SELECT
                        m.id AS match_id,
                        m.lost_item_id,
                        m.found_item_id,
                        m.match_score,
                        m.score_breakdown,
                        m.status AS match_status,
                        m.calculated_at,
                        i.id AS item_id,
                        i.user_id,
                        i.item_type,
                        i.category_id,
                        i.location_id,
                        i.title,
                        i.description,
                        i.identifying_features,
                        i.incident_date,
                        i.incident_time,
                        i.image_path,
                        i.custody_status,
                        i.status AS item_status,
                        i.created_at,
                        c.name AS category_name,
                        c.icon AS category_icon,
                        l.building AS location_building,
                        l.campus_zone AS location_zone,
                        l.specific_area AS location_area,
                        u.full_name AS reporter_name
                    FROM smart_matches m
                    JOIN items i ON m.found_item_id = i.id
                    JOIN categories c ON i.category_id = c.id
                    JOIN locations l ON i.location_id = l.id
                    LEFT JOIN users u ON i.user_id = u.id
                    WHERE m.lost_item_id = :item_id AND i.status = "open"
                    ORDER BY m.match_score DESC';
            } else {
                // Return matched lost items
                $query = '
                    SELECT
                        m.id AS match_id,
                        m.lost_item_id,
                        m.found_item_id,
                        m.match_score,
                        m.score_breakdown,
                        m.status AS match_status,
                        m.calculated_at,
                        i.id AS item_id,
                        i.user_id,
                        i.item_type,
                        i.category_id,
                        i.location_id,
                        i.title,
                        i.description,
                        i.identifying_features,
                        i.incident_date,
                        i.incident_time,
                        i.image_path,
                        i.custody_status,
                        i.status AS item_status,
                        i.created_at,
                        c.name AS category_name,
                        c.icon AS category_icon,
                        l.building AS location_building,
                        l.campus_zone AS location_zone,
                        l.specific_area AS location_area,
                        u.full_name AS reporter_name
                    FROM smart_matches m
                    JOIN items i ON m.lost_item_id = i.id
                    JOIN categories c ON i.category_id = c.id
                    JOIN locations l ON i.location_id = l.id
                    LEFT JOIN users u ON i.user_id = u.id
                    WHERE m.found_item_id = :item_id AND i.status = "open"
                    ORDER BY m.match_score DESC';
            }

            $mStmt = $pdo->prepare($query);
            $mStmt->execute([':item_id' => $itemId]);
            $rows = $mStmt->fetchAll();

            $results = [];
            foreach ($rows as $row) {
                // Decode JSON breakdown
                $breakdown = null;
                if (!empty($row['score_breakdown'])) {
                    $breakdown = json_decode($row['score_breakdown'], true);
                }

                // Privacy protection: identifying_features is strictly hidden from unauthorized callers
                $isReporter = $currentUserId && ((int)$row['user_id'] === $currentUserId);
                $canViewPrivate = $isReporter || $isAdmin;

                if (!$canViewPrivate) {
                    $row['identifying_features'] = null;
                    if (!$isAdmin) {
                        unset($row['user_id']);
                    }
                }

                $row['match_score'] = (float) $row['match_score'];
                $row['score_breakdown'] = $breakdown;
                $row['score_label'] = 'Heuristic Similarity Score (0-100)';
                $row['image_url'] = !empty($row['image_path']) ? url($row['image_path']) : null;

                $results[] = $row;
            }

            return $results;

        } catch (Throwable $e) {
            error_log('MatchingService::getMatchesForItem error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Recalculate matches for an item on-demand (e.g., when edited or refreshed).
     *
     * @param int $itemId Target item ID
     * @return array [ 'success' => bool, 'matches_found' => int, 'matches' => array ]
     */
    public static function recalculateMatchesForItem(int $itemId): array
    {
        $count = self::findMatchesForItem($itemId, false);
        $matches = self::getMatchesForItem($itemId);

        return [
            'success'       => true,
            'item_id'       => $itemId,
            'matches_found' => $count,
            'matches'       => $matches
        ];
    }

    /**
     * Handle item status change (e.g. claimed, closed, rejected).
     * Automatically dismisses or cleans up active matches to prevent stale links.
     *
     * @param int $itemId
     * @param string $newStatus
     * @return void
     */
    public static function handleItemStatusChange(int $itemId, string $newStatus): void
    {
        if ($itemId <= 0) {
            return;
        }

        try {
            $pdo = get_db_connection();
            if (in_array($newStatus, ['claimed', 'closed', 'rejected'], true)) {
                // Stale matches should be dismissed or removed
                $stmt = $pdo->prepare(
                    'UPDATE smart_matches
                     SET status = "dismissed"
                     WHERE (lost_item_id = :id1 OR found_item_id = :id2) AND status = "suggested"'
                );
                $stmt->execute([
                    ':id1' => $itemId,
                    ':id2' => $itemId
                ]);
            }
        } catch (Throwable $e) {
            error_log('MatchingService::handleItemStatusChange error: ' . $e->getMessage());
        }
    }
}
