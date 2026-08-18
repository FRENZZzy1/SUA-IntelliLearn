<?php
/**
 * gemini_client.php
 * -----------------------------------------------------------------
 * Thin wrapper around Google's Gemini API (generateContent endpoint).
 * Used to turn raw at-risk metrics into a teacher-facing explanation:
 * WHY a student is flagged, HOW that conclusion was reached, and
 * RECOMMENDED ACTIONS the teacher can take.
 *
 * Requires GEMINI_API_KEY to be defined in config.php. Get a free key
 * at https://aistudio.google.com/apikey and paste it into:
 *     define('GEMINI_API_KEY', 'your-key-here');
 *
 * MODEL SELECTION
 * ----------------
 * Tries each model in GEMINI_MODEL_FALLBACKS, in order, until one
 * succeeds. This protects against a single model being over quota,
 * temporarily unavailable, or retired — if the first choice fails,
 * it automatically falls back to the next one instead of erroring out.
 * To change which models are used (or their priority), edit the list
 * below — that's the only place in the app this needs to be touched.
 * -----------------------------------------------------------------
 */

const GEMINI_MODEL_FALLBACKS = [
    'gemini-3.5-flash-lite',
    'gemini-3.1-flash-lite',
    'gemini-2.5-flash-lite',
    'gemini-2.5-flash',
];

if (!function_exists('gemini_is_configured')) {
    function gemini_is_configured(): bool
    {
        return defined('GEMINI_API_KEY') && trim(GEMINI_API_KEY) !== '';
    }
}

/**
 * Ask Gemini to explain why a student is at risk and what to do about it.
 *
 * @param string $studentName
 * @param string $subjectName
 * @param string $riskLabel      'Low' | 'Medium' | 'High'
 * @param array  $metrics        Structured metrics, see at_risk_functions.php
 * @return array{success:bool, why?:string, how?:string, recommended_actions?:string[], error?:string}
 */
function gemini_analyze_student_risk(string $studentName, string $subjectName, string $riskLabel, array $metrics): array
{
    if (!gemini_is_configured()) {
        return [
            'success' => false,
            'error'   => 'GEMINI_API_KEY is not configured in config.php.',
        ];
    }

    $prompt = build_gemini_risk_prompt($studentName, $subjectName, $riskLabel, $metrics);

    $requestBody = [
        'contents' => [
            [
                'role'  => 'user',
                'parts' => [['text' => $prompt]],
            ],
        ],
        'generationConfig' => [
            'temperature'      => 0.4,
            'maxOutputTokens'  => 700,
            'responseMimeType' => 'application/json',
            'responseSchema'   => [
                'type'       => 'OBJECT',
                'properties' => [
                    'why' => [
                        'type'        => 'STRING',
                        'description' => '2-4 sentence, teacher-facing explanation of WHY this student is flagged at this risk level, referencing the actual numbers given.',
                    ],
                    'how' => [
                        'type'        => 'STRING',
                        'description' => '1-3 sentences explaining HOW the risk level was determined (which factors weighed most heavily).',
                    ],
                    'recommended_actions' => [
                        'type'  => 'ARRAY',
                        'items' => ['type' => 'STRING'],
                        'description' => '3-5 short, concrete, actionable next steps the teacher can take this week.',
                    ],
                ],
                'required' => ['why', 'how', 'recommended_actions'],
            ],
        ],
    ];

    $lastError = 'No models were attempted.';

    foreach (GEMINI_MODEL_FALLBACKS as $model) {
        $result = gemini_call_model($model, $requestBody);

        if ($result['success']) {
            return $result;
        }

        $lastError = $result['error'];
        // Only fall through to the next model on errors that suggest THIS
        // model is the problem (not found/retired, overloaded, rate-limited,
        // or a transport failure). A malformed-request error would fail the
        // same way on every model, so don't waste calls retrying that.
        if (!in_array($result['http_code'] ?? 0, [404, 429, 503, 0], true)) {
            break;
        }
    }

    return ['success' => false, 'error' => $lastError];
}

/**
 * Makes a single generateContent call against one specific model.
 * Returns the same shape as gemini_analyze_student_risk(), plus an
 * internal 'http_code' key (0 for transport-level failures) used by
 * the fallback loop above to decide whether to try the next model.
 */
function gemini_call_model(string $model, array $requestBody): array
{
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . urlencode($model) . ':generateContent?key=' . urlencode(GEMINI_API_KEY);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($requestBody),
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'http_code' => 0, 'error' => "[$model] Could not reach Gemini API: " . $curlError];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $errBody = json_decode($response, true);
        $msg = $errBody['error']['message'] ?? ('Gemini API returned HTTP ' . $httpCode);
        return ['success' => false, 'http_code' => $httpCode, 'error' => "[$model] $msg"];
    }

    $data = json_decode($response, true);
    $rawText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (!$rawText) {
        return ['success' => false, 'http_code' => $httpCode, 'error' => "[$model] Gemini returned an empty response."];
    }

    $parsed = json_decode($rawText, true);
    if (!is_array($parsed) || !isset($parsed['why'], $parsed['how'], $parsed['recommended_actions'])) {
        // Fall back to showing the raw text if structured parsing fails.
        return [
            'success' => true,
            'model'               => $model,
            'why'                 => is_string($rawText) ? $rawText : 'Unable to parse structured response.',
            'how'                 => '',
            'recommended_actions' => [],
        ];
    }

    return [
        'success'             => true,
        'model'               => $model,
        'why'                 => (string) $parsed['why'],
        'how'                 => (string) $parsed['how'],
        'recommended_actions' => array_values(array_map('strval', (array) $parsed['recommended_actions'])),
    ];
}

function build_gemini_risk_prompt(string $studentName, string $subjectName, string $riskLabel, array $metrics): string
{
    $fmt = fn($v, $suffix = '%') => $v === null ? 'no data' : (round((float) $v, 1) . $suffix);

    $attendance = $fmt($metrics['attendance_pct'] ?? null);
    $attendanceDetail = isset($metrics['attendance_total']) && $metrics['attendance_total'] > 0
        ? sprintf(
            '(%d present, %d late, %d absent, %d excused, out of %d recorded days)',
            $metrics['attendance_present'] ?? 0,
            $metrics['attendance_late'] ?? 0,
            $metrics['attendance_absent'] ?? 0,
            $metrics['attendance_excused'] ?? 0,
            $metrics['attendance_total']
        )
        : 'no attendance has been recorded yet';

    $assignments = $fmt($metrics['assignment_pct'] ?? null);
    $assignmentsMissing = ($metrics['assignment_missing'] ?? 0) . ' of ' . ($metrics['assignment_total_due'] ?? 0) . ' due assignments not submitted';

    $quizzes = $fmt($metrics['quiz_pct'] ?? null);
    $quizzesMissing = ($metrics['quiz_missing'] ?? 0) . ' of ' . ($metrics['quiz_total_due'] ?? 0) . ' due quizzes not attempted';

    $riskScore = $metrics['risk_score'] !== null ? round((float) $metrics['risk_score'], 1) : 'N/A';

    return <<<PROMPT
You are an academic early-warning assistant helping a K-12 teacher understand
why a student was flagged by an automated at-risk detection system, and what
to do about it. Be specific, concise, and encouraging in tone — this goes
directly in front of a teacher, not the student.

Student: {$studentName}
Subject/Class: {$subjectName}
Computed risk level: {$riskLabel} (risk score {$riskScore} / 100, higher = more at risk)

Underlying data used to compute this:
- Attendance rate: {$attendance} {$attendanceDetail}
- Assignment average: {$assignments}, {$assignmentsMissing}
- Quiz average: {$quizzes}, {$quizzesMissing}

The risk score is a weighted blend: attendance 30%, assignments 40%, quizzes 30%
(a component is skipped and weights are redistributed if that category has no
data yet).

Respond with:
1. "why" — a short, specific explanation of why this student landed at the
   {$riskLabel} risk level, citing the actual numbers above.
2. "how" — a brief note on which factor(s) drove the score most.
3. "recommended_actions" — 3-5 concrete, practical steps this teacher could
   take this week (e.g. outreach, targeted practice, parent contact, flexible
   deadlines), tailored to the specific weak area(s) shown above. Avoid vague
   advice like "monitor closely" — be concrete.
PROMPT;
}