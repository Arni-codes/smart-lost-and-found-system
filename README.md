# Smart College Lost and Found System (SCLFS)

[![Environment](https://img.shields.io/badge/Environment-XAMPP-orange.svg)](https://www.apachefriends.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B%20(PDO)-blue.svg)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%20%2F%20MariaDB-blue.svg)](https://mariadb.org/)
[![Version](https://img.shields.io/badge/Version-1.0.0-green.svg)](PROJECT_SPEC.md)

A centralized, responsive, and secure web application for tracking lost and found personal belongings across a college campus. Designed with plain PHP (PDO), MySQL, vanilla JavaScript, and modern CSS, with zero runtime package dependencies.

---

## 1. Prerequisites

Before setting up the project, ensure you have:
- **XAMPP** (version with **PHP 8.1+** and **MySQL / MariaDB 10.4+**) installed.
  - Download from: [https://www.apachefriends.org/](https://www.apachefriends.org/)
- **Git** installed on your operating system.
- A modern web browser (Google Chrome, Firefox, or Edge).

---

## 2. Project Directory Placement

Clone or place the repository directly inside your XAMPP `htdocs` directory:

### Windows:
```
C:\xampp\htdocs\smart-lost-and-found-system\
```

### macOS / Linux:
```
/Applications/XAMPP/xamppfiles/htdocs/smart-lost-and-found-system/
# or
/opt/lampp/htdocs/smart-lost-and-found-system/
```

---

## 3. Local XAMPP Setup & Database Import

Follow these exact steps to initialize the local environment:

### Step 1: Start Apache and MySQL
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Ensure both services show green status indicators.

### Step 2: Import the Database

You can import the database using either **phpMyAdmin** (Web UI) or the **MySQL Command Line**:

#### Option A: Using phpMyAdmin (Recommended for Beginners)
1. Open your browser and navigate to: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Click the **Import** tab in the top navigation bar.
3. Click **Choose File** and select `database/schema.sql` from the project folder.
4. Click **Import** at the bottom of the page. This automatically creates the `smart_lost_and_found` database and all 8 tables.
5. In the left sidebar, click the newly created **`smart_lost_and_found`** database.
6. Click the **Import** tab again, select `database/seed.sql`, and click **Import**. This populates categories, campus locations, demo users, and sample items.

#### Option B: Using MySQL Command Line
Open your terminal (PowerShell, CMD, or Bash) in the project folder and run:
```bash
# Windows (standard XAMPP path)
C:\xampp\mysql\bin\mysql.exe -u root < database/schema.sql
C:\xampp\mysql\bin\mysql.exe -u root < database/seed.sql

# macOS / Linux
/opt/lampp/bin/mysql -u root < database/schema.sql
/opt/lampp/bin/mysql -u root < database/seed.sql
```

---

## 4. Local Application URLs

Once Apache and MySQL are running and the database is imported, access the system at:

| Page / Tool | Local URL |
|---|---|
| **Public Homepage** | [http://localhost/smart-lost-and-found-system/](http://localhost/smart-lost-and-found-system/) |
| **Database & Health Check** | [http://localhost/smart-lost-and-found-system/test_db.php](http://localhost/smart-lost-and-found-system/test_db.php) |
| **phpMyAdmin Database Tool** | [http://localhost/phpmyadmin](http://localhost/phpmyadmin) |

> **Tip:** Open `test_db.php` first. It automatically checks your PHP version, PDO connection, directory permissions, and validates that all 8 tables are present with row counts.

---

## 5. Seeded Demo Accounts (For Local Evaluation Only)

The `database/seed.sql` fixture provides pre-configured demo accounts for grading and testing:

| Role | Email | Password | Student / Staff ID | Department |
|---|---|---|---|---|
| **Admin** | `admin@college.edu` | `Admin@123` | `ADMIN001` | Campus Security Desk |
| **Student** | `alex@college.edu` | `Student@123` | `STU2026001` | Computer Science |
| **Student** | `priya@college.edu` | `Student@123` | `STU2026002` | Electrical Engineering |

> [!CAUTION]
> Passwords in the database are stored as **Bcrypt hashes**; plaintext passwords are never stored in SQL. Before deploying to any public or staging server, change or remove these demo accounts.

---

## 6. Project Architecture & Directory Layout

```
smart-lost-and-found-system/
│
├── .gitignore                    # Protects uploads and local configurations
├── README.md                     # This quickstart guide
├── PROJECT_SPEC.md               # Master engineering specification & architecture
├── index.php                     # Public landing page
├── test_db.php                   # Safe database & system diagnostics tool
│
├── config/                       # Core Configuration
│   ├── database.php              # Reusable PDO singleton database connection
│   └── config.php                # App constants, paths, and session setup
│
├── database/                     # SQL DDL & Seed Fixtures
│   ├── schema.sql                # Master database schema (8 tables)
│   └── seed.sql                  # Seed data (categories, buildings, demo users)
│
├── includes/                     # Shared Partials & Libraries
│   ├── functions.php             # XSS escaping, CSRF, flash messaging, URL helpers
│   ├── header.php                # Reusable header and navigation
│   └── footer.php                # Reusable footer and scripts
│
├── assets/                       # Client Assets
│   ├── css/
│   │   ├── variables.css         # Design tokens & color system
│   │   ├── style.css             # Component, layout, and card styling
│   │   └── responsive.css        # Mobile and tablet breakpoints
│   ├── js/
│   │   └── app.js                # Mobile toggle, CSRF helpers, toast alerts
│   └── images/
│       ├── logo.svg              # System vector logo
│       └── default-item.svg      # Image placeholder
│
├── uploads/                      # Local File Storage (Protected)
│   ├── items/                    # Item images (.gitkeep tracked)
│   ├── proofs/                   # Sensitive claim proof uploads (.gitkeep tracked)
│   └── .htaccess                 # PHP script execution strictly disabled
│
├── api/                          # Asynchronous JSON Endpoints (Modularized by role)
├── services/                     # Business Logic Services (Matching, Uploads, Notifs)
└── admin/                        # Admin Portal Web Views
```

---

## 7. Five-Member Team Work Division

All members branch off the `develop` branch and work in their designated areas as detailed in [PROJECT_SPEC.md](PROJECT_SPEC.md):

| Member | Focus Domain | Key Responsibilities |
|---|---|---|
| **Member 1** | **Frontend UI/UX** | Design system, responsive layouts, navigation, and HTML view structures. |
| **Member 2** | **Frontend JavaScript** | Asynchronous `fetch` calls, live search debounce, client validation, modals, toast alerts. |
| **Member 3** | **Auth & Core Backend** | User authentication, sessions, role authorization, item report CRUD, image uploads. |
| **Member 4** | **Database & Matching** | Database queries, indexing, and the 4-factor heuristic smart matching engine. |
| **Member 5** | **Claims & Admin Desk** | Ownership claims adjudication, security desk handover verification, notifications, integration QA. |

---

## 8. Git Workflow for Team Members

1. **Clone the repository:**
   ```bash
   git clone <repo-url> smart-lost-and-found-system
   cd smart-lost-and-found-system
   ```

2. **Switch to the integration branch:**
   ```bash
   git checkout develop
   ```

3. **Create your feature branch:**
   ```bash
   # Example for Member 3:
   git checkout -b feature/m3-auth-api
   ```

4. **Keep your branch updated before opening a Pull Request:**
   ```bash
   git fetch origin develop
   git merge origin/develop
   ```

5. **Commit with conventional prefixes:**
   ```bash
   git commit -m "feat(auth): implement user registration endpoint"
   ```

---

## 9. Verification & Troubleshooting

- **Database Connection Error?**
  - Check that MySQL is running in XAMPP (port 3306).
  - Verify that `database/schema.sql` was imported and the database `smart_lost_and_found` exists.
  - If your MySQL uses a custom password, create `config/env.local.php` (gitignored) and define `DB_PASS`.
- **404 Not Found on CSS or Images?**
  - Ensure the folder name in `htdocs` is exactly `smart-lost-and-found-system`.
  - The `config/config.php` file automatically resolves the base URL path.
- **Run the automated diagnostic:**
  - Visit: [http://localhost/smart-lost-and-found-system/test_db.php](http://localhost/smart-lost-and-found-system/test_db.php)