(cd "$(git rev-parse --show-toplevel)" && git apply --3way <<'EOF'
diff --git a/README.md b/README.md
--- a/README.md
+++ b/README.md
@@ -0,0 +1,43 @@
+## Internship Management System
+
+A PHP-based application for managing internships with roles for students, employers, alumni, and admins. Security-hardened with centralized bootstrap and middleware.
+
+### Getting Started
+
+1. Copy `.env.example` to `.env` and adjust values:
+```
+DB_HOST=localhost
+DB_USER=root
+DB_PASSWORD=
+DB_NAME=internship_db
+
+ENCRYPTION_KEY=change-me-to-a-32-char-secret-please-rotate
+ENVIRONMENT=development
+```
+2. Ensure PHP extensions are enabled: mysqli, pdo_mysql, openssl.
+3. Import `classes/db.sql` into your MySQL server.
+4. Serve the app from the project root (so includes resolve correctly).
+
+### Bootstrap
+
+- All pages should `require_once 'config.php'` which delegates to `secure_config.php`.
+- Provides `$conn` (MySQLi) and `$pdo` (PDO) plus `$security` and initializes `AuthMiddleware` automatically.
+
+### Project Structure
+
+- `secure_config.php`: Centralized security and configuration bootstrap
+- `middleware/auth_middleware.php`: Session/role enforcement
+- `src/PHPMailer*.php`: Email stack
+- `includes/`: Layout includes
+- `admin/`: Admin features and security dashboard
+- `uploads/`: User-uploaded assets (hardened)
+
+### Security Notes
+
+- CSRF tokens available via `csrf_field()` / `verify_csrf()`.
+- Use `$security->sanitizeInput()` or the `sanitize()` helper.
+- Avoid raw queries; use prepared statements via `$pdo` or `$conn`.
+
+### Development
+
+- For production, set `ENVIRONMENT=production` and configure SSL options in `secure_config.php`.
EOF
)