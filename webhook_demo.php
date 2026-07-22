<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Auth.php';

$auth = new NuAuth();
if (!$auth->checkAuth()) {
    header('Location: index.php');
    exit;
}

// Access restricted to globeadmin or admin
$currUser = $auth->getCurrentUser();
$role     = strtolower($currUser['usr_role'] ?? '');
if ($role !== 'globeadmin' && $role !== 'admin') {
    http_response_code(403);
    echo "Access denied: Developer/Admin only.";
    exit;
}

if (empty($nuConfig['enableWebhookDemo'])) {
    http_response_code(403);
    echo "Webhook Demo is disabled in config.php.";
    exit;
}

$db = NuDatabase::getInstance();

// Safe self-healing table setup for database demo logs (Optional/Bonus fallback)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `nu_webhook_logs` (
        `log_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `delivery_id` VARCHAR(64) NOT NULL,
        `webhook_id` INT UNSIGNED DEFAULT NULL,
        `event_type` VARCHAR(100) NOT NULL,
        `method` VARCHAR(10) NOT NULL DEFAULT 'POST',
        `url` VARCHAR(500) NOT NULL,
        `headers` TEXT DEFAULT NULL,
        `payload` TEXT DEFAULT NULL,
        `response` TEXT DEFAULT NULL,
        `http_code` INT DEFAULT 0,
        `status` VARCHAR(20) NOT NULL DEFAULT 'success',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    error_log("[Webhook Demo log schema setup warning] " . $e->getMessage());
}

// Check if we have at least one test webhook set up for api/webhook_demo_listener.php
$demoWebhook = null;
try {
    $demoWebhook = $db->fetchOne("SELECT * FROM `nu_webhooks` WHERE `webhook_url` LIKE '%webhook_demo_listener.php%' LIMIT 1");
} catch (Throwable $ignore) {}

$siteTitle = $nuConfig['siteTitle'] ?? 'nuvis';
$theme = $nuConfig['theme'] ?? 'auto';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars((string)$theme, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>nuvis Webhook Playground & Developer Demo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="assets/css/nubuilder-next.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-page, #f8fafc);
            color: var(--text, #1e293b);
            margin: 0;
            padding: 0;
        }
        .demo-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 24px;
        }
        .demo-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color, #e2e8f0);
            padding-bottom: 24px;
            margin-bottom: 32px;
        }
        .demo-title-area h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px 0;
            color: var(--accent, #4f6bed);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .demo-title-area p {
            margin: 0;
            color: var(--text-secondary, #64748b);
            font-size: 15px;
        }
        .badge-live {
            background: #dcfce7;
            color: #166534;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .playground-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
        }
        @media (max-width: 1024px) {
            .playground-grid {
                grid-template-columns: 1fr;
            }
        }
        .card-playground {
            background-color: var(--card-bg, #ffffff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            padding: 24px;
            margin-bottom: 24px;
        }
        .card-playground-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text, #1e293b);
            border-bottom: 1px solid var(--border-color, #edf2f7);
            padding-bottom: 12px;
        }
        .stepper {
            display: flex;
            justify-content: space-between;
            margin-bottom: 32px;
            background: var(--bg-secondary, #f1f5f9);
            padding: 16px;
            border-radius: 8px;
        }
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            text-align: center;
            position: relative;
        }
        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #cbd5e1;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 8px;
            z-index: 2;
            transition: all 0.3s ease;
        }
        .step-item.active .step-number {
            background: var(--accent, #4f6bed);
            color: white;
            box-shadow: 0 0 0 4px rgba(79, 107, 237, 0.2);
        }
        .step-item.completed .step-number {
            background: #10b981;
            color: white;
        }
        .step-title {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
        }
        .step-item.active .step-title {
            color: var(--accent, #4f6bed);
            font-weight: 600;
        }
        .playground-step-content {
            display: none;
        }
        .playground-step-content.active {
            display: block;
        }
        .btn-playground {
            font-weight: 500;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid transparent;
        }
        .btn-playground-primary {
            background-color: var(--accent, #4f6bed);
            color: white;
        }
        .btn-playground-primary:hover {
            background-color: #3b58db;
        }
        .btn-playground-secondary {
            background-color: var(--bg-secondary, #edf2f7);
            color: var(--text, #1e293b);
            border-color: var(--border-color, #cbd5e1);
        }
        .btn-playground-secondary:hover {
            background-color: #e2e8f0;
        }
        .btn-playground-success {
            background-color: #10b981;
            color: white;
        }
        .btn-playground-success:hover {
            background-color: #059669;
        }
        .btn-playground:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .sim-btn-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }
        .sim-card {
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 8px;
            padding: 14px;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--card-bg, #fff);
            text-align: left;
        }
        .sim-card:hover {
            border-color: var(--accent, #4f6bed);
            box-shadow: 0 4px 12px rgba(79, 107, 237, 0.08);
            transform: translateY(-1px);
        }
        .sim-card.active {
            border-color: var(--accent, #4f6bed);
            background-color: #eff6ff;
        }
        .sim-card-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .sim-card-desc {
            font-size: 12px;
            color: var(--text-secondary, #64748b);
        }
        .console-box {
            font-family: 'JetBrains Mono', monospace;
            background-color: #0f172a;
            color: #38bdf8;
            border-radius: 8px;
            padding: 16px;
            font-size: 13px;
            line-height: 1.6;
            overflow-x: auto;
            border: 1px solid #1e293b;
        }
        .code-snippet-tabs {
            display: flex;
            gap: 4px;
            border-bottom: 1px solid var(--border-color, #e2e8f0);
            margin-bottom: 16px;
        }
        .code-tab-btn {
            background: none;
            border: none;
            padding: 8px 16px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary, #64748b);
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        .code-tab-btn.active {
            color: var(--accent, #4f6bed);
            border-bottom-color: var(--accent, #4f6bed);
            font-weight: 600;
        }
        .snippet-content {
            display: none;
        }
        .snippet-content.active {
            display: block;
        }
        .copy-btn {
            float: right;
            background: rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: -8px;
        }
        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .hmac-compare {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 16px;
        }
        .hmac-box {
            border-radius: 8px;
            padding: 12px;
            font-size: 12px;
        }
        .hmac-received {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .hmac-computed {
            background-color: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .info-alert {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            color: #1e3a8a;
            padding: 14px;
            border-radius: 0 8px 8px 0;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .guide-step {
            margin-bottom: 12px;
            padding-left: 24px;
            position: relative;
        }
        .guide-step::before {
            content: "✓";
            position: absolute;
            left: 0;
            top: 2px;
            color: #10b981;
            font-weight: bold;
        }
        .payload-template-select {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid var(--border-color, #cbd5e1);
            background-color: var(--card-bg, #fff);
            color: var(--text, #1e293b);
            font-weight: 500;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>

<div class="demo-container">

    <!-- Header -->
    <div class="demo-header">
        <div class="demo-title-area">
            <h1>
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                </svg>
                Webhook Playground & Developer Demo
            </h1>
            <p>Real connections, customizable templates, and signature calculations for nuvis integration developers</p>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <span class="badge-live">Playground Ready</span>
            <a href="index.php#integrations" class="btn-playground btn-playground-secondary" style="text-decoration: none;">
                ← Back to Webhooks
            </a>
        </div>
    </div>

    <!-- Stepper Navigation -->
    <div class="stepper">
        <div class="step-item active" id="stepIndicator-1" onclick="jumpToStep(1)">
            <div class="step-number">1</div>
            <div class="step-title">Provision Endpoint</div>
        </div>
        <div class="step-item" id="stepIndicator-2" onclick="jumpToStep(2)">
            <div class="step-number">2</div>
            <div class="step-title">Configure & Select Template</div>
        </div>
        <div class="step-item" id="stepIndicator-3" onclick="jumpToStep(3)">
            <div class="step-number">3</div>
            <div class="step-title">Trigger Event Simulation</div>
        </div>
        <div class="step-item" id="stepIndicator-4" onclick="jumpToStep(4)">
            <div class="step-number">4</div>
            <div class="step-title">Secure & Verify Signatures</div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="playground-grid">

        <!-- Left Column: Controller Section -->
        <div>
            <!-- STEP 1: PROVISION ENDPOINT -->
            <div class="card-playground playground-step-content active" id="stepContent-1">
                <div class="card-playground-title">
                    <span>1. Automatic Mock Endpoint Provisioning</span>
                </div>
                <div class="info-alert">
                    The playground auto-detects and targets the local webhook demo listener to capture dispatches instantly in real-time.
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Receiver URL (Local Mock Endpoint):</label>
                    <input type="text" class="nu-input" id="demoTargetUrl" readonly style="width: 100%; font-family: monospace; font-size: 13px;">
                </div>
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Demo HMAC Secret Code:</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" class="nu-input" id="demoHmacSecret" value="nuvis_playground_secret_key" style="width: 100%; font-family: monospace; font-size: 13px;">
                        <button class="btn-playground btn-playground-secondary" onclick="generateRandomSecret()" style="white-space: nowrap;">Randomize</button>
                    </div>
                    <small style="color: var(--text-muted, #718096); display: block; margin-top: 4px;">This key will compute the secure SHA256 signature passed in the <code>X-Nuvis-Signature</code> header.</small>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <span style="color: var(--text-muted); font-size: 13px;">Status: <strong style="color: #10b981;">● Online</strong></span>
                    <button class="btn-playground btn-playground-primary" onclick="nextStep(2)">Configure Payload Template →</button>
                </div>
            </div>

            <!-- STEP 2: PAYLOAD CUSTOMIZER / TEMPLATE SELECTOR -->
            <div class="card-playground playground-step-content" id="stepContent-2">
                <div class="card-playground-title">
                    <span>2. Custom Output Formats & Templates</span>
                </div>
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
                    Select one of the pre-built target platforms to see how nuvis transforms generic webhook data into custom platform-compatible JSON bodies.
                </p>

                <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Target Integration Platform Template:</label>
                <select class="payload-template-select" id="payloadTemplateSelector" onchange="loadSelectedTemplate()">
                    <option value="slack">Slack Incoming Webhook (Block Kit Message)</option>
                    <option value="discord">Discord Embed (Rich Notification Panel)</option>
                    <option value="zapier">Zapier / Make / Generic JSON Object</option>
                    <option value="custom">Custom Format Template (User Editable)</option>
                </select>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Custom JSON Template Code:</label>
                    <textarea class="nu-input" id="payloadTemplateText" rows="7" style="width: 100%; font-family: 'JetBrains Mono', monospace; font-size: 12px;"></textarea>
                    <div style="margin-top: 6px; font-size: 11px; color: var(--text-muted);">
                        Placeholders: <code>{{event_type}}</code>, <code>{{record_id}}</code>, <code>{{table}}</code>, <code>{{actor}}</code>, <code>{{data.fieldname}}</code>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <button class="btn-playground btn-playground-secondary" onclick="prevStep(1)">← Back</button>
                    <button class="btn-playground btn-playground-primary" onclick="nextStep(3)">Configure Simulator Events →</button>
                </div>
            </div>

            <!-- STEP 3: SIMULATOR EVENTS TRIGGER -->
            <div class="card-playground playground-step-content" id="stepContent-3">
                <div class="card-playground-title">
                    <span>3. Trigger Simulation Dispatcher</span>
                </div>
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 16px;">
                    Click any simulation event to assemble a payload, replace the template variables, sign it, and post it to the endpoint.
                </p>

                <div class="sim-btn-grid">
                    <button class="sim-card active" onclick="selectSimEvent('form_insert', this)">
                        <span class="sim-card-title">📝 Record Created</span>
                        <span class="sim-card-desc">Simulates inserting a new customer record</span>
                    </button>
                    <button class="sim-card" onclick="selectSimEvent('form_update', this)">
                        <span class="sim-card-title">🔄 Record Updated</span>
                        <span class="sim-card-desc">Simulates editing a warehouse SKU entry</span>
                    </button>
                    <button class="sim-card" onclick="selectSimEvent('workflow_advance', this)">
                        <span class="sim-card-title">🚀 Workflow Advanced</span>
                        <span class="sim-card-desc">Fires when a manager signs off on an invoice</span>
                    </button>
                    <button class="sim-card" onclick="selectSimEvent('user_login', this)">
                        <span class="sim-card-title">🔑 User Logged In</span>
                        <span class="sim-card-desc">Triggers security checks upon user sign in</span>
                    </button>
                </div>

                <div class="info-alert" style="background: #fafafa; border-color: #e2e8f0; color: #334155; margin-bottom: 24px;">
                    <strong>Event Payload Data:</strong>
                    <pre id="payloadDataPreview" style="font-size: 11px; margin: 6px 0 0 0; background: #f1f5f9; padding: 8px; border-radius: 4px; overflow-x: auto; max-height: 120px; font-family: monospace;"></pre>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 16px;">
                    <button class="btn-playground btn-playground-secondary" onclick="prevStep(2)">← Back</button>
                    <button class="btn-playground btn-playground-success" id="fireWebhookBtn" onclick="fireSimulatedWebhook()">
                        ⚡ Dispatch Simulated Webhook
                    </button>
                </div>
            </div>

            <!-- STEP 4: DEVELOPER DOCS & HMAC MATHEMATICS -->
            <div class="card-playground playground-step-content" id="stepContent-4">
                <div class="card-playground-title">
                    <span>4. How To Secure Your Webhooks</span>
                </div>
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
                    To protect your external listener from spoofing or unauthorized calls, always verify the <code>X-Nuvis-Signature</code> header.
                </p>

                <div class="info-alert" style="background: #fbfbfe; border-color: #818cf8; color: #1e1b4b; padding: 12px; font-size: 12px; margin-bottom: 16px;">
                    <strong>The Verification Formula:</strong><br>
                    Compute HMAC SHA256 using the raw request body as the string and your configured Secret Key as the hash secret.
                    <code style="display: block; background: #e0e7ff; padding: 4px 8px; border-radius: 4px; margin-top: 4px; font-weight: 600;">ComputedSig = hex(hmac_sha256(RawPostPayload, SecretCode))</code>
                </div>

                <div class="code-snippet-tabs">
                    <button class="code-tab-btn active" onclick="switchSnippetTab('php')">PHP</button>
                    <button class="code-tab-btn" onclick="switchSnippetTab('node')">Node.js</button>
                    <button class="code-tab-btn" onclick="switchSnippetTab('python')">Python</button>
                    <button class="code-tab-btn" onclick="switchSnippetTab('curl')">cURL</button>
                </div>

                <!-- PHP Snippet -->
                <div id="snippet-php" class="snippet-content active">
                    <button class="copy-btn" onclick="copySnippetText('snippetText-php')">Copy Code</button>
                    <pre class="console-box" id="snippetText-php" style="background: #1e293b; color: #f8fafc; font-size: 11px; max-height: 250px; overflow-y: auto;">&lt;?php
$secret = 'your_secret_key';
$headers = getallheaders();
$receivedSig = $headers['X-Nuvis-Signature'] ?? '';
$payload = file_get_contents('php://input');

if (empty($receivedSig)) {
    http_response_code(400);
    die('Missing signature header');
}

$computedSig = hash_hmac('sha256', $payload, $secret);

if (!hash_equals($computedSig, $receivedSig)) {
    http_response_code(401);
    die('Unauthorized payload signature');
}

// Signature matches! Safe to process event:
$event = $headers['X-Nuvis-Event'] ?? '';
$data = json_decode($payload, true);
echo "Successfully authenticated!";
?&gt;</pre>
                </div>

                <!-- Node.js Snippet -->
                <div id="snippet-node" class="snippet-content">
                    <button class="copy-btn" onclick="copySnippetText('snippetText-node')">Copy Code</button>
                    <pre class="console-box" id="snippetText-node" style="background: #1e293b; color: #f8fafc; font-size: 11px; max-height: 250px; overflow-y: auto;">const express = require('express');
const crypto = require('crypto');
const app = express();

const SECRET = 'your_secret_key';

app.post('/webhook', express.raw({ type: 'application/json' }), (req, res) => {
  const signature = req.headers['x-nuvis-signature'];
  const payload = req.body.toString('utf8');

  if (!signature) {
    return res.status(400).send('Missing X-Nuvis-Signature');
  }

  const computedSig = crypto
    .createHmac('sha256', SECRET)
    .update(payload)
    .digest('hex');

  if (signature !== computedSig) {
    return res.status(401).send('Invalid signature');
  }

  // Handle verified payload
  const event = req.headers['x-nuvis-event'];
  console.log(`Received event: ${event}`);
  res.status(200).send({ success: true });
});

app.listen(3000);</pre>
                </div>

                <!-- Python Snippet -->
                <div id="snippet-python" class="snippet-content">
                    <button class="copy-btn" onclick="copySnippetText('snippetText-python')">Copy Code</button>
                    <pre class="console-box" id="snippetText-python" style="background: #1e293b; color: #f8fafc; font-size: 11px; max-height: 250px; overflow-y: auto;">import hmac
import hashlib
from flask import Flask, request, abort

app = Flask(__name__)
SECRET = b"your_secret_key"

@app.route('/webhook', methods=['POST'])
def webhook():
    signature = request.headers.get('X-Nuvis-Signature')
    if not signature:
        abort(400, "Missing signature")

    # Calculate signature on raw request body bytes
    computed = hmac.new(SECRET, request.data, hashlib.sha256).hexdigest()

    if not hmac.compare_digest(computed, signature):
        abort(401, "Invalid signature header")

    event_type = request.headers.get('X-Nuvis-Event')
    print(f"Verified event: {event_type}")
    return {"status": "success"}, 200</pre>
                </div>

                <!-- cURL Snippet -->
                <div id="snippet-curl" class="snippet-content">
                    <button class="copy-btn" onclick="copySnippetText('snippetText-curl')">Copy Code</button>
                    <pre class="console-box" id="snippetText-curl" style="background: #1e293b; color: #f8fafc; font-size: 11px; max-height: 250px; overflow-y: auto;">curl -X POST https://yourdomain.com/webhook \
  -H "Content-Type: application/json" \
  -H "X-Nuvis-Event: test_webhook" \
  -H "X-Nuvis-Signature: hex_computed_hash_here" \
  -d '{"event_type":"test_webhook","record_id":"123","actor":"Demo"}'</pre>
                </div>

                <div style="display: flex; gap: 8px; align-items: center; margin-top: 14px;">
                    <button class="btn-playground btn-playground-secondary" onclick="prevStep(3)">← Back</button>
                    <button class="btn-playground btn-playground-primary" onclick="jumpToStep(1)">Start Over</button>
                </div>
            </div>
        </div>

        <!-- Right Column: Real-time Output & Logs Monitoring -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <!-- LIVE SIGNATURE DECODER PANEL -->
            <div class="card-playground" style="margin-bottom: 0;">
                <div class="card-playground-title">
                    <span>💡 Real-time HMAC Signature Decoder</span>
                </div>
                <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 12px;">
                    When you click dispatch, the live comparison below validates the HMAC key algorithm integrity.
                </div>

                <div style="background-color: var(--bg-secondary, #f8fafc); border: 1px dashed var(--border-color, #e2e8f0); border-radius: 8px; padding: 14px;">
                    <div style="font-size: 12px; margin-bottom: 6px;"><strong>Raw Computed Event String:</strong></div>
                    <code id="computedStringOutput" style="word-break: break-all; font-size: 11px; color: #475569;">(Awaiting Event Dispatch...)</code>
                </div>

                <div class="hmac-compare">
                    <div class="hmac-box hmac-received">
                        <strong>Received Signature Header:</strong>
                        <div id="receivedSigHash" style="word-break: break-all; font-family: monospace; font-size: 11px; margin-top: 6px;">None</div>
                    </div>
                    <div class="hmac-box hmac-computed">
                        <strong>Computed SHA256 Signature:</strong>
                        <div id="computedSigHash" style="word-break: break-all; font-family: monospace; font-size: 11px; margin-top: 6px;">None</div>
                    </div>
                </div>

                <div style="margin-top: 14px; text-align: center; font-size: 12px; font-weight: 600;" id="signatureMatchResult">
                    <span style="color: var(--text-muted);">Please click "Dispatch Simulated Webhook" in Step 3 to test connections.</span>
                </div>
            </div>

            <!-- STREAM / RECEIVED LOGS -->
            <div class="card-playground" style="flex: 1; display: flex; flex-direction: column; margin-bottom: 0;">
                <div class="card-playground-title" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span>📬 Real-time Received Payload Stream</span>
                    <div style="display: flex; gap: 6px;">
                        <button class="btn-playground btn-playground-secondary" onclick="fetchReceivedLogs()" style="padding: 4px 10px; font-size: 11px; background: none;">
                            🔄 Refresh Stream
                        </button>
                        <button class="btn-playground" onclick="clearLogs()" style="padding: 4px 10px; font-size: 11px; background: #fee2e2; color: #dc2626; border-color: #fecaca;">
                            🗑️ Clear Logs
                        </button>
                    </div>
                </div>
                <div id="receivedPayloadLogs" style="flex: 1; min-height: 250px; max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; padding: 14px; background-color: #0f172a; color: #38bdf8;">
                    <div style="color: #64748b; font-style: italic; text-align: center; padding-top: 80px;">
                        Listening for local webhooks... Dispatching events will capture and display live logs here.
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    let currentStep = 1;
    let selectedEvent = 'form_insert';

    const mockEventPayloads = {
        form_insert: {
            event_type: 'form_insert',
            record_id: 'cust_8273',
            table: 'nu_customers',
            data: {
                company_name: 'Acme Global Corp',
                contact_email: 'billing@acme.com',
                account_type: 'enterprise',
                country: 'United States',
                created_at: '2026-05-27 10:14:32'
            }
        },
        form_update: {
            event_type: 'form_update',
            record_id: 'sku_99388',
            table: 'nu_warehouse_stock',
            data: {
                item_label: 'Gigabit Network Router v5',
                stock_quantity: 42,
                bin_location: 'Aisle 14B',
                restock_level: 15,
                unit_price: 189.50
            }
        },
        workflow_advance: {
            event_type: 'workflow_advance',
            record_id: 'inv_44093',
            table: 'nu_invoices',
            data: {
                invoice_amount: 12450.00,
                approved_by: 'Alex Johnson',
                previous_stage: 'manager_review',
                current_stage: 'accounts_payable',
                advance_message: 'Approved within budget thresholds.'
            }
        },
        user_login: {
            event_type: 'user_login',
            record_id: 'usr_293849',
            table: 'nu_users',
            data: {
                username: 'lisa_dev',
                login_ip_address: '192.168.1.144',
                auth_strategy: '2FA_authenticator',
                user_role_scope: 'admin',
                session_duration_minutes: 60
            }
        }
    };

    const prebuiltTemplates = {
        slack: `{
  "blocks": [
    {
      "type": "header",
      "text": {
        "type": "plain_text",
        "text": "🚨 nuvis Event: {{event_type}}"
      }
    },
    {
      "type": "section",
      "fields": [
        {
          "type": "mrkdwn",
          "text": "*Record ID:* {{record_id}}"
        },
        {
          "type": "mrkdwn",
          "text": "*Table:* {{table}}"
        },
        {
          "type": "mrkdwn",
          "text": "*Operator:* {{actor}}"
        },
        {
          "type": "mrkdwn",
          "text": "*Timestamp:* {{timestamp}}"
        }
      ]
    }
  ]
}`,
        discord: `{
  "username": "nuvis Webhook",
  "avatar_url": "https://raw.githubusercontent.com/ishaana10/nuvis/main/assets/images/logo.png",
  "embeds": [
    {
      "title": "System Webhook Triggered - {{event_type}}",
      "color": 5202925,
      "fields": [
        {"name": "Database Table", "value": "{{table}}", "inline": true},
        {"name": "Record ID", "value": "{{record_id}}", "inline": true},
        {"name": "Actor Profile", "value": "{{actor}}", "inline": true}
      ],
      "footer": {
        "text": "nuvis Portal Alerts • {{timestamp}}"
      }
    }
  ]
}`,
        zapier: `{
  "event_type": "{{event_type}}",
  "record_id": "{{record_id}}",
  "table": "{{table}}",
  "actor": "{{actor}}",
  "timestamp": "{{timestamp}}",
  "payload_data": {{data}}
}`,
        custom: `{
  "custom_message": "Hello from custom template!",
  "event_meta": {
    "action": "{{event_type}}",
    "timestamp_unix": "{{timestamp}}"
  }
}`
    };

    // Initialize Page
    document.addEventListener('DOMContentLoaded', function () {
        updateListenerUrlInfo();
        loadSelectedTemplate();
        selectSimEvent('form_insert', document.querySelector('.sim-card'));
        fetchReceivedLogs();
        // Auto-poll logs every 4 seconds
        setInterval(fetchReceivedLogs, 4000);
    });

    function updateListenerUrlInfo() {
        const origin = window.location.origin;
        const path = window.location.pathname.replace('webhook_demo.php', '').replace(/\/$/, '');
        const url = `${origin}${path}/api/webhook_demo_listener.php`;
        document.getElementById('demoTargetUrl').value = url;
    }

    function jumpToStep(step) {
        currentStep = step;
        document.querySelectorAll('.step-item').forEach((indicator, index) => {
            const idx = index + 1;
            indicator.classList.remove('active', 'completed');
            if (idx === step) {
                indicator.classList.add('active');
            } else if (idx < step) {
                indicator.classList.add('completed');
            }
        });

        document.querySelectorAll('.playground-step-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(`stepContent-${step}`).classList.add('active');
    }

    function nextStep(step) {
        jumpToStep(step);
    }

    function prevStep(step) {
        jumpToStep(step);
    }

    function generateRandomSecret() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        let result = '';
        for (let i = 0; i < 20; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('demoHmacSecret').value = result;
    }

    function loadSelectedTemplate() {
        const platform = document.getElementById('payloadTemplateSelector').value;
        const template = prebuiltTemplates[platform];
        document.getElementById('payloadTemplateText').value = template;
    }

    function selectSimEvent(event, btn) {
        selectedEvent = event;
        document.querySelectorAll('.sim-card').forEach(card => card.classList.remove('active'));
        btn.classList.add('active');

        // Render mock data preview
        const data = mockEventPayloads[event];
        document.getElementById('payloadDataPreview').textContent = JSON.stringify(data, null, 2);
    }

    function switchSnippetTab(lang) {
        document.querySelectorAll('.code-tab-btn').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');

        document.querySelectorAll('.snippet-content').forEach(snippet => snippet.classList.remove('active'));
        document.getElementById(`snippet-${lang}`).classList.add('active');
    }

    function copySnippetText(id) {
        const text = document.getElementById(id).textContent;
        navigator.clipboard.writeText(text).then(() => {
            alert('Code template copied to clipboard!');
        }).catch(err => {
            alert('Failed to auto-copy code. Please select and copy manually.');
        });
    }

    // Live execution
    async function fireSimulatedWebhook() {
        const fireBtn = document.getElementById('fireWebhookBtn');
        fireBtn.disabled = true;
        fireBtn.innerHTML = '⚡ Processing Dispatch...';

        const event = selectedEvent;
        const payloadData = mockEventPayloads[event];
        const secret = document.getElementById('demoHmacSecret').value;
        const template = document.getElementById('payloadTemplateText').value;
        const targetUrl = document.getElementById('demoTargetUrl').value;

        try {
            const res = await fetch('api/webhook.php?action=save_and_test_playground', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    url: targetUrl,
                    secret: secret,
                    event_type: event,
                    payload_data: payloadData,
                    template: template
                })
            });

            const data = await res.json();
            if (data.success) {
                // Update live signature decoder
                document.getElementById('computedStringOutput').textContent = data.raw_rendered_payload;
                document.getElementById('receivedSigHash').textContent = data.signature;
                document.getElementById('computedSigHash').textContent = data.computed_signature;

                const matchResult = document.getElementById('signatureMatchResult');
                if (data.signature === data.computed_signature) {
                    matchResult.innerHTML = '<span style="color: #10b981;">✓ SIGNATURE VERIFIED: Computed and Received headers match perfectly! Connection secure.</span>';
                } else {
                    matchResult.innerHTML = '<span style="color: #dc2626;">⚠ WARNING: Signature mismatch. Received and Computed hashes do not match. Check Secret Key.</span>';
                }

                // Advance user visually to security panel
                setTimeout(() => {
                    jumpToStep(4);
                }, 1200);

                // Fetch latest logs
                fetchReceivedLogs();
            } else {
                alert('Webhook simulation failed: ' + (data.error || 'Unknown Error'));
            }
        } catch (e) {
            alert('Error running simulation: ' + e.message);
        } finally {
            fireBtn.disabled = false;
            fireBtn.innerHTML = '⚡ Dispatch Simulated Webhook';
        }
    }

    async function fetchReceivedLogs() {
        const logBox = document.getElementById('receivedPayloadLogs');
        if (!logBox) return;

        try {
            const res = await fetch('api/webhook_demo_listener.php?action=list_logs');
            const data = await res.json();

            if (data.success && data.logs && data.logs.length > 0) {
                logBox.innerHTML = '';
                data.logs.forEach(log => {
                    const item = document.createElement('div');
                    item.style.cssText = 'border-bottom: 1px solid #1e293b; padding-bottom: 12px; margin-bottom: 12px;';

                    const sigBadge = log.headers['X-Nuvis-Signature'] || log.headers['x-nuvis-signature']
                        ? `<span style="background: #10b981; color: white; padding: 1px 4px; border-radius: 3px; font-size: 10px; font-weight: 700; margin-left: 6px;">HMAC Signed</span>`
                        : `<span style="background: #ef4444; color: white; padding: 1px 4px; border-radius: 3px; font-size: 10px; font-weight: 700; margin-left: 6px;">Unsigned</span>`;

                    item.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <span>
                                <span style="background: #34d399; color: #0f172a; font-weight: 800; padding: 1px 6px; border-radius: 3px; font-size: 11px; margin-right: 6px;">${log.method}</span>
                                <span style="color: #e2e8f0; font-weight: 600;">Event: ${log.event}</span>
                                ${sigBadge}
                            </span>
                            <span style="color: #64748b; font-size: 11px;">${log.received_at}</span>
                        </div>
                        <div style="color: #a5f3fc; font-size: 11px; margin-bottom: 4px;"><strong>HTTP Signature Header:</strong></div>
                        <code style="background: #1e293b; color: #f43f5e; display: block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-family: monospace; overflow-x: auto; margin-bottom: 6px;">${log.headers['X-Nuvis-Signature'] || log.headers['x-nuvis-signature'] || 'None'}</code>
                        <div style="color: #a5f3fc; font-size: 11px; margin-bottom: 4px;"><strong>Resolved Body Payload:</strong></div>
                        <pre style="background: #020617; color: #38bdf8; padding: 8px; border-radius: 4px; font-size: 11px; overflow-x: auto; margin: 0; font-family: monospace;">${JSON.stringify(log.payload, null, 2)}</pre>
                    `;
                    logBox.appendChild(item);
                });
            } else {
                logBox.innerHTML = `
                    <div style="color: #64748b; font-style: italic; text-align: center; padding-top: 80px;">
                        Listening for local webhooks... Dispatching events will capture and display live logs here.
                    </div>
                `;
            }
        } catch (e) {
            logBox.innerHTML = `<div style="color: #ef4444; font-size: 12px; text-align: center; padding-top: 50px;">Error reloading logs: ${e.message}</div>`;
        }
    }

    async function clearLogs() {
        if (!confirm('Are you sure you want to clear all webhook delivery streams?')) return;
        try {
            const res = await fetch('api/webhook_demo_listener.php?action=clear_logs', { method: 'POST' });
            const data = await res.json();
            if (data.success) {
                fetchReceivedLogs();
            }
        } catch (e) {
            alert('Failed to clear logs: ' + e.message);
        }
    }
</script>

</body>
</html>
