<?php
/* ══════════════════════════════════════════
   Returns coupons the current visitor is
   actually eligible to see/use right now:
   - active
   - not expired
   - not past usage_limit
   - public (user_id IS NULL) OR targeted at this user_id
══════════════════════════════════════════ */
function get_available_coupons(PDO $conn, ?int $user_id): array
{
    if ($user_id) {
        $stmt = $conn->prepare("
            SELECT code, type, value, min_order, expires_at, user_id
            FROM coupons
            WHERE active = 1
              AND (expires_at IS NULL OR expires_at > NOW())
              AND (usage_limit IS NULL OR used_count < usage_limit)
              AND (user_id IS NULL OR user_id = ?)
            ORDER BY (user_id IS NOT NULL) DESC, min_order ASC
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $conn->prepare("
            SELECT code, type, value, min_order, expires_at, user_id
            FROM coupons
            WHERE active = 1
              AND user_id IS NULL
              AND (expires_at IS NULL OR expires_at > NOW())
              AND (usage_limit IS NULL OR used_count < usage_limit)
            ORDER BY min_order ASC
        ");
        $stmt->execute();
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}