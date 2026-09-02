# PROJECT SPECIFICATION — AI-Assisted QRIS Payment Verification SaaS

## 1. ROLE

You are a senior software architect, backend engineer, frontend/mobile engineer, database designer, AI engineer, and security engineer.

Your task is to design and develop a production-oriented SaaS application for payment proof verification using QRIS-related payment workflows and AI-assisted OCR.

The system must use a **Human-in-the-Loop** approach.

AI is responsible for extracting and analyzing payment information from uploaded payment proof images, but **AI must never make the final payment validity decision**.

The final decision must always be made by an authorized human user/admin.

---

# 2. PRODUCT VISION

Build a multi-tenant SaaS platform called:

**AI Payment Verification**

The platform allows businesses to manage payment transactions and verify customer payment proofs.

The main problem being solved:

> Businesses often receive payment screenshots/images from customers and have to manually inspect the payment amount and transaction information.

The system should reduce manual work by:

1. Creating payment/invoice records.
2. Allowing customers to upload payment proof.
3. Using OCR/AI to extract payment information.
4. Comparing extracted information with expected transaction data.
5. Giving the admin an analysis/recommendation.
6. Allowing the admin to make the final VALID or INVALID decision.
7. Recording the verification history for auditing.

---

# 3. IMPORTANT BUSINESS RULE

The AI must NOT decide whether a payment is valid.

The correct workflow is:

Customer
→ Payment
→ Upload payment proof
→ AI/OCR extraction
→ System validation
→ Admin review
→ Admin decision
→ Final payment status

The AI can provide:

* extracted amount
* extracted date
* extracted time
* extracted bank/payment provider
* reference number
* OCR confidence
* detected information
* comparison result
* risk indicators

But the final status must be determined by the authorized user.

Example:

Expected amount:

Rp125,000

AI detects:

Rp125,000

AI confidence:

97%

System recommendation:

"Amount matches expected payment."

However, the system must still show:

[ VALID ]

[ INVALID ]

The administrator chooses the final result.

---

# 4. TARGET USERS

The application is multi-tenant.

There are at least three roles:

## Super Admin

Responsible for the SaaS platform.

Capabilities:

* manage businesses
* manage subscriptions
* view system statistics
* manage system settings
* manage AI configuration
* monitor system activity

## Business Owner

Responsible for one business.

Capabilities:

* manage business profile
* manage users/admins
* manage products/services
* create invoices
* manage payment transactions
* manage QRIS/payment configuration
* review payment proofs
* view reports
* view analytics

## Business Staff/Admin

Capabilities:

* view assigned transactions
* review payment proofs
* see AI extraction results
* approve/reject payments
* add verification notes

Staff must not have access to other businesses' data.

---

# 5. CORE FEATURES

## Authentication

Implement:

* registration
* login
* logout
* password reset
* email verification if appropriate
* role-based authorization
* business/tenant isolation

Use secure authentication practices.

---

# 6. MULTI-TENANCY

The application must support multiple businesses.

Every business-owned resource must be associated with a business_id/tenant_id.

Example:

users
businesses
invoices
payments
payment_proofs
verification_logs

must maintain tenant isolation.

A user from Business A must NEVER be able to access:

* Business B invoices
* Business B payments
* Business B payment proofs
* Business B users
* Business B reports

Implement tenant authorization at the backend level.

Do not rely only on frontend restrictions.

---

# 7. PAYMENT WORKFLOW

The primary payment lifecycle should be:

PENDING
→ WAITING_PAYMENT
→ PROOF_UPLOADED
→ AI_PROCESSING
→ WAITING_VERIFICATION
→ VERIFIED
or
→ REJECTED

Optional state:

AI_PROCESSING_FAILED

The system must preserve status history.

---

# 8. INVOICE

Business users can create invoices.

Invoice example:

Invoice Number:
INV-20260902-0001

Customer:
John Doe

Description:
Product A

Expected Amount:
Rp125,000

Payment Status:
WAITING_PAYMENT

Each invoice can have one or more payment attempts if the business logic requires it.

---

# 9. PAYMENT PROOF

Customers can upload payment proof images.

Supported formats should be determined based on implementation, but initially support common image formats such as:

* JPG/JPEG
* PNG
* WEBP

The backend must:

* validate file type
* validate file size
* securely store the file
* generate unique filenames
* prevent executable uploads
* avoid exposing internal storage paths directly
* optionally resize/compress images
* preserve the original file where necessary

---

# 10. AI/OCR PROCESSING

When a payment proof is uploaded:

1. Save the proof.
2. Create an AI processing job.
3. Process the image.
4. Extract payment information.
5. Store structured extraction results.
6. Calculate confidence.
7. Compare extracted information with the invoice/payment.
8. Present the result to the administrator.

The AI should attempt to extract:

```json
{
  "amount": 125000,
  "currency": "IDR",
  "transaction_date": "2026-09-02",
  "transaction_time": "14:32",
  "payment_provider": "BCA",
  "reference_number": "ABC123",
  "merchant_name": "Example Store",
  "transaction_status": "SUCCESS",
  "confidence": 0.97
}
```

The actual schema may be adjusted during implementation.

---

# 11. OCR EXTRACTION

Separate OCR/extraction from business validation.

Architecture:

Payment Proof
→ Image Processing
→ OCR
→ AI Information Extraction
→ Structured Data
→ Validation Engine

Do not mix all logic into one function.

The system must be modular so that the OCR/AI provider can be replaced later.

For example:

```text
OCRService
AIExtractionService
PaymentValidationService
RiskAnalysisService
```

Use interfaces/abstractions where appropriate.

---

# 12. PAYMENT VALIDATION ENGINE

The validation engine compares:

Expected transaction information

against

AI-extracted information.

At minimum compare:

* expected amount vs detected amount
* expected currency vs detected currency
* transaction date
* merchant/business name if available
* reference number if available

Example:

Expected:

Rp125,000

Detected:

Rp125,000

Result:

AMOUNT_MATCH = true

If:

Expected:

Rp125,000

Detected:

Rp100,000

Result:

AMOUNT_MATCH = false

The system should NOT automatically reject the payment solely because the AI detects a mismatch.

Instead:

```text
AI/System Finding:
⚠ Amount does not match.

Admin Decision:
[ VALID ]
[ INVALID ]
```

---

# 13. CONFIDENCE SCORE

The AI extraction should expose confidence information.

Example:

```text
Amount:
Rp125,000

Confidence:
97%
```

For low confidence:

```text
Amount:
Rp125,000

Confidence:
58%

Warning:
The payment proof may be unclear.
Manual verification is strongly recommended.
```

The confidence score must be clearly distinguished from payment validity.

Important:

AI confidence ≠ payment validity.

---

# 14. RISK INDICATORS

Create a separate risk-analysis layer.

Potential indicators:

* amount mismatch
* unreadable image
* missing transaction information
* suspicious duplicate proof
* old transaction date
* unusual transaction amount
* inconsistent merchant name
* repeated reference number
* previously used payment proof

Do not label a payment as fraudulent solely from AI analysis.

Instead use:

```text
Risk Level:
LOW
MEDIUM
HIGH
```

and explain the reasons.

Final decision remains with the authorized admin.

---

# 15. DUPLICATE PAYMENT PROOF

The system should eventually detect whether the same payment proof has already been uploaded.

Possible mechanisms:

* file hash
* perceptual hash
* reference number comparison
* transaction information comparison

Example:

```text
Warning:

This payment proof appears similar to
Payment #INV-00121.

Please review manually.
```

Do not automatically reject unless explicitly configured by the business.

---

# 16. ADMIN VERIFICATION SCREEN

Create a dedicated payment verification interface.

Display:

### Invoice Information

* invoice number
* customer
* expected amount
* invoice date

### Uploaded Proof

Display the payment proof image.

### AI Extraction

```text
Detected Amount: Rp125,000
Detected Date: 02 Sep 2026
Detected Time: 14:32
Provider: BCA
Reference: ABC123
Confidence: 97%
```

### Validation

```text
Expected Amount: Rp125,000
Detected Amount: Rp125,000

✓ Amount matches
```

### Risk

```text
Risk: LOW
```

### Final Decision

```text
[ VALID ]
[ INVALID ]
```

If INVALID is selected, require a reason.

If VALID is selected, optionally allow a verification note.

---

# 17. AUDIT LOG

Every important action must be recorded.

Examples:

* invoice created
* payment created
* proof uploaded
* AI processing started
* AI processing completed
* AI processing failed
* admin opened proof
* payment approved
* payment rejected
* payment status changed

Audit log should contain:

* user
* business
* action
* target/resource
* timestamp
* optional metadata

Example:

```text
Admin John verified payment INV-00123
Decision: VALID
Time: 2026-09-02 15:42
```

Audit logs should not be easily editable or deleted by normal business users.

---

# 18. NOTIFICATION SYSTEM

The system should support notifications for important events.

Examples:

When proof is uploaded:

"Payment proof for INV-00123 requires verification."

When payment is approved:

"Payment INV-00123 has been verified."

When payment is rejected:

"Payment INV-00123 was rejected."

Design the notification system so that additional channels can be added later:

* in-app
* email
* WhatsApp
* webhook

Do not tightly couple notification logic with payment logic.

---

# 19. QRIS INTEGRATION

QRIS integration must be designed as a replaceable payment-provider layer.

Do NOT build a payment gateway from scratch.

Instead create an abstraction such as:

```text
PaymentProviderInterface
```

Possible responsibilities:

* create payment
* generate QR/payment information
* check payment status
* process webhook
* retrieve transaction details

The actual provider must be configurable.

The architecture should allow replacing Provider A with Provider B without rewriting the entire application.

---

# 20. WEBHOOK

If the selected payment provider supports webhooks, implement:

```text
Payment Provider
      ↓
Webhook
      ↓
Laravel API
      ↓
Verify Signature
      ↓
Find Payment
      ↓
Update Payment Status
      ↓
Create Audit Log
      ↓
Notification
```

Webhook security is critical.

Always verify webhook authenticity/signatures where supported.

Never trust arbitrary webhook requests.

---

# 21. RECONCILIATION

The system should eventually support reconciliation between:

1. Expected payment
2. AI-extracted proof
3. Actual payment provider transaction

Example:

```text
Expected:
Rp125,000

Proof:
Rp125,000

Provider:
Rp125,000

Result:
ALL MATCH
```

If:

```text
Expected:
Rp125,000

Proof:
Rp125,000

Provider:
No transaction found
```

Display:

```text
⚠ PAYMENT PROVIDER DATA NOT FOUND

Manual verification required.
```

This is stronger than relying solely on screenshots.

---

# 22. DASHBOARD

Business dashboard should display:

* total transactions
* pending verification
* verified payments
* rejected payments
* total payment amount
* today's transactions
* monthly transactions
* verification rate
* AI processing statistics
* suspicious/risky payments

Example:

```text
Today's Transactions
--------------------
Total: 128

Pending: 12
Verified: 109
Rejected: 7
```

Add charts where useful.

---

# 23. REPORTING

Business owners should be able to view:

* daily transactions
* weekly transactions
* monthly transactions
* verified vs rejected
* total revenue
* payment provider distribution
* AI processing statistics
* manual verification statistics

Allow filtering by:

* date range
* payment status
* payment provider
* staff/admin
* amount

---

# 24. TECHNOLOGY STACK

Use the following preferred architecture unless there is a strong technical reason to change it.

Backend:

* Laravel
* REST API
* MySQL or PostgreSQL
* Laravel Queue
* Laravel Scheduler where needed
* Laravel Policies/Gates
* Laravel validation
* Laravel filesystem/storage

Frontend/Mobile:

* Flutter

Web admin dashboard can use a suitable modern frontend if required, but keep the API architecture clean.

AI:

* OCR provider
* LLM/AI provider
* modular AI service layer

Infrastructure:

* object/file storage
* queue worker
* database
* cache if needed

---

# 25. API DESIGN

Design RESTful APIs.

Example:

```text
POST   /api/auth/login
POST   /api/auth/logout

GET    /api/business
PUT    /api/business

GET    /api/invoices
POST   /api/invoices
GET    /api/invoices/{id}

GET    /api/payments
POST   /api/payments
GET    /api/payments/{id}

POST   /api/payments/{id}/proof
GET    /api/payments/{id}/proof

GET    /api/payments/{id}/analysis
POST   /api/payments/{id}/verify

POST   /api/webhooks/{provider}
```

Adjust endpoint naming when necessary.

Use consistent API response structures.

---

# 26. SECURITY REQUIREMENTS

Security is a priority.

Implement:

* authentication
* authorization
* tenant isolation
* request validation
* rate limiting
* secure file uploads
* file type validation
* file size limits
* secure storage
* signed URLs where appropriate
* webhook signature validation
* audit logging
* protection against IDOR
* protection against mass assignment
* protection against SQL injection
* CSRF protection where applicable
* secure password handling
* secrets in environment variables
* no API keys hardcoded in source code

Never expose sensitive credentials.

---

# 27. PRIVACY

Payment proof images may contain sensitive information.

Therefore:

* do not expose payment proof publicly
* restrict access based on business/user authorization
* use private storage
* avoid logging sensitive payment information unnecessarily
* avoid storing unnecessary personal information
* provide a mechanism to delete payment proofs according to business/data-retention policy

---

# 28. AI FAILURE HANDLING

AI/OCR can fail.

Possible states:

```text
AI_PROCESSING
AI_COMPLETED
AI_FAILED
```

If AI fails:

```text
AI processing failed.

Please verify the payment manually.
```

The payment must NOT automatically become invalid.

The admin should still be able to review the uploaded proof manually.

---

# 29. ASYNCHRONOUS PROCESSING

AI processing should preferably use background jobs.

Example:

```text
Upload proof
     ↓
Return response immediately
     ↓
Queue AI job
     ↓
OCR
     ↓
AI extraction
     ↓
Validation
     ↓
Update database
     ↓
Notify admin
```

Do not block the HTTP request for expensive AI processing when avoidable.

Use Laravel Queue/Jobs.

---

# 30. DATABASE DESIGN

Design normalized database tables.

At minimum consider:

```text
users
businesses
business_user
roles
invoices
invoice_items
payments
payment_proofs
payment_extractions
payment_validation_results
payment_risk_assessments
payment_verifications
payment_provider_transactions
webhook_events
notifications
audit_logs
subscriptions
```

Do not create unnecessary tables.

Use proper:

* primary keys
* foreign keys
* indexes
* unique constraints
* timestamps
* soft deletes where appropriate

Pay particular attention to indexes for:

* business_id
* payment status
* invoice number
* reference number
* created_at

---

# 31. DEVELOPMENT APPROACH

Do NOT implement the entire system blindly in one step.

Work incrementally.

Before implementing each major phase:

1. Explain the plan.
2. Identify affected files.
3. Identify database changes.
4. Implement.
5. Run tests.
6. Fix errors.
7. Verify functionality.
8. Only then continue.

Do not overwrite existing code unnecessarily.

Reuse existing components when appropriate.

Follow the existing project conventions.

---

# 32. DEVELOPMENT PHASES

## PHASE 1 — Project Foundation

Implement:

* project structure
* environment configuration
* authentication
* users
* businesses
* roles
* tenant isolation
* database migrations
* seeders
* basic API structure

Acceptance criteria:

* user can register/login
* user belongs to a business
* Business A cannot access Business B data

---

## PHASE 2 — Invoice & Payment

Implement:

* products/services if needed
* invoices
* payment records
* payment status
* payment history

Acceptance criteria:

Business owner can create an invoice and payment record.

---

## PHASE 3 — Payment Proof Upload

Implement:

* upload payment proof
* private storage
* validation
* secure file naming
* proof preview
* payment-proof relationship

Acceptance criteria:

Admin can upload/view a payment proof securely.

---

## PHASE 4 — OCR/AI Extraction

Implement:

* OCR service abstraction
* AI extraction service
* queue job
* extraction result storage
* confidence score
* AI failure handling

Acceptance criteria:

Uploaded proof can produce structured information such as:

```text
amount
date
time
provider
reference
status
confidence
```

---

## PHASE 5 — Validation Engine

Implement:

* expected vs detected amount
* date comparison
* provider comparison
* reference comparison
* validation findings
* risk indicators

Acceptance criteria:

System clearly shows matching/mismatching information.

AI must not determine the final payment status.

---

## PHASE 6 — Human Verification

Implement:

* verification screen
* VALID button
* INVALID button
* rejection reason
* verification notes
* verification timestamp
* verified_by
* audit log

Acceptance criteria:

Only authorized users can finalize payment validity.

---

## PHASE 7 — QRIS / Payment Provider

Implement provider abstraction.

Do not hardcode provider-specific logic throughout the application.

Implement:

* create payment
* QRIS/payment information
* transaction status
* webhook
* signature validation
* provider transaction storage

The exact provider should be selected before implementing provider-specific API code.

---

## PHASE 8 — Reconciliation

Implement:

Expected payment
+
AI proof information
+
Provider transaction

Comparison.

Provide a clear reconciliation result.

---

## PHASE 9 — Dashboard & Analytics

Implement:

* transaction dashboard
* revenue dashboard
* verification statistics
* rejection statistics
* AI statistics
* risk statistics
* charts
* filters

---

## PHASE 10 — SaaS Features

Implement:

* subscription plans
* usage limits
* AI usage tracking
* transaction limits
* business billing
* feature flags

Example:

```text
FREE
50 verifications/month

STARTER
500 verifications/month

BUSINESS
2,000 verifications/month

PRO
10,000 verifications/month
```

These values are placeholders and must remain configurable.

---

# 33. TESTING

Create automated tests for critical functionality.

At minimum test:

### Authentication

* login
* logout
* authorization

### Tenant Isolation

* Business A cannot access Business B resources

### Payment

* create payment
* update status
* retrieve payment

### Proof

* upload valid image
* reject invalid file
* authorization for proof access

### AI

* successful extraction
* failed extraction
* low confidence extraction

### Validation

* matching amount
* mismatching amount

### Verification

* authorized admin can approve
* unauthorized user cannot approve
* rejection requires reason

### Webhook

* valid signature
* invalid signature
* duplicate webhook

---

# 34. IMPORTANT ARCHITECTURAL PRINCIPLES

Follow these principles:

1. Separation of concerns.
2. SOLID principles where appropriate.
3. Keep business logic out of controllers.
4. Use service classes for complex business logic.
5. Use Form Requests for validation.
6. Use Policies for authorization.
7. Use Jobs for long-running AI operations.
8. Use Events/Listeners where useful.
9. Keep payment-provider logic isolated.
10. Keep AI-provider logic isolated.
11. Keep tenant isolation enforced server-side.
12. Avoid premature overengineering.
13. Prefer maintainable code over clever code.
14. Document important architectural decisions.

---

# 35. UI/UX PRINCIPLES

The interface should be simple enough for non-technical business owners.

Prioritize:

* clear payment status
* clear AI findings
* clear confidence level
* clear expected vs detected amount
* obvious VALID / INVALID actions
* warning messages
* responsive design
* loading states
* error states
* empty states

Do not overwhelm users with raw AI output.

Translate technical AI results into understandable business information.

---

# 36. IMPORTANT DISTINCTION

Always distinguish these three concepts:

### AI Extraction Confidence

"How confident is the AI that it read the image correctly?"

### Validation Result

"Does the extracted information match the expected transaction?"

### Final Payment Decision

"Has an authorized human confirmed this payment?"

Example:

```text
AI Confidence:
97%

Amount Match:
YES

Final Payment Decision:
WAITING FOR ADMIN
```

This is the correct state.

NOT:

```text
AI Confidence:
97%

Payment:
VALID
```

---

# 37. FUTURE FEATURES

Do not implement these unless the current architecture requires preparation for them.

Possible future features:

* WhatsApp notifications
* email notifications
* automated invoice reminders
* AI business assistant
* advanced fraud detection
* image manipulation detection
* machine learning fraud scoring
* accounting integration
* e-commerce integration
* POS integration
* marketplace integration
* mobile push notification
* public payment page
* API for third-party businesses

The architecture should remain extensible for these features.

---

# 38. PRODUCT POSITIONING

The product should be positioned as:

**AI-Assisted Payment Verification SaaS**

NOT:

"AI that guarantees payment authenticity."

The product assists businesses in reviewing payment proofs and reconciling payment information.

Human verification remains the final authority unless a future business configuration explicitly enables trusted automated workflows.

---

# 39. FIRST TASK

Before writing implementation code:

1. Inspect the existing project structure.
2. Identify the current framework and versions.
3. Identify existing dependencies.
4. Identify existing database configuration.
5. Identify existing authentication implementation.
6. Identify existing UI structure.
7. Identify reusable components.
8. Identify potential conflicts with this specification.

Then provide:

### A. Current Project Analysis

### B. Recommended Architecture

### C. Database ERD proposal

### D. API architecture

### E. Folder/module structure

### F. Development roadmap

### G. Potential technical risks

### H. Security risks

### I. AI/OCR integration strategy

### J. Questions that must be answered before implementation

Do NOT start implementing the entire project immediately.

First complete the analysis and architecture proposal.

After the architecture is approved, implement the project phase-by-phase.

---

# 40. FINAL RULE

Always prioritize:

**Security > Data Integrity > Maintainability > Reliability > User Experience > Development Speed**

Never sacrifice payment/data integrity merely to make the implementation easier.

The system must always preserve a clear distinction between:

**what the customer claims they paid,**

**what AI/OCR extracted from the proof,**

**what the payment provider reports,**

and

**what the human administrator finally verified.**
