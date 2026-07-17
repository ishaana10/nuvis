# Calendar & Scheduler Module

## Overview
The Calendar & Scheduler module provides a visual timeline and day-by-day calendar grid interface within the application. It enables users to map, create, edit, and organize dynamic events (such as meetings, tasks, reminders, and deadlines).

---

## Architecture & Key Files
- **`modules/calendar/calendar.php`**: Standard monthly calendar render grid utilizing DateTime math, displaying personal and globally visible scheduling slots.

---

## Technical Details

### Database Schema (`nu_calendar_events`)
The calendar structure operates on the `nu_calendar_events` table:

```sql
CREATE TABLE IF NOT EXISTS nu_calendar_events (
    event_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_title       VARCHAR(255)  NOT NULL,
    event_description TEXT          DEFAULT NULL,
    event_start       DATETIME      NOT NULL,
    event_end         DATETIME      DEFAULT NULL,
    event_type        VARCHAR(32)   NOT NULL DEFAULT 'event',
    event_color       VARCHAR(16)   NOT NULL DEFAULT '#0ea5e9',
    event_user_id     VARCHAR(64)   DEFAULT NULL,
    event_created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_user (event_user_id),
    INDEX idx_event_span (event_start, event_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Usage Examples

### SQL Schema and Seed Data
```sql
-- Seed standard meetings
INSERT INTO nu_calendar_events (event_title, event_description, event_start, event_end, event_type, event_color)
VALUES (
    'Sprint Planning',
    'Discuss upcoming priorities and roadmap items',
    DATE_ADD(CURRENT_DATE, INTERVAL 9 HOUR),
    DATE_ADD(CURRENT_DATE, INTERVAL 10 HOUR),
    'meeting',
    '#10b981'
);
```

### Saving an Event via Client fetch Payload
```javascript
const savePayload = {
  action: "save",
  title: "Q3 Strategy Presentation",
  description: "Executive strategy align session",
  start: "2026-08-15T14:00:00",
  end: "2026-08-15T15:30:00",
  type: "meeting",
  color: "#8b5cf6"
};

fetch('api/crud.php?table=nu_calendar_events', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(savePayload)
})
.then(res => res.json())
.then(data => {
  if (data.success) {
     console.log("Calendar slot locked!");
  }
});
```
