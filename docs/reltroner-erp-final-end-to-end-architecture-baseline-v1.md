---
title: "Reltroner ERP — Final End-to-End Architecture Baseline"
description: "The locked production architecture for Reltroner ERP v1, covering product boundaries, repositories, identity, authorization, tenancy, deployment, data, security, operations, and future evolution."
project: "Reltroner ERP"
architecture_version: "1.0"
status: "locked"
date: "2026-08-05"
owner: "Reltroner Studio"
---

# Reltroner ERP — Final End-to-End Architecture Baseline

## Document Status

```text
Architecture baseline: LOCKED
Architecture decision completion: 100%
Product implementation completion: tracked separately
```

This document defines the final architecture baseline for **Reltroner ERP v1 Production-Ready Release** before further engineering execution begins.

The architecture is intentionally designed around the infrastructure currently available to the project:

- Cloudflare Workers Free
- Cloudflare DNS and edge services
- Hostinger shared web hosting
- Hostinger MariaDB/MySQL
- An externally managed Keycloak deployment
- Separate GitHub repositories for the tenant frontend, platform administration frontend, and backend API

This architecture is production-oriented within the project's current financial and infrastructure constraints. It does not introduce microservices, Kubernetes, Docker-based production infrastructure, self-hosted Redis, or self-hosted message brokers at this stage.

Any future architectural change must be introduced through a documented Architecture Decision Record and justified by production evidence, customer requirements, security requirements, or infrastructure limitations.

---

# 1. Product Identity and Objective

## 1.1 Product Name

**Reltroner ERP**

## 1.2 Canonical Product Positioning

**Reltroner ERP: Production-Ready Business Operations SaaS for Growing SMEs**

## 1.3 Product Purpose

Reltroner ERP is a multi-tenant business operations SaaS designed to help growing organizations integrate and control:

- Organization and tenant administration
- Users, employees, roles, and permissions
- Procurement
- Suppliers
- Products
- Inventory
- Warehouse operations
- Sales
- Finance and accounting
- Approvals
- Audit trails
- Usage metering
- Subscription entitlements
- Operational reports
- Platform administration

The current priority is to build a real, deployable, supportable, and monetizable product.

Formal academic research is not part of the current implementation workflow. Academic evaluation may be added only after the product-owned v1 production scope has reached 100%.

---

# 2. Core Architecture Decision

## 2.1 Selected Architecture

Reltroner ERP uses:

> **A modular-monolith backend with two independently deployed frontend applications and one shared relational database.**

The final architecture is:

```text
Tenant ERP Frontend
        +
Platform Admin Frontend
        +
Laravel Modular-Monolith Backend
        +
Shared Multi-Tenant MariaDB/MySQL Database
        +
Externally Managed Keycloak Identity Provider
```

## 2.2 Rejected Architecture for v1

Reltroner ERP v1 will not use a microservices architecture.

Microservices are rejected for the current phase because they would introduce:

- Distributed transaction complexity
- Service-to-service authentication
- Eventual consistency across critical ERP workflows
- Message-broker operational requirements
- Distributed tracing requirements
- Additional deployments
- Higher infrastructure cost
- Increased failure modes
- Higher maintenance burden without current production evidence

ERP workflows such as procurement, inventory posting, accounts payable, payment, and journal posting require strong transactional integrity. These workflows are safer and easier to operate inside one backend application and one relational transaction boundary.

## 2.3 Modular Monolith Rule

A modular monolith does not mean an unstructured application.

Reltroner ERP must preserve explicit domain boundaries inside the backend. Modules may communicate through application contracts, internal domain events, or defined interfaces. They must not freely manipulate each other's internal tables or repositories.

---

# 3. Repository Architecture

Reltroner ERP consists of three product repositories.

## 3.1 Tenant Application Repository

```text
Repository:
erp-reltroner-fe

Responsibility:
Tenant-facing ERP workspace
```

Primary responsibilities:

- User login initiation
- OIDC callback handling
- Tenant workspace UI
- Dashboard
- Inventory UI
- Procurement UI
- Sales UI
- Finance UI
- Reports UI
- Company settings UI
- Operator profile
- Same-origin API proxy
- Frontend route protection
- Responsive and accessible product experience

Current runtime:

```text
Next.js 16
OpenNext for Cloudflare
Cloudflare Workers
```

Current deployed environment:

```text
https://erp-reltroner-fe.rei-reltroner.workers.dev
```

Canonical production domain target:

```text
https://erp.reltroner.com
```

## 3.2 Platform Administration Repository

```text
Repository:
erp-reltroner-admin

Responsibility:
Reltroner ERP platform control plane
```

Primary pages and responsibilities:

- Platform dashboard
- Tenant management
- Platform user administration
- Usage monitoring
- Audit-log review
- Feature-flag management
- System-health monitoring
- Subscription and entitlement operations
- Support access governance
- Internal platform administration

Current runtime target:

```text
Next.js
OpenNext for Cloudflare
Cloudflare Workers
```

Canonical production domain:

```text
https://admin.erp.reltroner.com
```

The admin frontend is not a microservice. It is a separately deployed frontend for the platform control plane.

## 3.3 Backend Repository

```text
Repository:
erp-reltroner-be

Responsibility:
Central business and security source of truth
```

Current runtime:

```text
Laravel
PHP
Hostinger shared web hosting
MariaDB/MySQL
```

Canonical production API domain:

```text
https://api.reltroner.com
```

The backend owns:

- Access-token validation
- Platform-admin validation
- Tenant membership
- Tenant resolution
- Application RBAC
- Permission overrides
- Approval authority
- Business rules
- Transaction integrity
- Audit logging
- Subscription plans
- Feature entitlements
- Usage metering
- Idempotency
- Data persistence
- ERP business workflows

---

# 4. End-to-End Deployment Topology

```text
                               Internet
                                  |
                                  v
                       Cloudflare DNS and Edge
                TLS, DNS, edge routing, basic protection
                                  |
              +-------------------+-------------------+
              |                                       |
              v                                       v
   https://erp.reltroner.com              https://admin.erp.reltroner.com
   Tenant ERP Frontend                    Platform Admin Frontend
   Cloudflare Worker                      Cloudflare Worker
   erp-reltroner-fe                       erp-reltroner-admin
              |                                       |
              +-------------------+-------------------+
                                  |
                        Same-origin API proxy
                                  |
                                  v
                      https://api.reltroner.com
                     Laravel Modular Monolith
                     Hostinger Shared Hosting
                                  |
                                  v
                      Hostinger MariaDB/MySQL
                                  |
                                  v
                     Application files and logs

External identity dependency:

Browser
   |
   +---- OIDC Authorization Code + PKCE ---->
         Externally Managed Keycloak
```

---

# 5. Domain and Routing Model

## 5.1 Domain Map

```text
reltroner.com
= corporate and product website

erp.reltroner.com
= tenant-facing ERP application

admin.erp.reltroner.com
= internal platform administration console

api.reltroner.com
= Laravel API origin

existing external Keycloak domain
= OpenID Connect identity provider
```

## 5.2 Environment Map

```text
Local tenant frontend:
http://localhost:3000

Local admin frontend:
http://localhost:3001

Temporary/staging frontend:
https://erp-reltroner-fe.rei-reltroner.workers.dev

Production tenant frontend:
https://erp.reltroner.com

Production admin frontend:
https://admin.erp.reltroner.com

Production API:
https://api.reltroner.com
```

Production and local redirect URIs must be explicit. Broad wildcard redirect URIs are not permitted unless technically unavoidable and documented.

---

# 6. Identity Architecture

## 6.1 Identity Provider Decision

Reltroner ERP uses the existing externally managed Keycloak deployment as its OpenID Connect identity provider.

Keycloak is responsible for:

- Authentication
- Login session
- Logout session
- Password handling
- Access-token issuance
- Refresh-token issuance where used
- Identity claims
- Coarse platform roles

Reltroner ERP does not own the Keycloak server.

## 6.2 External Identity Infrastructure Boundary

The following are outside the Reltroner ERP v1 production completion scope:

- Keycloak login-theme customization
- Creation or replacement of the Keycloak Server Super Admin
- Deletion or hardening of the temporary Keycloak Server Super Admin
- Keycloak server runtime administration
- Keycloak server backup
- Keycloak server patching
- Server-level Keycloak recovery
- Infrastructure-level Keycloak availability guarantees

These items are not counted in the Reltroner ERP v1 completion denominator because the project does not control them.

Reltroner ERP remains responsible for all controllable integration responsibilities:

- OIDC client configuration
- Redirect URI correctness
- Web-origin correctness
- Authorization Code Flow
- PKCE configuration when available
- Audience configuration
- Token validation
- User mapping
- Admin mapping
- Application RBAC
- Tenant resolution
- Safe login failure handling
- Auditability

## 6.3 OIDC Clients

Two separate public OIDC clients are used.

### Tenant Frontend Client

```text
Client ID:
erp-reltroner-fe

Application:
erp-reltroner-fe
```

### Platform Admin Client

```text
Client ID:
erp-reltroner-admin

Application:
erp-reltroner-admin
```

Both browser applications use public-client behavior:

```text
Client authentication: Off
Standard flow: On
Implicit flow: Off
Direct access grants: Off
Service accounts: Off
Authorization: Off
PKCE: S256 when controllable
```

The backend audience must be included in access tokens through the existing backend audience scope.

## 6.4 Authentication Flow

```text
1. User opens the tenant or admin frontend.
2. The frontend starts OIDC Authorization Code Flow with PKCE.
3. Keycloak authenticates the user.
4. Keycloak redirects to the correct callback route.
5. The frontend receives and processes the authorization response.
6. The frontend calls the same-origin API proxy.
7. The proxy forwards the bearer token to Laravel.
8. Laravel validates:
   - JWT signature
   - issuer
   - audience
   - expiration
   - token structure
9. Laravel maps the Keycloak subject to an internal user or admin record.
10. Laravel resolves application authorization and tenant context.
```

## 6.5 Token Storage Rule

Access tokens must not be treated as long-lived browser storage.

Preferred rules:

- Keep tokens in memory where possible.
- Avoid permanent localStorage token persistence.
- Do not expose confidential client secrets to browser code.
- Do not log access tokens.
- Do not include tokens in URLs.
- Clear authentication state on logout or unrecoverable validation failure.

A future Backend-for-Frontend session architecture may replace browser-managed tokens, but it is not required for the current zero-budget baseline.

---

# 7. Authorization Architecture

## 7.1 Authorization Source of Truth

Keycloak answers:

> Who is this identity?

Laravel answers:

> What is this identity allowed to do inside Reltroner ERP?

Application authorization remains in Laravel.

## 7.2 Authority Levels

Reltroner ERP distinguishes four authority levels.

```text
1. Keycloak Server Administrator
2. Reltroner Platform Administrator
3. Tenant Owner or Tenant Administrator
4. Tenant Operational User
```

### Keycloak Server Administrator

Controls the external identity infrastructure.

This role is not part of the Reltroner ERP application RBAC model.

### Reltroner Platform Administrator

Uses:

```text
https://admin.erp.reltroner.com
```

Controls the SaaS platform, including:

- Tenants
- Platform users
- Plans
- Subscriptions
- Usage
- Entitlements
- Feature flags
- Platform audit logs
- System health
- Support operations

### Tenant Owner or Tenant Administrator

Controls one tenant only:

- Tenant employees
- Tenant users
- Tenant roles
- Tenant permissions
- Approval authority
- Company settings
- Tenant-level subscription visibility
- Business modules allowed by subscription

### Tenant Operational User

Receives limited permissions based on job responsibilities.

Examples:

- Procurement officer
- Warehouse operator
- Finance officer
- Sales operator
- Approver
- Read-only auditor

## 7.3 Platform Admin Validation

Access to `/api/admin/*` requires all of the following:

```text
1. Valid Keycloak bearer token
2. Correct issuer
3. Correct backend audience
4. Required coarse platform role where configured
5. Active internal admin_users record
6. Required application admin permission
7. Successful runtime protection checks
8. Audit context
```

A user with a tenant account is not automatically a platform administrator.

A Keycloak role alone is not sufficient if the internal platform-admin record is missing, inactive, suspended, or revoked.

## 7.4 Tenant Authorization

Access to `/api/erp/*` requires:

```text
1. Valid Keycloak bearer token
2. Internal user mapping
3. Active tenant membership
4. Resolved active tenant
5. Required application permission
6. Feature entitlement
7. Usage-limit validation where relevant
8. Audit context for sensitive actions
```

## 7.5 Platform Admin Access to Tenant Data

The platform super admin has the highest application authority, but operational customer data should be accessed through explicit support access.

Target control:

```text
Admin chooses tenant
→ provides support reason
→ receives time-limited access
→ access is read-only by default
→ every action is audited
→ access expires or is revoked
```

The control plane may always expose tenant metadata, subscription status, usage, provisioning state, and platform health. Access to invoices, journals, inventory, customers, employees, or other operational records must remain explicit and auditable.

---

# 8. Multi-Tenancy Architecture

## 8.1 Tenancy Model

Reltroner ERP v1 uses:

> **A shared application and shared database with tenant-scoped rows.**

The initial database is MariaDB/MySQL on Hostinger shared hosting.

## 8.2 Tenant Isolation Rule

Every tenant-owned business table must include:

```text
tenant_id
```

Every tenant-owned query must be scoped to the active tenant.

The backend must never trust a tenant identifier from the frontend without validating that the authenticated user has an active membership in that tenant.

## 8.3 Tenant Context Resolution

```text
Authenticated Keycloak subject
        |
        v
Internal application user
        |
        v
Active tenant membership
        |
        v
Resolved tenant context
        |
        v
Permission and entitlement evaluation
```

A bootstrap endpoint should provide the frontend context:

```http
GET /api/erp/context
```

Expected response areas:

- User profile
- Active tenant
- Available memberships
- Permissions
- Entitlements
- Subscription
- Usage summary
- Platform notices

## 8.4 No-Membership Behavior

If a valid authenticated user has no tenant membership, the application must not display `Unknown Workspace` as the final state.

It must provide an explicit state:

- Create workspace
- Request invitation
- Join workspace
- Contact administrator
- Access denied, where appropriate

## 8.5 Multi-Membership Behavior

If a user belongs to multiple tenants:

- Show an authorized workspace selector.
- Validate every workspace switch on the backend.
- Never accept a tenant switch based only on client state.
- Re-resolve permissions and entitlements after switching.

## 8.6 Database Constraints

Tenant-aware uniqueness must include `tenant_id`.

Examples:

```text
unique(tenant_id, supplier_code)
unique(tenant_id, product_sku)
unique(tenant_id, employee_number)
unique(tenant_id, document_number)
```

Global identifiers may still use UUIDs or globally unique IDs, but business uniqueness remains tenant-scoped.

---

# 9. Backend Module Architecture

## 9.1 Current Foundation

The backend already contains foundations for:

- Audit logging
- Request context
- Keycloak token validation
- Platform admin protection
- Permission validation
- Idempotency
- Runtime protection
- Tenant resolution
- Users
- Admin users
- Tenants
- Tenant memberships
- Permissions
- Role permissions
- Permission overrides
- Plans
- Plan limits
- Tenant subscriptions
- Feature entitlements
- Usage counters
- Usage events
- User profiles

## 9.2 Target Modular Structure

The backend must evolve toward explicit modules:

```text
app/
├── Modules/
│   ├── IdentityAccess/
│   ├── PlatformAdministration/
│   ├── Tenancy/
│   ├── Organization/
│   ├── HumanResources/
│   ├── Suppliers/
│   ├── Products/
│   ├── Procurement/
│   ├── Inventory/
│   ├── Warehouse/
│   ├── Sales/
│   ├── Finance/
│   ├── Accounting/
│   ├── Subscription/
│   ├── UsageMetering/
│   ├── Notifications/
│   ├── Reporting/
│   └── Audit/
└── Shared/
    ├── Application/
    ├── Domain/
    ├── Infrastructure/
    └── Support/
```

Migration to this structure may be incremental. Existing Laravel conventions may remain while module ownership is established.

## 9.3 Module Communication Rule

Allowed:

- Application service contracts
- Domain service interfaces
- Internal domain events
- Explicit query services
- Shared immutable value objects

Not allowed:

- Uncontrolled cross-module model mutation
- Arbitrary cross-module table updates
- Circular module dependencies
- Business rules duplicated across controllers
- Frontend-controlled accounting or inventory truth

## 9.4 Transaction Boundary

Critical workflows must execute in database transactions where appropriate.

Example:

```text
Post Inventory GRN
→ create inventory movement
→ update inventory balance
→ create payable
→ create audit record
→ commit together
```

If one required operation fails, the transaction must roll back.

---

# 10. API Architecture

## 10.1 API Zones

The Laravel backend uses explicit API zones.

```text
/api/public/*
= unauthenticated or publicly safe endpoints

/api/erp/*
= authenticated tenant data plane

/api/admin/*
= authenticated platform control plane

/api/system/*
= restricted operational and health endpoints
```

## 10.2 Tenant API Middleware

Recommended middleware chain:

```text
Bearer-token validation
Request context
Runtime protection
Tenant resolution
Permission validation
Entitlement validation
Usage-limit validation where required
Audit context
Idempotency for sensitive writes
```

## 10.3 Admin API Middleware

Recommended middleware chain:

```text
Bearer-token validation
Request context
Runtime protection
Admin-zone validation
Admin permission validation
Audit context
Idempotency for sensitive writes
```

## 10.4 System API

System endpoints may include:

```text
GET /api/system/health
GET /api/system/version
GET /api/system/readiness
```

Detailed internal diagnostics must not be public.

## 10.5 API Contract Rules

- Use stable versioned contracts where breaking changes are possible.
- Validate all input on the backend.
- Return consistent error envelopes.
- Include request or correlation identifiers.
- Never reveal stack traces in production.
- Never trust totals calculated by the frontend.
- Never trust frontend permission checks.
- Use pagination for collection endpoints.
- Use idempotency keys for critical create or post operations.

---

# 11. Frontend and BFF Boundary

Both frontend repositories contain:

```text
app/api/[...path]/route.ts
```

This route acts as a same-origin API proxy.

## 11.1 Purpose

- Reduce browser cross-origin complexity
- Centralize upstream API configuration
- Preserve a stable frontend API surface
- Forward authorized requests to Laravel
- Normalize selected errors
- Avoid exposing internal origin details unnecessarily

## 11.2 Proxy Rules

The proxy must:

- Forward the HTTP method
- Forward safe headers
- Forward bearer authorization
- Preserve request body
- Preserve query parameters
- Propagate request IDs
- Avoid logging tokens
- Enforce an upstream allowlist
- Apply timeouts
- Avoid caching authenticated responses

## 11.3 Frontend Responsibility Boundary

Frontend permission checks improve UX only.

Backend authorization remains mandatory and authoritative.

Frontend code must not:

- Post journals directly
- Modify inventory truth locally
- Decide tenant ownership
- Calculate final payable balances as source of truth
- Enforce subscription limits as the only control
- Trust role claims without backend verification

---

# 12. Data Architecture

## 12.1 Database

Current production database:

```text
MariaDB/MySQL
Hostinger shared hosting
```

This is accepted for the zero-budget v1 architecture.

PostgreSQL may be considered later after migration to infrastructure that supports it, but it is not required for the current v1 baseline.

## 12.2 Source-of-Truth Principles

Examples:

```text
Inventory ledger
= source of truth for inventory movement history

Posted journal entries
= source of truth for accounting

Payments
= source of truth for settlement events

Tenant memberships
= source of truth for tenant access

Feature entitlements
= source of truth for plan feature access

Usage events
= source of truth for usage metering
```

Cached balances and summaries may exist as projections, but must be reconstructable or reconcilable from authoritative records.

## 12.3 Immutable and Append-Only Data

The following should be append-only or reversal-based after posting:

- Inventory ledger entries
- Posted journal entries
- Usage events
- Critical audit records
- Finalized payment records
- Final document snapshots

Corrections must use reversal, adjustment, or compensating records instead of destructive edits.

## 12.4 Money and Quantities

- Use fixed-precision decimal fields.
- Do not use binary floating-point values for money.
- Store currency explicitly where multi-currency support is introduced.
- Define rounding rules centrally.
- Define unit conversions centrally.
- Keep financial calculations on the backend.

## 12.5 Time

- Store timestamps consistently.
- Prefer UTC in persistent storage.
- Convert to tenant or user timezone at presentation boundaries.
- Preserve event and posting dates separately when business meaning differs.

---

# 13. Subscription, Entitlement, and Usage Architecture

## 13.1 Backend Source of Truth

Laravel owns:

- Plans
- Plan limits
- Tenant subscriptions
- Feature entitlements
- Usage counters
- Usage events

## 13.2 Enforcement Rule

```text
Frontend gate
= user experience

Backend entitlement and quota check
= authoritative enforcement
```

The backend must block unauthorized feature access even if a user bypasses frontend restrictions.

## 13.3 Usage Flow

```text
User requests a metered operation
→ backend validates entitlement
→ backend validates quota
→ business operation executes
→ usage event is recorded
→ usage counter is updated transactionally
```

## 13.4 Internal Tenant

The Reltroner internal workspace may use an internal plan or explicit platform entitlement. Internal access must still be represented clearly rather than bypassing all application controls through hidden conditions.

---

# 14. Queue, Scheduler, and Background Work

Hostinger shared hosting does not support the same operational model as a dedicated VPS.

## 14.1 Current Queue Decision

```text
Queue driver:
Database queue where asynchronous processing is required
```

## 14.2 Worker Decision

Permanent queue daemons are not part of the current shared-hosting architecture.

Use:

- Synchronous execution for critical short operations
- Database queue for non-critical asynchronous tasks
- Short-lived queue execution triggered through cron
- Laravel Scheduler through Hostinger cron

## 14.3 Suitable Background Tasks

- Email delivery attempts
- Notification generation
- Usage aggregation
- Report preparation
- Expiration checks
- Subscription reminders
- Non-critical audit enrichment

## 14.4 Unsuitable Tasks for Current Infrastructure

- High-volume real-time event processing
- Long-running PDF rendering at scale
- Kafka or RabbitMQ consumers
- WebSocket servers
- Heavy machine-learning workloads
- Continuous ETL
- High-frequency process-mining computation

These capabilities must wait for infrastructure migration or use external managed services.

---

# 15. File and Document Storage

## 15.1 Current Storage

The initial implementation may use Laravel's filesystem abstraction backed by Hostinger storage.

Suitable early files:

- User avatars
- Supplier documents
- Payment evidence
- Product images
- Generated exports
- Internal attachments

## 15.2 Storage Rules

- Validate MIME type and extension.
- Validate file size.
- Generate non-guessable storage names.
- Keep private files outside the public web root.
- Serve private files through authorized application routes.
- Never trust client-provided file names.
- Record tenant ownership.
- Record uploader and upload time.
- Audit access to sensitive financial or employee files where needed.

## 15.3 Future Evolution

The filesystem abstraction must allow migration to object storage without rewriting business modules.

---

# 16. Security Architecture

## 16.1 Security Boundaries

```text
Cloudflare
= DNS, TLS edge, basic edge protection

Keycloak
= external authentication provider

Laravel
= application authorization and business security

MariaDB/MySQL
= persistent application data

Frontend
= presentation and interaction layer
```

## 16.2 Required Controls

- Strict bearer-token validation
- Issuer validation
- Audience validation
- Expiration validation
- Tenant isolation
- Backend permission enforcement
- Platform admin validation
- Rate limiting
- Request-size limits
- Input validation
- SQL injection protection through framework practices
- Output escaping
- Secure file upload validation
- Idempotency for critical writes
- Audit logging
- Safe production errors
- Secret isolation
- Production debug disabled
- Exact CORS origins
- Security headers
- Backup procedure

## 16.3 Secrets

Secrets must not be committed.

Excluded from Git:

```text
.env
.env.*
!.env.example
.next/
.open-next/
.wrangler/
vendor/
node_modules/
Laravel runtime logs
compiled Blade views
framework cache
session files
```

If a secret was ever committed, removing the file is insufficient. The secret must be rotated.

## 16.4 Audit Logging

Audit records should capture:

- Actor
- Actor type
- Tenant
- Platform-admin context
- Action
- Entity
- Entity identifier
- Request ID
- Timestamp
- Relevant before and after data
- Support-access reason when applicable
- Source IP where appropriate and legally acceptable

Sensitive credentials and complete access tokens must never be stored in audit logs.

---

# 17. Observability and Operations

## 17.1 Current Observability

The zero-budget baseline uses:

- Cloudflare Worker logs
- Cloudflare metrics
- Laravel application logs
- Laravel audit logs
- Request IDs
- System health endpoints
- Hostinger resource monitoring
- Database backup records

## 17.2 Required Operational Signals

- Request count
- Error count
- Authentication failures
- Authorization failures
- Tenant-resolution failures
- Queue failures
- Slow database queries where observable
- Critical business posting failures
- Usage-limit rejections
- Subscription failures
- Admin access events
- System health

## 17.3 Health Endpoints

Health endpoints should distinguish:

```text
Liveness
= application process is responding

Readiness
= application can serve required dependencies

Detailed diagnostics
= internal admin-only information
```

## 17.4 Backup

At minimum:

- Use available Hostinger backups.
- Define an application database export procedure.
- Document restoration steps.
- Test restoration before claiming production readiness.
- Protect backup files as sensitive data.
- Record the last successful backup and restore test.

---

# 18. Deployment and Delivery

## 18.1 Frontend Deployment

Tenant and admin frontends are independently deployed to Cloudflare Workers through OpenNext.

Each application must use separate:

- Worker service
- OIDC client ID
- Application URL
- Deployment variables
- Custom domain
- Release history

## 18.2 Backend Deployment

The Laravel backend is deployed to Hostinger shared hosting.

Production deployment must include:

- Production `.env`
- `APP_DEBUG=false`
- Correct application URL
- Correct database credentials
- Correct Keycloak issuer and audience
- Correct frontend and admin origins
- Cached configuration where safe
- Database migrations
- Required seed data
- Writable Laravel storage
- Health verification
- Rollback procedure

## 18.3 CI/CD Baseline

Manual deployment is acceptable during the early zero-budget phase, but every deployment must be repeatable and documented.

Target evolution:

```text
GitHub repository
→ automated checks
→ build
→ deploy
→ health verification
```

Automated production deployment must not be introduced before secrets, rollback behavior, and environment separation are understood.

---

# 19. Product Control Plane and Data Plane

## 19.1 Tenant Data Plane

The tenant application serves operational users and tenant administrators.

Includes:

- Procurement
- Inventory
- Sales
- Finance
- Reports
- Company settings
- Tenant users
- Tenant permissions

## 19.2 Platform Control Plane

The admin application serves Reltroner ERP platform operators.

Includes:

- Tenant provisioning
- Platform user administration
- Subscription operations
- Usage monitoring
- Feature flags
- Platform audit
- System health
- Support operations

## 19.3 Separation Rule

Tenant users cannot access platform-admin routes.

Platform administrators cannot access tenant operational data silently. Operational tenant access must be explicit and audited.

---

# 20. Initial Business Workflow Architecture

The first complete end-to-end business workflow should connect:

```text
Purchase need
→ Purchase Requisition
→ Approval
→ Purchase Order
→ Goods Receipt
→ Inventory GRN
→ Inventory Ledger
→ Account Payable
→ Payment
→ Journal Entry
→ Management Report
```

This workflow is selected because it demonstrates:

- Requirement understanding
- Stakeholder separation
- Approval authority
- Supplier management
- Inventory integrity
- Financial integration
- Auditability
- Transaction consistency
- Business reporting
- Production-oriented engineering

Other modules may be added incrementally, but they must follow the same source-of-truth and control principles.

---

# 21. Current Infrastructure Constraints

The following constraints are accepted:

- Shared hosting CPU and process limits
- Shared hosting memory limits
- No self-hosted Docker production environment
- No Kubernetes
- No self-hosted Redis
- No RabbitMQ or Kafka
- No permanent queue workers
- No self-hosted Keycloak administration
- No Keycloak theme ownership
- No control over Keycloak Server Super Admin
- No PostgreSQL on the current Hostinger shared-hosting plan
- No full observability stack
- Limited zero-budget operational capacity

These constraints do not invalidate production readiness for an early-stage SaaS. They define the supported scale and operational boundary.

---

# 22. Migration Triggers

Reltroner ERP will migrate from shared hosting to a VPS or managed infrastructure when one or more of the following becomes true:

- Paying customer requires stronger availability or isolation
- Repeated resource-limit incidents occur
- Queue delays affect business operations
- PostgreSQL becomes a justified requirement
- Redis becomes necessary
- Permanent background workers are required
- WebSocket or real-time server support is required
- Document processing becomes too heavy
- Multiple production services require independent runtime management
- Backup and recovery requirements exceed shared-hosting capabilities
- Compliance requires infrastructure control
- Customer volume makes shared hosting operationally unsafe

Migration must be based on evidence, not architecture fashion.

---

# 23. Future Service Extraction Policy

Reltroner ERP remains a modular monolith until production evidence justifies extraction.

Likely early extraction candidates:

```text
Notification delivery
Document generation
Email campaign processing
External integration adapters
Search indexing
Analytics projections
AI assistant workloads
```

Critical transactional domains should remain together longer:

```text
Procurement transactional core
Inventory ledger
Payments
Accounts payable
Finance journal
Accounting posting
```

A module may become an independent service only when:

- Data ownership is explicit
- API or event contracts are stable
- Failure isolation is valuable
- Scaling requirements differ
- Deployment independence is justified
- Operational capacity exists
- The migration cost has a measurable business reason

---

# 24. Explicit Non-Goals for v1

Reltroner ERP v1 does not require:

- Microservices
- Kubernetes
- Production Docker orchestration
- Self-hosted message broker
- Event sourcing for the entire platform
- Multi-region deployment
- Active-active database replication
- Custom Keycloak theme
- Keycloak Server Super Admin changes
- Academic publication
- Formal research methodology
- Predictive AI before reliable operational data exists
- Unlimited enterprise scale on shared hosting

---

# 25. Production-Ready Definition

Reltroner ERP v1 may reach 100% when all product-owned and controllable responsibilities meet their acceptance criteria.

## 25.1 Included in the 100% Target

- Tenant frontend
- Platform admin frontend
- Laravel backend
- MariaDB/MySQL application database
- OIDC integration within project control
- Token validation
- Tenant isolation
- Application RBAC
- Platform-admin authorization
- Audit logs
- Subscription and entitlement controls
- Usage limits
- Core business workflow
- Error handling
- Security baseline
- Backup and recovery documentation
- Deployment documentation
- Customer onboarding
- Product documentation
- Commercial readiness

## 25.2 Excluded from the 100% Target

- Keycloak theme customization
- Keycloak Server Super Admin configuration
- Keycloak server ownership
- Keycloak server operational certification
- External identity-provider infrastructure guarantees

## 25.3 Meaning of 100%

> Reltroner ERP v1 is production-ready within the system boundary owned or controllable by the Reltroner ERP project.

It does not mean that externally owned infrastructure has been certified, modified, or fully controlled by Reltroner ERP.

---

# 26. Locked Architecture Summary

```text
Product:
Reltroner ERP

Architecture:
Modular monolith

Repositories:
1. erp-reltroner-fe
2. erp-reltroner-admin
3. erp-reltroner-be

Tenant frontend:
Next.js + OpenNext + Cloudflare Workers

Admin frontend:
Next.js + OpenNext + Cloudflare Workers

Backend:
Laravel modular monolith

Backend hosting:
Hostinger shared web hosting

Database:
Hostinger MariaDB/MySQL

DNS and edge:
Cloudflare

Authentication:
Externally managed Keycloak via OIDC

Authorization:
Laravel application RBAC and permission system

Tenancy:
Shared database, tenant-scoped rows

Admin model:
Separate platform control plane

Tenant data access by platform admins:
Explicit, time-limited, reason-bound, and audited

Queues:
Database queue with short-lived cron execution

Storage:
Laravel filesystem abstraction on current Hostinger storage

Microservices:
Not used in v1

Academic research:
Deferred until product completion

Keycloak theme:
Out of scope

Keycloak Server Super Admin:
Out of scope
```

---

# 27. Architecture Governance

This architecture is now locked as **Reltroner ERP Architecture Baseline v1.0**.

Engineering work must follow this baseline.

Changes require an Architecture Decision Record containing:

- Context
- Problem
- Decision
- Alternatives considered
- Business impact
- Security impact
- Operational impact
- Migration impact
- Rollback approach

Acceptable reasons for architectural change include:

- Verified production limitations
- Customer requirements
- Security findings
- Compliance obligations
- Cost reduction
- Reliability improvements
- Infrastructure ownership changes

Technology preference alone is not an acceptable reason.

---

# 28. Final Decision

Reltroner ERP will move forward as a production-first, zero-budget-aware SaaS using the infrastructure already available.

The architecture prioritizes:

```text
Customer value
→ business correctness
→ transactional integrity
→ security
→ maintainability
→ operational realism
→ monetization readiness
→ career and portfolio value
```

The project will not delay product delivery to pursue infrastructure sophistication that has not yet been justified by customers, traffic, revenue, or operational evidence.

---

# Reltroner ERP v1 — Master Engineering Execution Map

Dokumen arsitektur yang terkunci menjadi **satu-satunya sumber kebenaran arsitektur** untuk seluruh pekerjaan engineering:

```text
docs/reltroner-erp-final-end-to-end-architecture-baseline-v1.md
```

Master plan ini mengikuti batas yang telah ditetapkan: modular monolith, tiga repository, Cloudflare Workers, Hostinger shared hosting, MariaDB/MySQL, external Keycloak, Laravel sebagai authorization source of truth, serta pengecualian Keycloak theme, Keycloak Server Super Admin, microservices, Kubernetes, dan penelitian akademis dari target v1. 

## Status resmi

```text
Architecture Baseline:             100% LOCKED
Master Engineering Map:            100% DEFINED
Verified Implementation Progress:  PENDING REPOSITORY AUDIT
```

Progress implementasi tidak akan ditebak dari jumlah file. Nilai awal resmi baru ditentukan setelah **Phase 0 — Current-State Audit** selesai.

---

# 1. Aturan perhitungan 100%

Total release dibagi menjadi 14 phase berbobot.

| Phase     | Komponen                                    |    Bobot |
| --------- | ------------------------------------------- | -------: |
| 0         | Governance and Current-State Baseline       |       3% |
| 1         | Repository Hygiene and Reproducibility      |       5% |
| 2         | Production Infrastructure Foundation        |       8% |
| 3         | Identity and Authentication Integration     |       8% |
| 4         | Tenancy, RBAC, Audit, and Support Access    |      10% |
| 5         | Platform Administration Control Plane       |       7% |
| 6         | SaaS Plans, Entitlements, and Usage         |       7% |
| 7         | Organization and Master Data                |       6% |
| 8         | Procurement Workflow                        |      10% |
| 9         | Receiving and Inventory                     |      10% |
| 10        | Finance and Accounting                      |      10% |
| 11        | Reporting and Operational Visibility        |       5% |
| 12        | Production Hardening and Release Validation |       7% |
| 13        | Customer-Ready and Commercial Launch        |       4% |
| **Total** | **Reltroner ERP v1**                        | **100%** |

Sebuah phase hanya memperoleh bobot penuh apabila seluruh chunk dan phase gate-nya lulus. Code yang “sudah ada” tetapi belum diuji, belum terhubung, atau belum production-ready tidak dihitung selesai.

---

# 2. Mode eksekusi

## GUI

Digunakan untuk konfigurasi yang tidak berada di source code:

```text
GUI-KC  = Keycloak Admin Console
GUI-CF  = Cloudflare Dashboard
GUI-HS  = Hostinger hPanel
GUI-GH  = GitHub Settings / Actions
GUI-BR  = Browser manual testing
```

## PowerShell

Digunakan untuk:

* Git status, branch, commit, push, dan tag.
* Instalasi dependency yang memang telah disetujui.
* Build, lint, type-check, dan test.
* Laravel Artisan.
* Composer dan npm.
* Wrangler deployment.
* HTTP smoke test.
* Pengumpulan struktur dan current-state evidence.
* Verifikasi hasil Codex.

## Codex

Digunakan hanya untuk:

* Membuat atau mengubah source code.
* Membuat migrations, models, services, controllers, tests, dan UI.
* Refactoring.
* Dokumentasi teknis yang melekat pada suatu chunk.

Codex tidak boleh digunakan untuk:

* Mengubah Keycloak melalui GUI.
* Mengubah Cloudflare atau Hostinger.
* Menjalankan deployment production.
* Menjalankan CLI tanpa instruksi khusus.
* Menginstal dependency sendiri.
* Mengubah file di luar scope prompt.
* Mengambil keputusan arsitektur baru.

Codex dilarang digunakan untuk:

Generate dokumentasi.
Generate audit report.
Generate progress report.
Generate architecture document.
Generate roadmap.
Generate capability map.
Generate gap register.
Generate release notes.
Generate ADR.
Generate README atau dokumen penjelasan.
Menulis laporan hasil analisis repository.
Mengulang isi dokumen referensi.

---

# 3. Dependency utama

```text
Phase 0
  ↓
Phase 1
  ↓
Phase 2
  ↓
Phase 3
  ↓
Phase 4
  ├── Phase 5
  ├── Phase 6
  └── Phase 7
         ↓
      Phase 8
         ↓
      Phase 9
         ↓
      Phase 10
         ↓
      Phase 11
         ↓
      Phase 12
         ↓
      Phase 13
```

Phase 5, 6, dan 7 dapat berjalan sebagian paralel setelah identity, tenancy, dan authorization foundation stabil.

---

# Phase 0 — Governance and Current-State Baseline

**Bobot: 3%**

Tujuannya bukan membuat fitur, tetapi menetapkan posisi engineering yang benar.

| Chunk | Pekerjaan                                                                                                               | Mode                       |
| ----- | ----------------------------------------------------------------------------------------------------------------------- | -------------------------- |
| 0.1   | Pastikan baseline architecture tersedia di ketiga repository atau memiliki canonical shared reference                   | PowerShell                 |
| 0.2   | Audit branch, Git status, tracked secrets, build artifacts, dependencies, tests, dan deployment state ketiga repository | PowerShell                 |
| 0.3   | Petakan implemented, partial, placeholder, broken, dan missing capabilities                                             | Codex — documentation only |
| 0.4   | Buat release progress ledger berdasarkan bobot phase                                                                    | Codex — documentation only |
| 0.5   | Tetapkan ADR template dan architecture-change rule                                                                      | Codex — documentation only |

### Phase gate

* Ketiga repository telah diaudit.
* Tidak ada persentase yang berasal dari asumsi.
* Setiap capability memiliki status dan evidence.
* Dependencies antarchunk telah tercatat.

---

# Phase 1 — Repository Hygiene and Reproducibility

**Bobot: 5%**

| Chunk | Pekerjaan                                                                                        | Mode                     |
| ----- | ------------------------------------------------------------------------------------------------ | ------------------------ |
| 1.1   | Bersihkan `.next`, `.open-next`, `.wrangler`, logs, compiled views, dan secret dari Git tracking | Codex + PowerShell       |
| 1.2   | Perkuat `.gitignore` pada FE, Admin, dan BE                                                      | Codex                    |
| 1.3   | Audit dan rotasi secret yang pernah ter-commit                                                   | GUI terkait + PowerShell |
| 1.4   | Standarkan `.env.example` dan environment contract                                               | Codex                    |
| 1.5   | Pastikan install, build, lint, type-check, dan tests dapat direproduksi                          | PowerShell               |
| 1.6   | Tambahkan CI baseline untuk pull request                                                         | Codex + GUI-GH           |
| 1.7   | Tetapkan branch, commit, release tag, dan rollback convention                                    | PowerShell + GUI-GH      |

### Phase gate

Ketiga repository dapat di-clone dan diverifikasi dari clean environment tanpa bergantung pada artefak lokal atau secret tersembunyi.

---

# Phase 2 — Production Infrastructure Foundation

**Bobot: 8%**

| Chunk | Pekerjaan                                                    | Mode                |
| ----- | ------------------------------------------------------------ | ------------------- |
| 2.1   | Buat `api.reltroner.com` dan arahkan DNS                     | GUI-CF + GUI-HS     |
| 2.2   | Buat MariaDB/MySQL database dan production user              | GUI-HS              |
| 2.3   | Konfigurasikan Laravel public directory dan writable storage | GUI-HS + PowerShell |
| 2.4   | Siapkan production environment backend                       | GUI-HS              |
| 2.5   | Implementasikan liveness, readiness, dan version endpoints   | Codex               |
| 2.6   | Konfigurasikan database queue dan Laravel Scheduler          | Codex + GUI-HS      |
| 2.7   | Buat manual deployment dan rollback procedure                | Codex + PowerShell  |
| 2.8   | Jalankan backend smoke test dari public internet             | PowerShell          |

### Phase gate

```text
https://api.reltroner.com/api/system/health
```

harus dapat diakses secara aman, database dapat digunakan, migration dapat dijalankan, dan rollback dasar tersedia.

---

# Phase 3 — Identity and Authentication Integration

**Bobot: 8%**

| Chunk | Pekerjaan                                                                   | Mode                |
| ----- | --------------------------------------------------------------------------- | ------------------- |
| 3.1   | Bersihkan redirect URI dan web origins tenant client                        | GUI-KC              |
| 3.2   | Bersihkan redirect URI dan web origins admin client                         | GUI-KC              |
| 3.3   | Aktifkan PKCE S256 bila berada dalam kewenangan realm/client                | GUI-KC              |
| 3.4   | Verifikasi backend audience mapper                                          | GUI-KC              |
| 3.5   | Stabilkan login, callback, silent callback, dan logout tenant FE            | Codex               |
| 3.6   | Stabilkan login, callback, silent callback, dan logout Admin FE             | Codex               |
| 3.7   | Perkuat issuer, audience, signature, expiry, dan malformed-token validation | Codex               |
| 3.8   | Perkuat same-origin BFF proxy pada kedua frontend                           | Codex               |
| 3.9   | Jalankan login/logout dan invalid-token test                                | PowerShell + GUI-BR |

### Phase gate

* Tenant frontend login berhasil.
* Admin frontend login berhasil.
* Backend menolak wrong issuer, wrong audience, expired, malformed, dan missing token.
* Tenant user belum otomatis mendapat akses admin.
* Tidak ada token sensitif dalam log atau URL.

Keycloak theme dan Server Super Admin tidak termasuk phase ini.

---

# Phase 4 — Tenancy, RBAC, Audit, and Support Access

**Bobot: 10%**

| Chunk | Pekerjaan                                                           | Mode       |
| ----- | ------------------------------------------------------------------- | ---------- |
| 4.1   | Mapping Keycloak `sub` ke internal `users`                          | Codex      |
| 4.2   | Mapping Keycloak `sub` ke `admin_users`                             | Codex      |
| 4.3   | Implementasikan `/api/erp/context`                                  | Codex      |
| 4.4   | Implementasikan no-membership state                                 | Codex      |
| 4.5   | Implementasikan multi-membership dan workspace switching            | Codex      |
| 4.6   | Perkuat tenant resolution dan tenant-scoped query rules             | Codex      |
| 4.7   | Implementasikan role, permission, dan permission override           | Codex      |
| 4.8   | Perkuat platform-admin authorization                                | Codex      |
| 4.9   | Implementasikan time-limited support access                         | Codex      |
| 4.10  | Perkuat audit, request context, runtime protection, dan idempotency | Codex      |
| 4.11  | Buat tenant-isolation dan privilege-escalation tests                | Codex      |
| 4.12  | Jalankan full security test suite                                   | PowerShell |

### Phase gate

* User tenant A tidak dapat membaca atau mengubah tenant B.
* Tenant user mendapat 403 pada Admin API.
* Keycloak role saja tidak cukup tanpa active internal admin record.
* Support access memiliki reason, scope, expiry, dan audit trail.
* `Unknown Workspace` tidak menjadi final UI state.

---

# Phase 5 — Platform Administration Control Plane

**Bobot: 7%**

| Chunk | Pekerjaan                                               | Mode                |
| ----- | ------------------------------------------------------- | ------------------- |
| 5.1   | Tambahkan OpenNext/Cloudflare configuration ke Admin FE | Codex               |
| 5.2   | Deploy Admin Worker                                     | PowerShell          |
| 5.3   | Hubungkan `admin.erp.reltroner.com`                     | GUI-CF              |
| 5.4   | Implementasikan tenant management API dan UI            | Codex               |
| 5.5   | Implementasikan platform-user management                | Codex               |
| 5.6   | Implementasikan usage dashboard                         | Codex               |
| 5.7   | Implementasikan audit-log explorer                      | Codex               |
| 5.8   | Implementasikan feature-flag management                 | Codex               |
| 5.9   | Implementasikan system-health dashboard                 | Codex               |
| 5.10  | Jalankan platform-admin UAT dan negative-access tests   | PowerShell + GUI-BR |

### Phase gate

Admin Console dapat digunakan untuk mengoperasikan control plane tanpa memberikan akses kepada tenant user.

---

# Phase 6 — SaaS Plans, Entitlements, and Usage

**Bobot: 7%**

| Chunk | Pekerjaan                                                                   | Mode       |
| ----- | --------------------------------------------------------------------------- | ---------- |
| 6.1   | Finalisasi plan and plan-limit model                                        | Codex      |
| 6.2   | Finalisasi tenant subscription lifecycle                                    | Codex      |
| 6.3   | Implementasikan feature entitlement evaluation                              | Codex      |
| 6.4   | Implementasikan usage-event recording                                       | Codex      |
| 6.5   | Implementasikan transactional usage counter                                 | Codex      |
| 6.6   | Implementasikan backend quota enforcement                                   | Codex      |
| 6.7   | Implementasikan frontend feature and quota presentation                     | Codex      |
| 6.8   | Implementasikan internal plan untuk Reltroner workspace                     | Codex      |
| 6.9   | Implementasikan trial, activation, suspension, upgrade, dan downgrade state | Codex      |
| 6.10  | Jalankan quota, replay, dan concurrency tests                               | PowerShell |

### Phase gate

Frontend restriction bukan satu-satunya kontrol. Backend harus menolak feature atau quota yang tidak diperbolehkan.

Payment gateway tidak diwajibkan pada v1. Subscription dapat dioperasikan secara manual melalui Admin Console selama lifecycle dan audit-nya benar.

---

# Phase 7 — Organization and Master Data

**Bobot: 6%**

| Chunk | Pekerjaan                                               | Mode                |
| ----- | ------------------------------------------------------- | ------------------- |
| 7.1   | Company profile and tenant settings                     | Codex               |
| 7.2   | Employee and tenant-user lifecycle                      | Codex               |
| 7.3   | Invitation and membership management                    | Codex               |
| 7.4   | Supplier master                                         | Codex               |
| 7.5   | Product master                                          | Codex               |
| 7.6   | Category, unit of measure, tax, and currency foundation | Codex               |
| 7.7   | Tenant-aware numbering and uniqueness                   | Codex               |
| 7.8   | Import validation for essential master data             | Codex               |
| 7.9   | Master-data UAT                                         | PowerShell + GUI-BR |

### Phase gate

Tenant dapat menyiapkan organisasi, employee, suppliers, dan products yang diperlukan untuk menjalankan workflow procurement.

---

# Phase 8 — Procurement Workflow

**Bobot: 10%**

| Chunk | Pekerjaan                                          | Mode                |
| ----- | -------------------------------------------------- | ------------------- |
| 8.1   | Purchase Requisition model, migration, API, dan UI | Codex               |
| 8.2   | Approval authority dan approval workflow           | Codex               |
| 8.3   | Purchase Order model, API, dan UI                  | Codex               |
| 8.4   | PR-to-PO conversion rules                          | Codex               |
| 8.5   | Supplier and delivery status                       | Codex               |
| 8.6   | Document numbering and history                     | Codex               |
| 8.7   | Cancellation, rejection, and revision rules        | Codex               |
| 8.8   | Idempotency and concurrency protection             | Codex               |
| 8.9   | Procurement audit events                           | Codex               |
| 8.10  | End-to-end procurement test                        | PowerShell + GUI-BR |

### Phase gate

```text
Purchase need
→ Purchase Requisition
→ Approval
→ Purchase Order
```

berjalan end-to-end dengan permission, audit, tenant isolation, dan state transition yang benar.

---

# Phase 9 — Receiving and Inventory

**Bobot: 10%**

| Chunk | Pekerjaan                                      | Mode                |
| ----- | ---------------------------------------------- | ------------------- |
| 9.1   | Goods Receipt model, API, dan UI               | Codex               |
| 9.2   | Partial and multiple receipt rules             | Codex               |
| 9.3   | Inventory GRN posting                          | Codex               |
| 9.4   | Inventory Ledger                               | Codex               |
| 9.5   | Inventory balance projection                   | Codex               |
| 9.6   | Warehouse and location foundation              | Codex               |
| 9.7   | Stock adjustment and transfer                  | Codex               |
| 9.8   | Reversal and correction workflow               | Codex               |
| 9.9   | Transactional posting and reconciliation tests | Codex               |
| 9.10  | End-to-end receiving and stock UAT             | PowerShell + GUI-BR |

### Phase gate

* PO tidak langsung menambah stok.
* Goods Receipt mencatat penerimaan fisik.
* Inventory GRN melakukan official posting.
* Ledger dapat menjelaskan seluruh perubahan saldo.
* Posting gagal harus rollback secara atomik.

---

# Phase 10 — Finance and Accounting

**Bobot: 10%**

| Chunk | Pekerjaan                                   | Mode                |
| ----- | ------------------------------------------- | ------------------- |
| 10.1  | Chart of Accounts foundation                | Codex               |
| 10.2  | Account Payable creation from posted GRN    | Codex               |
| 10.3  | Supplier invoice and matching foundation    | Codex               |
| 10.4  | Payment workflow                            | Codex               |
| 10.5  | Cash and bank account foundation            | Codex               |
| 10.6  | Journal entry model and posting             | Codex               |
| 10.7  | Procurement-to-accounting posting rules     | Codex               |
| 10.8  | Immutable posting, reversal, and adjustment | Codex               |
| 10.9  | Financial reconciliation tests              | Codex               |
| 10.10 | Full procurement-to-finance UAT             | PowerShell + GUI-BR |

### Phase gate

```text
Purchase Requisition
→ Purchase Order
→ Goods Receipt
→ Inventory GRN
→ Account Payable
→ Payment
→ Journal Entry
```

harus berjalan sebagai satu business lifecycle yang dapat ditelusuri.

---

# Phase 11 — Reporting and Operational Visibility

**Bobot: 5%**

| Chunk | Pekerjaan                                  | Mode       |
| ----- | ------------------------------------------ | ---------- |
| 11.1  | Tenant operational dashboard               | Codex      |
| 11.2  | Procurement reports                        | Codex      |
| 11.3  | Inventory and stock reports                | Codex      |
| 11.4  | AP and payment reports                     | Codex      |
| 11.5  | Journal and financial summaries            | Codex      |
| 11.6  | Audit and process-history presentation     | Codex      |
| 11.7  | CSV/export foundation                      | Codex      |
| 11.8  | Report accuracy and tenant-isolation tests | PowerShell |

### Phase gate

Semua angka pada report dapat direkonsiliasi dengan source-of-truth records, bukan dihitung secara independen oleh frontend.

---

# Phase 12 — Production Hardening and Release Validation

**Bobot: 7%**

| Chunk | Pekerjaan                                         | Mode                |
| ----- | ------------------------------------------------- | ------------------- |
| 12.1  | Security regression matrix                        | Codex + PowerShell  |
| 12.2  | Tenant-isolation regression suite                 | Codex + PowerShell  |
| 12.3  | Permission and privilege-escalation suite         | Codex + PowerShell  |
| 12.4  | Performance and shared-hosting capacity baseline  | PowerShell          |
| 12.5  | Rate limit and request-size verification          | Codex + PowerShell  |
| 12.6  | Backup procedure                                  | GUI-HS + PowerShell |
| 12.7  | Restore drill                                     | GUI-HS + PowerShell |
| 12.8  | Deployment rollback drill                         | PowerShell          |
| 12.9  | Logging, request ID, and health visibility        | Codex               |
| 12.10 | Responsive, accessibility, and browser validation | GUI-BR              |
| 12.11 | Release Candidate UAT                             | GUI-BR + PowerShell |
| 12.12 | Critical and high-severity defect closure         | Codex + PowerShell  |

### Phase gate

Tidak ada unresolved critical defect, tenant-isolation failure, authorization bypass, accounting inconsistency, atau untested recovery procedure.

---

# Phase 13 — Customer-Ready and Commercial Launch

**Bobot: 4%**

| Chunk | Pekerjaan                                              | Mode                                      |
| ----- | ------------------------------------------------------ | ----------------------------------------- |
| 13.1  | Demo tenant dan realistic sample data                  | Codex + PowerShell                        |
| 13.2  | Guided onboarding                                      | Codex                                     |
| 13.3  | Empty, loading, failure, and no-permission UX          | Codex                                     |
| 13.4  | Pricing and plan presentation                          | Codex                                     |
| 13.5  | Trial and manual subscription sales process            | Codex + GUI-BR                            |
| 13.6  | Help documentation and support channel                 | Codex                                     |
| 13.7  | Terms, privacy, and service-boundary pages             | Codex; legal review later when affordable |
| 13.8  | Customer-facing product demo                           | GUI-BR                                    |
| 13.9  | Recruiter-facing architecture and business walkthrough | GUI-BR                                    |
| 13.10 | v1 release signoff and Git tag                         | PowerShell + GUI-GH                       |

### Phase gate

Reltroner ERP dapat:

* Didemonstrasikan.
* Digunakan oleh tenant.
* Di-onboard.
* Diberi plan.
* Dioperasikan melalui Admin Console.
* Ditawarkan kepada calon customer.
* Dijelaskan kepada recruiter dari problem hingga production operation.

---

# 4. Aturan prompt Codex hemat token

Setiap prompt Codex harus mewakili **satu chunk**, bukan satu phase.

## Struktur prompt resmi

```text
You are working in:
<absolute repository path>

Task:
Implement Reltroner ERP v1 Phase <P> Chunk <C> — <chunk title>.

Architecture source of truth:
docs/reltroner-erp-final-end-to-end-architecture-baseline-v1.md

Read only:
- docs/reltroner-erp-final-end-to-end-architecture-baseline-v1.md
- <specific source file 1>
- <specific source file 2>
- <specific test or contract file>
- direct dependencies only when required

Do not:
- change the locked architecture
- introduce microservices
- install dependencies
- run CLI commands
- run tests
- modify environment files
- modify files outside the allowed list
- create unrelated documentation
- weaken tenant isolation, RBAC, audit, or idempotency
- move authorization responsibility to the frontend

Create or modify only:
- <explicit file path>
- <explicit file path>
- <explicit file path>

Requirements:
1. <functional requirement>
2. <security requirement>
3. <tenant requirement>
4. <validation requirement>
5. <error-handling requirement>
6. <test requirement>

Acceptance criteria:
- <observable result>
- <observable result>
- <observable result>

At completion, return only:
1. Files changed.
2. Concise implementation summary.
3. Assumptions or unresolved blockers.
4. Commands that the user should run manually for validation.

Do not execute those commands.
```

## Token-efficiency rules

* Maksimal satu business concern per prompt.
* Biasanya 4–10 reference files per prompt.
* Jangan meminta Codex membaca seluruh repository.
* Selalu berikan daftar file yang boleh diubah.
* Jangan meminta Codex menulis penjelasan panjang.
* Jangan gabungkan backend, tenant FE, dan Admin FE jika dapat dipisah.
* Codex menulis code; PowerShell memvalidasi code.
* GUI configuration selalu dikerjakan terpisah.
* Prompt berikutnya dibuat berdasarkan hasil validasi chunk sebelumnya.

---

# 5. Workflow per chunk

Setiap chunk mengikuti siklus tetap:

```text
1. Navigate
   PowerShell memeriksa branch, status, dan relevant files.

2. Specify
   Buat prompt Codex berdasarkan baseline dan file konkret.

3. Generate
   Codex mengubah hanya file yang diizinkan.

4. Validate
   User menjalankan lint, test, build, atau smoke test di PowerShell.

5. Review
   Periksa diff dan business behavior.

6. Commit
   Commit satu chunk dengan scope yang jelas.

7. Update Progress
   Chunk ditandai complete hanya setelah acceptance criteria lulus.
```

Tidak boleh:

```text
Codex generate
→ langsung dianggap selesai
```

Yang benar:

```text
Codex generate
→ PowerShell validation
→ browser/API validation
→ diff review
→ commit
→ progress update
```

---

# 6. Definition of Complete

Reltroner ERP v1 mencapai 100% hanya apabila:

```text
Phase 0  = complete
Phase 1  = complete
Phase 2  = complete
Phase 3  = complete
Phase 4  = complete
Phase 5  = complete
Phase 6  = complete
Phase 7  = complete
Phase 8  = complete
Phase 9  = complete
Phase 10 = complete
Phase 11 = complete
Phase 12 = complete
Phase 13 = complete
```

Dikecualikan dari denominator:

* Keycloak theme.
* Keycloak Server Super Admin.
* Keycloak server operations.
* Microservices.
* Kubernetes.
* VPS migration.
* Predictive AI.
* Process Mining.
* Formal academic research.
* Unlimited enterprise-scale infrastructure.

---

# Langkah pertama yang benar

Engineering tidak dimulai dari procurement atau UI baru.

Langkah pertama adalah:

```text
Phase 0
Chunk 0.2
Current-State Audit of all three repositories
```

**Lingkungan:** PowerShell terminal.

Hasil audit tersebut akan menentukan:

* Persentase implementasi awal yang dapat diverifikasi.
* File mana yang sudah production-capable.
* File mana yang placeholder.
* Technical debt yang menjadi blocker.
* Prompt Codex pertama yang benar-benar diperlukan.
* Urutan chunk yang dapat dikerjakan tanpa mengulang pekerjaan.
