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
 * @param string $lang           'en' (English) or 'tl' (Tagalog/Filipino)
 * @return array{success:bool, why?:string, how?:string, key_observations?:string[], recommended_actions?:string[], error?:string}
 */
function gemini_analyze_student_risk(string $studentName, string $subjectName, string $riskLabel, array $metrics, string $lang = 'en'): array
{
    if (!gemini_is_configured()) {
        return [
            'success' => false,
            'error'   => 'GEMINI_API_KEY is not configured in config.php.',
        ];
    }

    $lang = $lang === 'tl' ? 'tl' : 'en';
    $prompt = build_gemini_risk_prompt($studentName, $subjectName, $riskLabel, $metrics, $lang);

    $requestBody = [
        'contents' => [
            [
                'role'  => 'user',
                'parts' => [['text' => $prompt]],
            ],
        ],
        'generationConfig' => [
            'temperature'      => 0.4,
            'maxOutputTokens'  => 1100,
            'responseMimeType' => 'application/json',
            'responseSchema'   => [
                'type'       => 'OBJECT',
                'properties' => [
                    'why' => [
                        'type'        => 'STRING',
                        'description' => '4-6 sentence, teacher-facing explanation of WHY this student is flagged at this risk level. Reference the actual numbers given, describe the pattern over time where possible (e.g. which area is weakest, whether it looks like a one-off dip or a sustained trend), and note any interaction between factors (e.g. absences lining up with missed assignments).',
                    ],
                    'how' => [
                        'type'        => 'STRING',
                        'description' => '2-4 sentences explaining HOW the risk level was determined: which factor(s) weighed most heavily in the weighted score, and how the weighting (attendance 30%, assignments 40%, quizzes 30%) played out for this specific student.',
                    ],
                    'key_observations' => [
                        'type'  => 'ARRAY',
                        'items' => ['type' => 'STRING'],
                        'description' => '3-6 short, specific, individually-scannable data points pulled directly from the numbers given (e.g. exact percentages, counts of missing work, attendance breakdown). Each one sentence or less. These appear as a bullet list above the "why" narrative.',
                    ],
                    'recommended_actions' => [
                        'type'  => 'ARRAY',
                        'items' => ['type' => 'STRING'],
                        'description' => '4-6 short, concrete, actionable next steps the teacher can take this week, tailored to this student\'s specific weak area(s). Avoid vague advice like "monitor closely" — be concrete (who to contact, what to adjust, what to check first).',
                    ],
                ],
                'required' => ['why', 'how', 'key_observations', 'recommended_actions'],
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
            'key_observations'    => [],
            'recommended_actions' => [],
        ];
    }

    return [
        'success'             => true,
        'model'               => $model,
        'why'                 => (string) $parsed['why'],
        'how'                 => (string) $parsed['how'],
        'key_observations'    => array_values(array_map('strval', (array) ($parsed['key_observations'] ?? []))),
        'recommended_actions' => array_values(array_map('strval', (array) $parsed['recommended_actions'])),
    ];
}

function build_gemini_risk_prompt(string $studentName, string $subjectName, string $riskLabel, array $metrics, string $lang = 'en'): string
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

    $langInstruction = $lang === 'tl'
        ? 'Write your ENTIRE response — every field ("why", "how", "key_observations", and every item in "recommended_actions") — in natural, conversational Tagalog/Filipino, the way a Filipino teacher or school guidance counselor would actually speak. Keep specific numbers, percentages, and student/subject names as-is (do not translate numbers or proper nouns). Avoid overly formal or textbook-stiff Tagalog; write it the way it would actually be said in a Philippine classroom/faculty room setting. Do not mix in English sentences — if a term has no natural Tagalog equivalent (e.g. "quiz", "grade"), it is fine to keep that specific word, but full sentences should be in Tagalog.'
        : 'Write your entire response in clear, natural English.';

    return <<<PROMPT
You are an academic early-warning assistant helping a K-12 teacher understand
why a student was flagged by an automated at-risk detection system, and what
to do about it. Be specific, concise, and encouraging in tone — this goes
directly in front of a teacher, not the student. Go into real detail: don't
just restate the risk label, actually walk through the pattern in the data.

Student: {$studentName}
Subject/Class: {$subjectName}
Computed risk level: {$riskLabel} (risk score {$riskScore} / 100, higher = more at risk)

Underlying data used to compute this:
- Attendance rate: {$attendance} {$attendanceDetail}
- Assignment average: {$assignments}, {$assignmentsMissing}
- Quiz average: {$quizzes}, {$quizzesMissing}

The risk score is a weighted blend: attendance 30%, assignments 40%, quizzes 30%
(a component is skipped and weights are redistributed if that category doesn't
yet have at least 3 logged items — so a category shown as "no data" genuinely
isn't factored into the score, it's not being hidden).

Respond with:
1. "why" — a detailed (4-6 sentence), specific explanation of why this student
   landed at the {$riskLabel} risk level. Reference the actual numbers above,
   describe whether this looks like a one-off dip or a sustained pattern, and
   note any interaction between factors (e.g. do the absences line up with the
   missed assignments/quizzes?).
2. "how" — 2-4 sentences on which factor(s) drove the score most and how the
   30/40/30 weighting played out specifically for this student.
3. "key_observations" — 3-6 short, individually scannable bullet points, each
   citing a specific number from the data above (e.g. exact percentage, exact
   count of missing items, exact attendance breakdown). One short sentence each.
4. "recommended_actions" — 4-6 concrete, practical steps this teacher could
   take this week (e.g. outreach, targeted practice, parent contact, flexible
   deadlines), tailored to the specific weak area(s) shown above. Avoid vague
   advice like "monitor closely" — be concrete about who to contact, what to
   check first, and what to adjust.

{$langInstruction}
PROMPT;
}