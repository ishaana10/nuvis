<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';

// Retrieve some system details
$currentUser = $auth->getCurrentUser();
$userRole = strtolower($currentUser['usr_role'] ?? '');
?>

<div class="nu-barcode-module">

  <!-- ── PAGE HEADER ────────────────────────────────────────────────── -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
    <div>
      <h2 style="font-size:20px;font-weight:600;margin:0;">Barcode Management System</h2>
      <p style="color:var(--text-secondary);font-size:13px;margin:2px 0 0;">Generate, print, and scan high-fidelity barcodes for products, goods, and services.</p>
    </div>
  </div>

  <div id="bcAlertArea"></div>

  <!-- ── TABS BAR ───────────────────────────────────────────────────── -->
  <div class="bc-tab-bar flex gap-2 p-1 bg-slate-100 dark:bg-slate-800 rounded-lg w-fit mb-6">
    <button type="button" class="bc-tab-btn active px-4 py-2 text-xs font-semibold rounded-md transition-all duration-150" onclick="bcSwitchTab('products', this)">📦 Item Inventory</button>
    <button type="button" class="bc-tab-btn px-4 py-2 text-xs font-semibold rounded-md transition-all duration-150" onclick="bcSwitchTab('scanner', this)">🔍 Scan &amp; Read</button>
    <button type="button" class="bc-tab-btn px-4 py-2 text-xs font-semibold rounded-md transition-all duration-150" onclick="bcSwitchTab('generator', this)">⚙️ Barcode Generator</button>
  </div>

  <!-- ── TAB 1: PRODUCT INVENTORY ───────────────────────────────────── -->
  <div class="bc-tab-panel active" id="bc-tab-products">
    <div class="nu-card" style="padding:20px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--border-color);padding-bottom:10px;">
        <h3 style="font-size:15px;font-weight:600;margin:0;">Registered Items &amp; Codes</h3>
        <button type="button" class="nu-btn nu-btn-primary nu-btn-sm" onclick="bcOpenItemModal(null)">+ Add New Item</button>
      </div>

      <div style="overflow-x:auto;">
        <table class="bc-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Type</th>
              <th>Barcode</th>
              <th>Price</th>
              <th>Description</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody id="bcProductsBody">
            <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-secondary);">Loading items…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── TAB 2: SCANNER & READER ────────────────────────────────────── -->
  <div class="bc-tab-panel" id="bc-tab-scanner" style="display:none;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

      <!-- LEFT: Live Scan Camera and Manual Input -->
      <div class="flex flex-col gap-5">
        <div class="nu-card p-5">
          <h3 style="font-size:15px;font-weight:600;margin:0 0 12px;border-bottom:1px solid var(--border-color);padding-bottom:10px;">🔴 Live Camera Scan</h3>

          <div id="bcCameraContainer" class="relative bg-slate-900 rounded-lg overflow-hidden border border-slate-700 flex flex-col items-center justify-center mb-4" style="height:280px;">
            <div id="bcInteractiveReader" class="w-full h-full"></div>
            <div id="bcCameraOverlay" class="absolute inset-0 pointer-events-none border-2 border-dashed border-red-500/50 m-10 rounded-lg flex items-center justify-center animate-pulse">
              <div class="w-full h-0.5 bg-red-500 absolute animate-bounce" style="top:50%;"></div>
            </div>
            <div id="bcNoCamera" class="absolute inset-0 bg-slate-950 flex flex-col items-center justify-center p-6 text-center text-slate-400">
              <svg class="w-12 h-12 text-slate-600 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
              </svg>
              <p class="font-semibold text-sm mb-1 text-slate-200">Camera Feed Inactive</p>
              <p class="text-xs max-w-xs mb-3 text-slate-500">Enable webcam to scan 1D or 2D barcodes in real-time.</p>
              <button type="button" class="nu-btn nu-btn-primary nu-btn-sm" onclick="bcStartWebcam()">⚡ Start Camera</button>
            </div>
          </div>

          <div style="display:flex;gap:10px;">
            <button type="button" class="nu-btn nu-btn-ghost flex-1 py-2 text-xs" onclick="bcStopWebcam()">Stop Camera</button>
          </div>
        </div>

        <div class="nu-card p-5">
          <h3 style="font-size:15px;font-weight:600;margin:0 0 12px;border-bottom:1px solid var(--border-color);padding-bottom:10px;">⌨️ Keyboard / Scanner Input</h3>
          <div class="flex gap-2">
            <input type="text" class="nu-input flex-1" id="bcManualInputCode" placeholder="Enter barcode number (e.g. 7501031311309)" onkeypress="if(event.key==='Enter') bcLookupManual()">
            <button type="button" class="nu-btn nu-btn-primary" onclick="bcLookupManual()">Lookup</button>
          </div>
        </div>

        <div class="nu-card p-5">
          <h3 style="font-size:15px;font-weight:600;margin:0 0 12px;border-bottom:1px solid var(--border-color);padding-bottom:10px;">🎯 Quick Simulation (Click to scan)</h3>
          <p class="text-xs text-slate-500 mb-3">Simulate a barcode hardware scan by clicking on any of these registered items:</p>
          <div id="bcSimulationList" class="flex flex-col gap-2 max-h-48 overflow-y-auto pr-1"></div>
        </div>
      </div>

      <!-- RIGHT: Scan Result Profile Card -->
      <div class="nu-card p-6 min-h-[400px] flex flex-col justify-between">
        <div>
          <h3 style="font-size:15px;font-weight:600;margin:0 0 16px;border-bottom:1px solid var(--border-color);padding-bottom:10px;">📄 Scanned Item Profile</h3>

          <div id="bcScanResultEmpty" class="flex flex-col items-center justify-center py-20 text-center text-slate-400">
            <svg class="w-16 h-16 text-slate-300 dark:text-slate-700 mb-3" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 14.25v.75m0 0v1.5m0-1.5h-1.5m1.5 0H21m-2.25 3h1.5m-1.5 0H18m1.5 0V21m-1.5-1.5H15M15 15h1.5m-1.5 0V13.5m0 1.5H13.5" />
            </svg>
            <p class="font-medium text-sm text-slate-400">Waiting for scans or manual lookup…</p>
          </div>

          <div id="bcScanResultContent" class="hidden flex flex-col gap-4">
            <div class="flex justify-between items-start">
              <div>
                <span id="bcResultType" class="px-2.5 py-0.5 text-[10px] font-bold tracking-wide uppercase rounded-full">PRODUCT</span>
                <h4 id="bcResultName" class="text-xl font-bold text-slate-800 dark:text-slate-100 mt-1">Item Name</h4>
              </div>
              <div id="bcResultPrice" class="text-2xl font-black text-emerald-600 dark:text-emerald-400">$0.00</div>
            </div>

            <div class="bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-lg p-3">
              <span class="text-xs text-slate-400 font-semibold uppercase block mb-1">Barcode Value</span>
              <span id="bcResultCode" class="font-mono text-base font-bold text-slate-800 dark:text-slate-100 block">7501031311309</span>
            </div>

            <div>
              <span class="text-xs text-slate-400 font-semibold uppercase block mb-1">Description</span>
              <p id="bcResultDesc" class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">No description provided for this item.</p>
            </div>

            <div class="flex justify-center py-4 bg-white dark:bg-slate-950 border border-slate-100 dark:border-slate-900 rounded-lg shadow-inner">
              <svg id="bcResultSvg"></svg>
              <div id="bcResultQr" class="hidden"></div>
            </div>
          </div>
        </div>

        <div id="bcScanResultActions" class="hidden flex gap-2 pt-4 border-t border-slate-100 dark:border-slate-800 mt-6">
          <button type="button" class="nu-btn nu-btn-ghost flex-1 py-2 text-xs" id="bcEditBtnResult" onclick="">⚙️ Edit Item Details</button>
          <button type="button" class="nu-btn nu-btn-primary flex-1 py-2 text-xs" id="bcPrintBtnResult" onclick="">🖨️ Print Label</button>
        </div>
      </div>

    </div>
  </div>

  <!-- ── TAB 3: BARCODE GENERATOR ───────────────────────────────────── -->
  <div class="bc-tab-panel" id="bc-tab-generator" style="display:none;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

      <!-- LEFT: Configure Generator -->
      <div class="nu-card p-5">
        <h3 style="font-size:15px;font-weight:600;margin:0 0 16px;border-bottom:1px solid var(--border-color);padding-bottom:10px;">🏷️ Barcode Designer</h3>

        <div class="nu-field mb-4">
          <label>Select Item to Code</label>
          <select id="bcGenItemSelect" class="nu-input" onchange="bcOnGenItemChange()">
            <option value="">-- Choose registered item --</option>
          </select>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div class="nu-field" style="margin:0;">
            <label>Barcode Standard</label>
            <select id="bcGenFormat" class="nu-input" onchange="bcRenderGeneratedBarcode()">
              <option value="CODE128">CODE 128 (Standard)</option>
              <option value="CODE39">CODE 39 (Alphanumeric)</option>
              <option value="EAN13">EAN-13 (Numeric Only)</option>
              <option value="QR">QR Code (2D)</option>
            </select>
          </div>
          <div class="nu-field" style="margin:0;">
            <label>Label Prefix</label>
            <input type="text" class="nu-input" id="bcGenPrefix" value="" placeholder="e.g. PRD-" oninput="bcRenderGeneratedBarcode()">
          </div>
        </div>

        <div class="nu-field mb-4">
          <label>Barcode Code/Value</label>
          <div class="flex gap-2">
            <input type="text" class="nu-input flex-1" id="bcGenCode" placeholder="7501031311309" oninput="bcRenderGeneratedBarcode()">
            <button type="button" class="nu-btn nu-btn-ghost text-xs" onclick="bcGenerateNewCode()">⚡ Auto-Gen</button>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:16px;">
          <div class="nu-field" style="margin:0;">
            <label>Width/Scale</label>
            <input type="number" class="nu-input" id="bcGenWidth" value="2" min="1" max="4" oninput="bcRenderGeneratedBarcode()">
          </div>
          <div class="nu-field" style="margin:0;">
            <label>Height (px)</label>
            <input type="number" class="nu-input" id="bcGenHeight" value="70" min="20" max="150" oninput="bcRenderGeneratedBarcode()">
          </div>
          <div class="nu-field" style="margin:0;">
            <label>Label Size</label>
            <select id="bcLabelSize" class="nu-input">
              <option value="standard">Standard (2.0" x 1.0")</option>
              <option value="small">Small (1.5" x 0.5")</option>
              <option value="large">Large (3.0" x 2.0")</option>
            </select>
          </div>
        </div>

        <div class="nu-field mb-5">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;">
            <input type="checkbox" id="bcGenIncludeText" checked style="width:16px;height:16px;" onchange="bcRenderGeneratedBarcode()">
            Include human-readable code text below barcode
          </label>
        </div>
      </div>

      <!-- RIGHT: Preview & Print Sheet -->
      <div class="nu-card p-6 flex flex-col justify-between" style="min-height:360px;">
        <div>
          <h3 style="font-size:15px;font-weight:600;margin:0 0 16px;border-bottom:1px solid var(--border-color);padding-bottom:10px;">🖼️ Label Preview</h3>

          <div id="bcGenEmpty" class="flex flex-col items-center justify-center py-16 text-center text-slate-400">
            <p class="font-medium text-sm">Select an item or enter a value to view preview.</p>
          </div>

          <div id="bcGenPreviewContainer" class="hidden flex flex-col items-center justify-center bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg p-6">
            <div id="bcLabelPrintArea" class="bg-white text-black p-4 rounded shadow-md flex flex-col items-center justify-center border border-slate-300" style="width:280px; min-height:140px; font-family: sans-serif;">
              <span id="bcPrintLabelName" class="text-xs font-bold text-center mb-1 max-w-[240px] truncate block text-slate-900">Organic Coffee Beans</span>
              <div class="flex justify-center items-center py-2">
                <svg id="bcGenSvg"></svg>
                <div id="bcGenQr"></div>
              </div>
              <span id="bcPrintLabelPrice" class="text-xs font-black mt-1 text-slate-900">$18.99</span>
            </div>
          </div>
        </div>

        <div id="bcGenActions" class="hidden flex gap-2 pt-4 border-t border-slate-100 dark:border-slate-800 mt-6">
          <button type="button" class="nu-btn nu-btn-ghost flex-1 py-2 text-xs" onclick="bcDownloadLabelSvg()">💾 Save as SVG</button>
          <button type="button" class="nu-btn nu-btn-primary flex-1 py-2 text-xs" onclick="bcPrintLabel()">🖨️ Print Label</button>
        </div>
      </div>

    </div>
  </div>

  <!-- ── ITEM CREATE / EDIT MODAL ───────────────────────────────────── -->
  <div id="bcItemModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[10000] hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl max-w-md w-full shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">

      <!-- Modal Header -->
      <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
        <h3 id="bcModalTitle" class="font-bold text-slate-800 dark:text-slate-100 text-base">Register New Item</h3>
        <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 font-bold text-xl" onclick="bcCloseItemModal()">&times;</button>
      </div>

      <!-- Modal Body -->
      <div class="p-5 flex flex-col gap-4">
        <input type="hidden" id="bcItemId">

        <div class="nu-field">
          <label class="font-semibold text-slate-700 dark:text-slate-300">Item Name</label>
          <input type="text" class="nu-input" id="bcItemName" placeholder="e.g. Ergonomic Wireless Keyboard">
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="nu-field">
            <label class="font-semibold text-slate-700 dark:text-slate-300">Type</label>
            <select id="bcItemType" class="nu-input" onchange="bcOnModalTypeChange()">
              <option value="product">Product</option>
              <option value="good">Good</option>
              <option value="service">Service</option>
            </select>
          </div>
          <div class="nu-field">
            <label class="font-semibold text-slate-700 dark:text-slate-300">Price ($)</label>
            <input type="number" step="0.01" class="nu-input" id="bcItemPrice" placeholder="0.00" min="0">
          </div>
        </div>

        <div class="nu-field">
          <label class="font-semibold text-slate-700 dark:text-slate-300">Barcode Code / Number</label>
          <div class="flex gap-2">
            <input type="text" class="nu-input flex-1" id="bcItemBarcode" placeholder="Leave blank to auto-generate">
            <button type="button" class="nu-btn nu-btn-ghost text-xs" onclick="bcGenerateModalBarcode()">⚡ Auto-Gen</button>
          </div>
          <span class="text-[10px] text-slate-400 mt-1 block">Specify custom barcode or leave empty to auto-assign standard EAN-13 code.</span>
        </div>

        <div class="nu-field">
          <label class="font-semibold text-slate-700 dark:text-slate-300">Description</label>
          <textarea class="nu-input" id="bcItemDesc" rows="3" placeholder="Brief details about the item..."></textarea>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
        <button type="button" class="nu-btn nu-btn-ghost text-xs py-2 px-4" onclick="bcCloseItemModal()">Cancel</button>
        <button type="button" class="nu-btn nu-btn-primary text-xs py-2 px-4" onclick="bcSaveItem()">Save Item</button>
      </div>

    </div>
  </div>

</div>

<!-- ── CUSTOM STYLE OVERRIDES ─────────────────────────────────────── -->
<style>
.bc-tab-btn {
  color: var(--text-secondary);
}
.bc-tab-btn:hover {
  color: var(--text-primary);
}
.bc-tab-btn.active {
  background: var(--bg-elevated);
  color: var(--color-primary);
  box-shadow: var(--shadow-sm);
}
.bc-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.bc-table th {
  background: var(--bg-secondary);
  padding: 10px 12px;
  text-align: left;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  font-size: 11px;
  letter-spacing: 0.04em;
  border-bottom: 1px solid var(--border-color);
}
.bc-table td {
  padding: 12px;
  border-bottom: 1px solid var(--border-color);
  vertical-align: middle;
  color: var(--text-primary);
}
.bc-table tr:hover td {
  background: var(--bg-secondary);
}
.bc-badge-product { background: color-mix(in oklch, var(--color-primary) 12%, transparent); color: var(--color-primary); }
.bc-badge-good { background: color-mix(in oklch, #10b981 12%, transparent); color: #047857; }
.bc-badge-service { background: color-mix(in oklch, #8b5cf6 12%, transparent); color: #6d28d9; }
</style>

<!-- ── BARCODE INTEGRATION JAVASCRIPT ────────────────────────────── -->
<script>
(function() {

  // Guard to prevent multiple initialization issues
  if (window.BC_MODULE_INITIATED) return;
  window.BC_MODULE_INITIATED = true;

  const API = 'api/barcode.php';
  let _items = [];
  let _html5Qrcode = null;

  // Load external helper libraries dynamically from CDN
  const loadScript = (url) => {
    return new Promise((resolve, reject) => {
      const existing = document.querySelector(`script[src="${url}"]`);
      if (existing) return resolve();
      const s = document.createElement('script');
      s.src = url;
      s.onload = resolve;
      s.onerror = reject;
      document.head.appendChild(s);
    });
  };

  // We load JsBarcode and Html5Qrcode for real-time canvas building and webcam feed decoding!
  Promise.all([
    loadScript('https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js'),
    loadScript('https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js'),
    loadScript('https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js')
  ]).then(() => {
    console.log('[Barcode] CDNs loaded successfully.');
    bcLoadItems();
  }).catch((err) => {
    console.error('[Barcode] CDN loader error:', err);
  });

  // Switch tabs smoothly
  window.bcSwitchTab = function(tabId, btn) {
    document.querySelectorAll('.bc-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('.bc-tab-panel').forEach(panel => panel.style.display = 'none');
    document.getElementById('bc-tab-' + tabId).style.display = 'block';

    if (tabId === 'scanner') {
      bcRenderSimulationList();
    }
    // Stop camera if navigating away from Scanner
    if (tabId !== 'scanner') {
      bcStopWebcam();
    }
  };

  // Toast warning/success alerts
  function alertMessage(msg, type = 'success') {
    if (window.NuApp && typeof NuApp.toast === 'function') {
      NuApp.toast(msg, type);
    } else {
      const alertArea = document.getElementById('bcAlertArea');
      if (alertArea) {
        alertArea.innerHTML = `<div class="p-3 mb-4 rounded text-sm ${type === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'}">${msg}</div>`;
        setTimeout(() => alertArea.innerHTML = '', 3500);
      }
    }
  }

  // Load registered products
  window.bcLoadItems = async function() {
    try {
      const res = await fetch(API + '?action=list', { credentials: 'same-origin' });
      const data = await res.json();
      if (!data.success) return;
      _items = data.data || [];
      bcRenderInventoryTable();
      bcPopulateGenSelect();
    } catch(err) {
      console.error(err);
      document.getElementById('bcProductsBody').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-red-500">Failed to load items.</td></tr>';
    }
  };

  // Render product table
  function bcRenderInventoryTable() {
    const tbody = document.getElementById('bcProductsBody');
    if (!_items.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-slate-400">No items registered yet. Click <strong>+ Add New Item</strong> to add products, goods, or services.</td></tr>';
      return;
    }

    tbody.innerHTML = _items.map(t => {
      const typeBadge = t.type === 'product' ? 'bc-badge-product' : (t.type === 'good' ? 'bc-badge-good' : 'bc-badge-service');
      const itemJson = JSON.stringify(t).replace(/'/g, "&apos;").replace(/"/g, "&quot;");
      return `<tr>
        <td class="font-semibold text-slate-500">${t.id}</td>
        <td class="font-bold text-slate-800">${esc(t.name)}</td>
        <td><span class="px-2.5 py-0.5 text-[10px] font-extrabold uppercase rounded-full ${typeBadge}">${t.type}</span></td>
        <td><code class="bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400">${esc(t.barcode)}</code></td>
        <td class="font-bold text-slate-700">$${parseFloat(t.price).toFixed(2)}</td>
        <td class="text-slate-500 max-w-xs truncate" title="${esc(t.description || '')}">${esc(t.description || 'No description')}</td>
        <td style="text-align:right;">
          <div style="display:inline-flex;gap:4px;">
            <button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" onclick='bcEditItem(${itemJson})'>Edit</button>
            <button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" style="color:var(--color-error);" onclick="bcDeleteItem(${t.id})">Delete</button>
            <button type="button" class="nu-btn nu-btn-primary nu-btn-sm text-[10px]" onclick='bcQuickViewBarcode(${itemJson})'>🏷️ Label</button>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  // Populate drop-down in generator tab
  function bcPopulateGenSelect() {
    const sel = document.getElementById('bcGenItemSelect');
    if (!sel) return;
    sel.innerHTML = '<option value="">-- Choose registered item --</option>' +
      _items.map(t => `<option value="${t.id}">${esc(t.name)} (${esc(t.barcode)})</option>`).join('');
  }

  // Handle dropdown change in Generator
  window.bcOnGenItemChange = function() {
    const id = document.getElementById('bcGenItemSelect').value;
    const item = _items.find(t => t.id == id);
    if (!item) {
      document.getElementById('bcGenCode').value = '';
      bcRenderGeneratedBarcode();
      return;
    }

    // Autofill values matching selected product details
    document.getElementById('bcGenCode').value = item.barcode;

    // Guess EAN-13 if code is purely 13 digits, otherwise CODE128
    const isPureNumericEan = /^\d{13}$/.test(item.barcode);
    document.getElementById('bcGenFormat').value = isPureNumericEan ? 'EAN13' : 'CODE128';

    bcRenderGeneratedBarcode();
  };

  // Generate unique barcode inside Modal
  window.bcGenerateModalBarcode = async function() {
    const type = document.getElementById('bcItemType').value;
    try {
      const res = await fetch(API + `?action=generate_code&type=${type}`, { credentials: 'same-origin' });
      const data = await res.json();
      if (data.success) {
        document.getElementById('bcItemBarcode').value = data.barcode;
      }
    } catch(e) { console.error(e); }
  };

  // Generate unique barcode inside Generator designer
  window.bcGenerateNewCode = async function() {
    const id = document.getElementById('bcGenItemSelect').value;
    const item = _items.find(t => t.id == id);
    const type = item ? item.type : 'product';
    try {
      const res = await fetch(API + `?action=generate_code&type=${type}`, { credentials: 'same-origin' });
      const data = await res.json();
      if (data.success) {
        document.getElementById('bcGenCode').value = data.barcode;
        bcRenderGeneratedBarcode();
      }
    } catch(e) { console.error(e); }
  };

  // Change type inside modal
  window.bcOnModalTypeChange = function() {
    const codeVal = document.getElementById('bcItemBarcode').value;
    // Auto re-generate code matching new prefix only if currently empty
    if (!codeVal) {
      bcGenerateModalBarcode();
    }
  };

  // Open item modal for add/edit
  window.bcOpenItemModal = function(t) {
    const modal = document.getElementById('bcItemModal');
    if (t) {
      document.getElementById('bcModalTitle').textContent = 'Edit Registered Item';
      document.getElementById('bcItemId').value = t.id;
      document.getElementById('bcItemName').value = t.name;
      document.getElementById('bcItemType').value = t.type;
      document.getElementById('bcItemPrice').value = t.price;
      document.getElementById('bcItemBarcode').value = t.barcode;
      document.getElementById('bcItemDesc').value = t.description || '';
    } else {
      document.getElementById('bcModalTitle').textContent = 'Register New Item';
      document.getElementById('bcItemId').value = '';
      document.getElementById('bcItemName').value = '';
      document.getElementById('bcItemType').value = 'product';
      document.getElementById('bcItemPrice').value = '';
      document.getElementById('bcItemBarcode').value = '';
      document.getElementById('bcItemDesc').value = '';
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    if (!t) {
      bcGenerateModalBarcode();
    }
  };

  window.bcEditItem = function(t) {
    bcOpenItemModal(t);
  };

  window.bcCloseItemModal = function() {
    const modal = document.getElementById('bcItemModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  };

  // Save item (Insert/Update)
  window.bcSaveItem = async function() {
    const id = document.getElementById('bcItemId').value || 0;
    const name = document.getElementById('bcItemName').value.trim();
    const type = document.getElementById('bcItemType').value;
    const price = document.getElementById('bcItemPrice').value.trim();
    const barcode = document.getElementById('bcItemBarcode').value.trim();
    const description = document.getElementById('bcItemDesc').value.trim();

    if (!name) { alertMessage('Item name is required', 'warning'); return; }

    const payload = {
      action: 'save',
      id: id,
      name: name,
      type: type,
      price: price || 0,
      barcode: barcode,
      description: description
    };

    try {
      const res = await fetch(API, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        alertMessage(data.message, 'success');
        bcCloseItemModal();
        bcLoadItems();
      } else {
        alertMessage('Save failed: ' + data.message, 'warning');
      }
    } catch(err) {
      alertMessage('Network error: ' + err.message, 'warning');
    }
  };

  // Delete product
  window.bcDeleteItem = async function(id) {
    if (!confirm('Are you sure you want to remove this item? This deletes its barcode schema permanently.')) return;
    try {
      const res = await fetch(API, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id: id })
      });
      const data = await res.json();
      if (data.success) {
        alertMessage('Item deleted', 'success');
        bcLoadItems();
      } else {
        alertMessage('Delete failed: ' + data.message, 'warning');
      }
    } catch(err) {
      alertMessage('Network error', 'warning');
    }
  };

  // Live lookup from barcode
  window.bcLookupManual = async function() {
    const val = document.getElementById('bcManualInputCode').value.trim();
    if (!val) return;
    bcPerformLookup(val);
  };

  // Actual search lookup execution
  async function bcPerformLookup(barcode) {
    try {
      const res = await fetch(API + `?action=lookup&barcode=${barcode}`, { credentials: 'same-origin' });
      const data = await res.json();
      if (data.success) {
        bcRenderResultProfile(data.data);
        alertMessage('Item found: ' + data.data.name, 'success');
      } else {
        alertMessage(data.message || 'Barcode lookup failed', 'warning');
        bcClearResultProfile();
      }
    } catch(err) {
      console.error(err);
      alertMessage('Network lookup error', 'warning');
    }
  }

  // Clear looked up result
  function bcClearResultProfile() {
    document.getElementById('bcScanResultEmpty').classList.remove('hidden');
    document.getElementById('bcScanResultContent').classList.add('hidden');
    document.getElementById('bcScanResultActions').classList.add('hidden');
  }

  // Render profile
  function bcRenderResultProfile(item) {
    document.getElementById('bcScanResultEmpty').classList.add('hidden');
    document.getElementById('bcScanResultContent').classList.remove('hidden');
    document.getElementById('bcScanResultActions').classList.remove('hidden');

    const typeBadge = document.getElementById('bcResultType');
    typeBadge.className = 'px-2.5 py-0.5 text-[10px] font-bold tracking-wide uppercase rounded-full ';
    if (item.type === 'product') typeBadge.className += 'bc-badge-product';
    else if (item.type === 'good') typeBadge.className += 'bc-badge-good';
    else typeBadge.className += 'bc-badge-service';

    typeBadge.textContent = item.type;
    document.getElementById('bcResultName').textContent = item.name;
    document.getElementById('bcResultPrice').textContent = '$' + parseFloat(item.price).toFixed(2);
    document.getElementById('bcResultCode').textContent = item.barcode;
    document.getElementById('bcResultDesc').textContent = item.description || 'No description provided for this item.';

    // Generate barcode render in the result card
    const isPureNumericEan = /^\d{13}$/.test(item.barcode);
    const format = isPureNumericEan ? 'EAN13' : 'CODE128';

    if (format === 'EAN13' || format === 'CODE128' || format === 'CODE39') {
      document.getElementById('bcResultSvg').style.display = 'block';
      document.getElementById('bcResultQr').style.display = 'none';
      try {
        JsBarcode('#bcResultSvg', item.barcode, {
          format: format,
          width: 2,
          height: 60,
          displayValue: true,
          fontOptions: 'bold',
          fontSize: 12
        });
      } catch(e) {
        // Fallback standard CODE128 if EAN13 format check fails
        JsBarcode('#bcResultSvg', item.barcode, { format: 'CODE128', width: 2, height: 60 });
      }
    }

    // Set buttons actions dynamically
    const itemJson = JSON.stringify(item).replace(/'/g, "&apos;").replace(/"/g, "&quot;");
    document.getElementById('bcEditBtnResult').onclick = () => bcEditItem(item);
    document.getElementById('bcPrintBtnResult').onclick = () => bcQuickViewBarcode(item);
  }

  // Render Simulation List
  function bcRenderSimulationList() {
    const container = document.getElementById('bcSimulationList');
    if (!container) return;
    if (!_items.length) {
      container.innerHTML = '<span class="text-xs text-slate-500">No items available.</span>';
      return;
    }

    container.innerHTML = _items.map(t => {
      return `<button type="button" class="w-full text-left text-xs bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded p-2 transition-all flex justify-between items-center" onclick="bcTriggerSimulatedScan('${t.barcode}')">
        <div>
          <span class="font-bold text-slate-800 dark:text-slate-100 block">${esc(t.name)}</span>
          <span class="text-[10px] text-slate-400 font-mono">${esc(t.barcode)}</span>
        </div>
        <span class="font-black text-slate-600 dark:text-slate-300">$${parseFloat(t.price).toFixed(2)}</span>
      </button>`;
    }).join('');
  }

  // Trigger simulated barcode click scan
  window.bcTriggerSimulatedScan = function(barcode) {
    bcPerformLookup(barcode);
  };

  // Real-time camera scanner initialization
  window.bcStartWebcam = function() {
    document.getElementById('bcNoCamera').style.display = 'none';

    _html5Qrcode = new Html5Qrcode("bcInteractiveReader");
    _html5Qrcode.start(
      { facingMode: "environment" },
      {
        fps: 15,
        qrbox: { width: 250, height: 180 }
      },
      (decodedText, decodedResult) => {
        // Successfully scanned code!
        bcPerformLookup(decodedText);
        bcStopWebcam();
        alertMessage('Barcode scanned successfully: ' + decodedText, 'success');
      },
      (errorMessage) => {
        // Silent query errors to avoid noise
      }
    ).catch(err => {
      console.warn('Webcam start failed:', err);
      alertMessage('Camera failed to start. Permissions might be denied.', 'warning');
      document.getElementById('bcNoCamera').style.display = 'flex';
    });
  };

  // Stop Webcam
  window.bcStopWebcam = function() {
    if (_html5Qrcode) {
      _html5Qrcode.stop().then(() => {
        _html5Qrcode = null;
        document.getElementById('bcNoCamera').style.display = 'flex';
      }).catch(err => {
        console.warn('Webcam stop failed:', err);
        _html5Qrcode = null;
        document.getElementById('bcNoCamera').style.display = 'flex';
      });
    } else {
      const nc = document.getElementById('bcNoCamera');
      if (nc) nc.style.display = 'flex';
    }
  };

  // Render barcode in generator designer preview
  window.bcRenderGeneratedBarcode = function() {
    const rawVal = document.getElementById('bcGenCode').value.trim();
    const prefix = document.getElementById('bcGenPrefix').value.trim();
    const format = document.getElementById('bcGenFormat').value;
    const width = parseInt(document.getElementById('bcGenWidth').value, 10) || 2;
    const height = parseInt(document.getElementById('bcGenHeight').value, 10) || 60;
    const includeText = document.getElementById('bcGenIncludeText').checked;

    const code = prefix + rawVal;

    const previewContainer = document.getElementById('bcGenPreviewContainer');
    const previewEmpty = document.getElementById('bcGenEmpty');
    const genActions = document.getElementById('bcGenActions');

    if (!code) {
      previewContainer.classList.add('hidden');
      previewEmpty.classList.remove('hidden');
      genActions.classList.add('hidden');
      return;
    }

    previewContainer.classList.remove('hidden');
    previewEmpty.classList.add('hidden');
    genActions.classList.remove('hidden');

    // Fill printer label data
    const itemSelect = document.getElementById('bcGenItemSelect');
    const selectedItem = _items.find(t => t.id == itemSelect.value);
    document.getElementById('bcPrintLabelName').textContent = selectedItem ? selectedItem.name : 'Custom Code';
    document.getElementById('bcPrintLabelPrice').textContent = selectedItem ? '$' + parseFloat(selectedItem.price).toFixed(2) : '';

    const svgEl = document.getElementById('bcGenSvg');
    const qrEl = document.getElementById('bcGenQr');

    svgEl.innerHTML = '';
    qrEl.innerHTML = '';

    if (format === 'QR') {
      svgEl.style.display = 'none';
      qrEl.style.display = 'block';
      new QRCode(qrEl, {
        text: code,
        width: height + 20,
        height: height + 20,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
      });
    } else {
      svgEl.style.display = 'block';
      qrEl.style.display = 'none';
      try {
        JsBarcode('#bcGenSvg', code, {
          format: format,
          width: width,
          height: height,
          displayValue: includeText,
          fontOptions: 'bold',
          fontSize: 11
        });
      } catch(e) {
        // Fallback CODE128
        JsBarcode('#bcGenSvg', code, {
          format: 'CODE128',
          width: width,
          height: height,
          displayValue: includeText,
          fontSize: 11
        });
      }
    }
  };

  // Trigger quick popup or redirect to generator
  window.bcQuickViewBarcode = function(item) {
    bcSwitchTab('generator', document.querySelectorAll('.bc-tab-btn')[2]);
    document.getElementById('bcGenItemSelect').value = item.id;
    bcOnGenItemChange();
  };

  // Download SVG
  window.bcDownloadLabelSvg = function() {
    const svg = document.getElementById('bcGenSvg');
    if (svg.style.display === 'none') {
      alertMessage('Download as SVG is supported only for linear 1D barcodes. For QR Codes, please print.', 'warning');
      return;
    }
    const svgData = new XMLSerializer().serializeToString(svg);
    const blob = new Blob([svgData], { type: "image/svg+xml;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `barcode_${document.getElementById('bcGenCode').value}.svg`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    alertMessage('Barcode SVG downloaded successfully', 'success');
  };

  // Print Label Sheet using hidden iframe to bypass popup blockers and avoid reloads
  window.bcPrintLabel = function() {
    const size = document.getElementById('bcLabelSize').value;
    const printContents = document.getElementById('bcLabelPrintArea').innerHTML;

    // Remove existing iframe if it exists
    let frame = document.getElementById('bcPrintFrame');
    if (frame) {
      frame.remove();
    }

    // Create a new hidden iframe
    frame = document.createElement('iframe');
    frame.id = 'bcPrintFrame';
    frame.style.position = 'fixed';
    frame.style.right = '0';
    frame.style.bottom = '0';
    frame.style.width = '0';
    frame.style.height = '0';
    frame.style.border = '0';
    document.body.appendChild(frame);

    const doc = frame.contentWindow.document || frame.contentDocument;
    doc.open();
    doc.write(`
      <html>
        <head>
          <title>Print Barcode Label</title>
          <style>
            body {
              margin: 0;
              padding: 20px;
              display: flex;
              align-items: center;
              justify-content: center;
              font-family: sans-serif;
            }
            .label-box {
              border: 1px solid #000;
              padding: 15px;
              border-radius: 4px;
              text-align: center;
              display: flex;
              flex-direction: column;
              align-items: center;
              justify-content: center;
              box-sizing: border-box;
              background: #fff;
              color: #000;
              ${size === 'small' ? 'width: 2.2in; height: 1.0in; padding: 5px;' : ''}
              ${size === 'large' ? 'width: 4.0in; height: 3.0in; padding: 25px;' : ''}
              ${size === 'standard' ? 'width: 3.2in; height: 2.0in;' : ''}
            }
            @media print {
              body { padding: 0; }
              .no-print { display: none; }
            }
          </style>
        </head>
        <body>
          <div class="label-box">
            ${printContents}
          </div>
          \x3Cscript>
            window.onload = function() {
              window.focus();
              window.print();
            };
          \x3C/script>
        </body>
      </html>
    `);
    doc.close();
  };

  // utility escaper
  function esc(s) {
    return String(s !== null && s !== undefined ? s : '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

})();
</script>
