<?php
/**
 * assests/api/model_settings.php
 *
 * Admin-only endpoint backing a "chatbot model" picker on the dashboard.
 *
 *   GET  -> { models: [{id, name, context_length}, ...], selected: "id"|null }
 *           Populates a <select> with currently-free OpenRouter models,
 *           and reports whichever one is currently chosen (if any).
 *
 *   POST { "model": "some/model:free" }  -> saves the admin's pick.
 *   POST { "model": "" }                 -> clears the pick (falls back
 *           to auto-selection: cache -> preferred list -> live catalog).
 *
 * Only ever stores/accepts model ids that are actually in the live free
 * catalog at save time, so an admin can't accidentally lock in a paid
 * or retired model id by typo or stale page.
 */

header('Content-Type: application/json');

require_once '../../../../config/config.php';
requireAdmin(); // admin-only — reuses the helper already in config.php

require_once 'openrouter_model.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $live = get_live_free_openrouter_models_detailed();

    if (empty($live)) {
        http_response_code(502);
        echo json_encode(['error' => 'Could not reach OpenRouter to list free models. Try again shortly.']);
        exit;
    }

    echo json_encode([
        'models' => $live,
        'selected' => get_selected_openrouter_model(),
    ]);
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $model = is_array($body) ? trim((string) ($body['model'] ?? '')) : '';

    // Clearing the selection is always allowed.
    if ($model === '') {
        set_selected_openrouter_model(null);
        echo json_encode(['success' => true, 'selected' => null]);
        exit;
    }

    // Otherwise, verify it's a real, currently-free model before saving.
    $liveIds = array_column(get_live_free_openrouter_models_detailed(), 'id');
    if (!in_array($model, $liveIds, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'That model is not currently in OpenRouter\'s free lineup.']);
        exit;
    }

    set_selected_openrouter_model($model);
    echo json_encode(['success' => true, 'selected' => $model]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Invalid request method.']);