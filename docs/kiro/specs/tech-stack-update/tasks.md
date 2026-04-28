# Implementation Plan

- [x] 1. Install Composer dependency and update configuration files
  - Run `composer require barryvdh/laravel-dompdf` in the `backend/` directory.
  - Update `config/database.php`: change the default `DB_CONNECTION` fallback from `sqlite` to `mysql`.
  - Update `config/database.php`: remove the entire `redis` configuration block.
  - Update `config/queue.php`: change `batching.database` fallback from `sqlite` to `mysql`.
  - Update `config/queue.php`: change `failed.database` fallback from `sqlite` to `mysql`.
  - Update `config/mail.php`: change the default `MAIL_MAILER` fallback from `log` to `smtp`.
  - Update `.env.example`: replace `DB_CONNECTION=sqlite` with MySQL configuration block (`DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
  - Update `.env.example`: remove all `REDIS_*` configuration variables.
  - Update `.env.example`: replace `log` mail configuration with Gmail SMTP configuration (`MAIL_MAILER=smtp`, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION=tls`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`) with instructional comments.
  - _Requirements: 1.1, 1.2, 1.3, 1.5, 1.6, 2.1, 2.2, 2.3, 2.4, 2.5, 5.1, 5.2, 5.4, 7.6_

- [x] 2. Create the `InvoicePdfService` and register the `@formatCurrency` Blade directive
  - Create `backend/app/Services/InvoicePdfService.php` with the `generate()` and `generateRaw()` methods.
  - Add the `formatCurrency()` static helper method on `InvoicePdfService` for currency formatting (JPY with ¥ and period separators, IDR with period separators, USD with comma separators).
  - Register the `@formatCurrency` Blade directive in `backend/app/Providers/AppServiceProvider.php` boot method.
  - _Requirements: 7.2, 7.3, 7.8_

- [x] 3. Create the invoice PDF Blade template
  - Create `backend/resources/views/pdf/invoice.blade.php` as a standalone HTML template.
  - Implement the layout to match `InvoicePrintView.tsx`: company header, invoice meta bar, customer/sender details, total summary box, line items table, grand total.
  - Use `@formatCurrency` directive for all currency amounts.
  - Use component toggle checks via `$components` array (e.g., `company_header`, `invoice_meta`, `customer_details`, `sender_details`, `total_summary_box`, `line_items`, `grand_total`).
  - Use locale-aware `$labels` for all text (invoice, to, description, qty, unit_price, price, total_sum, amount_of_payment, invoice_number).
  - _Requirements: 7.2, 7.3, 7.4, 7.7, 7.8_

- [x] 4. Create the `InvoiceEmail` Mailable and email Blade template
  - Create `backend/app/Mail/InvoiceEmail.php` Mailable class with `envelope()`, `content()`, and `attachments()` methods.
  - In `attachments()`, use `InvoicePdfService::generateRaw()` to generate and attach the PDF as `Invoice-{invoice_number}.pdf`.
  - Create `backend/resources/views/emails/invoice.blade.php` Markdown email template using `<x-mail::message>` component.
  - Include invoice number, date, optional due date, total, and optional custom message body in the email content.
  - _Requirements: 4.3, 7.1_

- [x] 5. Create the `SendInvoiceEmailJob` queued job
  - Create `backend/app/Jobs/SendInvoiceEmailJob.php` implementing `ShouldQueue` with the `Queueable` trait.
  - Set `$tries = 3` and `$backoff = [10, 60, 300]` for retry logic.
  - Accept `Invoice $invoice`, `string $recipientEmail`, `string $subject`, and `?string $messageBody` via the constructor.
  - Implement the `handle()` method to send `InvoiceEmail` via `Mail::to()`.
  - _Requirements: 3.1, 3.3, 4.1, 4.2, 4.5, 4.6_

- [x] 6. Wire job dispatch into `InvoiceService` and implement `downloadPdf`
  - Modify `backend/app/Services/InvoiceService.php` `send()` method: replace the TODO comment with `SendInvoiceEmailJob::dispatch(...)` passing `invoice`, `recipientEmail`, `subject`, and `messageBody`.
  - Modify `backend/app/Services/InvoiceService.php` `sendReminder()` method: replace the TODO comment with `SendInvoiceEmailJob::dispatch(...)` (reuse same job).
  - Modify `backend/app/Http/Controllers/InvoiceController.php` `downloadPdf()` method: replace the 501 TODO with actual PDF generation via `InvoicePdfService` and return `$pdf->download($filename)`.
  - _Requirements: 4.1, 4.4, 7.5_

- [x] 7. Update steering documentation (`tech.md`)
  - Update the architecture diagram: replace `SQLite / MySQL` with `MySQL` in the Database box; add a `Queue / Email` layer showing MySQL Jobs and SMTP Gmail.
  - Update the Backend Stack table: change Database from `SQLite / MySQL` to `MySQL`; add rows for Queue (`Laravel Jobs (MySQL database driver)`), Mail (`SMTP (Gmail)`), and PDF (`barryvdh/laravel-dompdf`).
  - Add queue worker commands to the Development Commands section (`php artisan queue:work`, `php artisan queue:retry all`).
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6_

- [x] 8. Write unit tests
  - Create `backend/tests/Unit/SendInvoiceEmailJobTest.php`: test job sends email via mailable, test retry config, test correct data passed to mailable.
  - Create `backend/tests/Unit/InvoiceEmailTest.php`: test correct subject, test rendered content contains invoice data, test message body inclusion, test null message body handling, test PDF attachment.
  - Create `backend/tests/Unit/InvoicePdfServiceTest.php`: test `generate()` returns PDF instance, test `generateRaw()` returns string starting with `%PDF`, test `formatCurrency` for IDR/JPY/USD, test customer template component loading, test fallback to default components.
  - _Requirements: 4.3, 4.5, 7.1, 7.2, 7.3, 7.8_

- [x] 9. Write integration/feature tests and run full test suite
  - Add test cases to `backend/tests/Feature/Http/Controllers/InvoiceControllerTest.php`: test send dispatches `SendInvoiceEmailJob`, test resend dispatches job, test send-reminder dispatches job, test `GET /api/invoices/{id}/pdf` returns PDF response (200 with `application/pdf` content-type).
  - Run `cd backend && php artisan test` to verify all existing and new tests pass.
  - _Requirements: 3.1, 3.2, 4.1, 4.2, 7.5_
