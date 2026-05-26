
-- Phase 3 Schema Updates

-- Document Management
CREATE TABLE IF NOT EXISTS nu_documents (
    doc_id INT AUTO_INCREMENT PRIMARY KEY,
    doc_title VARCHAR(255) NOT NULL,
    doc_description TEXT,
    doc_file_id INT,
    doc_category VARCHAR(50),
    doc_status ENUM('draft','pending','approved','rejected','archived') DEFAULT 'draft',
    doc_created_by INT,
    doc_created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    doc_updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (doc_file_id) REFERENCES nu_files(file_id) ON DELETE SET NULL,
    FOREIGN KEY (doc_created_by) REFERENCES nu_users(usr_id) ON DELETE SET NULL,
    INDEX idx_status (doc_status),
    INDEX idx_category (doc_category)
) ENGINE=InnoDB;

-- Digital Signatures
CREATE TABLE IF NOT EXISTS nu_signatures (
    sig_id INT AUTO_INCREMENT PRIMARY KEY,
    sig_document_id INT NOT NULL,
    sig_user_id INT NOT NULL,
    sig_data LONGTEXT NOT NULL,
    sig_ip VARCHAR(45),
    sig_user_agent VARCHAR(255),
    sig_signed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sig_document_id) REFERENCES nu_documents(doc_id) ON DELETE CASCADE,
    FOREIGN KEY (sig_user_id) REFERENCES nu_users(usr_id) ON DELETE CASCADE,
    UNIQUE KEY unique_doc_user (sig_document_id, sig_user_id)
) ENGINE=InnoDB;

-- Workflow Engine
CREATE TABLE IF NOT EXISTS nu_workflows (
    wf_id INT AUTO_INCREMENT PRIMARY KEY,
    wf_code VARCHAR(50) NOT NULL UNIQUE,
    wf_name VARCHAR(100) NOT NULL,
    wf_description TEXT,
    wf_config JSON,
    wf_active TINYINT(1) DEFAULT 1,
    wf_created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Workflow Steps
CREATE TABLE IF NOT EXISTS nu_workflow_steps (
    wfs_id INT AUTO_INCREMENT PRIMARY KEY,
    wfs_wf_id INT NOT NULL,
    wfs_step_order INT DEFAULT 0,
    wfs_step_name VARCHAR(100),
    wfs_approver_role VARCHAR(30),
    wfs_approver_user_id INT,
    wfs_action_required ENUM('approve','review','sign') DEFAULT 'approve',
    FOREIGN KEY (wfs_wf_id) REFERENCES nu_workflows(wf_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Workflow Instances
CREATE TABLE IF NOT EXISTS nu_workflow_instances (
    wfi_id INT AUTO_INCREMENT PRIMARY KEY,
    wfi_wf_id INT NOT NULL,
    wfi_document_id INT,
    wfi_record_table VARCHAR(50),
    wfi_record_id INT,
    wfi_status ENUM('pending','approved','rejected','in_progress') DEFAULT 'pending',
    wfi_current_step INT DEFAULT 0,
    wfi_started_by INT,
    wfi_started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    wfi_completed_at DATETIME,
    FOREIGN KEY (wfi_wf_id) REFERENCES nu_workflows(wf_id) ON DELETE CASCADE,
    FOREIGN KEY (wfi_document_id) REFERENCES nu_documents(doc_id) ON DELETE SET NULL,
    FOREIGN KEY (wfi_started_by) REFERENCES nu_users(usr_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Workflow Approvals
CREATE TABLE IF NOT EXISTS nu_workflow_approvals (
    wfa_id INT AUTO_INCREMENT PRIMARY KEY,
    wfa_wfi_id INT NOT NULL,
    wfa_step_id INT NOT NULL,
    wfa_approver_id INT,
    wfa_action ENUM('approved','rejected','delegated') DEFAULT NULL,
    wfa_comment TEXT,
    wfa_signed TINYINT(1) DEFAULT 0,
    wfa_created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wfa_wfi_id) REFERENCES nu_workflow_instances(wfi_id) ON DELETE CASCADE,
    FOREIGN KEY (wfa_step_id) REFERENCES nu_workflow_steps(wfs_id) ON DELETE CASCADE,
    FOREIGN KEY (wfa_approver_id) REFERENCES nu_users(usr_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Dashboard Widgets
CREATE TABLE IF NOT EXISTS nu_dashboard_widgets (
    widget_id INT AUTO_INCREMENT PRIMARY KEY,
    widget_user_id INT,
    widget_type VARCHAR(30) NOT NULL,
    widget_title VARCHAR(100),
    widget_config JSON,
    widget_position_x INT DEFAULT 0,
    widget_position_y INT DEFAULT 0,
    widget_width INT DEFAULT 4,
    widget_height INT DEFAULT 3,
    widget_active TINYINT(1) DEFAULT 1,
    widget_created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (widget_user_id) REFERENCES nu_users(usr_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Plugins
CREATE TABLE IF NOT EXISTS nu_plugins (
    plugin_id INT AUTO_INCREMENT PRIMARY KEY,
    plugin_code VARCHAR(50) NOT NULL UNIQUE,
    plugin_name VARCHAR(100) NOT NULL,
    plugin_version VARCHAR(20) DEFAULT '1.0.0',
    plugin_description TEXT,
    plugin_author VARCHAR(100),
    plugin_path VARCHAR(255),
    plugin_hooks JSON,
    plugin_active TINYINT(1) DEFAULT 0,
    plugin_installed_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed workflows
INSERT INTO nu_workflows (wf_code, wf_name, wf_description, wf_config) VALUES
('doc_approval', 'Document Approval', 'Standard document approval workflow', '{"steps": 2}'),
('leave_request', 'Leave Request', 'Employee leave approval', '{"steps": 3}');

INSERT INTO nu_workflow_steps (wfs_wf_id, wfs_step_order, wfs_step_name, wfs_approver_role, wfs_action_required) VALUES
(1, 1, 'Manager Review', 'admin', 'review'),
(1, 2, 'Final Approval', 'globeadmin', 'approve');
