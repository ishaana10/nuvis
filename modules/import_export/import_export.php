<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/core/module_bootstrap.php';

$db        = NuDatabase::getInstance();

// Self-healing / Auto-migration for existing databases
try {
    $exists = $db->fetchOne("SELECT menu_id FROM nu_menus WHERE menu_target = 'import_export'");
    if (!$exists) {
        $adminGroup = $db->fetchOne("SELECT menu_id FROM nu_menus WHERE menu_label = 'Admin Tools' AND menu_type = 'group'");
        $parentId = $adminGroup ? (int)$adminGroup['menu_id'] : 0;
        $db->insert('nu_menus', [
            'menu_label'       => 'Import / Export',
            'menu_type'        => 'form',
            'menu_target'      => 'import_export',
            'menu_parent_id'   => $parentId,
            'menu_order'       => 85,
            'menu_roles'       => 'globeadmin,admin',
            'menu_role_access' => '["globeadmin","admin"]',
            'menu_active'      => 1,
            'menu_icon'        => 'clipboard'
        ]);
    }
} catch (Exception $e) {
    // Fail silently in case nu_menus table doesn't exist yet during initial setup phases
}

// Fetch all tables
$tables    = $db->fetchAll("SHOW TABLES");
$tableList = [];
foreach ($tables as $t) {
    $vals        = array_values($t);
    $tableList[] = $vals[0];
}
?>

<div class="nu-import-export">
    <div class="nu-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <!-- Export Card -->
        <div class="nu-card">
            <div class="nu-card-header">
                <h3 class="nu-card-title">Export Data</h3>
            </div>
            <div class="nu-modal-body">
                <div class="nu-field" style="margin-bottom: 12px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 6px;">Select Table</label>
                    <select class="nu-input" id="exportTable" style="width: 100%;">
                        <option value="">-- Choose table to export --</option>
                        <?php foreach ($tableList as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="nu-field" style="margin-bottom: 12px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 6px;">Format</label>
                    <select class="nu-input" id="exportFormat" style="width: 100%;">
                        <option value="csv">CSV (Comma Separated Values)</option>
                        <option value="json">JSON (Structured JavaScript Object Notation)</option>
                    </select>
                </div>
                <button class="nu-btn nu-btn-primary" onclick="exportData()" style="margin-top: 12px; width: 100%;">
                    Download Export File
                </button>
            </div>
        </div>

        <!-- Import Card -->
        <div class="nu-card" id="importMainCard">
            <div class="nu-card-header">
                <h3 class="nu-card-title">Import Data</h3>
            </div>
            <div class="nu-modal-body">
                <div class="nu-field" style="margin-bottom: 12px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 6px;">Select Target Table</label>
                    <select class="nu-input" id="importTable" style="width: 100%;" onchange="resetImportMapping()">
                        <option value="">-- Choose target table --</option>
                        <?php foreach ($tableList as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="nu-field" style="margin-bottom: 12px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 6px;">CSV File</label>
                    <input type="file" class="nu-input" id="importFile" accept=".csv" style="width: 100%;" onchange="resetImportMapping()">
                </div>
                <button class="nu-btn nu-btn-primary" onclick="analyzeCSV()" style="margin-top: 12px; width: 100%;">
                    Analyze CSV & Configure Mapping
                </button>
            </div>
        </div>
    </div>

    <!-- Mapping and Preview Panel (Dynamic) -->
    <div class="nu-card" id="mappingPanel" style="display: none; margin-top: 20px; animation: fadeIn 0.3s ease;">
        <div class="nu-card-header" style="background: var(--bg-elevated, #fcfcfc); border-bottom: 1px solid var(--border-color, #eee); display: flex; justify-content: space-between; align-items: center; padding: 16px 24px;">
            <h3 class="nu-card-title" id="mappingPanelTitle" style="margin: 0;">Configure Column Mapping</h3>
            <span class="nu-badge" id="targetTableBadge" style="background: var(--primary, #4f6bed); color: #fff; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;"></span>
        </div>
        <div class="nu-modal-body" style="padding: 24px;">
            <div style="margin-bottom: 20px; font-size: 14px; color: var(--text-muted, #666);">
                We have analyzed your CSV headers and automatically matched them with the database columns of the target table. Please verify or update the mapping options below.
            </div>

            <!-- Mapping Table -->
            <div style="overflow-x: auto; margin-bottom: 30px; border: 1px solid var(--border-color, #ddd); border-radius: 8px;">
                <table class="nu-table" style="width: 100%; border-collapse: collapse; font-size: 14px; text-align: left;">
                    <thead>
                        <tr style="background: var(--table-head-bg, #f8f9fa); border-bottom: 2px solid var(--border-color, #ddd);">
                            <th style="padding: 12px 16px; font-weight: 600;">CSV Header</th>
                            <th style="padding: 12px 16px; font-weight: 600;">Sample Value (Row 1)</th>
                            <th style="padding: 12px 16px; font-weight: 600;">Maps to Database Column</th>
                            <th style="padding: 12px 16px; font-weight: 600;">Type & Constraints</th>
                        </tr>
                    </thead>
                    <tbody id="mappingTableBody">
                        <!-- Filled by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Preview Section -->
            <h4 style="font-size: 15px; font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                <span>📊 Data Import Preview (First 3 Rows)</span>
            </h4>
            <div style="overflow-x: auto; margin-bottom: 24px; border: 1px solid var(--border-color, #ddd); border-radius: 8px;">
                <table class="nu-table" style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                    <thead id="previewTableHead" style="background: var(--table-head-bg, #f8f9fa); border-bottom: 2px solid var(--border-color, #ddd);">
                        <!-- Filled by JavaScript -->
                    </thead>
                    <tbody id="previewTableBody">
                        <!-- Filled by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Import Action -->
            <div style="display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid var(--border-color, #eee); padding-top: 20px;">
                <button class="nu-btn nu-btn-ghost" onclick="resetImportMapping()">Cancel</button>
                <button class="nu-btn nu-btn-primary" id="btnRunImport" onclick="executeImport()">Confirm & Start Import</button>
            </div>
        </div>
    </div>
</div>

<script>
// Local variables to hold analysis state
let csvHeaders = [];
let csvRows = [];
let dbColumns = [];

// Clean names for matching
function cleanName(name) {
    return name.toLowerCase().replace(/[^a-z0-9]/g, '');
}

// Find best matching DB column
function findBestMatch(csvHeader, dbCols) {
    const cleanHeader = cleanName(csvHeader);

    // 1. Direct or cleaned match
    for (let col of dbCols) {
        if (cleanName(col.name) === cleanHeader) {
            return col.name;
        }
    }

    // 2. Substring match
    for (let col of dbCols) {
        const cleanCol = cleanName(col.name);
        if (cleanHeader.includes(cleanCol) || cleanCol.includes(cleanHeader)) {
            return col.name;
        }
    }

    return ''; // default to skip
}

// RFC-4180-compliant CSV line parser
function parseCSVRow(text) {
    let cells = [];
    let cell = '';
    let inQuotes = false;
    for (let i = 0; i < text.length; i++) {
        let char = text[i];
        if (char === '"') {
            if (inQuotes && text[i + 1] === '"') {
                cell += '"';
                i++;
            } else {
                inQuotes = !inQuotes;
            }
        } else if (char === ',' && !inQuotes) {
            cells.push(cell);
            cell = '';
        } else if ((char === '\r' || char === '\n') && !inQuotes) {
            cells.push(cell);
            return cells;
        } else {
            cell += char;
        }
    }
    cells.push(cell);
    return cells;
}

// Parse first few rows of CSV
function parseCSVLines(text, maxLines = 10) {
    let lines = [];
    let currentLine = '';
    let inQuotes = false;

    for (let i = 0; i < text.length; i++) {
        let char = text[i];
        if (char === '"') {
            inQuotes = !inQuotes;
            currentLine += char;
        } else if (char === '\n' && !inQuotes) {
            lines.push(currentLine);
            currentLine = '';
            if (lines.length >= maxLines) break;
        } else {
            currentLine += char;
        }
    }
    if (currentLine && lines.length < maxLines) {
        lines.push(currentLine);
    }

    return lines.map(line => parseCSVRow(line.trim()));
}

// Reset UI state
function resetImportMapping() {
    document.getElementById('mappingPanel').style.display = 'none';
    csvHeaders = [];
    csvRows = [];
    dbColumns = [];
}

// Export function
function exportData() {
    const table = document.getElementById('exportTable').value;
    const format = document.getElementById('exportFormat').value;
    if (!table) {
        NuApp.toast('Please select a table to export.', 'error');
        return;
    }
    window.open(`api/export.php?table=${encodeURIComponent(table)}&format=${format}`, '_blank');
}

// Step 1: Analyze CSV file and load target table schema
async function analyzeCSV() {
    const table = document.getElementById('importTable').value;
    const fileInput = document.getElementById('importFile');
    const file = fileInput.files[0];

    if (!table) {
        NuApp.toast('Please select a target database table first.', 'error');
        return;
    }
    if (!file) {
        NuApp.toast('Please select a CSV file to import.', 'error');
        return;
    }

    try {
        NuApp.toast('Analyzing target schema...', 'info');

        // 1. Fetch DB Columns & schema constraints
        const res = await fetch(`api/import.php?action=get_columns&table=${encodeURIComponent(table)}`, {
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) {
            throw new Error(json.error || 'Failed to load table schema.');
        }
        dbColumns = json.columns || [];

        // 2. Parse CSV client-side
        const reader = new FileReader();
        reader.onload = function(e) {
            const text = e.target.result;
            const parsed = parseCSVLines(text, 5);
            if (parsed.length === 0 || parsed[0].length === 0) {
                NuApp.toast('CSV file appears to be empty or invalid.', 'error');
                return;
            }

            csvHeaders = parsed[0];
            csvRows = parsed.slice(1).filter(r => r.length > 0);

            // Render mapping panel
            renderMappingUI(table);
        };
        reader.readAsText(file);

    } catch (err) {
        console.error(err);
        NuApp.toast(err.message || 'Error occurred during analysis.', 'error');
    }
}

// Render dynamic mapping panel
function renderMappingUI(table) {
    const tableBody = document.getElementById('mappingTableBody');
    tableBody.innerHTML = '';

    document.getElementById('targetTableBadge').textContent = table;

    const sampleRow = csvRows[0] || [];

    csvHeaders.forEach((header, index) => {
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid var(--border-color, #eee)';

        // Column 1: CSV Header Name
        const tdHeader = document.createElement('td');
        tdHeader.style.padding = '12px 16px; font-weight: 500;';
        tdHeader.textContent = header;
        tr.appendChild(tdHeader);

        // Column 2: Sample value
        const tdSample = document.createElement('td');
        tdSample.style.padding = '12px 16px; color: var(--text-muted, #777); font-style: italic;';
        tdSample.textContent = sampleRow[index] !== undefined ? sampleRow[index] : '(blank)';
        tr.appendChild(tdSample);

        // Column 3: Mapped DB Column Select
        const tdSelect = document.createElement('td');
        tdSelect.style.padding = '8px 16px;';

        const select = document.createElement('select');
        select.className = 'nu-input mapping-select';
        select.style.padding = '6px 12px; width: 100%; max-width: 280px;';
        select.dataset.csvHeader = header;
        select.onchange = updatePreviewTable;

        // Skip option
        const optSkip = document.createElement('option');
        optSkip.value = '__skip__';
        optSkip.textContent = '❌ [Skip Column]';
        select.appendChild(optSkip);

        // Populate options with DB columns
        dbColumns.forEach(col => {
            const opt = document.createElement('option');
            opt.value = col.name;
            opt.textContent = col.name;
            select.appendChild(opt);
        });

        // Pre-select best fuzzy match
        const bestMatch = findBestMatch(header, dbColumns);
        if (bestMatch) {
            select.value = bestMatch;
        } else {
            select.value = '__skip__';
        }

        tdSelect.appendChild(select);
        tr.appendChild(tdSelect);

        // Column 4: Constraint badges
        const tdType = document.createElement('td');
        tdType.style.padding = '12px 16px;';
        const typeContainer = document.createElement('div');
        typeContainer.id = `typeBadge_${cleanName(header)}`;
        tdType.appendChild(typeContainer);
        tr.appendChild(tdType);

        tableBody.appendChild(tr);
    });

    document.getElementById('mappingPanel').style.display = 'block';

    // Update types and layout preview
    updatePreviewTable();
}

// Update the type badges and the first-3-rows data preview dynamically
function updatePreviewTable() {
    const selects = document.querySelectorAll('.mapping-select');
    const mapping = {};

    selects.forEach(sel => {
        const csvHeader = sel.dataset.csvHeader;
        const dbCol = sel.value;
        mapping[csvHeader] = dbCol;

        // Update target data type constraint badge
        const badgeDiv = document.getElementById(`typeBadge_${cleanName(csvHeader)}`);
        if (badgeDiv) {
            badgeDiv.innerHTML = '';
            if (dbCol !== '__skip__') {
                const colInfo = dbColumns.find(c => c.name === dbCol);
                if (colInfo) {
                    const badge = document.createElement('span');
                    let badgeBg = '#7f8c8d';
                    if (colInfo.type === 'numeric') badgeBg = '#27ae60';
                    if (colInfo.type === 'boolean') badgeBg = '#2980b9';
                    if (colInfo.type === 'date' || colInfo.type === 'datetime') badgeBg = '#8e44ad';

                    badge.style.cssText = `background: ${badgeBg}; color: #fff; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; text-transform: uppercase; margin-right: 4px;`;
                    badge.textContent = colInfo.type;
                    badgeDiv.appendChild(badge);

                    if (!colInfo.nullable) {
                        const reqBadge = document.createElement('span');
                        reqBadge.style.cssText = 'background: #e74c3c; color: #fff; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; text-transform: uppercase;';
                        reqBadge.textContent = 'Required';
                        badgeDiv.appendChild(reqBadge);
                    } else {
                        const nullBadge = document.createElement('span');
                        nullBadge.style.cssText = 'background: var(--bg-elevated, #f0f0f0); color: var(--text-muted, #777); padding: 3px 8px; border-radius: 10px; font-size: 11px; border: 1px solid var(--border-color, #ddd);';
                        nullBadge.textContent = 'Nullable';
                        badgeDiv.appendChild(nullBadge);
                    }
                }
            } else {
                const badge = document.createElement('span');
                badge.style.cssText = 'background: #95a5a6; color: #fff; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 600;';
                badge.textContent = 'Skipped';
                badgeDiv.appendChild(badge);
            }
        }
    });

    // Generate head of preview table
    const thead = document.getElementById('previewTableHead');
    thead.innerHTML = '';
    const hTr = document.createElement('tr');

    const mappedCols = [];
    selects.forEach(sel => {
        if (sel.value !== '__skip__') {
            mappedCols.push({
                csvHeader: sel.dataset.csvHeader,
                dbCol: sel.value
            });
            const th = document.createElement('th');
            th.style.padding = '10px 14px; font-weight: 600; color: var(--primary, #4f6bed);';
            th.textContent = `${sel.value} (Mapped)`;
            hTr.appendChild(th);
        }
    });

    if (mappedCols.length === 0) {
        const th = document.createElement('th');
        th.style.padding = '10px 14px; text-align: center; color: var(--text-muted, #999); font-style: italic;';
        th.textContent = 'No columns mapped yet. Map at least one column to preview.';
        hTr.appendChild(th);
        thead.appendChild(hTr);

        document.getElementById('previewTableBody').innerHTML = '';
        return;
    }
    thead.appendChild(hTr);

    // Generate body of preview table (up to 3 rows)
    const tbody = document.getElementById('previewTableBody');
    tbody.innerHTML = '';

    const previewRows = csvRows.slice(0, 3);
    if (previewRows.length === 0) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = mappedCols.length;
        td.style.padding = '20px; text-align: center; color: #888;';
        td.textContent = 'No CSV data rows found to preview.';
        tr.appendChild(td);
        tbody.appendChild(tr);
        return;
    }

    previewRows.forEach(row => {
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid var(--border-color, #eee)';

        mappedCols.forEach(col => {
            const indexInCsv = csvHeaders.indexOf(col.csvHeader);
            const val = indexInCsv !== -1 && row[indexInCsv] !== undefined ? row[indexInCsv] : '';
            const td = document.createElement('td');
            td.style.padding = '10px 14px;';
            td.textContent = val;
            tr.appendChild(td);
        });

        tbody.appendChild(tr);
    });
}

// Step 3: Run the import by calling the backend with file + mapping
async function executeImport() {
    const table = document.getElementById('importTable').value;
    const fileInput = document.getElementById('importFile');
    const file = fileInput.files[0];
    const btn = document.getElementById('btnRunImport');

    if (!table || !file) {
        NuApp.toast('Target table and CSV file are required.', 'error');
        return;
    }

    // Build the mapping object
    const selects = document.querySelectorAll('.mapping-select');
    const mapping = {};
    let hasMapping = false;

    selects.forEach(sel => {
        if (sel.value !== '__skip__') {
            mapping[sel.dataset.csvHeader] = sel.value;
            hasMapping = true;
        }
    });

    if (!hasMapping) {
        NuApp.toast('Please map at least one CSV column to a database column.', 'error');
        return;
    }

    try {
        btn.disabled = true;
        btn.textContent = 'Importing Data...';
        NuApp.toast('Import in progress...', 'info');

        const formData = new FormData();
        formData.append('table', table);
        formData.append('file', file);
        formData.append('mapping', JSON.stringify(mapping));

        const res = await fetch('api/import.php?action=import', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });

        const json = await res.json();
        btn.disabled = false;
        btn.textContent = 'Confirm & Start Import';

        if (json.success) {
            NuApp.toast(`Import Completed! Successfully loaded ${json.imported} records.`, 'success');

            let message = `Successfully imported ${json.imported} rows.`;
            if (json.failed > 0) {
                message += ` Failed ${json.failed} rows.`;
                if (json.errors && json.errors.length > 0) {
                    message += `\nErrors:\n- ${json.errors.slice(0, 5).join('\n- ')}`;
                }
                alert(message);
            } else {
                alert(message);
            }

            resetImportMapping();
            fileInput.value = '';
        } else {
            throw new Error(json.error || 'Unknown import error occurred.');
        }

    } catch (err) {
        console.error(err);
        btn.disabled = false;
        btn.textContent = 'Confirm & Start Import';
        NuApp.toast(err.message || 'Import failed.', 'error');
        alert('Import Error: ' + (err.message || 'Import failed.'));
    }
}
</script>
