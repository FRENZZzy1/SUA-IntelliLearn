<?php
/**
 * assests/api/gemini_model.php
 *
 * Picks a Gemini model (Google AI Studio) to use for chatbot.php, with
 * three layers, tried in this order:
 *
 *   0. Admin override: a model an admin explicitly chose via the
 *      dashboard's model picker (model_settings.php), stored on disk.
 *      Takes priority over everything else if set.
 *   1. Cache: whichever model last worked (file-based, ~1hr TTL) — fast
 *      path, skips straight to a known-good model on most requests.
 *   2. Preferred list: a curated set of Gemini models confirmed to be
 *      on Google AI Studio's free tier, tried in order.
 *
 * NOTE: unlike OpenRouter, the Gemini API doesn't expose a per-model
 * "is this free" flag you can query live — free vs. paid is a property
 * of your project's billing/usage tier, not the model id itself. So
 * there's no "live catalog" step here; we just stick to a curated list
 * of models Google documents as free-tier eligible (Flash / Flash-Lite
 * family). Edit GEMINI_PREFERRED_FREE_MODELS below as that lineup
 * changes — check https://ai.google.dev/gemini-api/docs/pricing for
 * the current free-tier model list.
 *
 * chatbot.php calls get_gemini_model_candidates() to get an ordered
 * list to try, and cache_working_gemini_model() once one succeeds.
 *
 * model_settings.php (admin-only AJAX endpoint) lets an admin pick one
 * of these models, via set_selected_gemini_model().
 */

// Edit this list as Google's free-tier lineup changes. Order = preference.
// Check https://ai.google.dev/gemini-api/docs/pricing for what's currently free.
const GEMINI_PREFERRED_FREE_MODELS = [
    'gemini-3.5-flash-lite',
    'gemini-3.1-flash-lite',
    'gemini-2.5-flash-lite',
    'gemini-2.5-flash',
];

// Friendly display names for the admin picker dropdown (model_settings.php).
// Falls back to the raw id if a model isn't listed here.
const GEMINI_MODEL_DISPLAY_NAMES = [
    'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite',
    'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash-Lite',
    'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite',
    'gemini-2.5-flash'      => 'Gemini 2.5 Flash',
];

function _gemini_cache_file(): string {
    return sys_get_temp_dir() . '/gemini_free_model.json';
}

/**
 * Where the admin's manually-picked model is stored. Deliberately kept
 * OUTSIDE sys_get_temp_dir() (unlike the auto-cache) since this is a
 * real setting, not disposable — it shouldn't vanish if the OS clears
 * /tmp. Adjust the path if your host restricts writes outside webroot.
 */
function _gemini_settings_file(): string {
    return __DIR__ . '/gemini_settings.json';
}

/**
 * The model an admin explicitly chose via the dashboard picker, if any.
 * Returns null if no admin selection has been made (falls through to
 * cache -> preferred list).
 */
function get_selected_gemini_model(): ?string {
    $file = _gemini_settings_file();
    if (!file_exists($file)) return null;

    $settings = json_decode(file_get_contents($file), true);
    $model = $settings['model'] ?? null;

    return (is_string($model) && $model !== '') ? $model : null;
}

/**
 * Save (or clear, by passing null/'') the admin's manual model choice.
 */
function set_selected_gemini_model(?string $model): void {
    file_put_contents(_gemini_settings_file(), json_encode([
        'model' => $model,
        'set_at' => time(),
    ]));
}

/**
 * Last model that successfully answered a request, if still fresh.
 */
function get_cached_gemini_model(): ?string {
    $cacheFile = _gemini_cache_file();
    $cacheTtl = 3600; // 1 hour

    if (!file_exists($cacheFile) || (time() - filemtime($cacheFile)) >= $cacheTtl) {
        return null;
    }

    $cached = json_decode(file_get_contents($cacheFile), true);
    return $cached['model'] ?? null;
}

function cache_working_gemini_model(string $model): void {
    file_put_contents(_gemini_cache_file(), json_encode([
        'model' => $model,
        'cached_at' => time(),
    ]));
}

/**
 * The curated free-tier model list, with display details — used to
 * populate the admin's model picker dropdown (mirrors the shape
 * OpenRouter's live catalog used to return, so model_settings.php's
 * JSON contract — and chatbot.js, which reads it — didn't need to
 * change).
 */
function get_free_gemini_models_detailed(): array {
    return array_map(function ($id) {
        return [
            'id' => $id,
            'name' => GEMINI_MODEL_DISPLAY_NAMES[$id] ?? $id,
        ];
    }, GEMINI_PREFERRED_FREE_MODELS);
}

/**
 * Ordered, de-duplicated list of model ids to try for this request:
 * admin override -> cached-known-good -> preferred list.
 */
function get_gemini_model_candidates(): array {
    $candidates = [];

    // Admin's explicit pick always goes first — that's the whole point
    // of giving them a picker. Everything below is just fallback in
    // case their pick is temporarily down or rate-limited.
    $selected = get_selected_gemini_model();
    if ($selected) $candidates[] = $selected;

    $cached = get_cached_gemini_model();
    if ($cached) $candidates[] = $cached;

    $candidates = array_merge($candidates, GEMINI_PREFERRED_FREE_MODELS);
    $candidates = array_values(array_unique($candidates));

    return $candidates;
}