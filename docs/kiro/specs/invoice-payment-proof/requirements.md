# Requirements: Invoice Payment Proof

## Introduction
The Invoice Payment Proof feature enhances the invoice management process by allowing users to attach a proof of payment document when marking an invoice as paid. This document is then accessible from the invoice detail view, providing a clear audit trail and easy verification of received payments. It also updates the existing UI to change "View Receipt" to "View Proof of payment" and ensures the button is disabled when no document is present.

## Requirements

### Requirement 1: Upload Proof of Payment
**User Story:** As a Finance User, I want to optionally upload a proof of payment when marking an invoice as paid, so that I can keep a record of the transaction.

**Acceptance Criteria:**
1. WHEN the user initiates the "Mark as Paid" action for an invoice, THEN the system SHALL display an option to upload a proof of payment document.
2. IF the user chooses to upload a document, THEN the system SHALL accept valid file formats (e.g., PDF, JPG, PNG).
3. IF the user submits the "Mark as Paid" form with a valid document, THEN the system SHALL save the document and link it to the invoice.
4. IF the user submits the "Mark as Paid" form without a document, THEN the system SHALL process the status change successfully, as the upload is optional.

### Requirement 2: View Proof of Payment in Invoice Detail
**User Story:** As a Finance User, I want to see a "View Proof of payment" button in the invoice detail view, so that I can access the uploaded payment proof.

**Acceptance Criteria:**
1. WHERE the invoice detail view previously displayed a "View Receipt" button, THEN the system SHALL display a "View Proof of payment" button instead.
2. WHEN the user clicks the "View Proof of payment" button on an invoice that has an uploaded document, THEN the system SHALL open or download the corresponding proof of payment document.

### Requirement 3: Disable Button When No Document Exists
**User Story:** As a Finance User, I want the "View Proof of payment" button to be disabled if no document is uploaded, so that I immediately know there is no proof of payment available.

**Acceptance Criteria:**
1. WHEN the system loads the invoice detail view for an invoice, IF there is no proof of payment document uploaded for that invoice, THEN the system SHALL render the "View Proof of payment" button in a disabled (unclickable) state.
2. IF the "View Proof of payment" button is disabled, THEN the system SHALL visually indicate its disabled state (e.g., greyed out, not responsive to hover).
