**Requirements: Recurring Invoice System**

**Introduction**
The current Accounting Timedoor system supports only manually-created invoices. This feature introduces a Recurring Invoice system that allows admins to create invoice templates per customer with configurable recurrence schedules (monthly, weekly, bi-weekly, tri-weekly, manual, and counted). The system will automatically check daily which recurring invoices should be generated and create them. Admins will see pending and recently auto-generated recurring invoice information on the dashboard. Additionally, invoices will be classified by type (recurring vs. manual), the invoice list will display this type with filter support, and the due date field on invoices will become optional.

**Requirements**

---

**Requirement 1: Invoice Template with Recurring Schedule**
**User Story:** As an Admin, I want to create an invoice template for each customer with a recurring schedule, so that the system can automatically generate invoices at defined intervals without manual intervention.

**Acceptance Criteria**
1. WHEN the Admin navigates to a customer's detail page, THEN the system SHALL display an option to create or edit a "Recurring Invoice" for that customer.
2. WHEN the Admin creates a new invoice template, THEN the system SHALL require the following fields: template name, line items (description, quantity, unit price), tax rate, and recurrence type.
3. The recurrence type SHALL support the following values: `monthly`, `weekly`, `bi-weekly` (every 2 weeks), `tri-weekly` (every 3 weeks), `manual`, and `counted`.
4. IF the recurrence type is set to `counted`, THEN the system SHALL require an additional field specifying the total number of invoices to generate and an interval (e.g., every N days/weeks/months).
5. IF the recurrence type is set to `manual`, THEN the system SHALL NOT auto-generate invoices; the Admin must trigger generation manually.
6. WHEN the Admin saves the invoice template, THEN the system SHALL persist the template with its associated customer, line items, tax rate, recurrence configuration, and a `start_date`.
7. WHEN a template is saved with a non-manual recurrence type, THEN the system SHALL calculate and store the `next_invoice_date` based on the start date and the recurrence interval.
8. IF a template already exists for a customer, THEN the system SHALL allow the Admin to update or deactivate it.
9. WHEN the Admin deactivates a template, THEN the system SHALL stop generating future invoices from that template but SHALL NOT affect previously generated invoices.
10. WHEN the Admin terminates a running recurring invoice (status "In Progress"), THEN the system SHALL immediately stop all future invoice generation from that template. All previously generated invoices SHALL remain intact and unaffected.
11. WHEN viewing a recurring invoice template, THEN the system SHALL display the `start_date` and a status indicator. The status SHALL be one of the following:
    - **Pending** — the template has been created but the `start_date` is in the future; no invoices have been generated yet.
    - **In Progress** — the template is active and invoices are being generated on schedule.
    - **Done** — the template has completed its cycle (e.g., all counted invoices have been generated).
    - **Terminated** — the Admin has manually terminated the recurring invoice before its cycle completed.
12. The recurring invoice status SHALL be automatically computed by the system based on the template's `start_date`, `active` flag, recurrence type, and (for counted) the number of invoices generated vs. the total count.

---

**Requirement 2: Daily Scheduled Invoice Generation**
**User Story:** As an Admin, I want the system to automatically check daily which recurring invoices should be generated, so that invoices are created on time without manual effort.

**Acceptance Criteria**
1. The system SHALL run a daily scheduled job (e.g., Laravel scheduler / cron) that checks all active invoice templates.
2. WHEN the scheduled job runs, THEN the system SHALL query all active templates where `next_invoice_date <= today` and the template is not `manual`.
3. FOR EACH matching template, the system SHALL create a new invoice in `draft` status with the line items, tax rate, and customer from the template.
4. WHEN a new invoice is generated from a template, THEN the system SHALL auto-assign an invoice number following the existing `InvoiceSequence` numbering scheme.
5. WHEN a new invoice is generated from a template, THEN the system SHALL set the `invoice_date` to today's date.
6. WHEN a new invoice is generated, THEN the system SHALL set the invoice `type` field to `recurring`.
7. IF the template has a due date offset configured (e.g., "due 30 days after invoice date"), THEN the system SHALL calculate and set the `due_date` accordingly. IF no due date offset is configured, THEN `due_date` SHALL be left empty (null).
8. AFTER generating an invoice, the system SHALL update the template's `next_invoice_date` to the next recurrence date.
9. IF the recurrence type is `counted` AND the total number of invoices has been reached, THEN the system SHALL automatically deactivate the template after the final invoice is generated.
10. IF the scheduled job encounters an error while generating a specific invoice, THEN it SHALL log the error and continue processing the remaining templates without halting.

---

**Requirement 3: Dashboard Recurring Invoice Information**
**User Story:** As an Admin, I want to see information about upcoming and recently auto-generated recurring invoices on the dashboard, so that I can stay informed about billing activity.

**Acceptance Criteria**
1. WHEN the Admin views the dashboard, THEN the system SHALL display a "Recurring Invoices" section (card or widget).
2. The recurring invoices section SHALL show the number of invoices auto-generated today or since the last check.
3. The recurring invoices section SHALL show a list of upcoming recurring invoices due to be generated within the next 7 days, including: customer name, template name, and scheduled generation date.
4. IF there are no upcoming recurring invoices, THEN the system SHALL display a message such as "No upcoming recurring invoices."
5. WHEN the Admin clicks on an upcoming recurring invoice entry, THEN the system SHALL navigate to the corresponding invoice template for that customer.

---

**Requirement 4: Invoice Type Classification**
**User Story:** As an Admin, I want each invoice to be classified as either "recurring" or "manual", so that I can distinguish between automatically and manually created invoices.

**Acceptance Criteria**
1. The `invoices` table SHALL have a new `type` field with allowed values: `recurring` and `manual`.
2. WHEN an invoice is created manually by the Admin (via the "Create Invoice" form), THEN the system SHALL set the `type` to `manual`.
3. WHEN an invoice is auto-generated from a recurring template, THEN the system SHALL set the `type` to `recurring`.
4. WHEN an invoice of type `recurring` is viewed, THEN the system SHALL display a reference to its source template (e.g., template name, link to customer's template settings).

---

**Requirement 5: Invoice List — Type Display and Filter**
**User Story:** As an Admin, I want to see the invoice type in the invoice list and filter by type, so that I can quickly find and manage recurring vs. manual invoices.

**Acceptance Criteria**
1. WHEN the Admin views the invoice list, THEN each invoice row SHALL display a "Type" column showing either "Recurring" or "Manual" with a distinguishing visual indicator (e.g., badge or icon).
2. The invoice list filter controls SHALL include a "Type" dropdown with options: "All Types", "Recurring", and "Manual".
3. WHEN the Admin selects a type filter, THEN the system SHALL display only invoices matching the selected type.
4. The type filter SHALL work in combination with the existing status, search, and date range filters.

---

**Requirement 6: Optional Due Date on Invoices**
**User Story:** As an Admin, I want the due date field on invoices to be optional, so that I can create invoices without a due date when no specific payment deadline is required.

**Acceptance Criteria**
1. WHEN the Admin creates or edits an invoice, THEN the `due_date` field SHALL be optional (nullable).
2. IF the `due_date` is left empty, THEN the system SHALL save the invoice without a due date and display "—" or "No due date" in the due date column of the invoice list.
3. IF the `due_date` is left empty, THEN the invoice SHALL NOT be considered for overdue status processing.
4. The system SHALL continue to accept and validate `due_date` when provided, ensuring it is a valid date.
5. WHEN displaying an invoice detail, IF the `due_date` is null, THEN the system SHALL show "No due date" instead of an empty or broken field.

---

**Requirement 7: Manual Generation from Template**
**User Story:** As an Admin, I want to manually trigger invoice generation from a template, so that I can create one-off invoices based on a predefined template when needed.

**Acceptance Criteria**
1. WHEN viewing an invoice template with recurrence type `manual`, THEN the system SHALL display a "Generate Invoice" button.
2. WHEN the Admin clicks "Generate Invoice", THEN the system SHALL create a new invoice in `draft` status using the template's line items, tax rate, and customer.
3. WHEN the Admin clicks "Generate Invoice" on any active template (regardless of recurrence type), THEN the system SHALL allow on-demand invoice creation without affecting the automatic schedule.
4. AFTER manual generation, THEN the system SHALL display a success notification with a link to the newly created invoice.

