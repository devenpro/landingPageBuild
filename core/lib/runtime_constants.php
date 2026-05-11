<?php
// core/lib/runtime_constants.php — bridge legacy GUA_* constants to settings (v2 Stage 1).
//
// Existing v1 code (auth, layout, sections, AI providers, form handler,
// chatbot endpoint, sitemap, admin pages) reads runtime config via constants
// like GUA_SITE_NAME, GUA_APP_URL, etc. v1 defined these in config.php from
// .env at boot time, which meant changes required a redeploy.
//
// v2 Stage 1 introduces site_settings — admin can edit values via the UI.
// This file redefines the same constants via settings_get(), so legacy
// callers see the DB value (or .env fallback, or hard default) without any
// other code changes.
//
// Loaded by bootstrap.php AFTER config.php, db.php, and settings.php.
// Side-effect-free except for the define() calls; safe to require multiple
// times because of GUA_CONFIG_LOADED-style guards on each constant.

declare(strict_types=1);

require_once __DIR__ . '/settings.php';

if (!defined('GUA_APP_ENV')) {
    define('GUA_APP_ENV', (string)settings_get('app_env', 'production'));
}
if (!defined('GUA_APP_URL')) {
    define('GUA_APP_URL', (string)settings_get('app_url', ''));
}
if (!defined('GUA_SITE_NAME')) {
    define('GUA_SITE_NAME', (string)settings_get('site_name', 'Go Ultra AI'));
}
if (!defined('GUA_ADMIN_EMAIL')) {
    define('GUA_ADMIN_EMAIL', (string)settings_get('admin_email', ''));
}
if (!defined('GUA_SESSION_LIFETIME_HOURS')) {
    define('GUA_SESSION_LIFETIME_HOURS', (int)settings_get('session_lifetime_hours', 8));
}
if (!defined('GUA_WEBHOOK_URL')) {
    define('GUA_WEBHOOK_URL', (string)settings_get('webhook_url', ''));
}
if (!defined('GUA_WEBHOOK_TIMEOUT_SECONDS')) {
    define('GUA_WEBHOOK_TIMEOUT_SECONDS', (int)settings_get('webhook_timeout_seconds', 10));
}
if (!defined('GUA_MAX_IMAGE_BYTES')) {
    define('GUA_MAX_IMAGE_BYTES', (int)settings_get('max_image_bytes', 5_242_880));
}
if (!defined('GUA_MAX_VIDEO_BYTES')) {
    define('GUA_MAX_VIDEO_BYTES', (int)settings_get('max_video_bytes', 52_428_800));
}
if (!defined('GUA_AI_DEFAULT_PROVIDER')) {
    define('GUA_AI_DEFAULT_PROVIDER', (string)settings_get('ai_default_provider', 'huggingface'));
}
if (!defined('GUA_HF_DEFAULT_MODEL')) {
    define('GUA_HF_DEFAULT_MODEL', (string)settings_get('hf_default_model', 'meta-llama/Llama-3.3-70B-Instruct'));
}
if (!defined('GUA_GEMINI_DEFAULT_MODEL')) {
    define('GUA_GEMINI_DEFAULT_MODEL', (string)settings_get('gemini_default_model', 'gemini-2.5-flash'));
}
if (!defined('GUA_OPENROUTER_DEFAULT_MODEL')) {
    define('GUA_OPENROUTER_DEFAULT_MODEL', (string)settings_get('openrouter_default_model', 'anthropic/claude-3.5-haiku'));
}
if (!defined('GUA_ANTHROPIC_DEFAULT_MODEL')) {
    define('GUA_ANTHROPIC_DEFAULT_MODEL', (string)settings_get('anthropic_default_model', 'claude-haiku-4-5-20251001'));
}
if (!defined('GUA_OPENAI_DEFAULT_MODEL')) {
    define('GUA_OPENAI_DEFAULT_MODEL', (string)settings_get('openai_default_model', 'gpt-4o-mini'));
}
if (!defined('GUA_GROK_DEFAULT_MODEL')) {
    define('GUA_GROK_DEFAULT_MODEL', (string)settings_get('grok_default_model', 'grok-2-latest'));
}
if (!defined('GUA_AI_CHAT_ENABLED')) {
    define('GUA_AI_CHAT_ENABLED', (bool)settings_get('ai_chat_enabled', false));
}
if (!defined('GUA_AI_CHAT_PERSIST')) {
    define('GUA_AI_CHAT_PERSIST', (bool)settings_get('ai_chat_persist', false));
}
