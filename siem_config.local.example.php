<?php
/**
 * Copy to siem_config.local.php (git-ignored).
 * SIEM_API_KEY must match SIEM_API_KEY in the SIEM backend's environment.
 */
const SIEM_API_BASE = 'http://localhost:8000';
const SIEM_API_KEY = '';
const SIEM_SELF_TEST_ENABLED = false;
const SIEM_TRUST_PROXY = false; // true only behind a proxy/CDN you control
