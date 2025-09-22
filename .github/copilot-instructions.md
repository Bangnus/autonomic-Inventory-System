# Copilot Instructions for autonomic PHP Codebase

## Overview
This is a PHP web application for managing users, products, and requests, with separate admin and user dashboards. The project is structured for modularity and role-based access.

## Architecture
- **Entry Points**: Main pages are in the root (`index.php`, `login.php`, `register.php`, etc.), with role-specific dashboards in `admin/` and `user/`.
- **Authentication**: Handled in `includes/auth.php`. All protected pages should include this for session validation.
- **Database Access**: Use `db.php` for all database connections. SQL schema is in `database.sql`.
- **PDF Generation**: Use `vendor/fpdf/fpdf.php` for exporting reports (see `export_pdf.php`).
- **Layout**: Shared UI components (e.g., sidebar) are in `layouts/`.
- **Assets**: Images and static files are in `assets/`.

## Developer Workflows
- **Local Development**: Use XAMPP (Windows) for Apache/MySQL. Place code in `htdocs` and access via `localhost/autonomic`.
- **Database Setup**: Import `database.sql` into MySQL before running the app.
- **Debugging**: Use `error_log()` and check PHP error logs. No custom debug tooling is present.
- **PDF Export**: For report generation, use the FPDF library as shown in `export_pdf.php`.

## Conventions & Patterns
- **Session Management**: Always start sessions at the top of PHP files that require authentication.
- **Role Checks**: Admin/user separation is enforced by including `auth.php` and checking session variables.
- **Reusable Functions**: Common utilities are in `includes/utils.php`.
- **Page Structure**: Most pages follow a pattern: include `auth.php`, connect to DB, process form/input, render HTML.
- **File Naming**: Use descriptive names for pages by function and role (e.g., `manage_products.php`, `history.php`).

## Integration Points
- **FPDF**: For PDF export, instantiate FPDF in `export_pdf.php` or similar files.
- **MySQL**: All data is stored in MySQL, accessed via `db.php`.

## Examples
- To add a new admin feature, create a PHP file in `admin/`, include `auth.php`, and use functions from `db.php`.
- To add a user-facing page, place it in `user/`, include `auth.php`, and follow the dashboard pattern.

## Key Files & Directories
- `db.php`: Database connection
- `includes/auth.php`: Authentication/session
- `includes/utils.php`: Utility functions
- `admin/`, `user/`: Role-based dashboards and features
- `layouts/sidebar.php`: Shared sidebar
- `vendor/fpdf/fpdf.php`: PDF library

---
For questions or unclear conventions, review the referenced files or ask for clarification.
