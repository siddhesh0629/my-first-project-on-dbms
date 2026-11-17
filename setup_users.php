<?php
// Run only from the command line: php setup_users.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/config/db_connect.php';

function ask($prompt) {
    echo $prompt;
    $value = fgets(STDIN);
    if ($value === false) { exit("Input cancelled.\n"); }
    return trim($value);
}

$accounts = [
    ['admin1', 'System Administrator', 'ADMIN'],
    ['stock_mgr', 'Inventory Controller', 'STOCK_MANAGER'],
    ['user1', 'General Auditor', 'USER'],
];
$stmt = mysqli_prepare($conn, 'INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password), full_name = VALUES(full_name), role = VALUES(role)');
if (!$stmt) { exit("Could not prepare user creation query.\n"); }
foreach ($accounts as [$username, $fullName, $role]) {
    echo "\nCreate password for {$username} ({$role}). Input will be visible.\n";
    do {
        $password = ask('Password (at least 12 characters): ');
        if (strlen($password) < 12) { echo "Please use at least 12 characters.\n"; }
    } while (strlen($password) < 12);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    mysqli_stmt_bind_param($stmt, 'ssss', $username, $hash, $fullName, $role);
    if (!mysqli_stmt_execute($stmt)) { exit("Could not create {$username}.\n"); }
    echo "Created/updated {$username}.\n";
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
echo "\nDemo accounts ready. Do not reuse important passwords.\n";
