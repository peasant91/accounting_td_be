**Requirements: Customer Currency & Company Details**

**Introduction**
This feature adds the ability for customers to select a preferred currency and specify a company name. These fields will directly influence how invoices are generated and displayed, allowing for multi-currency invoicing and B2B addressing.

**Requirements**

**Requirement 1**
**User Story:** As a Finance User, I want to assign a currency to a customer, so that their invoices are generated in the correct currency.

**Acceptance Criteria**
1. WHEN creating or editing a customer, THEN the system SHALL provide a currency selection dropdown (e.g., USD, IDR).
2. IF no currency is selected, THEN the system SHALL default to the system's base currency (e.g., IDR).
3. WHEN an invoice is created for a customer, THEN the invoice currency SHALL automatically match the customer's defined currency.
4. WHERE multiple currencies are used, THEN the invoice totals and line items SHALL use the customer's currency symbol or code.

**Requirement 2**
**User Story:** As a Finance User, I want to add a company name to a customer profile, so that it appears on their invoices.

**Acceptance Criteria**
1. WHEN creating or editing a customer, THEN the system SHALL allow entering an optional "Company Name".
2. IF a "Company Name" is provided, THEN it SHALL be displayed on the Customer List and Detail views.
3. WHEN generating an invoice PDF or view, IF the customer has a "Company Name", THEN the system SHALL display the Company Name prominently in the "Bill To" section.
4. IF no "Company Name" is present, THEN the system SHALL fallback to displaying the customer's personal Name.

**Requirement 3**
**User Story:** As a Finance User, I want invoices to reflect the customer's currency and company details, so that invoices are accurate and professional.

**Acceptance Criteria**
1. WHEN viewing an invoice, THEN all monetary values (Subtotal, Tax, Total) SHALL be formatted according to the customer's assigned currency locale/symbol.
2. IF the invoice is in a non-default currency, THEN the system SHALL store the currency code with the invoice.
