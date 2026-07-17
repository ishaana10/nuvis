# AI Module

## Overview
The AI Assistant module integrates generative artificial intelligence capabilities (e.g., OpenAI or Claude models) directly into the nuvis platform. It allows developers and administrators to chat with an AI assistant to get help building forms, writing SQL queries, analyzing data, or answering structural questions. It also includes utility functions to auto-generate form definitions or SQL reports from plain-English descriptions.

---

## Architecture & Key Files
- **`modules/ai/ai.php`**: Frontend interface rendering the AI Assistant chat interface, Auto-Generate Form input panel, and Auto-Generate SQL Report input panel.
- **`api/ai.php`**: REST API backend routing AI chat/generation requests, calling external AI services (like OpenAI API), or falling back to local mock/demo responses if credentials are not configured.

---

## Technical Details

### AI Provider Configuration
The module reads parameters from global settings stored in `$GLOBALS['nuConfig']`:
- `aiProvider`: The provider to use (`'openai'` or `'claude'`).
- `aiApiKey`: The API secret key.
- `aiModel`: The target model (e.g., `'gpt-3.5-turbo'`, `'gpt-4'`).

If `aiApiKey` is missing or empty, the API automatically falls back to a helper method, returning deterministic mock templates for prototyping and demonstration purposes.

---

## REST API Reference

| Endpoint | Method | Action | Description |
|---|---|---|---|
| `/api/ai.php?action=chat` | `POST` | `chat` | Sends a prompt to the AI and gets a conversational response. |
| `/api/ai.php?action=generate_form` | `POST` | `generate_form` | Sends a visual form request and gets a structured JSON layout back. |
| `/api/ai.php?action=generate_report` | `POST` | `generate_report` | Sends a report requirements string and returns a complete SELECT SQL query and column definitions. |

### Payload JSON Formats

#### 1. Chat Response
```json
// POST /api/ai.php?action=chat
{
  "prompt": "How do I fetch active orders sorted by date?"
}

// Response
{
  "success": true,
  "response": "For that SQL query, you could use: SELECT * FROM orders WHERE status = 'active' ORDER BY created_at DESC."
}
```

#### 2. Auto-Generate Form Layout
```json
// POST /api/ai.php?action=generate_form
{
  "prompt": "Create an order checkout form"
}

// Response
{
  "success": true,
  "data": {
    "name": "AI Generated: Create an order checkout form",
    "table": "ai_generated_1680000000",
    "fields": [
      { "type": "text", "name": "name", "label": "Name", "required": true },
      { "type": "email", "name": "email", "label": "Email", "required": true },
      { "type": "select", "name": "category", "label": "Category", "options": [
          { "value": "a", "label": "Option A" },
          { "value": "b", "label": "Option B" }
        ]
      },
      { "type": "date", "name": "date", "label": "Date" },
      { "type": "textarea", "name": "notes", "label": "Notes" }
    ]
  }
}
```

#### 3. Auto-Generate Report SQL
```json
// POST /api/ai.php?action=generate_report
{
  "prompt": "Monthly sales summary"
}

// Response
{
  "success": true,
  "data": {
    "name": "AI Report: Monthly sales summary",
    "sql": "SELECT DATE(created_at) as date, COUNT(*) as total, SUM(amount) as revenue FROM orders GROUP BY DATE(created_at) ORDER BY date DESC LIMIT 30",
    "columns": [
      { "field": "date", "label": "Date" },
      { "field": "total", "label": "Orders" },
      { "field": "revenue", "label": "Revenue" }
    ]
  }
}
```

---

## JavaScript Frontend Invocation

Using the global frontend API interface:

```javascript
// Triggering chat request in Javascript
fetch('api/ai.php?action=chat', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ prompt: 'How do I restrict a menu?' })
})
.then(res => res.json())
.then(data => {
  if (data.success) {
    console.log("AI says:", data.response);
  }
});
```
