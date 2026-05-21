# CodeKeep

CodeKeep is a beginner-friendly cloud file storage and repository web app for students. It uses vanilla PHP, MySQL, and Bootstrap.

## Features
- Register, login, logout with password hashing
- File upload, download, delete, rename
- Folder creation, deletion, nested folders, file move
- Recent uploads, storage usage, activity log
- Secure sessions, prepared statements, file validation
- Responsive UI built with Bootstrap
- API-structured backend in `/api`
- Uploads stored in `/uploads`

## Folder Structure
- `/api` - backend API endpoints
- `/assets/css` - custom CSS
- `/assets/js` - frontend JavaScript
- `/config` - database configuration
- `/database` - SQL schema and sample data
- `/uploads` - uploaded files storage
- `/views` - frontend pages
- `index.php` - redirect to login/dashboard
- `logout.php` - logout page

## Setup Instructions

### 1. Install XAMPP or Laragon
- Install XAMPP or Laragon on Windows.
- Start Apache and MySQL.

### 2. Copy the project files
- Place the `GithubLike` folder into your web server's document root.
  - XAMPP: `C:\xampp\htdocs\CodeKeep`
  - Laragon: `C:\laragon\www\CodeKeep`

### 3. Create the database
- Open phpMyAdmin or use MySQL shell.
- Import `database/schema.sql`.
- Optional: import `database/sample_data.sql` for sample user data.

### 4. Update database settings
- Open `config/config.php`.
- Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` to match your MySQL settings.

### 5. Set folder permissions
- Ensure `/uploads` is writable by the web server.

### 6. Open the app
- Visit `http://localhost/CodeKeep/` in your browser.

### 7. Sample login
- Email: `student@example.com`
- Password: `password`

## API Endpoints
- `/api/login.php` - login
- `/api/register.php` - register
- `/api/files.php` - list, upload, delete, rename, download
- `/api/folders.php` - create/delete/move folders
- `/api/profile.php` - update user profile
- `/api/search.php` - search files

## Notes
- Uploaded files are stored in `/uploads`.
- The app uses prepared statements to prevent SQL injection.
- Profile picture uploads are saved to the same `/uploads` folder.

## Recommended improvements
- Add secure HTTPS when deployed online.
- Use a stronger session cookie policy in production.
- Add folder nesting UI and file preview pages.
