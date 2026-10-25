<?php
/**
 * Smart College Lost and Found System (SCLFS)
 * NotificationService — In-App Notification Dispatcher
 *
 * Usage:
 *   NotificationService::send($userId, 'Title', 'Message body', 'claim_status', $refId, '/item-details.php?id=5');
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class NotificationService
{
    /** Valid notification types matching the DB ENUM */
    private const VALID_TYPES = [
        'match_found',
        'claim_received',
        'claim_status',
        'custody_update',
        'admin_alert',
        'system',
    ];

    /**
     * Insert a single in-app notification for a user.
     *
     * @param int         $userId       Recipient user ID
     * @param string      $title        Notification headline (max 150 chars)
     * @param string      $message      Notification body text
     * @param string      $type         ENUM value from VALID_TYPES
     * @param int|null    $referenceId  Related entity ID (item, claim, etc.)
     * @param string|null $referenceUrl Deep link URL for navigation
     * @return bool
     */
    public static function send(
        int     $userId,
        string  $title,
        string  $message,
        string  $type        = 'system',
        ?int    $referenceId = null,
        ?string $referenceUrl = null,
        bool    $deduplicate  = true
    ): bool {
        if (!in_array($type, self::VALID_TYPES, true)) {
            error_log("NotificationService::send — invalid type '{$type}' for user {$userId}");
            return false;
        }

        try {
            $pdo  = get_db_connection();
            if ($deduplicate) {
                $duplicateStmt = $pdo->prepare(
                    'SELECT id FROM notifications
                     WHERE user_id = :uid AND title = :title AND type = :type
                       AND reference_id ' . ($referenceId === null ? 'IS NULL' : '= :ref') . '
                     LIMIT 1'
                );
                $duplicateParams = [
                    ':uid'   => $userId,
                    ':title' => mb_substr($title, 0, 150),
                    ':type'  => $type,
                ];
                if ($referenceId !== null) {
                    $duplicateParams[':ref'] = $referenceId;
                }
                $duplicateStmt->execute($duplicateParams);
                if ($duplicateStmt->fetchColumn()) {
                    return true;
                }
            }

            $stmt = $pdo->prepare(
                'INSERT INTO notifications (user_id, title, message, type, reference_id, reference_url)
                 VALUES (:uid, :title, :msg, :type, :ref, :url)'
            );
            $stmt->execute([
                ':uid'   => $userId,
                ':title' => mb_substr($title, 0, 150),
                ':msg'   => $message,
                ':type'  => $type,
                ':ref'   => $referenceId,
                ':url'   => $referenceUrl,
            ]);
            return true;
        } catch (Throwable $e) {
            error_log("NotificationService::send failed for user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send the same notification to multiple users at once.
     *
     * @param int[]       $userIds      Array of recipient user IDs
     * @param string      $title
     * @param string      $message
     * @param string      $type
     * @param int|null    $referenceId
     * @param string|null $referenceUrl
     */
    public static function sendToMany(
        array   $userIds,
        string  $title,
        string  $message,
        string  $type        = 'system',
        ?int    $referenceId = null,
        ?string $referenceUrl = null
    ): void {
        foreach ($userIds as $uid) {
            self::send((int) $uid, $title, $message, $type, $referenceId, $referenceUrl);
        }
    }

    /**
     * Return the unread notification count for a given user.
     *
     * @param int $userId
     * @return int
     */
    public static function getUnreadCount(int $userId): int
    {
        $pdo  = get_db_connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
