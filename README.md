# Centralised Beneficiary Management and Impact Tracking System

## United Community Support Initiative (UCSI)

[![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/HTML)
[![CSS3](https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/CSS)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![GitHub](https://img.shields.io/badge/GitHub-main-181717?logo=github&logoColor=white)](https://github.com/saukakke/benefit_impact_system)
[![License](https://img.shields.io/badge/License-Not%20Specified-lightgrey)](https://github.com/saukakke/benefit_impact_system)

A web-based beneficiary management and impact tracking system designed for United Community Support Initiative (UCSI). The application centralises beneficiary records and connects them with programmes, interventions, enrolments, assessments, indicators and impact reporting.

## Project Overview

The system replaces fragmented beneficiary and programme records with a structured relational information system. It provides authorised users with a single platform for managing beneficiary information and tracking the relationship between programme activities and measurable outcomes.

### Core workflow

```text
Beneficiary
    ↓
Programme
    ↓
Intervention
    ↓
Beneficiary Enrolment
    ↓
Assessment
    ↓
Impact Indicator
    ↓
Indicator Value
    ↓
Impact Report
```

## Objectives

- Centralise beneficiary information.
- Provide secure beneficiary registration and management.
- Manage programmes and interventions.
- Track beneficiary participation in interventions.
- Record beneficiary assessments.
- Define and record impact indicators.
- Produce programme-level impact information.
- Provide role-based access control.
- Maintain audit records for significant system activities.
- Provide a responsive browser-based interface.

## Technology Stack

### Backend

- Vanilla PHP
- MySQL
- PDO
- Session-based authentication
- JSON/HTTP API endpoints

### Frontend

- HTML5
- CSS3
- JavaScript
- Bootstrap

### Development and Quality Assurance

- Git
- GitHub
- GitHub Actions
- MySQL test service

## Main Modules

1. **Authentication and Authorisation** — login, logout, sessions and role-based permissions.
2. **Dashboard** — consolidated operational statistics.
3. **Beneficiary Management** — registration, search, filtering, profile management and status management.
4. **Programme Management** — creation and management of organisational programmes.
5. **Intervention Management** — management of activities delivered under programmes.
6. **Beneficiary Enrolment** — association of beneficiaries with interventions.
7. **Assessments** — recording beneficiary assessment information and scores.
8. **Impact Indicators** — definition of measurable programme indicators.
9. **Indicator Values** — recording measurements for reporting periods.
10. **Impact Reports** — aggregation of programme and beneficiary impact information.
11. **Audit Logging** — recording significant system operations.
12. **Document and Notification Support** — structured support for associated records where enabled by the application configuration.

## Database Model

The system uses a relational MySQL database. The principal entities include:

| Entity | Purpose |
|---|---|
| `users` | System users, credentials, roles and account status |
| `beneficiaries` | Master beneficiary records |
| `programmes` | Programme records |
| `interventions` | Intervention records linked to programmes |
| `beneficiary_interventions` | Beneficiary participation/enrolment relationships |
| `assessments` | Beneficiary assessment records |
| `indicators` | Programme impact indicators |
| `indicator_values` | Indicator measurements by reporting period |
| `documents` | Supporting document metadata |
| `notifications` | System notifications |
| `audit_logs` | Significant user and system activity records |

## Architecture

The application follows a practical layered web architecture:

```text
┌──────────────────────────────────────────┐
│                 Browser                  │
│ HTML5 · CSS3 · Bootstrap · JavaScript   │
└─────────────────────┬────────────────────┘
                      │ HTTP / JSON
                      ▼
┌──────────────────────────────────────────┐
│               PHP Backend                │
│ Authentication · RBAC · Validation      │
│ Business Logic · API · Audit Logging     │
└─────────────────────┬────────────────────┘
                      │ PDO
                      ▼
┌──────────────────────────────────────────┐
│                 MySQL                    │
│ Beneficiaries · Programmes · Activities │
│ Assessments · Indicators · Audit Logs    │
└──────────────────────────────────────────┘
```

## Security

Security is treated as a core application requirement. The implementation includes controls such as:

- Password hashing and password verification.
- Session-based authentication.
- Session regeneration after authentication.
- Role-based authorisation.
- CSRF protection for state-changing requests.
- Prepared SQL statements through PDO.
- Server-side input validation.
- Identifier and enumeration validation.
- Security headers.
- Audit logging.
- Environment-based database configuration.
- Controlled file-upload handling where uploads are enabled.
- Production error handling that avoids exposing sensitive implementation details.

## Installation

### Requirements

A local or production environment should provide:

- PHP 8.x or a compatible supported PHP version used by the project configuration.
- MySQL 8.x or a compatible MySQL server.
- Apache or another PHP-capable web server.
- Git.
- A modern web browser.

### Setup

1. Clone the repository:

```bash
git clone https://github.com/saukakke/benefit_impact_system.git
cd benefit_impact_system
```

2. Create the MySQL database required by the project.

3. Import the project's database schema/SQL files into MySQL.

4. Configure the application's database environment variables according to the configuration files included in the repository.

5. Ensure the PHP application can read its configuration and write only to directories that require write access.

6. Start the PHP application using the configured web server or PHP development server.

7. Open the application in a browser and authenticate with an authorised account.

> Do not commit production database credentials, API keys, session secrets or other sensitive configuration to GitHub.

## Configuration

Database configuration should be supplied through environment-specific configuration rather than hard-coded production credentials.

Typical database settings include:

```text
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASS
```

The exact configuration expected by the application should be taken from the repository's current configuration files.

## Development Workflow

The recommended development workflow is:

1. Create a feature branch.
2. Implement the change.
3. Validate PHP syntax and application behaviour.
4. Test affected database operations.
5. Review authentication and authorisation implications.
6. Run the repository QA checks.
7. Review the frontend at desktop and mobile breakpoints.
8. Merge verified changes into `main`.

## Testing and QA

The repository includes automated QA configuration. Testing should cover:

- PHP syntax validation.
- Database initialisation.
- Authentication.
- Authorisation.
- Beneficiary CRUD operations.
- Programme CRUD operations.
- Intervention CRUD operations.
- Enrolment relationships.
- Assessment operations.
- Indicator operations.
- Impact reporting.
- Validation failures.
- CSRF protection.
- SQL injection resistance.
- Access-control failures.

Before production deployment, execute acceptance testing using representative non-production data and verify database backup restoration.

## Default Development Credentials

If development/test seed data is present in the repository, use the credentials defined by the current seed or setup scripts. Do not reuse development credentials in production.

## Project Structure

The repository is organised around the application's backend, frontend, database and operational configuration. The exact directory structure should be treated as authoritative because it may evolve as the implementation is extended.

## Data Protection

The system can contain personally identifiable beneficiary information. Production operators should therefore:

- Restrict access to authorised personnel.
- Use HTTPS.
- Apply least-privilege database permissions.
- Maintain secure backups.
- Establish retention and deletion policies.
- Avoid using real beneficiary information in development and testing environments unless formally authorised and appropriately protected.
- Review applicable Nigerian data-protection requirements before operational deployment.

## Academic Project Context

This software forms the implementation component of the academic project titled:

**Design and Implementation of a Centralised Beneficiary Management and Impact Tracking System: A Case Study of United Community Support Initiative (UCSI).**

The project demonstrates the application of database systems, web programming, information systems analysis, software engineering and application security to a community-support information-management problem.

## Future Enhancements

Potential future extensions include:

- Offline/mobile field data collection.
- SMS and email notifications.
- GIS-based beneficiary and intervention mapping.
- PDF, Excel and CSV report exports.
- Scheduled reports.
- Advanced analytics and visual dashboards.
- Additional document-management workflows.
- External monitoring and evaluation integrations.
- More granular permissions and organisational units.

## Repository

GitHub: https://github.com/saukakke/benefit_impact_system

## License

No open-source licence is declared in this README. Unless a licence is added to the repository, the source remains subject to the repository owner's applicable rights.