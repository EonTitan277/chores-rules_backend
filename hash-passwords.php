<?php
/**
 * Hash a password using PHP's password_hash() (bcrypt by default).
 * Usage: php hash-passwords.php "your_password"
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php hash-passwords.php \"your_password\"\n");
    exit(1);
}

$password = $argv[1];

// Use PASSWORD_DEFAULT (currently bcrypt) with cost 12 to match the original script's saltRounds
$hash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);

if ($hash === false) {
    fwrite(STDERR, "Error hashing password\n");
    exit(1);
}

echo "Store this hash: $hash\n";