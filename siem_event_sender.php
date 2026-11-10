<?php
/**
 * siem_event_sender.php
 *
 * Sends security-relevant events from the e-commerce site to the Smart
 * SIEM's /detect endpoint.
 *
 * SECURITY:
 * - SIEM credentials are loaded from siem_config.local.php.
 * - siem_config.local.php must NOT be committed to Git.
 * - This file is safe to keep in the public repository.
 *
 * Usage:
 *
 * require_once __DIR__ . '/siem_event_sender.php';
 *
 * Then call:
 *
 *     siem_login_success($username);
 *     siem_login_failed($username);
 *     siem_payment($userId, $amount);
 *     siem_admin_login($adminId);
 */

/* =========================================================
 * LOAD LOCAL SIEM CONFIGURATION
 * =========================================================
 *
 * siem_config.local.php should contain:
 *
 * <?php
 * const SIEM_API_BASE = 'http://localhost:8000';
 * const SIEM_API_KEY = 'paste-your-long-random-key-here';
 * const SIEM_SELF_TEST_ENABLED = false;
 * const SIEM_TRUST_PROXY = false; // true only behind a proxy/CDN you control
 *
 * This file is ignored by Git.
 */

$localConfig = __DIR__ . '/siem_config.local.php';

if (file_exists($localConfig)) {
    require_once $localConfig;
} else {
    /*
     * Safe defaults for environments where the local config
     * has not been created yet.
     */
    if (!defined('SIEM_API_BASE')) {
        define('SIEM_API_BASE', 'http://localhost:8000');
    }

    if (!defined('SIEM_API_KEY')) {
        define('SIEM_API_KEY', '');
    }

    if (!defined('SIEM_SELF_TEST_ENABLED')) {
        define('SIEM_SELF_TEST_ENABLED', false);
    }
}


/* =========================================================
 * CONFIGURATION
 * =========================================================
 */

/*
 * X-Forwarded-For can be forged by any client, so it is only trusted
 * when the site is explicitly configured as running behind a proxy.
 */
if (!defined('SIEM_TRUST_PROXY')) {
    define('SIEM_TRUST_PROXY', false);
}

if (!defined('SIEM_CONNECT_TIMEOUT_MS')) {
    define('SIEM_CONNECT_TIMEOUT_MS', 800);
}

if (!defined('SIEM_TOTAL_TIMEOUT_MS')) {
    define('SIEM_TOTAL_TIMEOUT_MS', 1500);
}


/* =========================================================
 * CORE SENDER
 * =========================================================
 */

/**
 * Sends a single security event to the SIEM /detect endpoint.
 *
 * The function is designed to be non-blocking from the
 * application's perspective:
 *
 * - Does not throw exceptions.
 * - Uses short connection/request timeouts.
 * - Logs errors to PHP's error log.
 * - Does not interrupt checkout/login if SIEM is unavailable.
 *
 * Expected event fields:
 *
 * event_type  - required
 * severity    - low | medium | high | critical
 * ip          - optional
 * timestamp   - optional
 * user_id     - optional
 * role        - optional
 * amount      - optional
 */
function siem_send_event(array $event): void
{
    /*
     * Do not attempt to contact the SIEM if no API key
     * has been configured.
     */
    if (SIEM_API_KEY === '') {
        error_log(
            '[SIEM] SIEM_API_KEY is not configured. Event not sent: ' .
            ($event['event_type'] ?? 'unknown')
        );

        return;
    }

    /*
     * Automatically add common event information.
     */
    $event['timestamp'] = $event['timestamp'] ?? gmdate('c');
    $event['ip'] = $event['ip'] ?? siem_client_ip();
    $event['role'] = $event['role'] ?? 'user';

    /*
     * Convert event to JSON.
     */
    $payload = json_encode($event);

    if ($payload === false) {
        error_log(
            '[SIEM] Failed to encode event payload: ' .
            json_last_error_msg()
        );

        return;
    }

    /*
     * Build SIEM endpoint URL.
     */
    $endpoint = rtrim(SIEM_API_BASE, '/') . '/detect';

    /*
     * Initialize CURL.
     */
    $ch = curl_init($endpoint);

    if ($ch === false) {
        error_log('[SIEM] Failed to initialize CURL.');

        return;
    }

    /*
     * Configure request.
     */
    curl_setopt_array($ch, [
        CURLOPT_POST => true,

        CURLOPT_POSTFIELDS => $payload,

        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-Key: ' . SIEM_API_KEY,
            'Content-Length: ' . strlen($payload),
        ],

        CURLOPT_RETURNTRANSFER => true,

        /*
         * Keep the request short so a SIEM outage does not
         * make the e-commerce website slow.
         */
        CURLOPT_TIMEOUT_MS => SIEM_TOTAL_TIMEOUT_MS,

        CURLOPT_CONNECTTIMEOUT_MS => SIEM_CONNECT_TIMEOUT_MS,

        /*
         * Verify HTTPS certificates when HTTPS is used.
         */
        CURLOPT_SSL_VERIFYPEER => true,

        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    /*
     * Execute request.
     */
    $response = curl_exec($ch);

    $errno = curl_errno($ch);

    $error = curl_error($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    /*
     * Close CURL before processing the result.
     */
    curl_close($ch);


    /* =====================================================
     * ERROR HANDLING
     * =====================================================
     */

    if ($errno !== 0) {

        error_log(
            "[SIEM] CURL error ({$errno}) sending " .
            ($event['event_type'] ?? 'unknown') .
            ": {$error}"
        );

        return;
    }

    /*
     * Unauthorized API key.
     */
    if ($httpCode === 401) {

        error_log(
            '[SIEM] HTTP 401 Unauthorized. ' .
            'Check SIEM_API_KEY configuration.'
        );

        return;
    }

    /*
     * Other HTTP errors.
     */
    if ($httpCode >= 400) {

        error_log(
            "[SIEM] HTTP {$httpCode} sending " .
            ($event['event_type'] ?? 'unknown') .
            ": {$response}"
        );

        return;
    }
}


/* =========================================================
 * CLIENT IP
 * =========================================================
 */

/**
 * Gets the client's IP address.
 *
 * X-Forwarded-For is used only when SIEM_TRUST_PROXY is true
 * (site is behind a proxy/CDN you control); otherwise REMOTE_ADDR.
 */
function siem_client_ip(): string
{
    if (SIEM_TRUST_PROXY && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {

        $parts = explode(
            ',',
            $_SERVER['HTTP_X_FORWARDED_FOR']
        );

        return trim($parts[0]);
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}


/* =========================================================
 * USER EVENTS
 * =========================================================
 */

/**
 * Successful customer login.
 */
function siem_login_success(string $userId): void
{
    siem_send_event([
        'event_type' => 'login',
        'severity' => 'low',
        'user_id' => $userId,
        'role' => 'user',
    ]);
}


/**
 * Failed customer login.
 */
function siem_login_failed(string $attemptedUsername): void
{
    siem_send_event([
        'event_type' => 'failed_login',
        'severity' => 'high',
        'user_id' => $attemptedUsername,
        'role' => 'user',
    ]);
}


/**
 * Customer logout.
 */
function siem_logout(string $userId): void
{
    siem_send_event([
        'event_type' => 'logout',
        'severity' => 'low',
        'user_id' => $userId,
        'role' => 'user',
    ]);
}


/**
 * Customer browsing activity.
 */
function siem_browse(string $userId): void
{
    siem_send_event([
        'event_type' => 'browse',
        'severity' => 'low',
        'user_id' => $userId,
        'role' => 'user',
    ]);
}


/**
 * Add product to cart.
 */
function siem_add_to_cart(string $userId): void
{
    siem_send_event([
        'event_type' => 'add_to_cart',
        'severity' => 'low',
        'user_id' => $userId,
        'role' => 'user',
    ]);
}


/**
 * Checkout started.
 */
function siem_checkout_started(string $userId): void
{
    siem_send_event([
        'event_type' => 'checkout',
        'severity' => 'low',
        'user_id' => $userId,
        'role' => 'user',
    ]);
}


/**
 * Payment event.
 *
 * Suspicious payments can be escalated to HIGH severity.
 */
function siem_payment(
    string $userId,
    float $amount,
    bool $isSuspicious = false
): void {

    siem_send_event([
        'event_type' => 'payment',

        'severity' => $isSuspicious
            ? 'high'
            : 'medium',

        'user_id' => $userId,

        'amount' => $amount,

        'role' => 'user',
    ]);
}


/**
 * Refund event.
 */
function siem_refund(
    string $userId,
    float $amount
): void {

    siem_send_event([
        'event_type' => 'refund',
        'severity' => 'medium',
        'user_id' => $userId,
        'amount' => $amount,
        'role' => 'user',
    ]);
}


/**
 * Chargeback event.
 */
function siem_chargeback(
    string $userId,
    float $amount
): void {

    siem_send_event([
        'event_type' => 'chargeback',
        'severity' => 'high',
        'user_id' => $userId,
        'amount' => $amount,
        'role' => 'user',
    ]);
}


/**
 * Password change.
 */
function siem_password_change(string $userId): void
{
    siem_send_event([
        'event_type' => 'password_change',
        'severity' => 'medium',
        'user_id' => $userId,
        'role' => 'user',
    ]);
}


/**
 * Customer profile update.
 */
function siem_profile_update(string $userId): void
{
    siem_send_event([
        'event_type' => 'profile_update',
        'severity' => 'low',
        'user_id' => $userId,
        'role' => 'user',
    ]);
}


/* =========================================================
 * ADMIN EVENTS
 * =========================================================
 */

/**
 * Admin login.
 */
function siem_admin_login(string $adminId): void
{
    siem_send_event([
        'event_type' => 'admin_login',
        'severity' => 'medium',
        'user_id' => $adminId,
        'role' => 'admin',
    ]);
}


/**
 * Admin configuration change.
 */
function siem_admin_config_change(string $adminId): void
{
    siem_send_event([
        'event_type' => 'config_change',
        'severity' => 'high',
        'user_id' => $adminId,
        'role' => 'admin',
    ]);
}


/**
 * Admin role change.
 */
function siem_admin_role_change(string $adminId): void
{
    siem_send_event([
        'event_type' => 'role_change',
        'severity' => 'high',
        'user_id' => $adminId,
        'role' => 'admin',
    ]);
}


/**
 * Admin deletes a user.
 */
function siem_admin_user_delete(string $adminId): void
{
    siem_send_event([
        'event_type' => 'user_delete',
        'severity' => 'critical',
        'user_id' => $adminId,
        'role' => 'admin',
    ]);
}


/* =========================================================
 * SELF TEST
 * =========================================================
 *
 * IMPORTANT:
 * SIEM_SELF_TEST_ENABLED should normally be FALSE.
 *
 * If you temporarily set it to TRUE for testing, access:
 *
 *     http://localhost/LectroHub/siem_event_sender.php
 *
 * After testing, change it back to FALSE.
 */

if (
    SIEM_SELF_TEST_ENABLED
    && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)
) {

    header('Content-Type: text/plain');

    echo "Testing outbound connection to ";
    echo SIEM_API_BASE;
    echo " ...\n\n";


    /*
     * Test /health endpoint.
     */
    $healthUrl = rtrim(
        SIEM_API_BASE,
        '/'
    ) . '/health';

    $ch = curl_init($healthUrl);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_TIMEOUT => 15,

        CURLOPT_SSL_VERIFYPEER => true,

        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);

    $errno = curl_errno($ch);

    $error = curl_error($ch);

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);


    /*
     * Display result.
     */
    if ($errno !== 0) {

        echo "FAILED\n";
        echo "CURL error {$errno}: {$error}\n\n";

        echo "Possible reasons:\n";
        echo "- SIEM server is not running.\n";
        echo "- Network connection is unavailable.\n";
        echo "- Hosting provider blocks outbound connections.\n";

    } elseif ($httpCode !== 200) {

        echo "FAILED\n";
        echo "HTTP {$httpCode}\n";
        echo "Response: {$response}\n";

    } else {

        echo "SUCCESS\n";
        echo "SIEM health endpoint reached successfully.\n";
        echo "Response: {$response}\n\n";


        /*
         * Test /detect.
         */
        echo "Testing /detect POST...\n";

        siem_send_event([
            'event_type' => 'login',
            'severity' => 'low',
            'user_id' => 'php_self_test_user',
            'role' => 'user',
        ]);

        echo "Test event sent.\n";
        echo "Check the SIEM dashboard Logs tab.\n\n";

        echo "IMPORTANT:\n";
        echo "Set SIEM_SELF_TEST_ENABLED to false after testing.\n";
    }

    exit;
}