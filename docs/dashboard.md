# Dashboard Module

## Overview
The Dashboard module acts as the default landing space upon successful user login. It serves two distinct visual designs depending on user privileges:
1. **Administrative Dashboard**: Restructured exclusively for `globeadmin` and `admin` roles, offering real-time KPI metrics, global activity feeds (sourced from the audit logger), customizable KPI cards/widgets, and access to builder shortcut actions.
2. **User Dashboard**: Sourced from `dashboard_user.php` for normal operational roles to interact with assigned workflow widgets, personal calendar summaries, and role-authorized workspace links.

---

## Architecture & Key Files
- **`modules/dashboard/dashboard.php`**: Primary wrapper handling role verification, loading global KPI metrics, and rendering the core executive viewport.
- **`modules/dashboard/dashboard_user.php`**: Simplified workspace display loaded automatically for non-admin accounts.
- **`modules/dashboard/widget_api.php`**: Background API endpoint that processes dynamic, interactive widgets securely.

---

## Technical Details

### Real-time KPI Card Statistics
Admin KPIs are computed on runtime loading with a fail-safe exception handler preventing any physical schema failure in peripheral logs from blocking dashboard load:

```php
function nu_safe_count(NuDatabase $db, string $sql): int {
    try {
        $r = $db->fetchOne($sql);
        return (int)($r['total'] ?? 0);
    } catch (Throwable $e) {
        error_log('[dashboard] ' . $e->getMessage());
        return 0;
    }
}
```

### Integrated Modular Subsystems
The Dashboard is highly dynamic and automatically includes widgets built with the **Widgets Module**:
```php
<?php require __DIR__ . '/../widgets/widgets.php'; ?>
```

---

## Usage Examples

### Overriding/Adding custom KPI panels programmatically
Developers can inject or modify raw KPI indicators within the `.nu-grid` wrap:

```html
<!-- Custom KPI Card example inside modules/dashboard/dashboard.php -->
<div class="nu-grid" style="margin-bottom:24px;">
    <!-- Core metrics -->
    <div class="nu-kpi">
        <span class="nu-kpi-label">Active Orders</span>
        <span class="nu-kpi-value"><?= nu_safe_count($db, "SELECT COUNT(*) as total FROM orders WHERE status = 'active'") ?></span>
        <span class="nu-kpi-change up">In Progress</span>
    </div>
</div>
```
