CREATE TABLE IF NOT EXISTS nu_report_dashboards (
    dashboard_id INT AUTO_INCREMENT PRIMARY KEY,
    dashboard_code VARCHAR(50) NOT NULL UNIQUE,
    dashboard_name VARCHAR(100) NOT NULL,
    dashboard_description TEXT,
    dashboard_active TINYINT(1) DEFAULT 1,
    dashboard_role_access JSON,
    dashboard_filters JSON,
    dashboard_reports JSON,
    dashboard_created_by INT,
    dashboard_created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    dashboard_updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed default menu entry for "Report Dashboards"
INSERT INTO nu_menus (menu_parent_id, menu_code, menu_label, menu_type, menu_target, menu_icon, menu_order) VALUES
(0, 'report_dashboards', 'Report Dashboards', 'form', 'report_dashboards', 'pie-chart', 5)
ON DUPLICATE KEY UPDATE menu_label=VALUES(menu_label);
