<?php
/**
 * get_shipping_quote()
 *
 * Looks up the delivery fee for a city from `shipping_zones`.
 *
 * Resolution order:
 *   1. An active zone matching $city exactly.
 *   2. The active zone flagged is_default = 1 (fallback for any city
 *      that isn't configured yet, or whose zone was deactivated).
 *   3. A hardcoded safety net (€4.99 / free at €50), only if the
 *      shipping_zones table is empty or has no default zone at all —
 *      this keeps checkout from breaking on a fresh install.
 *
 * @return array{
 *   fee: float,          Final fee to charge (0.00 if this order qualifies for free shipping)
 *   base_fee: float,     The zone's fee before any free-shipping override
 *   threshold: ?float,   Free-shipping subtotal threshold used, or null if this zone never waives shipping
 *   zone_city: ?string,  Which zone actually matched ('Yangon', etc.), or null if the hardcoded fallback fired
 *   is_fallback: bool,   True if no shipping_zones row was found at all
 * }
 */
function get_shipping_quote(PDO $conn, ?string $city, float $subtotal): array
{
    $zone = null;

    if ($city !== null && $city !== '') {
        $stmt = $conn->prepare("
            SELECT city, fee, free_shipping_threshold
            FROM shipping_zones
            WHERE city = ? AND active = 1
            LIMIT 1
        ");
        $stmt->execute([$city]);
        $zone = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$zone) {
        $zone = $conn->query("
            SELECT city, fee, free_shipping_threshold
            FROM shipping_zones
            WHERE is_default = 1 AND active = 1
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);
    }

    if (!$zone) {
        $threshold = 50.00;
        $base_fee  = 4.99;
        return [
            'fee'         => $subtotal >= $threshold ? 0.00 : $base_fee,
            'base_fee'    => $base_fee,
            'threshold'   => $threshold,
            'zone_city'   => null,
            'is_fallback' => true,
        ];
    }

    $threshold = $zone['free_shipping_threshold'] !== null ? (float)$zone['free_shipping_threshold'] : null;
    $base_fee  = (float)$zone['fee'];
    $is_free   = $threshold !== null && $subtotal >= $threshold;

    return [
        'fee'         => $is_free ? 0.00 : $base_fee,
        'base_fee'    => $base_fee,
        'threshold'   => $threshold,
        'zone_city'   => $zone['city'],
        'is_fallback' => false,
    ];
}