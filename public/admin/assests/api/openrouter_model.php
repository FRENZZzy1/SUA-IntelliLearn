<?php
/**
 * assests/api/openrouter_model.php
 *
 * Picks a free OpenRouter model to use for chatbot.php, with four layers,
 * tried in this order:
 *
 *   0. Admin override: a model an admin explicitly chose via the
 *      dashboard's model picker (model_settings.php), stored on disk.
 *      Takes priority over everything else if set.
 *   1. Cache: whichever model last worked (file-based, ~1hr TTL) — fast
 *      path, no extra HTTP call on most requests.
 *   2. Preferred list: a short, hand-picked set of free models we've
 *      actually tested and trust for quality. Tried in order.
 *   3. Live catalog: if every preferred model is currently down/retired,
 *      fetch OpenRouter's current free-model list and try those too.
 *
 * chatbot.php calls get_openrouter_model_candidates() to get an ordered
 * list to try, and cache_working_openrouter_model() once one succeeds.
 *
 * model_settings.php (admin-only AJAX endpoint) lets an admin browse the
 * live free-model list and pick one, via set_selected_openrouter_model().
 */

// Edit this list as you test/discover models you like. Order = preference.
// Check https://openrouter.ai/models?max_price=0 for what's currently free.
const OPENROUTER_PREFERRED_FREE_MODELS = [
    'meta-llama/llama-3.3-70b-instruct:free',
    'google/gemini-2.0-flash-exp:free',
    'deepseek/deepseek-chat:free',
    'mistralai/mistral-7b-instruct:free',
];

function _openrouter_cache_file(): string {
    return sys_get_temp_dir() . '/openrouter_free_model.json';
}

/**
 * Where the admin's manually-picked model is stored. Deliberately kept
 * OUTSIDE sys_get_temp_dir() (unlike the auto-cache) since this is a
 * real setting, not disposable — it shouldn't vanish if the OS clears
 * /tmp. Adjust the path if your host restricts writes outside webroot.
 */
function _openrouter_settings_file(): string {
    return __DIR__ . '/openrouter_settings.json';
}

/**
 * The model an admin explicitly chose via the dashboard picker, if any.
 * Returns null if no admin selection has been made (falls through to
 * cache -> preferred list -> live catalog).
 */
function get_selected_openrouter_model(): ?string {
    $file = _openrouter_settings_file();
    if (!file_exists($file)) return null;

    $settings = json_decode(file_get_contents($file), true);
    $model = $settings['model'] ?? null;

    return (is_string($model) && $model !== '') ? $model : null;
}

/**
 * Save (or clear, by passing null/'') the admin's manual model choice.
 */
function set_selected_openrouter_model(?string $model): void {
    file_put_contents(_openrouter_settings_file(), json_encode([
        'model' => $model,
        'set_at' => time(),
    ]));
}

/**
 * Last model that successfully answered a request, if still fresh.
 */
function get_cached_openrouter_model(): ?string {
    $cacheFile = _openrouter_cache_file();
    $cacheTtl = 3600; // 1 hour

    if (!file_exists($cacheFile) || (time() - filemtime($cacheFile)) >= $cacheTtl) {
        return null;
    }

    $cached = json_decode(file_get_contents($cacheFile), true);
    return $cached['model'] ?? null;
}

function cache_working_openrouter_model(string $model): void {
    file_put_contents(_openrouter_cache_file(), json_encode([
        'model' => $model,
        'cached_at' => time(),
    ]));
}

/**
 * Live free-model catalog from OpenRouter with display details (id,
 * name, context length) — used to populate the admin's model picker
 * dropdown. Sorted by context length (rough proxy for capability).
 */
function get_live_free_openrouter_models_detailed(): array {
    $ch = curl_init('https://openrouter.ai/api/v1/models');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return [];

    $data = json_decode($response, true)['data'] ?? [];

    $free = array_filter($data, function ($m) {
        return isset($m['pricing']['prompt'], $m['pricing']['completion'])
            && (float) $m['pricing']['prompt'] === 0.0
            && (float) $m['pricing']['completion'] === 0.0;
    });

    usort($free, fn($a, $b) => ($b['context_length'] ?? 0) <=> ($a['context_length'] ?? 0));

    return array_values(array_map(fn($m) => [
        'id' => $m['id'],
        'name' => $m['name'] ?? $m['id'],
        'context_length' => $m['context_length'] ?? null,
    ], $free));
}

/**
 * Same live free-model list, but just the ids — used by chatbot.php's
 * fallback loop, which doesn't need display metadata.
 */
function get_live_free_openrouter_models(): array {
    return array_column(get_live_free_openrouter_models_detailed(), 'id');
}

/**
 * Ordered, de-duplicated list of model ids to try for this request:
 * cached-known-good -> preferred list -> live catalog (only fetched
 * if needed, since it's an extra HTTP round trip).
 */
function get_openrouter_model_candidates(): array {
    $candidates = [];

    // Admin's explicit pick always goes first — that's the whole point
    // of giving them a picker. Everything below is just fallback in
    // case their pick is temporarily down.
    $selected = get_selected_openrouter_model();
    if ($selected) $candidates[] = $selected;

    $cached = get_cached_openrouter_model();
    if ($cached) $candidates[] = $cached;

    $candidates = array_merge($candidates, OPENROUTER_PREFERRED_FREE_MODELS);
    $candidates = array_values(array_unique($candidates));

    return $candidates;
}