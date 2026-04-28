**Implementation Plan**

- [ ] 1. Update Database and Backend Models
  - Create a migration to add `currency` and `company_name` to `customers` table.
  - Create a migration to add `currency` to `invoices` table.
  - Run database migrations.
  - Update `Customer` model to include `currency` and `company_name` in `$fillable`.
  - Update `Invoice` model to include `currency` in `$fillable`.
  - _Requirements: 1.2, 1.3, 2.1, 3.2_

- [ ] 2. Implement Backend Logic and API
  - Update `CustomerRequest` validation to include `currency` (valid options) and `company_name`.
  - Update `InvoiceController.store` logic to copy `currency` from Customer to Invoice upon creation.
  - Update API responses to include new fields.
  - _Requirements: 1.2, 1.3, 2.1, 2.4_

- [ ] 3. Update Frontend Types and Utilities
  - Update `Customer` interface in `types/index.ts` to include `currency` and `company_name`.
  - Update `Invoice` interface in `types/index.ts` to include `currency`.
  - Update `formatCurrency` utility in `lib/utils` to accept a currency code argument.
  - _Requirements: 1.4, 3.1_

- [ ] 4. Update Customer Management UI
  - Update `CustomerForm` to add a Select input for `currency` (options: IDR, USD, etc.).
  - Update `CustomerForm` to add a Text input for `company_name`.
  - _Requirements: 1.1, 1.2, 2.1_

- [ ] 5. Update Invoice Display and Logic
  - Update `InvoiceDetail` component to pass `invoice.currency` to `formatCurrency`.
  - Update `InvoiceDetail` to display `company_name` in the "Bill To" section, falling back to `name`.
  - _Requirements: 1.4, 2.3, 2.4, 3.1_
