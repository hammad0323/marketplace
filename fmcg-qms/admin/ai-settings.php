<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

$settings = get_ai_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $enabled = post('enabled') ? 1 : 0;
    $provider = post('provider', 'none');
    $apiKey = post_raw('api_key', '');
    $apiEndpoint = post('api_endpoint', '');
    $model = post('model', '');
    $temperature = post_float('temperature', 0.4);
    $tokenLimit = post_int('token_limit', 800);

    if ($settings && !empty($settings['id'])) {
        db_exec("UPDATE ai_settings SET provider=?, api_key=?, api_endpoint=?, model=?, temperature=?, token_limit=?, enabled=? WHERE id=?",
            [$provider, $apiKey, $apiEndpoint, $model, $temperature, $tokenLimit, $enabled, $settings['id']]);
    } else {
        db_exec("INSERT INTO ai_settings (provider, api_key, api_endpoint, model, temperature, token_limit, enabled) VALUES (?,?,?,?,?,?,?)",
            [$provider, $apiKey, $apiEndpoint, $model, $temperature, $tokenLimit, $enabled]);
    }
    log_activity(null, current_user_id(), 'update', 'ai_settings', null, 'Updated AI configuration');
    flash_set('success', 'AI settings saved.');
    redirect(base_url('admin/ai-settings.php'));
}

$pageTitle = 'AI Settings';
$activeMenu = 'ai-settings';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">AI Quality Assistant Settings</h4><p class="text-muted mb-0 small">Configure the AI provider used across all companies. Credentials are never hardcoded.</p></div>

<div class="row">
  <div class="col-lg-7">
    <form method="POST" class="qc-card">
      <?= csrf_field() ?>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" name="enabled" id="enabled" <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold" for="enabled">Enable AI Assistant platform-wide</label>
      </div>
      <div class="mb-3"><label class="form-label small fw-semibold">Provider</label>
        <select class="form-select" name="provider">
          <?php foreach (['none'=>'None (rule-based fallback only)','openai'=>'OpenAI-compatible','anthropic'=>'Anthropic-compatible','azure'=>'Azure OpenAI','custom'=>'Custom Endpoint'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($settings['provider'] ?? 'none')===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3"><label class="form-label small fw-semibold">API Endpoint</label>
        <input class="form-control" name="api_endpoint" value="<?= out($settings['api_endpoint'] ?? '') ?>" placeholder="https://api.provider.com/v1/chat/completions"></div>
      <div class="mb-3"><label class="form-label small fw-semibold">API Key</label>
        <input type="password" class="form-control" name="api_key" value="<?= out($settings['api_key'] ?? '') ?>" autocomplete="new-password"></div>
      <div class="row g-3 mb-3">
        <div class="col-md-4"><label class="form-label small fw-semibold">Model</label><input class="form-control" name="model" value="<?= out($settings['model'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Temperature</label><input type="number" step="0.1" min="0" max="1" class="form-control" name="temperature" value="<?= out($settings['temperature'] ?? 0.4) ?>"></div>
        <div class="col-md-4"><label class="form-label small fw-semibold">Token Limit</label><input type="number" class="form-control" name="token_limit" value="<?= out($settings['token_limit'] ?? 800) ?>"></div>
      </div>
      <button type="submit" class="btn btn-primary">Save AI Settings</button>
    </form>
  </div>
  <div class="col-lg-5">
    <div class="qc-card">
      <h3 class="mb-2">How this works</h3>
      <p class="small text-muted">Every AI request (form suggestions, root-cause assistance, dashboard insights, chat) is filtered by <code>company_id</code> before it reaches the provider, and logged to <code>ai_logs</code> with user, company, tokens and response.</p>
      <p class="small text-muted mb-0">When AI is disabled or unreachable, the platform automatically falls back to deterministic, rule-based insights generated from each company's own real data - the assistant never goes silent.</p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
