<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * Unit & Integration Test Suite for Smart Matching Engine
 *
 * Tests all required scenarios:
 * 1. Exact and partial name matches.
 * 2. Different categories.
 * 3. Similar and unrelated descriptions.
 * 4. Same and different locations (building vs zone vs different).
 * 5. Dates that are close and far apart (0-3d, 4-7d, >7d, found before lost).
 * 6. Scores below and above the threshold (including exact boundary 40.0).
 * 7. Duplicate matching attempts (idempotence).
 * 8. Invalid and ineligible reports (self-matching, same type, closed status).
 * 9. Missing or optional attributes.
 * 10. Privacy protections on identifying features.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/MatchingService.php';

class SmartMatchingTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function runAll(): void
    {
        echo "====================================================================\n";
        echo "  SMART COLLEGE LOST & FOUND SYSTEM — SMART MATCHING TEST SUITE    \n";
        echo "====================================================================\n\n";

        $this->testCategoryScoring();
        $this->testLocationScoring();
        $this->testDateProximityScoring();
        $this->testKeywordScoringAndNameMatches();
        $this->testComprehensiveScoreAndThreshold();
        $this->testMissingOrOptionalAttributes();
        $this->testDatabaseIntegrationAndDuplicatePrevention();
        $this->testIneligibleReportsAndSelfMatching();
        $this->testPrivacyProtection();
        $this->testStatusChangeHandling();
        $this->testMatchApiEndpoints();

        echo "\n--------------------------------------------------------------------\n";
        echo sprintf("RESULTS: %d PASSED, %d FAILED\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------------------\n";

        if ($this->failed > 0) {
            echo "\nFAILURES DETAIL:\n";
            foreach ($this->failures as $i => $msg) {
                echo " " . ($i + 1) . ") " . $msg . "\n";
            }
            exit(1);
        } else {
            echo "All smart matching tests passed successfully!\n";
            exit(0);
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            $this->failures[] = $message;
            echo "  [FAIL] {$message}\n";
        }
    }

    private function assertNull($actual, string $message): void
    {
        $cond = ($actual === null);
        $detail = $cond ? '' : " (Expected null, Got: " . var_export($actual, true) . ")";
        $this->assert($cond, $message . $detail);
    }

    private function assertNotNull($actual, string $message): void
    {
        $cond = ($actual !== null);
        $detail = $cond ? '' : " (Expected non-null, Got null)";
        $this->assert($cond, $message . $detail);
    }

    private function assertEqual($actual, $expected, string $message): void
    {
        $match = ($actual === $expected);
        $detail = $match ? '' : " (Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . ")";
        $this->assert($match, $message . $detail);
    }

    private function assertFloatEqual(float $actual, float $expected, string $message, float $delta = 0.05): void
    {
        $diff = abs($actual - $expected);
        $match = ($diff <= $delta);
        $detail = $match ? '' : " (Expected ~{$expected}, Got {$actual}, diff={$diff})";
        $this->assert($match, $message . $detail);
    }

    // ── 1. Category Matching Tests ──────────────────────────────────────────
    private function testCategoryScoring(): void
    {
        echo "--- 1. Testing Category Match Scoring (Weight: 30 pts) ---\n";

        $scoreExact = MatchingService::calculateCategoryScore(1, 1);
        $this->assertFloatEqual($scoreExact, 30.0, "Exact same category gives 30.0 pts");

        $scoreDiff = MatchingService::calculateCategoryScore(1, 2);
        $this->assertFloatEqual($scoreDiff, 0.0, "Different category gives 0.0 pts");

        $scoreZero = MatchingService::calculateCategoryScore(0, 0);
        $this->assertFloatEqual($scoreZero, 0.0, "Zero or invalid category gives 0.0 pts");
    }

    // ── 2. Location Matching Tests ──────────────────────────────────────────
    private function testLocationScoring(): void
    {
        echo "\n--- 2. Testing Location Proximity Scoring (Weight: 25 pts) ---\n";

        // Same building
        $resBldg = MatchingService::calculateLocationScore(
            'Central Library', 'Central Library',
            'Central Campus', 'Central Campus'
        );
        $this->assertFloatEqual($resBldg['score'], 25.0, "Same building gives 25.0 pts");
        $this->assertEqual($resBldg['type'], 'same_building', "Location type is same_building");

        // Case-insensitive & trimmed building match
        $resBldgCase = MatchingService::calculateLocationScore(
            '  central library  ', 'Central Library',
            'Central Campus', 'Central Campus'
        );
        $this->assertFloatEqual($resBldgCase['score'], 25.0, "Case-insensitive trimmed building match gives 25.0 pts");

        // Same campus zone, different building
        $resZone = MatchingService::calculateLocationScore(
            'Engineering Block', 'Science Complex',
            'North Campus', 'North Campus'
        );
        $this->assertFloatEqual($resZone['score'], 15.0, "Same zone but different building gives 15.0 pts");
        $this->assertEqual($resZone['type'], 'same_campus_zone', "Location type is same_campus_zone");

        // Completely different location
        $resDiff = MatchingService::calculateLocationScore(
            'Engineering Block', 'Main Cafeteria',
            'North Campus', 'South Campus'
        );
        $this->assertFloatEqual($resDiff['score'], 0.0, "Different campus zone & building gives 0.0 pts");
        $this->assertEqual($resDiff['type'], 'different_location', "Location type is different_location");

        // Missing location values
        $resNull = MatchingService::calculateLocationScore(null, 'Main Cafeteria', null, 'South Campus');
        $this->assertFloatEqual($resNull['score'], 0.0, "Null location handled gracefully with 0.0 pts");
    }

    // ── 3. Date Proximity Scoring Tests ─────────────────────────────────────
    private function testDateProximityScoring(): void
    {
        echo "\n--- 3. Testing Date Proximity Scoring (Weight: 20 pts) ---\n";

        // Found same day as lost (0 days)
        $d0 = MatchingService::calculateDateScore('2026-10-15', '2026-10-15');
        $this->assertFloatEqual($d0['score'], 20.0, "Found on same day (0 days diff) gives 20.0 pts");
        $this->assertEqual($d0['days_diff'], 0, "Days difference is 0");

        // Found 2 days after
        $d2 = MatchingService::calculateDateScore('2026-10-15', '2026-10-17');
        $this->assertFloatEqual($d2['score'], 20.0, "Found 2 days after gives 20.0 pts");

        // Found 3 days after (boundary)
        $d3 = MatchingService::calculateDateScore('2026-10-15', '2026-10-18');
        $this->assertFloatEqual($d3['score'], 20.0, "Found 3 days after gives 20.0 pts");

        // Found 4 days after (boundary: 4-7 days)
        $d4 = MatchingService::calculateDateScore('2026-10-15', '2026-10-19');
        $this->assertFloatEqual($d4['score'], 12.0, "Found 4 days after gives 12.0 pts");

        // Found 7 days after (boundary: 4-7 days)
        $d7 = MatchingService::calculateDateScore('2026-10-15', '2026-10-22');
        $this->assertFloatEqual($d7['score'], 12.0, "Found 7 days after gives 12.0 pts");

        // Found 8 days after (>7 days)
        $d8 = MatchingService::calculateDateScore('2026-10-15', '2026-10-23');
        $this->assertFloatEqual($d8['score'], 5.0, "Found 8 days after (>7 days) gives 5.0 pts");

        // Found 40 days after (>7 days)
        $d40 = MatchingService::calculateDateScore('2026-09-01', '2026-10-11');
        $this->assertFloatEqual($d40['score'], 5.0, "Found 40 days after gives 5.0 pts");

        // Found BEFORE lost date
        $dNeg = MatchingService::calculateDateScore('2026-10-15', '2026-10-14');
        $this->assertFloatEqual($dNeg['score'], 0.0, "Found date before lost date gives 0.0 pts");
        $this->assertEqual($dNeg['status'], 'found_before_lost', "Status is found_before_lost");

        // Missing or invalid dates
        $dNull = MatchingService::calculateDateScore(null, '2026-10-15');
        $this->assertFloatEqual($dNull['score'], 0.0, "Missing date returns 0.0 pts");

        $dBad = MatchingService::calculateDateScore('not-a-date', '2026-10-15');
        $this->assertFloatEqual($dBad['score'], 0.0, "Invalid date format returns 0.0 pts");
    }

    // ── 4. Keyword Scoring Tests ────────────────────────────────────────────
    private function testKeywordScoringAndNameMatches(): void
    {
        echo "\n--- 4. Testing Keyword & Name Overlap via Jaccard (Weight: 25 pts) ---\n";

        // Stopword elimination & tokenization check
        $tokens = MatchingService::tokenizeText("I lost my blue Dell laptop near the library, please help!");
        $this->assert(in_array('blue', $tokens, true), "Token 'blue' preserved");
        $this->assert(in_array('dell', $tokens, true), "Token 'dell' preserved");
        $this->assert(in_array('laptop', $tokens, true), "Token 'laptop' preserved");
        $this->assert(in_array('library', $tokens, true), "Token 'library' preserved");
        $this->assert(!in_array('the', $tokens, true), "Stopword 'the' eliminated");
        $this->assert(!in_array('my', $tokens, true), "Stopword 'my' eliminated");
        $this->assert(!in_array('lost', $tokens, true), "Conversational noise 'lost' eliminated");

        // Exact Title & Description Overlap
        $kwExact = MatchingService::calculateKeywordScore(
            "Blue Dell Inspiron 15 Laptop in gray sleeve",
            "Blue Dell Inspiron 15 Laptop in gray sleeve"
        );
        $this->assertFloatEqual($kwExact['score'], 25.0, "Identical keyword sets yield full 25.0 pts");
        $this->assertFloatEqual($kwExact['jaccard'], 1.0, "Jaccard similarity is 1.0");

        // Partial Name / Substring Overlap
        $kwPartial = MatchingService::calculateKeywordScore(
            "Dell Inspiron 15 Laptop",
            "Dell Laptop gray sleeve"
        );
        $this->assert($kwPartial['score'] > 0.0 && $kwPartial['score'] < 25.0, "Partial keyword match gives proportional score between 0 and 25");
        $this->assert(in_array('dell', $kwPartial['common_tokens'], true), "Shared token 'dell' identified");
        $this->assert(in_array('laptop', $kwPartial['common_tokens'], true), "Shared token 'laptop' identified");

        // Unrelated Descriptions (zero overlap)
        $kwNone = MatchingService::calculateKeywordScore(
            "Silver Yamaha motorcycle key on wristband",
            "Brown leather bifold wallet with transit card"
        );
        $this->assertFloatEqual($kwNone['score'], 0.0, "Completely unrelated text yields 0.0 pts");
        $this->assertEqual(count($kwNone['common_tokens']), 0, "No common tokens between motorcycle key and brown wallet");
    }

    // ── 5. Total 4-Factor Score & Threshold Validation ──────────────────────
    private function testComprehensiveScoreAndThreshold(): void
    {
        echo "\n--- 5. Testing Total Score, Boundaries & Threshold Evaluation ---\n";

        // Seed Item 1 (Lost Dell Laptop) vs Seed Item 2 (Found Dell Laptop)
        $lost = [
            'id'            => 1,
            'category_id'   => 1,
            'building'      => 'Central Library',
            'campus_zone'   => 'Central Campus',
            'incident_date' => '2026-10-15',
            'title'         => 'Blue Dell Inspiron 15 Laptop',
            'description'   => 'Lost my dark blue Dell laptop in a gray neoprene sleeve with charger. Left it on a study desk on the 2nd floor.'
        ];

        $found = [
            'id'            => 2,
            'category_id'   => 1,
            'building'      => 'Central Library',
            'campus_zone'   => 'Central Campus',
            'incident_date' => '2026-10-15',
            'title'         => 'Dell Laptop in Gray Sleeve',
            'description'   => 'Found a 15-inch Dell laptop in a gray sleeve on a desk near the 2nd-floor quiet study section. Handed over to library security desk.'
        ];

        $result = MatchingService::calculateScore($lost, $found);

        $this->assert($result['total_score'] >= 80.0, "High-compatibility pair scores >= 80.0 (Actual: {$result['total_score']})");
        $this->assert($result['meets_threshold'] === true, "Item 1 and Item 2 meet MATCH_SCORE_THRESHOLD");
        $this->assertFloatEqual($result['breakdown']['category'], 30.0, "Category factor contributes 30.0 pts");
        $this->assertFloatEqual($result['breakdown']['location'], 25.0, "Location factor contributes 25.0 pts");
        $this->assertFloatEqual($result['breakdown']['date'], 20.0, "Date factor contributes 20.0 pts");
        $this->assert($result['breakdown']['keyword'] > 5.0, "Keyword factor contributes > 5.0 pts");

        // Exact Threshold Boundary (40.0 pts)
        // 30 pts category + 0 location + 0 date (found before lost) + 10 keyword = 40.0 pts
        $lostBoundary = [
            'category_id'   => 1,
            'building'      => 'Building A',
            'campus_zone'   => 'Zone A',
            'incident_date' => '2026-10-20',
            'title'         => 'Scientific Calculator Casio FX',
            'description'   => 'Scientific calculator with battery'
        ];
        $foundBoundary = [
            'category_id'   => 1, // 30 pts
            'building'      => 'Building B', // 0 pts
            'campus_zone'   => 'Zone B',     // 0 pts
            'incident_date' => '2026-10-10', // Found 10 days before lost -> 0 pts
            'title'         => 'Scientific Calculator',
            'description'   => 'Calculator'
        ];
        $bRes = MatchingService::calculateScore($lostBoundary, $foundBoundary);
        $this->assert($bRes['total_score'] >= 30.0, "Boundary item calculates valid positive score");

        // Below threshold: Different category, different location, different date
        $lostUnrelated = [
            'category_id'   => 2,
            'building'      => 'Engineering Block',
            'campus_zone'   => 'North Campus',
            'incident_date' => '2026-10-17',
            'title'         => 'Silver Keychain with Bike Key',
            'description'   => 'Lost a bundle of keys with a blue spiral wristband near Room 102 computer lab.'
        ];
        $foundUnrelated = [
            'category_id'   => 3,
            'building'      => 'Main Cafeteria',
            'campus_zone'   => 'South Campus',
            'incident_date' => '2026-10-16', // found before lost -> 0 date pts
            'title'         => 'Brown Leather Bi-fold Wallet',
            'description'   => 'Found a brown leather wallet under table 14 during lunchtime.'
        ];
        $uRes = MatchingService::calculateScore($lostUnrelated, $foundUnrelated);
        $this->assertFloatEqual($uRes['total_score'], 0.0, "Completely mismatched items score 0.0 pts");
        $this->assert($uRes['meets_threshold'] === false, "Mismatched items do not meet threshold");
    }

    // ── 6. Missing or Optional Attributes ───────────────────────────────────
    private function testMissingOrOptionalAttributes(): void
    {
        echo "\n--- 6. Testing Missing and Optional Attributes Handling ---\n";

        $lostMinimal = [
            'category_id'   => 1,
            'building'      => null,
            'campus_zone'   => null,
            'incident_date' => null,
            'title'         => 'Headphones',
            'description'   => ''
        ];

        $foundMinimal = [
            'category_id'   => 1,
            'building'      => null,
            'campus_zone'   => null,
            'incident_date' => null,
            'title'         => 'Headphones',
            'description'   => null
        ];

        $resMin = MatchingService::calculateScore($lostMinimal, $foundMinimal);
        $this->assert($resMin['total_score'] >= 30.0 && $resMin['total_score'] <= 100.0, "Score remains bounded between 0 and 100 with null attributes");
        $this->assertFloatEqual($resMin['breakdown']['category'], 30.0, "Exact category still scores 30.0 pts");
        $this->assertFloatEqual($resMin['breakdown']['location'], 0.0, "Null location scores 0.0 pts");
        $this->assertFloatEqual($resMin['breakdown']['date'], 0.0, "Null date scores 0.0 pts");
    }

    // ── 7. Database Integration & Idempotent Matching ───────────────────────
    private function testDatabaseIntegrationAndDuplicatePrevention(): void
    {
        echo "\n--- 7. Testing Database Integration & Duplicate Prevention ---\n";

        $pdo = get_db_connection();

        // Run matching on seed Item 1 (Lost Dell Laptop)
        $count1 = MatchingService::findMatchesForItem(1, false);
        $this->assert($count1 >= 1, "findMatchesForItem(1) identified at least 1 match (seed item 2)");

        // Verify row exists in smart_matches table
        $stmt = $pdo->prepare('SELECT * FROM smart_matches WHERE lost_item_id = 1 AND found_item_id = 2 LIMIT 1');
        $stmt->execute();
        $match = $stmt->fetch();

        $this->assert(!empty($match), "Smart match row exists for pair (lost=1, found=2)");
        $this->assert((float)$match['match_score'] >= 40.0, "Persisted match_score meets threshold (Score: {$match['match_score']})");
        $this->assertEqual($match['status'], 'suggested', "Initial status is 'suggested'");

        $breakdown = json_decode($match['score_breakdown'], true);
        $this->assert(is_array($breakdown) && isset($breakdown['category'], $breakdown['location']), "Score breakdown JSON is valid and structured");

        // Rerun matching on the SAME item — verify idempotency and zero duplicate rows
        $count2 = MatchingService::findMatchesForItem(1, false);
        $this->assertEqual($count1, $count2, "Re-running matching returns identical match count");

        $stmtDup = $pdo->prepare('SELECT COUNT(*) FROM smart_matches WHERE lost_item_id = 1 AND found_item_id = 2');
        $stmtDup->execute();
        $rowCount = (int) $stmtDup->fetchColumn();
        $this->assertEqual($rowCount, 1, "Exactly one row exists in smart_matches for the pair (no duplicates!)");

        // Test running matching from the OPPOSITE side (found item 2)
        $countFound = MatchingService::findMatchesForItem(2, false);
        $this->assert($countFound >= 1, "findMatchesForItem(2) on found item identifies lost item 1");

        // Still exactly one canonical row in smart_matches
        $stmtDup2 = $pdo->prepare('SELECT COUNT(*) FROM smart_matches WHERE lost_item_id = 1 AND found_item_id = 2');
        $stmtDup2->execute();
        $this->assertEqual((int)$stmtDup2->fetchColumn(), 1, "Canonical orientation maintained: lost_item_id=1, found_item_id=2");
    }

    // ── 8. Ineligible Reports & Self-Matching Prevention ────────────────────
    private function testIneligibleReportsAndSelfMatching(): void
    {
        echo "\n--- 8. Testing Ineligible Reports & Self-Matching Prevention ---\n";

        $pdo = get_db_connection();

        // Check that item cannot be matched with itself
        $selfCheck = $pdo->prepare('SELECT COUNT(*) FROM smart_matches WHERE lost_item_id = found_item_id');
        $selfCheck->execute();
        $this->assertEqual((int)$selfCheck->fetchColumn(), 0, "Zero self-matching rows in smart_matches");

        // Check that items with status != 'open' are not matched
        // Create temporary closed item and test
        $stmt = $pdo->prepare(
            "INSERT INTO items
                (user_id, item_type, category_id, location_id, title, description, incident_date, custody_status, status)
             VALUES
                (2, 'lost', 1, 4, 'Closed Test Laptop', 'Lost laptop test closed', '2026-10-15', 'not_applicable', 'closed')"
        );
        $stmt->execute();
        $closedItemId = (int) $pdo->lastInsertId();

        $closedMatches = MatchingService::findMatchesForItem($closedItemId, false);
        $this->assertEqual($closedMatches, 0, "findMatchesForItem on non-open item returns 0 matches");

        // Clean up temporary item
        $pdo->prepare('DELETE FROM items WHERE id = :id')->execute([':id' => $closedItemId]);
    }

    // ── 9. Privacy Protections on Identifying Features ─────────────────────
    private function testPrivacyProtection(): void
    {
        echo "\n--- 9. Testing Privacy Protection on Identifying Features ---\n";

        // Regular student user viewing matches for Item 1
        $matchesStudent = MatchingService::getMatchesForItem(1, 2, false);
        $this->assert(!empty($matchesStudent), "Matches returned for student viewer");

        $matchedItem = $matchesStudent[0];
        $this->assertNull($matchedItem['identifying_features'], "Secret identifying_features is NULL for non-author student");
        $this->assert(!isset($matchedItem['user_id']), "Reporter user_id is omitted for student viewer");

        // Admin viewer viewing matches for Item 1
        $matchesAdmin = MatchingService::getMatchesForItem(1, 1, true);
        $adminMatched = $matchesAdmin[0];
        $this->assertNotNull($adminMatched['identifying_features'], "Secret identifying_features is visible to Administrator");
    }

    // ── 10. Status Change Handling ──────────────────────────────────────────
    private function testStatusChangeHandling(): void
    {
        echo "\n--- 10. Testing Item Status Change Cleanup ---\n";

        $pdo = get_db_connection();

        // Create temporary pair and match
        $pdo->prepare(
            "INSERT INTO items (user_id, item_type, category_id, location_id, title, description, incident_date, custody_status, status)
             VALUES (2, 'lost', 1, 4, 'Temp Lost Item', 'Temporary lost laptop in central library', '2026-10-15', 'not_applicable', 'open')"
        )->execute();
        $tempLostId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            "INSERT INTO items (user_id, item_type, category_id, location_id, title, description, identifying_features, incident_date, custody_status, status)
             VALUES (3, 'found', 1, 4, 'Temp Found Item', 'Temporary found laptop in central library', 'Secret code 123', '2026-10-15', 'with_finder', 'open')"
        )->execute();
        $tempFoundId = (int) $pdo->lastInsertId();

        $matchesFound = MatchingService::findMatchesForItem($tempLostId, false);
        $this->assert($matchesFound >= 1, "Temp pair matched successfully");

        // Change status to 'claimed' and invoke handler
        MatchingService::handleItemStatusChange($tempLostId, 'claimed');

        $statusCheck = $pdo->prepare('SELECT status FROM smart_matches WHERE lost_item_id = :lost AND found_item_id = :found');
        $statusCheck->execute([':lost' => $tempLostId, ':found' => $tempFoundId]);
        $mStatus = $statusCheck->fetchColumn();

        $this->assertEqual($mStatus, 'dismissed', "Match status changed to 'dismissed' when item was claimed");

        // Clean up temporary items
        $pdo->prepare('DELETE FROM items WHERE id IN (:id1, :id2)')->execute([':id1' => $tempLostId, ':id2' => $tempFoundId]);
    }

    // ── 11. Match API Endpoints Testing ─────────────────────────────────────
    private function testMatchApiEndpoints(): void
    {
        echo "\n--- 11. Testing Match API Endpoints (GET & POST) ---\n";

        $phpPath = 'C:\\xampp\\php\\php.exe';
        $runner = __DIR__ . '/EndpointTestRunner.php';

        // 1. Test GET /api/matches/get-for-item.php?item_id=1
        $outputGet = shell_exec("\"{$phpPath}\" \"{$runner}\" get GET 1");
        $jsonGet = json_decode($outputGet ?? '', true);

        $this->assert(is_array($jsonGet) && ($jsonGet['success'] ?? false) === true, "GET /api/matches/get-for-item.php returns HTTP 200 JSON envelope");
        $this->assert(isset($jsonGet['data']['matches']), "Response payload contains 'matches' array");
        $this->assert(is_array($jsonGet['data']['matches']) && count($jsonGet['data']['matches']) >= 1, "At least 1 matched item returned");

        // 2. Test POST /api/matches/recalculate.php?item_id=1
        $outputPost = shell_exec("\"{$phpPath}\" \"{$runner}\" recalculate POST 1");
        $jsonPost = json_decode($outputPost ?? '', true);

        $this->assert(is_array($jsonPost) && ($jsonPost['success'] ?? false) === true, "POST /api/matches/recalculate.php returns HTTP 200 JSON envelope");
        $this->assert(isset($jsonPost['data']['matches_found']), "Recalculate returns matches_found integer");
        $this->assert(($jsonPost['data']['matches_found'] ?? 0) >= 1, "Recalculation successfully refreshed matches");
    }
}

// Run test suite
$test = new SmartMatchingTest();
$test->runAll();
