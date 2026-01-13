# PHP Form Demo (Docker + SQLite)

This is a **basic PHP form demo** inspired by the [W3Schools tutorial](https://www.w3schools.com/php/php_forms.asp), but reorganized into separate files (.html, .php for better organization) with Docker support and SQLite persistence.  

It demonstrates:

- Running PHP 8.3 + Apache inside Docker.  
- Serving an HTML form and processing input with a PHP backend.  
- Using PHP superglobals (`$_POST`, `$_SERVER`) safely.  
- Sanitizing input (`trim`, `stripslashes`, `htmlspecialchars`).  
- Persisting submissions in an **SQLite** database.  
- Returning a summary view with security headers and anti-XSS escaping.  

---

## Project Structure
```
├── Dockerfile           # PHP 8.3 + Apache + PDO_SQLite
├── docker-compose.yml   # Service configuration (php on port 8080)
├── Makefile             # Helper commands
└── src/
├── form.html        # HTML form
└── demo_request.php # PHP script handling submissions
```

---

## Database
Data is stored in src/data/demo.sqlite.
You can inspect it with:
```bash
docker exec -it php-form-php-1 sqlite3 /var/www/html/data/demo.sqlite
SELECT * FROM demo_requests;
```
---

## Usage
Make sure you have **Docker** and **Docker Compose** installed.  
Then use the provided `Makefile`:

```bash
# Build the Docker image
make build

# Start the container (detached) and show URL
make start

# Stop the container
make stop

# Restart the PHP service
make restart

# Tail container logs
make logs

# List running containers
make ps

# Clean only the data base
make db-clean

# Clean local volumes/images for this project and the database
make clean

# Destroy EVERYTHING (all containers/images/volumes/DB, careful!)
make destroy
```
