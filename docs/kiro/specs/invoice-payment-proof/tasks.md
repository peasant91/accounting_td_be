**Implementation Plan**

- [ ] 1. Set up the database and data access layer
  - Create database migration to add the `payment_proof_path` column to the `invoices` table.
  - Run the database migration.
  - Update `App\Models\Invoice` to add `payment_proof_path` to the `$fillable` array.
  - Update `App\Http\Resources\InvoiceResource` to include the computed `payment_proof_url`.
  - _Requirements: 1.3_

- [ ] 2. Implement core business logic and backend validation
  - Update `App\Http\Requests\Invoice\MarkAsPaidRequest` to add validation rules for `payment_proof` (nullable, mimes, max size).
  - Update `App\Services\InvoiceService`'s `markAsPaid` method to handle the file upload, save to public storage, and persist the path.
  - Write feature tests in `InvoiceControllerTest` for successful payment with and without file uploads.
  - Write unit tests for `MarkAsPaidRequest` to verify file validation rules.
  - _Requirements: 1.2, 1.3, 1.4_

- [ ] 3. Expose the functionality via the Frontend
  - Add `payment_proof_url` to the `Invoice` TypeScript interface.
  - Refactor `MarkAsPaidModal.tsx` to include an optional file input and submit data using `FormData`.
  - Update `InvoiceDetail.tsx` to replace the "View Receipt" button with "View Proof of payment".
  - Implement disabled state logic and styling for the "View Proof of payment" button when no document is uploaded.
  - Integrate click handler to open the `payment_proof_url` in a new tab when clicked.
  - _Requirements: 1.1, 2.1, 2.2, 3.1, 3.2_
