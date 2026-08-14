<?php
/**
 * assests/api/model_settings.php
 *
 * Admin-only endpoint backing the "chatbot model" picker on the dashboard.
 *
 *   GET  -> { models: [{id, name}, ...], selected: "id"|null }
 *           Populates a <select> with the curated list of free-tier
 *           Gemini models (see GEMINI_PREFERRED_FREE_MODELS in
 *           gemini_model.php), and reports whichever one is currently
 *           chosen (if any).
 *
 *   POST { "model": "gemini-3.5-flash-lite" }  -> saves the admin's pick.
 *   POST { "model": "" }                       -> clears the pick (falls
 *           back to auto-selection: cache -> preferred list).
 *
 * Only ever stores/accepts model ids that are in the curated free-tier
 * list, so an admin can't accidentally lock in a typo'd or unsupported
 * model id.
 *
 * NOTE: unlike the old OpenRouter version of this file, there's no live
 * catalog fetch here — the Gemini API doesn't expose per-model pricing,
 * so "free" is just the curated list in gemini_model.php. Update that
 * list (and GEMINI_MODEL_DISPLAY_NAMES) as Google's free-tier lineup
 * changes.
 */

header('Content-Type: application/json');

require_once '../../../../config/config.php';
requireAdmin(); // admin-only — reuses the helper already in config.php

require_once 'gemini_model.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode([
        'models' => get_free_gemini_models_detailed(),
        'selected' => get_selected_gemini_model(),
    ]);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $model = is_array($body) ? trim((string) ($body['model'] ?? '')) : '';

    // Clearing the selection is always allowed.
    if ($model === '') {
        set_selected_gemini_model(null);
        echo json_encode(['success' => true, 'selected' => null]);
        exit;
    }

    // Otherwise, verify it's in the curated free-tier list before saving.
    $validIds = array_column(get_free_gemini_models_detailed(), 'id');
    if (!in_array($model, $validIds, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'That model is not in the supported free-tier list.']);
        exit;
    }

    set_selected_gemini_model($model);
    echo json_encode(['success' => true, 'selected' => $model]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Invalid request method.']);