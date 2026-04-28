**Implementation Plan**

- [ ] 1. Create database migrations and enum types
  - Create `app/Enums/RecurrenceType.php` enum with values: `monthly`, `weekly`, `bi_weekly`, `tri_weekly`, `manual`, `counted`.
  - Create `app/Enums/InvoiceType.php` enum with values: `manual`, `recurring`.
  - Create `app/Enums/RecurringInvoiceStatus.php` enum with computed values: `pending`, `in_progress`, `done`, `terminated`.
  - Create migration `create_recurring_invoices_table` with all columns: `customer_id`, `name`, `recurrence_type`, `interval_value`, `interval_unit`, `total_count`, `generated_count`, `tax_rate`, `due_date_offset`, `start_date`, `next_invoice_date`, `active`, `notes`, and indexes.
  - Create migration `create_recurring_invoice_items_table` with columns: `recurring_invoice_id`, `description`, `quantity`, `unit_price`, `sort_order`, and foreign key.
  - Create migration `add_type_and_recurring_fields_to_invoices` to add `type` enum column (default `manual`), `recurring_invoice_id` nullable FK, make `due_date` nullable, and add indexes.
  - Run all migrations and verify tables are created correctly.
  - _Requirements: 1.2, 1.6, 4.1, 6.1_

- [ ] 2. Create new Eloquent models for recurring invoices
  - Create `app/Models/RecurringInvoice.php` with fillable fields, casts (`recurrence_type` to `RecurrenceType`, `start_date`/`next_invoice_date` to `date`, `active` to `boolean`), `customer()` / `items()` / `invoices()` relationships, and `getStatusAttribute()` accessor computing `pending`, `in_progress`, `done`, or `terminated`.
  - Create `app/Models/RecurringInvoiceItem.php` with fillable fields, casts for `quantity` and `unit_price`, and `recurringInvoice()` relationship.
  - _Requirements: 1.6, 1.11, 1.12_

- [ ] 3. Modify existing Invoice and Customer models
  - Add `type` and `recurring_invoice_id` to `$fillable` in `Invoice.php`.
  - Add `'type' => InvoiceType::class` to `$casts` in `Invoice.php`.
  - Add `recurringInvoice(): BelongsTo` relationship to `Invoice.php`.
  - Add `scopeByType()` scope to `Invoice.php`.
  - Add `recurringInvoices(): HasMany` relationship to `Customer.php`.
  - _Requirements: 4.1, 4.4, 1.1_

- [ ] 4. Implement RecurringInvoiceService business logic
  - Create `app/Services/RecurringInvoiceService.php`.
  - Implement `list(Customer $customer)` to list all recurring invoices for a customer.
  - Implement `create(Customer $customer, array $data)` to create a recurring invoice with items and compute `next_invoice_date` based on `start_date` and recurrence type.
  - Implement `update(RecurringInvoice $ri, array $data)` to update template fields and sync items.
  - Implement `terminate(RecurringInvoice $ri)` to set `active = false`.
  - Implement `generateInvoice(RecurringInvoice $ri)` to create a draft invoice from template (copying line items, setting `type = recurring`, assigning invoice number via `InvoiceSequence`, computing `due_date` from offset), increment `generated_count`, and update `next_invoice_date`.
  - Implement `processAll()` to query all active non-manual templates where `next_invoice_date <= today`, generate invoices for each, deactivate counted templates that have reached `total_count`, and wrap each in try/catch with error logging.
  - Implement `calculateNextDate(RecurringInvoice $ri)` for monthly, weekly, bi-weekly, tri-weekly, and counted interval calculations.
  - _Requirements: 1.6, 1.7, 1.9, 1.10, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 2.9, 2.10, 7.2, 7.3_

- [ ] 5. Create API resources and form requests
  - Create `app/Http/Resources/RecurringInvoiceResource.php` including computed `status`, `items`, and `customer` data.
  - Create `app/Http/Resources/RecurringInvoiceCollection.php`.
  - Create `app/Http/Resources/RecurringInvoiceItemResource.php`.
  - Create `app/Http/Requests/RecurringInvoice/StoreRecurringInvoiceRequest.php` with validation: `name` required, `recurrence_type` enum, conditional rules for `counted` (require `interval_value`, `interval_unit`, `total_count`), `items` array required with `description`, `quantity`, `unit_price`.
  - Create `app/Http/Requests/RecurringInvoice/UpdateRecurringInvoiceRequest.php` with partial update validation.
  - Modify `app/Http/Requests/Invoice/StoreInvoiceRequest.php` to make `due_date` nullable.
  - Modify `app/Http/Resources/InvoiceResource.php` to include `type` and `recurring_invoice_id` in output.
  - _Requirements: 1.2, 1.3, 1.4, 6.1, 6.4, 4.4_

- [ ] 6. Create RecurringInvoiceController and register API routes
  - Create `app/Http/Controllers/RecurringInvoiceController.php` with methods: `index`, `store`, `show`, `update`, `destroy`.
  - Add `terminate` action method (POST) to set `active = false` on an in-progress template.
  - Add `generate` action method (POST) to manually generate an invoice from a template.
  - Add `destroy` with guard: reject deletion if invoices have been generated (return 403).
  - Register all routes in `routes/api.php` nested under `customers/{customer}/recurring-invoices`.
  - _Requirements: 1.1, 1.8, 1.9, 1.10, 7.1, 7.2, 7.3, 7.4_

- [ ] 7. Implement the daily scheduled command
  - Create `app/Console/Commands/ProcessRecurringInvoices.php` Artisan command (`recurring:process`).
  - In `handle()`, call `RecurringInvoiceService::processAll()`.
  - Register the command in Laravel's scheduler (`routes/console.php` or `AppServiceProvider`) to run daily.
  - _Requirements: 2.1, 2.2, 2.3, 2.8, 2.9, 2.10_

- [ ] 8. Modify InvoiceService and DashboardService for type filter and recurring summary
  - Modify `app/Services/InvoiceService.php`: add `type` filter support to `list()` method; set `type` to `manual` in `create()`.
  - Modify `app/Services/DashboardService.php`: add `getRecurringInvoiceSummary()` returning `generated_today` count and `upcoming` list (next 7 days), and include it in `getSummary()` response.
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 4.2, 4.3, 5.3, 5.4_

- [ ] 9. Create frontend TypeScript types and API client
  - Create `types/recurring-invoice.ts` with interfaces: `RecurringInvoice`, `RecurringInvoiceItem`, `RecurringInvoiceFormData`, `RecurringInvoiceListParams`, and type aliases for `RecurrenceType`, `RecurringInvoiceStatus`, `InvoiceType`.
  - Modify `types/invoice.ts`: add `type: InvoiceType` and `recurring_invoice_id` to `Invoice`/`InvoiceListItem`, make `due_date` nullable, add `type` to `InvoiceListParams`.
  - Create `lib/api/recurring-invoices.ts` with API client functions: `listRecurringInvoices`, `createRecurringInvoice`, `getRecurringInvoice`, `updateRecurringInvoice`, `deleteRecurringInvoice`, `terminateRecurringInvoice`, `generateInvoice`.
  - Create `lib/hooks/useRecurringInvoices.ts` with TanStack Query hooks for all CRUD + action operations.
  - Export new types and API module from barrel files.
  - _Requirements: 1.1, 4.1, 5.1, 6.1_

- [ ] 10. Build recurring invoice frontend components
  - Create `components/recurring-invoices/RecurringInvoiceStatusBadge.tsx` with color-coded badges for `pending`, `in_progress`, `done`, `terminated`.
  - Create `components/recurring-invoices/RecurringInvoiceList.tsx` displaying all templates for a customer with status badges, recurrence type, next invoice date, and actions (edit, terminate, generate).
  - Create `components/recurring-invoices/RecurringInvoiceForm.tsx` with fields for name, recurrence type, conditional counted fields (interval value/unit, total count), tax rate, due date offset, start date, notes, and dynamic line items.
  - Create `components/recurring-invoices/RecurringInvoiceDetail.tsx` showing full template details, generated invoices history, and action buttons (terminate, generate invoice).
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.8, 1.11, 7.1_

- [ ] 11. Create recurring invoice route pages and integrate with customer detail
  - Create `app/customers/[id]/recurring-invoices/page.tsx` route page (or tab on customer detail).
  - Create `app/customers/[id]/recurring-invoices/new/page.tsx` for creating a new template.
  - Create `app/customers/[id]/recurring-invoices/[riId]/page.tsx` for viewing/editing a template.
  - Wire navigation from customer detail page to recurring invoices section.
  - _Requirements: 1.1, 1.8_

- [ ] 12. Modify invoice list to display type column and type filter
  - Modify `components/invoices/InvoiceList.tsx` to add a "Type" column with "Recurring" / "Manual" badges.
  - Add a "Type" filter dropdown (All Types, Recurring, Manual) to the invoice list filter controls.
  - Ensure the type filter works in combination with existing status, search, and date range filters.
  - Modify `lib/api/invoices.ts` to pass `type` query parameter.
  - _Requirements: 5.1, 5.2, 5.3, 5.4_

- [ ] 13. Make due date optional in invoice form and display
  - Modify `components/invoices/InvoiceForm.tsx` to make the `due_date` field optional (not required).
  - Modify `components/invoices/InvoiceDetail.tsx` to display "No due date" when `due_date` is null.
  - Modify `components/invoices/InvoiceList.tsx` to display "—" for null due dates in the due date column.
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 14. Add recurring invoice detail link on invoice view and show source template
  - Modify `components/invoices/InvoiceDetail.tsx` to show a reference to the source template (template name + link) when viewing a `recurring` type invoice.
  - _Requirements: 4.4_

- [ ] 15. Build the dashboard recurring invoice widget
  - Create `components/dashboard/RecurringInvoiceWidget.tsx` showing: count of invoices generated today, list of upcoming recurring invoices (next 7 days) with customer name, template name, and scheduled date.
  - Display "No upcoming recurring invoices" when the list is empty.
  - Add click handler on each upcoming entry to navigate to the corresponding template.
  - Modify `components/dashboard/Dashboard.tsx` to include the `RecurringInvoiceWidget`.
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ] 16. Write backend unit and feature tests
  - Create `tests/Unit/RecurringInvoiceStatusTest.php` covering all 4 computed statuses (pending, in_progress, done, terminated).
  - Create `tests/Unit/RecurringInvoiceServiceTest.php` covering `calculateNextDate()` for all recurrence types.
  - Add test in `tests/Unit/InvoiceModelTest.php` verifying default `type` is `manual`.
  - Create `tests/Feature/RecurringInvoiceTest.php` covering CRUD, terminate action, manual generation, and validation errors.
  - Create `tests/Feature/ProcessRecurringInvoicesTest.php` verifying the daily command creates invoices, updates `next_invoice_date`, deactivates counted templates, and handles errors.
  - Add test in `tests/Feature/InvoiceFilterTest.php` verifying `?type=recurring` and `?type=manual` filters.
  - Add test in `tests/Feature/InvoiceTest.php` verifying invoice creation without `due_date`.
  - Add test in `tests/Feature/DashboardTest.php` verifying `recurring_invoices` section in dashboard response.
  - Run all tests and ensure they pass.
  - _Requirements: 1.11, 1.12, 2.1, 2.9, 2.10, 4.1, 5.3, 6.1, 6.2_
