# Workflow & Simulator Module

## Overview
The Workflow & Simulator module provides an advanced business process automation engine. It allows developers to define multi-stage pipelines (e.g. Draft, Manager Review, Director Review, Approved, Rejected) with conditional execution branches (using a built-in rule simulator) and automated trigger hooks (emails, webhooks, or record status updates). Active workflows are tracked in both list views and interactive, stage-colored Kanban boards.

---

## Architecture & Key Files
- **`modules/workflow/workflow.php`**: Primary workflow dashboard displaying lists of definitions, stage pipelines, right-side configuration drawer sheets, and timeline modals.
- **`api/workflow.php`**: Secure administrative backend executing workflow instances, evaluating transition routes, and logging history steps.
- **`core/Workflow.php`**: Foundational workflow engine class handling transaction advancing, permission checking, condition evaluating, and dispatching trigger hooks.

---

## Technical Details

### Workflow Tables Structure
Workflows, stages, transitions, instances, and historic actions are split across five relational tables:

```sql
-- 1. Main Definitions
CREATE TABLE IF NOT EXISTS nu_workflows (
    wf_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wf_code        VARCHAR(100)  NOT NULL UNIQUE,
    wf_name        VARCHAR(255)  NOT NULL,
    wf_description VARCHAR(500)  DEFAULT NULL,
    wf_form_code   VARCHAR(100)  DEFAULT NULL, -- Relational binding to form_code
    wf_active      TINYINT(1)    NOT NULL DEFAULT 1,
    wf_created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    wf_updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_wf_code (wf_code),
    INDEX idx_wf_active (wf_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Stages
CREATE TABLE IF NOT EXISTS nu_workflow_stages (
    wfs_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wfs_wf_id      INT UNSIGNED  NOT NULL,
    wfs_name       VARCHAR(100)  NOT NULL,
    wfs_code       VARCHAR(100)  NOT NULL,
    wfs_color      VARCHAR(16)   NOT NULL DEFAULT '#6366f1',
    wfs_is_start   TINYINT(1)    NOT NULL DEFAULT 0,
    wfs_is_end     TINYINT(1)    NOT NULL DEFAULT 0,
    wfs_order      INT UNSIGNED  NOT NULL DEFAULT 0,
    wfs_sla_hours  INT UNSIGNED  DEFAULT NULL,
    wfs_role       VARCHAR(50)   DEFAULT NULL, -- Authorized role needed to act on this stage
    INDEX idx_wfs_wf (wfs_wf_id),
    CONSTRAINT fk_wfs_wf FOREIGN KEY (wfs_wf_id) REFERENCES nu_workflows(wf_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Transitions
CREATE TABLE IF NOT EXISTS nu_workflow_transitions (
    wft_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wft_wf_id      INT UNSIGNED  NOT NULL,
    wft_from_id    INT UNSIGNED  NOT NULL,
    wft_to_id      INT UNSIGNED  NOT NULL,
    wft_action     VARCHAR(32)   NOT NULL DEFAULT 'advance', -- advance, reject, return, escalate
    wft_label      VARCHAR(100)  NOT NULL DEFAULT 'Advance',
    wft_condition  TEXT          DEFAULT NULL, -- JS/PHP condition string
    wft_hook       VARCHAR(64)   DEFAULT NULL, -- send_email, call_webhook, update_record
    INDEX idx_wft_wf (wft_wf_id),
    CONSTRAINT fk_wft_wf FOREIGN KEY (wft_wf_id) REFERENCES nu_workflows(wf_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Active Instances
CREATE TABLE IF NOT EXISTS nu_workflow_instances (
    wfi_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wfi_wf_id        INT UNSIGNED  NOT NULL,
    wfi_current_stage INT UNSIGNED  NOT NULL,
    wfi_status       VARCHAR(32)   NOT NULL DEFAULT 'active', -- active, completed, rejected, cancelled
    wfi_record_table VARCHAR(100)  DEFAULT NULL,
    wfi_record_id    VARCHAR(64)   DEFAULT NULL, -- VARCHAR PK mapping
    wfi_started_by   VARCHAR(64)   DEFAULT NULL,
    wfi_started_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    wfi_updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_wfi_wf (wfi_wf_id),
    INDEX idx_wfi_status (wfi_status),
    CONSTRAINT fk_wfi_wf FOREIGN KEY (wfi_wf_id) REFERENCES nu_workflows(wf_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Historical Log
CREATE TABLE IF NOT EXISTS nu_workflow_history (
    wfh_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wfh_instance_id INT UNSIGNED  NOT NULL,
    wfh_from_stage  INT UNSIGNED  DEFAULT NULL,
    wfh_to_stage    INT UNSIGNED  NOT NULL,
    wfh_action      VARCHAR(32)   NOT NULL, -- start, advance, reject, return, escalate
    wfh_actor_id    VARCHAR(64)   DEFAULT NULL,
    wfh_comment     TEXT          DEFAULT NULL,
    wfh_acted_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wfh_instance (wfh_instance_id),
    CONSTRAINT fk_wfh_instance FOREIGN KEY (wfh_instance_id) REFERENCES nu_workflow_instances(wfi_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Step-by-Step Transition Simulator
The Workflow module features an ad-hoc, sandboxed simulator:
- **Mock JSON Inputs**: Users enter mock data payloads in JSON structure (e.g., `{ "amount": 1500, "dept": "engineering" }`).
- **Rule Evaluator**: Evaluates transition conditional rules (`wft_condition`) using a sandboxed JS compiler:
```javascript
function evaluateCondition(conditionStr, data) {
  if (!conditionStr || conditionStr.trim() === '') return true;
  try {
    const keys = Object.keys(data);
    const vals = Object.values(data);
    const evaluator = new Function(...keys, `return (${conditionStr});`);
    return !!evaluator(...vals);
  } catch (e) {
    return false;
  }
}
```
- **Mermaid Flowcharting**: Dynamically draws and highlights the evaluated path node-by-node directly on the Mermaid.js graph container!

---

## Usage Examples

### Instantiating and Advancing a Workflow via fetch API
```javascript
// Advancing active instance #102 through transition #5
const payload = {
  instance_id: 102,
  transition_id: 5,
  comment: "Budget approved. Progressing to Procurement."
};

fetch('api/workflow.php?action=advance', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(payload)
})
.then(res => res.json())
.then(data => {
  if (data.success) {
      console.log("Workflow advanced to next stage successfully.");
  }
});
```
