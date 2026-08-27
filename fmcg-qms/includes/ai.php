<?php
/**
 * AI Engine - company-scoped AI Quality Assistant.
 * Provider/model/key are configured by Super Admin (ai_settings table), never hardcoded.
 * Every request is filtered by company_id and logged to ai_logs.
 * When AI is disabled or no key is configured, deterministic rule-based insights are
 * generated from real company data so the assistant still functions end-to-end.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/kpi_engine.php';

function get_ai_settings(): array
{
    $row = db_fetch_one("SELECT * FROM ai_settings ORDER BY id DESC LIMIT 1");
    return $row ?: ['enabled' => 0, 'provider' => 'none', 'api_key' => '', 'model' => '', 'temperature' => 0.4, 'token_limit' => 800];
}

function ai_is_enabled(): bool
{
    $s = get_ai_settings();
    return !empty($s['enabled']) && !empty($s['api_key']);
}

function ai_log(?int $companyId, ?int $userId, string $feature, string $prompt, string $response, int $tokens = 0): void
{
    db_execute(
        "INSERT INTO ai_logs (company_id, user_id, feature, prompt, response, tokens_used, created_at) VALUES (?,?,?,?,?,?,NOW())",
        'iisssi',
        [$companyId, $userId, $feature, mb_substr($prompt, 0, 4000), mb_substr($response, 0, 4000), $tokens]
    );
}

/**
 * Calls the configured AI provider over HTTPS. Generic chat-completion style request.
 * Returns null on any failure so callers can fall back to rule-based output.
 */
function ai_call_provider(string $systemPrompt, string $userPrompt): ?string
{
    $s = get_ai_settings();
    if (empty($s['enabled']) || empty($s['api_key']) || empty($s['api_endpoint'])) {
        return null;
    }
    $payload = json_encode([
        'model' => $s['model'] ?: 'default',
        'temperature' => (float)($s['temperature'] ?? 0.4),
        'max_tokens' => (int)($s['token_limit'] ?? 800),
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
    ]);
    $ch = curl_init($s['api_endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $s['api_key']],
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error || !$response) {
        return null;
    }
    $decoded = json_decode($response, true);
    return $decoded['choices'][0]['message']['content'] ?? $decoded['content'][0]['text'] ?? null;
}

/** AI Form Assistance - live suggestion while an employee enters tool data. */
function ai_form_suggestion(int $companyId, int $userId, string $toolName, array $fieldValues, array $deviations): string
{
    $prompt = "Tool: $toolName. Values: " . json_encode($fieldValues) . ". Deviations detected: " . json_encode($deviations);
    $response = ai_call_provider(
        "You are an FMCG quality assistant. Give one short, actionable suggestion (max 2 sentences) based on the submitted reading. Never state an unverified root cause as fact.",
        $prompt
    );
    if ($response === null) {
        if (empty($deviations)) {
            $response = "All values for \"$toolName\" are within specification. No action required.";
        } else {
            $first = $deviations[0];
            $response = "\"{$first['field']}\" is {$first['status']} (recorded {$first['value']}, expected {$first['range']}). "
                . "Verify measurement accuracy and check the process/equipment condition before escalating.";
        }
    }
    ai_log($companyId, $userId, 'form_assistance', $prompt, $response);
    return $response;
}

/** AI Root Cause Assistance for 5 Whys / Fishbone / FTA / 8D / A3 / Pareto / FMEA / DMAIC. */
function ai_root_cause_suggestion(int $companyId, int $userId, string $method, string $problemStatement, array $context = []): string
{
    $prompt = "Method: $method. Problem: $problemStatement. Context: " . json_encode($context);
    $response = ai_call_provider(
        "You are a Lean Six Sigma root-cause facilitator for FMCG manufacturing. Suggest possible contributing factors and next investigation "
        . "steps for the given $method problem-solving exercise. Present suggestions as hypotheses to verify, never as confirmed facts. Keep under 120 words.",
        $prompt
    );
    if ($response === null) {
        $response = "Consider common contributing factors across Man, Machine, Method, Material, Measurement and Environment for \"$problemStatement\". "
            . "Verify each hypothesis with data (readings, maintenance logs, training records, supplier certificates) before confirming a root cause. "
            . "Recommended next step: review recent deviations and change history around the time this issue occurred.";
    }
    ai_log($companyId, $userId, 'root_cause_assistance', $prompt, $response);
    return $response;
}

/** AI Dashboard Insights - deterministic, generated from real company KPI/trend data. */
function ai_dashboard_insights(int $companyId, int $userId = 0): array
{
    $insights = [];

    $topDept = db_fetch_one(
        "SELECT d.name, COUNT(qi.id) c FROM quality_issues qi JOIN departments d ON d.id = qi.department_id
         WHERE qi.company_id = ? AND qi.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY d.id ORDER BY c DESC LIMIT 1",
        'i', [$companyId]
    );
    if ($topDept && $topDept['c'] > 0) {
        $insights[] = "{$topDept['name']} recorded the highest number of quality issues this week ({$topDept['c']}).";
    }

    $shiftCompliance = db_fetch_all(
        "SELECT s.name, ROUND(SUM(CASE WHEN ts.status='missed' THEN 0 ELSE 1 END)/COUNT(*)*100,1) compliance
         FROM tool_submissions ts JOIN shifts s ON s.id = ts.shift_id
         WHERE ts.company_id = ? AND ts.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY s.id ORDER BY compliance ASC LIMIT 1",
        'i', [$companyId]
    );
    if ($shiftCompliance) {
        $insights[] = "{$shiftCompliance[0]['name']} shift has the lowest inspection compliance ({$shiftCompliance[0]['compliance']}%) over the last 14 days.";
    }

    $overdueCapa = db_count('capa', "company_id = ? AND status NOT IN ('closed','rejected') AND due_date < CURDATE()", 'i', [$companyId]);
    if ($overdueCapa > 0) {
        $insights[] = "$overdueCapa CAPA action(s) are overdue and require immediate follow-up.";
    }

    $paretoDefect = db_fetch_one(
        "SELECT defect_type, COUNT(*) c FROM quality_issues WHERE company_id = ? AND defect_type IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY defect_type ORDER BY c DESC LIMIT 1", 'i', [$companyId]
    );
    if ($paretoDefect) {
        $total = db_count('quality_issues', "company_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", 'i', [$companyId]);
        $pct = $total > 0 ? round($paretoDefect['c'] / $total * 100) : 0;
        $insights[] = "Defect type \"{$paretoDefect['defect_type']}\" represents {$pct}% of total defects logged in the last 30 days.";
    }

    $complaintTrend = db_fetch_all(
        "SELECT WEEK(created_at) wk, COUNT(*) c FROM customer_complaints WHERE company_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 28 DAY)
         GROUP BY wk ORDER BY wk ASC", 'i', [$companyId]
    );
    if (count($complaintTrend) >= 2) {
        $first = $complaintTrend[0]['c']; $last = end($complaintTrend)['c'];
        if ($first > 0 && $last > $first) {
            $growth = round((($last - $first) / $first) * 100);
            $insights[] = "Customer complaints have increased {$growth}% over the past 4 weeks.";
        }
    }

    if (empty($insights)) {
        $insights[] = "No significant quality anomalies detected yet. Insights will appear here as inspection and issue data accumulates.";
    }

    ai_log($companyId, $userId, 'dashboard_insights', 'auto-generated', implode(' | ', $insights));
    return $insights;
}

/** AI Quality Chat - answers only from this company's authorized data. */
function ai_chat_query(int $companyId, int $userId, string $question): string
{
    $context = build_ai_company_context($companyId);
    $response = ai_call_provider(
        "You are the AI Quality Assistant for an FMCG manufacturer using the " . APP_NAME . " QMS platform. "
        . "Answer ONLY using the JSON company data context provided. Never reference or assume data from any other company. "
        . "If the data doesn't contain the answer, say so. Be concise and specific (department/product/batch names, numbers).",
        "Company data context: " . json_encode($context) . "\n\nQuestion: $question"
    );
    if ($response === null) {
        $response = ai_rule_based_chat($companyId, $question, $context);
    }
    ai_log($companyId, $userId, 'chat', $question, $response);
    return $response;
}

function build_ai_company_context(int $companyId): array
{
    return [
        'top_defect_departments' => db_fetch_all(
            "SELECT d.name, COUNT(qi.id) issues FROM quality_issues qi JOIN departments d ON d.id=qi.department_id
             WHERE qi.company_id=? GROUP BY d.id ORDER BY issues DESC LIMIT 5", 'i', [$companyId]),
        'recurring_problems' => db_fetch_all(
            "SELECT defect_type, COUNT(*) occurrences FROM quality_issues WHERE company_id=? AND defect_type IS NOT NULL
             GROUP BY defect_type ORDER BY occurrences DESC LIMIT 5", 'i', [$companyId]),
        'top_complaint_products' => db_fetch_all(
            "SELECT p.name, COUNT(cc.id) complaints FROM customer_complaints cc JOIN products p ON p.id=cc.product_id
             WHERE cc.company_id=? GROUP BY p.id ORDER BY complaints DESC LIMIT 5", 'i', [$companyId]),
        'overdue_capa' => db_fetch_all(
            "SELECT capa_number, problem_statement, due_date, responsible_person FROM capa
             WHERE company_id=? AND status NOT IN ('closed','rejected') AND due_date < CURDATE() LIMIT 10", 'i', [$companyId]),
        'lowest_supplier_scores' => db_fetch_all(
            "SELECT s.name, ss.overall_score FROM supplier_scorecards ss JOIN suppliers s ON s.id=ss.supplier_id
             WHERE ss.company_id=? ORDER BY ss.overall_score ASC LIMIT 5", 'i', [$companyId]),
        'open_ncr' => db_count('ncr', "company_id=? AND status NOT IN ('closed')", 'i', [$companyId]),
        'open_capa' => db_count('capa', "company_id=? AND status NOT IN ('closed','rejected')", 'i', [$companyId]),
        'critical_issues_open' => db_count('quality_issues', "company_id=? AND severity='critical' AND status<>'closed'", 'i', [$companyId]),
    ];
}

function ai_rule_based_chat(int $companyId, string $question, array $context): string
{
    $q = strtolower($question);
    if (str_contains($q, 'department') && str_contains($q, 'defect')) {
        if (!empty($context['top_defect_departments'])) {
            $d = $context['top_defect_departments'][0];
            return "{$d['name']} has the highest defect rate with {$d['issues']} recorded issues.";
        }
    }
    if (str_contains($q, 'recurring') || str_contains($q, 'top') && str_contains($q, 'problem')) {
        if (!empty($context['recurring_problems'])) {
            $list = implode(', ', array_map(fn($r) => "{$r['defect_type']} ({$r['occurrences']}x)", array_slice($context['recurring_problems'], 0, 3)));
            return "Your top recurring quality problems are: $list.";
        }
    }
    if (str_contains($q, 'complaint')) {
        if (!empty($context['top_complaint_products'])) {
            $p = $context['top_complaint_products'][0];
            return "{$p['name']} has the most customer complaints ({$p['complaints']}).";
        }
    }
    if (str_contains($q, 'capa') && str_contains($q, 'overdue')) {
        $n = count($context['overdue_capa']);
        return $n > 0 ? "There are $n overdue CAPA action(s): " . implode(', ', array_column($context['overdue_capa'], 'capa_number')) . "."
            : "There are no overdue CAPA actions right now.";
    }
    if (str_contains($q, 'supplier')) {
        if (!empty($context['lowest_supplier_scores'])) {
            $s = $context['lowest_supplier_scores'][0];
            return "{$s['name']} has the lowest quality score ({$s['overall_score']}).";
        }
    }
    if (str_contains($q, 'investigate') || str_contains($q, 'first')) {
        if ($context['critical_issues_open'] > 0) {
            return "Start with the {$context['critical_issues_open']} open critical issue(s) - these carry the highest risk.";
        }
        if ($context['open_capa'] > 0) {
            return "Consider reviewing the {$context['open_capa']} open CAPA action(s) for priority follow-up.";
        }
    }
    return "Open issues: {$context['open_ncr']} NCR, {$context['open_capa']} CAPA, {$context['critical_issues_open']} critical quality issue(s). "
        . "Ask me about a specific department, product, supplier or CAPA for more detail.";
}
