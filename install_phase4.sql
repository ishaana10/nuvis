
-- Phase 4 Schema Updates

-- Calendar Events
CREATE TABLE IF NOT EXISTS nu_calendar_events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    event_title VARCHAR(255) NOT NULL,
    event_description TEXT,
    event_start DATETIME NOT NULL,
    event_end DATETIME,
    event_type ENUM('meeting','task','reminder','deadline') DEFAULT 'meeting',
    event_color VARCHAR(7) DEFAULT '#0ea5e9',
    event_user_id INT,
    event_active TINYINT(1) DEFAULT 1,
    event_created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_user_id) REFERENCES nu_users(usr_id) ON DELETE SET NULL,
    INDEX idx_start (event_start),
    INDEX idx_user (event_user_id)
) ENGINE=InnoDB;

-- Webhooks
CREATE TABLE IF NOT EXISTS nu_webhooks (
    webhook_id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_name VARCHAR(100) NOT NULL,
    webhook_url VARCHAR(500) NOT NULL,
    webhook_events VARCHAR(255),
    webhook_secret VARCHAR(255),
    webhook_active TINYINT(1) DEFAULT 1,
    webhook_last_triggered DATETIME,
    webhook_created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (webhook_active)
) ENGINE=InnoDB;

-- Email Templates
CREATE TABLE IF NOT EXISTS nu_email_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    template_code VARCHAR(50) NOT NULL UNIQUE,
    template_name VARCHAR(100),
    template_subject VARCHAR(255),
    template_body TEXT,
    template_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- Seed email templates
INSERT INTO nu_email_templates (template_code, template_name, template_subject, template_body) VALUES
('welcome', 'Welcome Email', 'Welcome to nuBuilder Next', '<h2>Welcome {{username}}</h2><p>Your account has been created successfully.</p>'),
('workflow_approve', 'Workflow Approved', 'Workflow Approved: {{workflow_name}}', '<h2>Workflow Approved</h2><p>The workflow "{{workflow_name}}" has been approved.</p>'),
('workflow_reject', 'Workflow Rejected', 'Workflow Rejected: {{workflow_name}}', '<h2>Workflow Rejected</h2><p>The workflow "{{workflow_name}}" has been rejected.</p><p>Comment: {{comment}}</p>'),
('password_reset', 'Password Reset', 'Password Reset Request', '<h2>Password Reset</h2><p>Click <a href="{{link}}">here</a> to reset your password.</p>');
