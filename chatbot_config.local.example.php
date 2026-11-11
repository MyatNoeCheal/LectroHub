<?php
/**
 * Copy to chatbot_config.local.php (git-ignored).
 * CHATBOT_SHARED_SECRET must equal CHATBOT_SHARED_SECRET in the Flask
 * chatbot's environment. Generate one: php -r "echo bin2hex(random_bytes(32));"
 */
const CHATBOT_API_URL = 'http://127.0.0.1:5000';
const CHATBOT_SHARED_SECRET = '';
