# Requirements: Tech Stack Update — Redis to Laravel Jobs (MySQL) & SMTP Gmail Email

## Introduction

The Accounting Timedoor application currently uses SQLite as its database (`DB_CONNECTION=sqlite`), has a placeholder TODO comment for an email dispatch job (`SendInvoiceEmailJob`) in `InvoiceService.php`, and the mail driver is set to `log` mode. Redis configuration exists in `.env` but is unused — the queue connection already uses the `database` driver.

This feature aims to:

1. **Switch the database from SQLite to MySQL** as the canonical and only database engine.
2. **Remove Redis references** from `.env` and confirm the MySQL-backed `database` queue driver is the canonical queue backend.
3. **Implement invoice email sending** using Gmail SMTP so invoices are delivered to customer inboxes.
4. **Create a Laravel Job** to dispatch invoice emails asynchronously via the MySQL-backed queue.
5. **Create a Laravel Mailable class** for the invoice email.
6. **Update the steering document** (`tech.md`) to reflect the updated tech stack (MySQL, no Redis, SMTP Gmail).

---

## Requirements

### Requirement 1 — Switch Database to MySQL

**As an** admin, **I want** the application to use MySQL as the database engine, **so that** the system uses a production-grade relational database suitable for queue jobs, caching, and application data.

**Acceptance Criteria:**

1. WHEN the `.env` file is reviewed, THEN `DB_CONNECTION` SHALL be set to `mysql`.
2. WHEN the `.env` file is reviewed, THEN `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` SHALL be configured with valid MySQL connection details.
3. WHEN the `.env.example` file is reviewed, THEN it SHALL contain MySQL configuration as the default with placeholder values.
4. WHEN `php artisan migrate` is run, THEN all existing migrations SHALL execute successfully against the MySQL database.
5. WHEN the queue config (`config/queue.php`) references the database, THEN it SHALL use the MySQL connection.
6. WHEN the cache config references the database, THEN it SHALL use the MySQL connection.

---

### Requirement 2 — Remove Redis Dependency

**As an** admin, **I want** the application to not depend on Redis, **so that** the deployment stack is simpler and only requires PHP, MySQL/SQLite, and an SMTP server.

**Acceptance Criteria:**

1. WHEN the application starts, THEN it SHALL NOT require a Redis connection to function correctly.
2. WHEN the `.env` file is reviewed, THEN it SHALL NOT contain any `REDIS_*` configuration variables.
3. WHEN the `.env.example` file is reviewed, THEN it SHALL NOT contain any `REDIS_*` configuration variables.
4. WHEN the `QUEUE_CONNECTION` environment variable is inspected, THEN its value SHALL be `database`.
5. WHEN the `CACHE_STORE` environment variable is inspected, THEN its value SHALL be `database`.

---

### Requirement 3 — Laravel Jobs with MySQL Queue

**As a** developer, **I want** background jobs to be processed using Laravel's database queue driver backed by MySQL, **so that** invoice emails are sent asynchronously without blocking the HTTP request.

**Acceptance Criteria:**

1. WHEN a job is dispatched, THEN it SHALL be persisted to the `jobs` table in the database.
2. WHEN the queue worker is started via `php artisan queue:work`, THEN it SHALL process jobs from the `jobs` table.
3. IF a job fails, THEN it SHALL be recorded in the `failed_jobs` table with error details.
4. WHEN the `jobs` migration is inspected, THEN it SHALL already exist (Laravel default) or be created if missing.
5. WHEN the `failed_jobs` migration is inspected, THEN it SHALL already exist (Laravel default) or be created if missing.

---

### Requirement 4 — Send Invoice Email via SMTP Gmail

**As a** finance user, **I want** to send an invoice to a customer via email when I click "Send", **so that** the customer receives the invoice in their inbox.

**Acceptance Criteria:**

1. WHEN the finance user triggers the "Send Invoice" action, THEN the system SHALL dispatch a `SendInvoiceEmailJob` to the queue.
2. WHEN the `SendInvoiceEmailJob` is processed, THEN the system SHALL send an email via Gmail SMTP to the `recipient_email` specified in the request.
3. WHEN the email is sent, THEN it SHALL contain:
   - The subject line provided by the user.
   - The message body provided by the user.
   - The invoice PDF as an attachment (IF PDF generation is available).
4. WHEN the email is sent successfully, THEN the invoice activity log SHALL record the event with `invoice_sent` type.
5. IF the email fails to send, THEN the job SHALL retry up to 3 times before being marked as failed.
6. IF the email fails permanently, THEN the failure SHALL be logged in the `failed_jobs` table.
7. WHEN the `.env` file is configured, THEN it SHALL contain valid Gmail SMTP settings:
   - `MAIL_MAILER=smtp`
   - `MAIL_HOST=smtp.gmail.com`
   - `MAIL_PORT=587`
   - `MAIL_USERNAME=<gmail address>`
   - `MAIL_PASSWORD=<gmail app password>`
   - `MAIL_ENCRYPTION=tls`
   - `MAIL_FROM_ADDRESS=<gmail address>`
   - `MAIL_FROM_NAME=<application name>`

---

### Requirement 5 — Gmail SMTP Configuration

**As an** admin, **I want** clear documentation on how to configure Gmail SMTP, **so that** I can set up email sending correctly in any environment.

**Acceptance Criteria:**

1. WHEN the `.env.example` file is reviewed, THEN it SHALL contain placeholder Gmail SMTP configuration with comments explaining each field.
2. WHEN a developer sets up the application, THEN the `MAIL_MAILER` SHALL default to `smtp` in `.env.example`.
3. WHEN Gmail "Less Secure Apps" is disabled (Google default), THEN the documentation SHALL instruct the admin to use a Gmail App Password.
4. IF the `MAIL_SCHEME` is set to `null` and `MAIL_PORT` is `587`, THEN Laravel SHALL automatically use STARTTLS encryption.

---

### Requirement 6 — Update Steering Documentation

**As a** developer, **I want** the `tech.md` steering document to reflect the updated tech stack, **so that** future development aligns with the current architecture.

**Acceptance Criteria:**

1. WHEN the `tech.md` file is reviewed, THEN the database SHALL be listed as "MySQL" (not "SQLite / MySQL").
2. WHEN the `tech.md` file is reviewed, THEN it SHALL list the queue backend as "Laravel Jobs (MySQL `database` driver)".
3. WHEN the `tech.md` file is reviewed, THEN it SHALL list the mail transport as "SMTP (Gmail)".
4. WHEN the `tech.md` file is reviewed, THEN it SHALL NOT mention Redis as part of the active tech stack.
5. WHEN the architecture diagram in `tech.md` is reviewed, THEN it SHALL show "MySQL" as the database and include a "Queue / Email" layer showing MySQL Jobs and SMTP Gmail.
6. WHEN the Backend Stack table in `tech.md` is reviewed, THEN the Database row SHALL show "MySQL" only.

---

### Requirement 7 — Invoice PDF Generation & Email Attachment

**As a** finance user, **I want** invoice emails to include a PDF attachment of the invoice, **so that** customers receive a professional, printable document they can use for their records.

**Acceptance Criteria:**

1. WHEN the `SendInvoiceEmailJob` is processed, THEN the email SHALL include the invoice as a PDF attachment named `Invoice-{invoice_number}.pdf`.
2. WHEN the PDF is generated, THEN it SHALL render the invoice using the customer's invoice template components (as defined in `InvoiceTemplate` model and `config/invoice.php`).
3. WHEN the PDF is generated, THEN it SHALL use the locale labels matching the customer's currency (IDR → Indonesian, USD → English, JPY → Japanese) as configured in `config/invoice.php`'s `currency_locale_map`.
4. WHEN the PDF layout is inspected, THEN it SHALL match the visual structure of the existing `InvoicePrintView.tsx` frontend component (company header, customer/sender details, total summary box, line items table, bank transfer info, grand total).
5. WHEN the `GET /api/invoices/{invoice}/pdf` endpoint is called, THEN it SHALL return the generated PDF as a downloadable file with `Content-Type: application/pdf`.
6. WHEN the `barryvdh/laravel-dompdf` package is inspected in `composer.json`, THEN it SHALL be listed as a project dependency.
7. WHEN the invoice PDF Blade template is inspected, THEN it SHALL exist at `resources/views/pdf/invoice.blade.php`.
8. WHEN the PDF is generated for an invoice with line items, THEN the PDF SHALL correctly render all line items with description, quantity, unit price, and amount formatted according to the invoice's currency.
