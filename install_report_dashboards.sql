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

CREATE TABLE IF NOT EXISTS nu_stations (
    station_id INT AUTO_INCREMENT PRIMARY KEY,
    station_name VARCHAR(100) NOT NULL,
    station_code VARCHAR(20) NOT NULL UNIQUE,
    station_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- Seed stations
INSERT INTO nu_stations (station_name, station_code) VALUES
('North Station', 'STN_NORTH'),
('South Station', 'STN_SOUTH'),
('East Station', 'STN_EAST'),
('West Station', 'STN_WEST')
ON DUPLICATE KEY UPDATE station_name=VALUES(station_name);

-- Seed a sample Revenue Report (which can filter by station/month/year)
INSERT INTO nu_reports (report_code, report_name, report_type, report_sql, report_columns, report_filters, report_active) VALUES
('revenue_report', 'Revenue Report', 'table',
 'SELECT ''July'' as month, ''2026'' as year, ''STN_NORTH'' as station, 54000.00 as revenue, 1250 as transactions UNION ALL SELECT ''July'' as month, ''2026'' as year, ''STN_SOUTH'' as station, 42000.00 as revenue, 980 as transactions UNION ALL SELECT ''August'' as month, ''2026'' as year, ''STN_EAST'' as station, 61000.00 as revenue, 1400 as transactions UNION ALL SELECT ''August'' as month, ''2026'' as year, ''STN_WEST'' as station, 35000.00 as revenue, 850 as transactions',
 '[{"field":"month","label":"Month"},{"field":"year","label":"Year"},{"field":"station","label":"Station Code"},{"field":"revenue","label":"Revenue"},{"field":"transactions","label":"Transactions"}]',
 '[]', 1)
ON DUPLICATE KEY UPDATE report_name=VALUES(report_name), report_sql=VALUES(report_sql), report_columns=VALUES(report_columns);

-- Let's also create a default menu entry for "Report Dashboards"
INSERT INTO nu_menus (menu_parent_id, menu_code, menu_label, menu_type, menu_target, menu_icon, menu_order) VALUES
(0, 'report_dashboards', 'Report Dashboards', 'form', 'report_dashboards', 'pie-chart', 5)
ON DUPLICATE KEY UPDATE menu_label=VALUES(menu_label);
