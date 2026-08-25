# UCSI Beneficiary Management and Impact Tracking System — Backend

Vanilla PHP 8.1+ and MySQL backend for the United Community Support Initiative (UCSI) beneficiary and impact management system.

## Structure

- `config/` application and PDO configuration
- `index.php` HTTP API router and endpoint implementation
- `bootstrap.php` security, authentication, validation, pagination and audit helpers
- `../database/schema.sql` normalized relational schema
- `../database/seed.php` initial role accounts and programme seed

## Setup

1. Create a MySQL database by running `database/schema.sql`.
2. Set the database constants in `backend/config/config.php` for the deployment environment.
3. Run `php database/seed.php` once from the project root.
4. Point the web server document root to the project and route `/backend/*` requests to `backend/index.php` using the included `.htaccess`.
5. Use HTTPS in production.

## Roles

- `admin`: full administration
- `manager`: programmes, interventions and beneficiary management
- `field_officer`: beneficiary registration, enrolment and assessments
- `analyst`: assessments, indicators and impact reporting
- `viewer`: read-only access

## API

Authentication: `POST /backend/api/auth/login`, `POST /backend/api/auth/logout`, `GET /backend/api/auth/me`.

Beneficiaries: `GET /backend/api/beneficiaries`, `GET /backend/api/beneficiaries/{id}`, `POST`, `PUT`, and `DELETE`.

Programmes: `GET` and `POST /backend/api/programmes`.

Interventions: `GET` and `POST /backend/api/interventions`.

Enrollments: `POST /backend/api/enrollments`.

Assessments: `GET` and `POST /backend/api/assessments`.

Indicators: `GET /backend/api/indicators` and `POST /backend/api/indicator-values`.

Reporting: `GET /backend/api/reports/impact` and `GET /backend/api/dashboard`.

State-changing requests require a valid session and CSRF token. Database access uses PDO prepared statements. Passwords are stored with PHP's password hashing API, and important write operations are recorded in `audit_logs`.

## Initial accounts

The seed script creates role accounts under the `ucsi.org` domain with a generated password defined in the seed script. Replace the seed password immediately after initial deployment and remove or restrict access to the seeder.
