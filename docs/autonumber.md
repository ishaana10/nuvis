# Auto-Number Field Type Guide

The **Auto-Number** (`autonumber`) field type allows developers to define dynamic, stateful, sequential counters for standard records and subform rows. Sequence generation is calculated securely on the backend at the time of database `INSERT` to guarantee absolute uniqueness and protect against front-end tampering.

---

## Configuration Properties

When you drag the **Auto-Number** field onto the Form Builder canvas and open the **Advanced** tab in the properties sidebar, you can configure the following properties:

1. **Sequence Code**
   - *Description*: Unique identifier for this counter sequence.
   - *Behavior*: If left blank, it defaults to `{form_code}_{field_name}`. Multiple forms or fields can share the same counter sequence by using the same Sequence Code.
   - *Example*: `service_request_seq`

2. **Prefix Pattern**
   - *Description*: The template for the prefix, supports static text, field placeholder tokens `{field_name}`, and dynamic date/time tokens.
   - *Example*: `INV-` or `{service_type}-`

3. **Suffix Pattern**
   - *Description*: The template for the suffix, supports static text, field placeholder tokens, and date/time tokens.
   - *Example*: `-{YEAR}` or `-{service_type}`

4. **Padding Length**
   - *Description*: Defines the minimum length of the numeric counter. Shorter numbers are left-padded with zeros.
   - *Example*: `5` (yields `00001`, `00002`... etc.)

5. **Prefix Map**
   - *Description*: A case-insensitive newline-separated mapping (`source:target`) that translates dynamic placeholder values (e.g. from select fields) to custom abbreviations.
   - *Example*:
     ```text
     post border:PB
     border:B
     airport:A
     ```

---

## 1. Dynamic Prefix Mapping with Dropdowns

Suppose you have a dropdown (select field) named `service_type` with options:
- `post border` (Post Border)
- `border` (Border)
- `airport` (Airport)

To automatically generate dynamic prefixes (e.g., `PB00001`, `B00002`) based on the dropdown:

1. **Add a Select field** to your form:
   - Set **Field Name** to: `service_type`
2. **Add an Auto-Number field** to your form:
   - Set **Field Name** to: `ref_number`
3. Click the Auto-Number field, go to the **Advanced** tab, and configure:
   - **Prefix Pattern**: `{service_type}`
   - **Prefix Map**:
     ```text
     post border:PB
     border:B
     airport:A
     ```
   - **Padding Length**: `5`

### How It Works:
- When a user adds a record, selects **Post Border**, and clicks **Save**:
  - The backend retrieves the value `post border` for the `{service_type}` token.
  - It searches the **Prefix Map** for `post border` (case-insensitively).
  - It finds the match `PB`.
  - It increments the atomic sequence counter for the Sequence Code to get `1`.
  - It pads the number `1` to 5 digits (`00001`).
  - Result: `PB00001` is saved to the database.

---

## 2. Dynamic Date and Time Tokens

The Auto-Number generator supports dynamic, real-time date tokens inside both **Prefix Pattern** and **Suffix Pattern**:

- `{YEAR}` or `{year}`: Current 4-digit year (e.g., `2026`)
- `{MONTH}` or `{month}`: Current 2-digit month (e.g., `08`)
- `{DAY}` or `{day}`: Current 2-digit day (e.g., `12`)

### Example: Adding Year to Suffix
- **Prefix Pattern**: `SRV-`
- **Suffix Pattern**: `-{YEAR}`
- **Padding Length**: `4`
- **Result**: `SRV-0001-2026`, `SRV-0002-2026`

---

## 3. Continuing from an Existing Table

If you are migrating an existing system or table and want the Auto-Number to continue numbering from a specific offset:

Nuvis statefully tracks all sequential counters inside a dedicated database table named `nu_sequence_counters`. It uses a transaction-safe incrementation process.

To offset or manually set the next number:

1. Locate the **Sequence Code** used by your Auto-Number field (e.g., `invoice_seq`).
2. Run a simple database `INSERT` or `UPDATE` query on your SQLite or MySQL database:

```sql
-- To continue sequence from 1250 (the next generated record will receive 1251)
INSERT INTO nu_sequence_counters (seq_code, seq_value)
VALUES ('invoice_seq', 1250)
ON DUPLICATE KEY UPDATE seq_value = 1250; -- For MySQL

-- For SQLite / MySQL cross-compatible update if row already exists:
UPDATE nu_sequence_counters SET seq_value = 1250 WHERE seq_code = 'invoice_seq';
```

---

## Full Scenario Example

### Goal:
Generate reference numbers matching patterns like: `PB2026-00045` or `B2026-00046`.

### Setup:
1. Field: `section` (Dropdown with value options: `post border`, `border`)
2. Field: `request_no` (Auto-Number Field)
   - **Sequence Code**: `custom_req_seq`
   - **Prefix Pattern**: `{section}{YEAR}-`
   - **Prefix Map**:
     ```text
     post border:PB
     border:B
     ```
   - **Padding Length**: `5`

### Execution Outcome:
1. User selects **Post Border**, clicks Save -> Generates `PB2026-00001`
2. User selects **Border**, clicks Save -> Generates `B2026-00002`
3. User selects **Post Border**, clicks Save -> Generates `PB2026-00003`
