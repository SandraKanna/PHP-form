<?php
/* ================================================================
   helper functions
   ================================================================ */

   //preformated code
function pre(...$values) {
	echo "<pre>";
	foreach ($values as $v) { 
		var_dump($v);
	}
	echo "</pre>";
}

	// safe output (anti-xss)
function antiXss($s) {
	// htmlspecialchars takes special chars as "plain"/safe text
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// W3Schools-like input cleaner (trim + stripslashes)
function test_input($data) {
    $data = trim((string)$data);
    $data = stripslashes($data);
    return $data;
}

/* ================================================================
   guard: allow only POST + restrict the content to the expected type
   ================================================================ */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	http_response_code(405);
	echo "<h1>Method Not Allowed</h1>";
	echo "<p>Use POST from <code>form.html</code>.</p>";
	exit;
}

$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
if ($ctype !== '' && stripos($ctype, 'application/x-www-form-urlencoded') === false
                 && stripos($ctype, 'multipart/form-data') === false) {
    http_response_code(415);
    echo "<h1>Unsupported Media Type</h1>";
    exit;
}
/* ================================================================
   read input (clean with test_input) 
   - note: $_REQUEST can be used for $_GET + $_POST + $_COOKIE --> but it's not explicit
   - preferred $_POST for clarity
   ================================================================ */
$name       = test_input($_POST['name']     ?? ''); //text
$email      = test_input($_POST['email']    ?? ''); //email
$age        = test_input($_POST['age']      ?? ''); //text
$level      = test_input($_POST['level']    ?? ''); //select from option
$country    = test_input($_POST['country']  ?? ''); //select from option
$comment    = test_input($_POST['comment']  ?? ''); // textarea
$contact    = test_input($_POST['contact']  ?? 'email'); // radio: email|phone
$newsletter = isset($_POST['newsletter']) ? 1 : 0; // checkbox

/* ================================================================
   basic normalization/validation
   ================================================================ */
$errors = [];

if ($name === '')  {
	$errors[] = 'name is required';
}
// extra protection with preg_match that Enables correct matching of UTF-8 encoded patterns
if (!preg_match('/^[\p{L}\s]+$/u', $name)) {
    $errors[] = 'name must contain only letters (any language) and spaces';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
	$errors[] = 'valid email is required';
}

$allowedLevels  = ['A1','A2','B1','B2','C1','C2'];
$allowedCountry = ['JP','FR','UK','BR','US','AUS'];
$allowedContact = ['email','phone'];

if (!in_array($level, $allowedLevels, true)) {
	$errors[] = 'invalid level';
}
if (!in_array($country, $allowedCountry, true)) {
	$errors[] = 'invalid country';
}
if (!in_array($contact, $allowedContact, true)) {
	$errors[] = 'invalid contact method';
}

$ageInt = null;
if ($age !== '') {
	if (ctype_digit($age)) {
		$ageInt = (int)$age;
	}
	else {
		$errors[] = 'age must be an integer';
	}
}

// if errors → show error and stop
if ($errors) {
	echo "<h1>Wrong input format</h1>";
	pre($errors);
	echo '<p><a href="form.html">Back to form</a></p>';
	exit;
}

/* ================================================================
   sqlite setup (file-based: $dbFile)
   - DB path: /var/www/html/data/demo.sqlite (persist via volume)
   - create dir/table if not exists
   - use PHP Data Objects (PDO) to access SQLite
   ================================================================ */
$dbDir  = __DIR__ . '/data';
$dbFile = $dbDir . '/demo.sqlite';
if (!is_dir($dbDir)) {
	mkdir($dbDir, 0777, true);
}

try {
	$dsn = 'sqlite:' . $dbFile;
	$pdo = new PDO($dsn); // creates the connexion to SQlite directly using the path to the SQlite file
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // throw exceptions in case of error

	$pdo->exec("
		CREATE TABLE IF NOT EXISTS demo_requests (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			name TEXT NOT NULL,
			email TEXT NOT NULL,
			age INTEGER NULL,
			level TEXT NOT NULL,
			country TEXT NOT NULL,
			newsletter INTEGER NOT NULL DEFAULT 0,
			comment TEXT,
			contact TEXT NOT NULL DEFAULT 'email',
			user_agent TEXT,
			ip TEXT,
			created_at TEXT NOT NULL
		)
	");

	// prepares an INSERT template with named parameters to avoid SQL injections and work with safe/known parameters
    $stmt = $pdo->prepare("
        INSERT INTO demo_requests
        (name, email, age, level, country, newsletter, comment, contact, user_agent, ip, created_at)
        VALUES (:name, :email, :age, :level, :country, :newsletter, :comment, :contact, :ua, :ip, :ts)
    ");

	$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
	$ip = $_SERVER['REMOTE_ADDR']     ?? '';
	$ts = date('c'); // ISO8601 standard to return current time/date

	// capture metadata from the POST request
	$stmt->execute([
		':name'      => $name,
		':email'     => $email,
		':age'       => $ageInt,
		':level'     => $level,
		':country'   => $country,
		':newsletter'=> $newsletter,
		':comment'    => $comment,
        ':contact'    => $contact,
		':ua'        => $ua,
		':ip'        => $ip,
		':ts'        => $ts,
	]);
	
	// INSERT the values in the table
	$newId = (int)$pdo->lastInsertId();
} catch (Throwable $e) {
	http_response_code(500);
	echo "<h1>DB error</h1>";
	pre($e->getMessage());
	exit;
}

/* ================================================================
   success view: show summary + a few superglobals + some security
   ================================================================ */

   // set security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer-when-downgrade");
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';");

	// safe helper function antiXss() to be used when printing any input from the user
echo "<h1>Thanks, request saved!</h1>";
echo "<p>ID: <b>" . antiXss($newId) . "</b></p>";

echo "<h3>Saved payload</h3>";
echo "<ul>";
echo "<li>Name: " . antiXss($name) . "</li>";
echo "<li>Email: " . antiXss($email) . "</li>";
echo "<li>Age: " . antiXss($ageInt) . "</li>";
echo "<li>Level: " . antiXss($level) . "</li>";
echo "<li>Country: " . antiXss($country) . "</li>";
echo "<li>Newsletter: " . ($newsletter ? 'yes' : 'no') . "</li>";
echo "<li>Comment:</li>";
echo antiXss($comment);
echo "</ul>";

echo "<h3>Print Superglobals</h3>";
echo "<ul>";
echo "<li>REQUEST_METHOD: " . antiXss($_SERVER['REQUEST_METHOD'] ?? '') . "</li>";
echo "<li>REQUEST_URI: " . antiXss($_SERVER['REQUEST_URI'] ?? '') . "</li>";
echo "<li>HTTP_USER_AGENT: " . antiXss($_SERVER['HTTP_USER_AGENT'] ?? '') . "</li>";
echo "<li>REMOTE_ADDR: " . antiXss($_SERVER['REMOTE_ADDR'] ?? '') . "</li>";
echo "</ul>";

echo '<p><a href="form.html">Back to form</a></p>';