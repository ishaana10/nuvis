<?php
require_once '../../config.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';

$auth = new NuAuth();
if (!$auth->checkAuth()) exit('Unauthorized');

$db = NuDatabase::getInstance();
$widgets = $db->fetchAll("SELECT * FROM nu_dashboard_widgets WHERE (widget_user_id = :user OR widget_user_id IS NULL) AND widget_active = 1 ORDER BY widget_position_y, widget_position_x", 
    [':user' => $_SESSION['nu_user_id']]);
?>

<div class="nu-widgets">
    <div class="nu-card" style="margin-bottom: 24px;">
        <div class="nu-card-header">
            <h3 class="nu-card-title">Dashboard Widgets</h3>
            <button class="nu-btn nu-btn-primary" onclick="openWidgetModal()">+ Add Widget</button>
        </div>
    </div>

    <div class="nu-widget-grid" id="widgetGrid" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:20px;">
        <?php foreach ($widgets as $w): 
            $config = json_decode($w['widget_config'], true);
        ?>
        <div class="nu-card" style="grid-column:span <?php echo $w['widget_width']; ?>;grid-row:span <?php echo $w['widget_height']; ?>;">
            <div class="nu-card-header">
                <h3 class="nu-card-title"><?php echo htmlspecialchars($w['widget_title']); ?></h3>
                <button class="nu-btn nu-btn-ghost nu-btn-sm" onclick="deleteWidget(<?php echo $w['widget_id']; ?>)">Remove</button>
            </div>
            <div class="nu-widget-content" id="widget_<?php echo $w['widget_id']; ?>">
                <?php echo renderWidget($w['widget_type'], $config, $db); ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($widgets)): ?>
        <div class="nu-card" style="grid-column:1/-1;text-align:center;padding:40px;">
            <p style="color:var(--text-tertiary);">No widgets yet. Add your first dashboard widget.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="nu-modal-overlay" id="widgetModal">
    <div class="nu-modal">
        <div class="nu-modal-header">
            <h3 class="nu-modal-title">Add Widget</h3>
            <button class="nu-modal-close" onclick="closeWidgetModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="nu-modal-body">
            <div class="nu-field">
                <label>Widget Type</label>
                <select class="nu-input" id="widgetType" onchange="updateWidgetConfig()">
                    <option value="stat">Statistic (Count)</option>
                    <option value="chart">Chart</option>
                    <option value="table">Data Table</option>
                    <option value="recent">Recent Activity</option>
                    <option value="custom">Custom HTML</option>
                </select>
            </div>
            <div class="nu-field">
                <label>Title</label>
                <input type="text" class="nu-input" id="widgetTitle" placeholder="Total Orders">
            </div>
            <div class="nu-field">
                <label>Width (columns)</label>
                <select class="nu-input" id="widgetWidth">
                    <option value="1">1</option>
                    <option value="2" selected>2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                </select>
            </div>
            <div class="nu-field" id="widgetConfigArea">
                <label>Configuration (JSON)</label>
                <textarea class="nu-input" id="widgetConfig" rows="4" placeholder='{"table": "orders", "column": "id"}'></textarea>
            </div>
        </div>
        <div class="nu-modal-footer">
            <button class="nu-btn nu-btn-ghost" onclick="closeWidgetModal()">Cancel</button>
            <button class="nu-btn nu-btn-primary" onclick="saveWidget()">Add Widget</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<?php
function renderWidget($type, $config, $db) {
    switch ($type) {
        case 'stat':
            $table = preg_replace('/[^a-z0-9_]/', '', $config['table'] ?? '');
            $column = preg_replace('/[^a-z0-9_]/', '', $config['column'] ?? 'id');
            if (!$table) return '<p style="color:var(--text-tertiary);">Invalid config</p>';
            $count = $db->fetchOne("SELECT COUNT({$column}) as total FROM {$table}")['total'] ?? 0;
            return '<div style="font-size:36px;font-weight:700;color:var(--accent);">' . number_format($count) . '</div>';

        case 'chart':
            $table = preg_replace('/[^a-z0-9_]/', '', $config['table'] ?? '');
            $groupBy = preg_replace('/[^a-z0-9_]/', '', $config['group_by'] ?? '');
            $countCol = preg_replace('/[^a-z0-9_]/', '', $config['count'] ?? 'id');
            if (!$table || !$groupBy) return '<p style="color:var(--text-tertiary);">Invalid chart config</p>';
            $data = $db->fetchAll("SELECT {$groupBy} as label, COUNT({$countCol}) as value FROM {$table} GROUP BY {$groupBy} LIMIT 10");
            $labels = array_column($data, 'label');
            $values = array_column($data, 'value');
            $chartData = json_encode(['type' => 'bar', 'data' => ['labels' => $labels, 'datasets' => [['label' => 'Count', 'data' => $values]]]]);
            return '<div style="height:200px;"><canvas class="widget-chart" data-chart='' . $chartData . ''></canvas></div>';

        case 'table':
            $table = preg_replace('/[^a-z0-9_]/', '', $config['table'] ?? '');
            $columns = $config['columns'] ?? ['*'];
            $limit = (int)($config['limit'] ?? 5);
            if (!$table) return '<p style="color:var(--text-tertiary);">Invalid config</p>';
            $cols = implode(',', array_map(function($c) { return preg_replace('/[^a-z0-9_]/', '', $c); }, $columns));
            $rows = $db->fetchAll("SELECT {$cols} FROM {$table} LIMIT {$limit}");
            $html = '<div class="nu-table-wrap"><table class="nu-table"><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $val) $html .= '<td>' . htmlspecialchars($val) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';
            return $html;

        case 'recent':
            $table = preg_replace('/[^a-z0-9_]/', '', $config['table'] ?? 'nu_audit_log');
            $limit = (int)($config['limit'] ?? 10);
            $rows = $db->fetchAll("SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit}");
            $html = '<div style="display:flex;flex-direction:column;gap:8px;">';
            foreach ($rows as $row) {
                $html .= '<div style="padding:8px 12px;background:var(--bg-secondary);border-radius:var(--radius-sm);font-size:13px;">';
                $html .= htmlspecialchars(json_encode($row, JSON_PRETTY_PRINT));
                $html .= '</div>';
            }
            $html .= '</div>';
            return $html;

        case 'custom':
            return $config['html'] ?? '<p>No content</p>';

        default:
            return '<p style="color:var(--text-tertiary);">Unknown widget type</p>';
    }
}
?>
