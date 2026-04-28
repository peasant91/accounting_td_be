# Implementation Plan: Accounting Module

---

## Phase 1: Foundation

- [x] 1. Set up database schema and migrations
  - Create migration for `customers` table with all fields (name, email, phone, address, tax_id, notes, status, soft delete)
  - Create migration for `invoices` table with all fields (invoice_number, dates, amounts, status, payment info, soft delete)
  - Create migration for `invoice_items` table (description, quantity, unit_price, amount, sort_order)
  - Create migration for `activity_logs` table (polymorphic design for audit logging)
  - Create migration for `invoice_sequences` table (for sequential invoice number generation)
  - Run all database migrations
  - _Requirements: NFR-3_

- [x] 2. Create Laravel Enums and base Models
  - Create `CustomerStatus` enum (active, inactive)
  - Create `InvoiceStatus` enum (draft, sent, paid, overdue, cancelled)
  - Create `PaymentMethod` enum (cash, bank_transfer, credit_card, other)
  - Create `Customer` model with SoftDeletes, fillable fields, and relationships
  - Create `Invoice` model with SoftDeletes, fillable fields, casts, and relationships
  - Create `InvoiceItem` model with auto-calculation on save
  - Create `ActivityLog` model with polymorphic relationship
  - Create `HasActivityLog` trait for automatic logging
  - _Requirements: NFR-2, NFR-3_

- [x] 3. Set up Next.js frontend project structure
  - Initialize Next.js 14+ project with App Router
  - Set up TypeScript configuration
  - Configure CSS Modules and global styles
  - Set up React Query provider for state management
  - Create base layout structure with auth and dashboard route groups
  - Create TypeScript interfaces for Customer, Invoice, InvoiceItem, and API responses
  - _Requirements: 18.1, 18.2, 18.3_

---

## Phase 2: Backend API - Customer Module

- [x] 4. Implement Customer API endpoints
  - Create `CustomerController` with CRUD methods
  - Create `StoreCustomerRequest` with validation rules (name required max 100, email required unique, phone format)
  - Create `UpdateCustomerRequest` with validation rules
  - Create `CustomerResource` and `CustomerCollection` for API responses
  - Create `CustomerService` with list, create, update, delete, and calculateReceivable methods
  - Implement GET `/customers` endpoint with pagination, search, and status filter
  - Implement POST `/customers` endpoint with duplicate email validation
  - Implement GET `/customers/{id}` endpoint with invoice summary
  - Implement PUT `/customers/{id}` endpoint
  - Implement DELETE `/customers/{id}` endpoint with soft-delete cascade to invoices
  - Write unit tests for `CustomerService`
  - Write feature tests for Customer API endpoints
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 5.1, 5.2, 6.1, 6.2, 6.3, 6.4, 6.5, 7.1, 7.2, 7.3, 7.4, 7.5_

---

## Phase 3: Backend API - Invoice Module

- [x] 5. Implement Invoice CRUD API endpoints
  - Create `InvoiceController` with CRUD methods
  - Create `StoreInvoiceRequest` with validation rules (customer_id required, dates, line items)
  - Create `UpdateInvoiceRequest` with draft-only edit validation
  - Create `InvoiceResource` and `InvoiceCollection` for API responses
  - Create `InvoiceService` with list, create, update, delete methods
  - Implement invoice number generation (INV-YYYY-NNNN format)
  - Implement invoice amount calculations (subtotal, tax, total)
  - Implement GET `/invoices` endpoint with pagination, search, and filters (status, customer, date range)
  - Implement POST `/invoices` endpoint with line items
  - Implement GET `/invoices/{id}` endpoint with available actions
  - Implement PUT `/invoices/{id}` endpoint (draft only)
  - Implement DELETE `/invoices/{id}` endpoint (draft only)
  - Write unit tests for `InvoiceService` and calculations
  - Write feature tests for Invoice API endpoints
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 10.1, 10.2, 11.1, 11.2, 11.3, 16.1, 16.2, 16.3, 16.4_

- [x] 6. Implement Invoice status action endpoints
  - Create `SendInvoiceRequest`, `MarkAsPaidRequest`, `CancelInvoiceRequest` validation classes
  - Implement POST `/invoices/{id}/send` endpoint
  - Implement POST `/invoices/{id}/resend` endpoint
  - Implement POST `/invoices/{id}/send-reminder` endpoint
  - Implement POST `/invoices/{id}/mark-as-paid` endpoint with payment details
  - Implement POST `/invoices/{id}/cancel` endpoint with cancellation reason
  - Update `InvoiceService` with send, sendReminder, markAsPaid, and cancel methods
  - Write feature tests for status action endpoints
  - _Requirements: 12.1, 12.2, 12.3, 12.4, 13.1, 13.2, 13.3, 14.1, 14.2, 14.3, 15.1, 15.2, 15.3, 15.4_

---

## Phase 4: Email & PDF Generation

- [ ] 7. Implement PDF generation for invoices
  - Install and configure DomPDF or Snappy package
  - Create `invoice.blade.php` PDF template with company logo, customer info, line items, totals
  - Create `PDFService` with `generateInvoicePDF` method
  - Implement GET `/invoices/{id}/pdf` endpoint to download invoice PDF
  - Write unit tests for `PDFService`
  - _Requirements: 12.3, NFR-1_

- [ ] 8. Implement email sending functionality
  - Configure Laravel Mail with SMTP settings
  - Create `InvoiceMail` mailable class with PDF attachment
  - Create `PaymentReminderMail` mailable class
  - Create `invoice.blade.php` email template
  - Create `payment-reminder.blade.php` email template
  - Create `SendInvoiceEmailJob` queue job
  - Create `SendReminderEmailJob` queue job
  - Configure rate limiting for email endpoints (10 requests/minute)
  - Write feature tests for email functionality with queue mocking
  - _Requirements: 12.3, 12.4, 12.5, 13.3, 13.4_

- [ ] 9. Implement automatic overdue status job
  - Create `UpdateOverdueInvoicesJob` scheduled job
  - Configure Laravel scheduler to run daily
  - Update invoices with status "Sent" and past due date to "Overdue"
  - Write feature tests for overdue job
  - _Requirements: 17.1, 17.2, 17.3_

---

## Phase 5: Dashboard API

- [x] 10. Implement Dashboard API endpoint
  - Create `DashboardController` with summary method
  - Create `DashboardService` with getSummary, getTotalReceivables, getTotalCustomers, getInvoicesDueThisMonth, getRecentActivity methods
  - Implement GET `/dashboard/summary` endpoint
  - Return total receivables, customer count, invoices due this month, recent activity
  - Write feature tests for Dashboard API
  - _Requirements: 1.1, 1.2, 1.3_

---

## Phase 6: Frontend - Core UI Components

- [x] 11. Build reusable UI component library
  - Create `Button` component with variants (primary, secondary, danger)
  - Create `Input` component with validation states
  - Create `Select` component with searchable option
  - Create `Table` component with sorting and row click handlers
  - Create `Pagination` component
  - Create `Modal` component for dialogs and confirmations
  - Create `EmptyState` component for empty lists
  - Create `Loading` component (skeleton and spinner variants)
  - Create `StatusBadge` component for invoice statuses
  - Set up CSS variables for theming
  - _Requirements: 18.4_

- [x] 12. Build API client and React Query hooks
  - Create `client.ts` with fetch wrapper and auth token handling
  - Create `customers.ts` API functions (list, get, create, update, delete)
  - Create `invoices.ts` API functions (list, get, create, update, delete, send, markAsPaid, cancel, downloadPdf)
  - Create `dashboard.ts` API function (getSummary)
  - Create `useCustomers.ts` React Query hooks (useCustomers, useCustomer, useCreateCustomer, useUpdateCustomer, useDeleteCustomer)
  - Create `useInvoices.ts` React Query hooks
  - Create `useDashboard.ts` React Query hook
  - Create `formatters.ts` utility (currency, date formatting)
  - Create `validators.ts` utility (form validation helpers)
  - _Requirements: NFR-1_

---

## Phase 7: Frontend - Customer Module

- [x] 13. Build Customer list page
  - Create `CustomerList.tsx` component with table, search, and pagination
  - Create `CustomerCard.tsx` for each row display
  - Create `app/(dashboard)/customers/page.tsx` list page
  - Implement real-time search by name, email, phone
  - Implement pagination with configurable page size
  - Handle empty state with "Create Your First Customer" CTA
  - Handle "No customers found" search result state
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

- [x] 14. Build Customer create/edit forms
  - Create `CustomerForm.tsx` component with all fields
  - Implement form validation (required: name, email; optional: phone, address, etc.)
  - Implement inline validation error display
  - Handle duplicate email error message
  - Create `app/(dashboard)/customers/new/page.tsx` create page
  - Create `app/(dashboard)/customers/[id]/edit/page.tsx` edit page
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [x] 15. Build Customer detail page
  - Create `CustomerDetail.tsx` component
  - Display all customer profile information
  - Display total receivable amount
  - Display list of customer invoices with status badges
  - Implement Edit and Delete action buttons
  - Create delete confirmation modal with invoice warning
  - Create `app/(dashboard)/customers/[id]/page.tsx` detail page
  - Handle "No invoices yet" empty state with Create Invoice button
  - Write frontend tests for CustomerForm and CustomerList
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_

---

## Phase 8: Frontend - Invoice Module

- [x] 16. Build Invoice list page
  - Create `InvoiceList.tsx` component with table, search, and filters
  - Create `InvoiceStatusBadge.tsx` for status display with visual highlighting for overdue
  - Create `app/(dashboard)/invoices/page.tsx` list page
  - Implement search by invoice number and customer name
  - Implement filters for status, date range, and customer
  - Handle empty state with "Create Your First Invoice" CTA
  - Handle "No invoices found" filter result state
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_

- [x] 17. Build Invoice create/edit forms
  - Create `InvoiceLineItems.tsx` component for dynamic line item management
  - Create `InvoiceForm.tsx` component with all fields
  - Implement customer searchable dropdown
  - Implement auto-calculation of line amounts, subtotal, tax, and total
  - Implement form validation (customer required, dates, at least one line item)
  - Create `app/(dashboard)/invoices/new/page.tsx` create page
  - Create `app/(dashboard)/invoices/[id]/edit/page.tsx` edit page (draft only)
  - Implement "Save as Draft" and "Save and Send" buttons
  - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.9, 11.1, 11.2, 11.3, 11.4_

- [x] 18. Build Invoice detail page
  - Create `InvoiceDetail.tsx` component with invoice header, line items, totals
  - Create `SendInvoiceModal.tsx` for send/resend dialogs with editable email fields
  - Create payment confirmation modal for Mark as Paid action
  - Create cancellation modal for Cancel action with reason input
  - Display action buttons based on invoice status (Edit, Send, Delete, Mark as Paid, Resend, Send Reminder, Cancel)
  - Implement PDF download functionality
  - Create `app/(dashboard)/invoices/[id]/page.tsx` detail page
  - Write frontend tests for InvoiceForm and InvoiceList
  - _Requirements: 10.1, 10.2, 10.3, 12.1, 12.2, 12.4, 13.1, 13.2, 14.1, 14.2, 14.3, 14.4, 15.1, 15.2, 15.3, 15.4, 15.5, 16.3, 16.4, 16.5_

---

## Phase 9: Frontend - Dashboard

- [x] 19. Build Dashboard page
  - Create `SummaryCard.tsx` component for metric display with click navigation
  - Create `QuickActions.tsx` component with New Customer and New Invoice buttons
  - Create `RecentActivity.tsx` component for activity feed
  - Create `app/(dashboard)/page.tsx` dashboard page
  - Display Total Receivables, Total Customers, Invoices Due This Month cards
  - Handle empty state with onboarding message and quick action buttons
  - Implement card click navigation to corresponding list views
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 2.3_

---

## Phase 10: Responsive Design & Layout

- [ ] 20. Implement responsive layouts
  - Create `app/(dashboard)/layout.tsx` with sidebar navigation
  - Implement desktop layout (>1024px) with full sidebar
  - Implement tablet layout (768-1024px) with collapsible sidebar
  - Implement mobile layout (<768px) with hamburger menu or bottom navigation
  - Ensure all forms, tables, and modals are usable on all device sizes
  - Write E2E tests for responsive layouts
  - _Requirements: 18.1, 18.2, 18.3, 18.4_

---

## Phase 11: Testing & QA

- [ ] 21. Complete automated test suites
  - Run and verify all backend unit tests pass
  - Run and verify all backend feature tests pass
  - Run and verify all frontend component tests pass
  - Create E2E test for customer CRUD lifecycle
  - Create E2E test for invoice CRUD lifecycle
  - Create E2E test for dashboard navigation and quick actions
  - _Requirements: NFR-1, NFR-2_

- [ ] 22. Manual verification and polish
  - Verify dashboard performance (<2 seconds load)
  - Verify pagination works with 25 records per page
  - Verify PDF generation completes within 5 seconds
  - Verify all user actions are logged
  - Verify duplicate email detection works
  - Verify invoice number sequential generation
  - Verify soft-delete cascade for customers with invoices
  - Verify overdue status job runs correctly
  - Final responsive design testing on all breakpoints
  - _Requirements: NFR-1, NFR-2, NFR-3_
