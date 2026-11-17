<?php
session_start();
require_once "config/db_connect.php";

$error = "";

// Redirect users who are already logged in.
if (isset($_SESSION['role'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $uname = trim($_POST['username'] ?? '');
    $pword = $_POST['password'] ?? '';

    if ($uname === '' || $pword === '') {
        $error = "Please enter both your username and password.";
    } else {
        // Use a prepared statement to prevent SQL injection.
        $stmt = mysqli_prepare(
            $conn,
            "SELECT user_id, username, full_name, role, password
             FROM users
             WHERE username = ?
             LIMIT 1"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $uname);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) === 1) {
                $row = mysqli_fetch_assoc($result);
                $stored_password = $row['password'];

                /*
                 * Supports existing plaintext demo passwords and
                 * password_hash() passwords.
                 * For production, migrate all passwords to hashes.
                 */
                $password_matches = password_verify($pword, $stored_password);

                if (!$password_matches) {
                    $password_matches = hash_equals(
                        (string) $stored_password,
                        (string) $pword
                    );
                }

                if ($password_matches) {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['full_name'] = $row['full_name'];
                    $_SESSION['role'] = $row['role'];

                    header("Location: dashboard.php");
                    exit();
                }
            }

            mysqli_stmt_close($stmt);
        }

        // Keep the error generic so account details are not revealed.
        $error = "Invalid username or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign In | Warehouse OS</title>
    
    <script src="js/app.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">

    <style>
        body {
            min-height: 100vh;
            background: #f1f5f9;
        }

        .login-wrapper {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        /* Left branding panel */
        .login-brand {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 48px;
            color: #ffffff;
            background:
                radial-gradient(circle at 15% 15%, rgba(14, 165, 233, .25), transparent 35%),
                linear-gradient(145deg, #0f172a, #172554 65%, #075985);
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -.5px;
        }

        .brand-logo-icon {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            color: #ffffff;
            background: rgba(56, 189, 248, .18);
            border: 1px solid rgba(125, 211, 252, .3);
        }

        .brand-content {
            max-width: 510px;
            margin: 50px auto;
            position: relative;
            z-index: 1;
        }

        .brand-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #bae6fd;
            background: rgba(14, 165, 233, .13);
            border: 1px solid rgba(125, 211, 252, .2);
            border-radius: 50px;
            padding: 8px 13px;
            font-size: .78rem;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .brand-content h1 {
            font-size: clamp(2.1rem, 4vw, 3.5rem);
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: -1.5px;
            margin-bottom: 20px;
        }

        .brand-content h1 span {
            color: #38bdf8;
        }

        .brand-content p {
            max-width: 430px;
            color: #cbd5e1;
            font-size: 1rem;
            line-height: 1.8;
        }

        .brand-features {
            display: grid;
            gap: 17px;
            margin-top: 34px;
        }

        .brand-feature {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #e2e8f0;
            font-size: .9rem;
        }

        .brand-feature i {
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            color: #7dd3fc;
            background: rgba(56, 189, 248, .12);
            border-radius: 9px;
        }

        .brand-footer {
            color: #94a3b8;
            font-size: .8rem;
        }

        /* Right login panel */
        .login-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
            background: #f8fafc;
        }

        .login-card {
            width: 100%;
            max-width: 430px;
            padding: 38px 34px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .07);
        }

        .login-heading {
            margin-bottom: 28px;
        }

        .login-heading h2 {
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: -.7px;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .login-heading p {
            color: #64748b;
            margin: 0;
            font-size: .9rem;
            line-height: 1.6;
        }

        .login-label {
            color: #334155;
            font-size: .85rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-input-group {
            display: flex;
            align-items: stretch;
        }

        .login-input-group .input-icon {
            width: 45px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            color: #64748b;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-right: 0;
            border-radius: 9px 0 0 9px;
        }

        .login-input-group .form-control {
            min-width: 0;
            min-height: 47px;
            border-radius: 0 9px 9px 0;
        }

        .login-input-group.has-toggle .form-control {
            border-radius: 0;
        }

        .password-toggle {
            min-width: 46px;
            border: 1px solid #cbd5e1;
            border-left: 0;
            border-radius: 0 9px 9px 0;
            background: #ffffff;
            color: #64748b;
        }

        .password-toggle:hover {
            background: #f1f5f9;
            color: #0284c7;
        }

        .login-submit {
            min-height: 48px;
            border-radius: 9px;
            font-weight: 700;
            box-shadow: 0 5px 12px rgba(2, 132, 199, .15);
        }

        .security-note {
            color: #94a3b8;
            text-align: center;
            font-size: .75rem;
            line-height: 1.6;
            margin-top: 28px;
        }

        @media (max-width: 900px) {
            .login-wrapper {
                grid-template-columns: 1fr;
            }

            .login-brand {
                padding: 26px;
            }

            .brand-content {
                margin: 45px 0 25px;
            }

            .brand-content h1 {
                font-size: 2.2rem;
            }

            .brand-features {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .brand-footer {
                margin-top: 15px;
            }

            .login-panel {
                padding: 30px 20px;
            }
        }

        @media (max-width: 480px) {
            .login-brand {
                padding: 22px;
            }

            .brand-content h1 {
                font-size: 1.9rem;
            }

            .brand-features {
                grid-template-columns: 1fr;
            }

            .login-panel {
                padding: 20px 12px;
            }

            .login-card {
                padding: 26px 20px;
                border-radius: 14px;
            }
        }
    </style>
</head>

<body>

<div class="login-wrapper">

    <!-- Branding section -->
    <section class="login-brand">

        <div class="brand-logo">
            <div class="brand-logo-icon">
                <i class="fa-solid fa-boxes-stacked fa-xl"></i>
            </div>
            <span>Warehouse OS</span>
        </div>

        <div class="brand-content">

            <div class="brand-eyebrow">
                <i class="fa-solid fa-circle-check"></i>
                SMART INVENTORY MANAGEMENT
            </div>

            <h1>
                Manage your warehouse.
                <span>Move smarter.</span>
            </h1>

            <p>
                Keep track of products, stock quantities, warehouse locations
                and inventory reports from one simple dashboard.
            </p>

            <div class="brand-features">
                <div class="brand-feature">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Centralized product inventory</span>
                </div>

                <div class="brand-feature">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Visual inventory analytics</span>
                </div>

                <div class="brand-feature">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>Stock activity tracking</span>
                </div>

                <div class="brand-feature">
                    <i class="fa-solid fa-user-shield"></i>
                    <span>Role-based access</span>
                </div>
            </div>

        </div>

        <div class="brand-footer">
            &copy; <?php echo date('Y'); ?> Warehouse OS
            <span class="mx-2">•</span>
            Warehouse Management System
        </div>

    </section>

    <!-- Login form section -->
    <section class="login-panel">

        <div class="login-card">

            <div class="login-heading">
                <div class="d-flex align-items-center gap-2 mb-3 d-lg-none">
                    <i class="fa-solid fa-boxes-stacked text-primary fa-lg"></i>
                    <strong>Warehouse OS</strong>
                </div>

                <h2>Welcome back</h2>
                <p>Enter your assigned account details to access the system.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"
                    ></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php">
                <div class="mb-3">
                    <label for="usernameField" class="login-label">Username</label>

                    <div class="login-input-group">
                        <span class="input-icon">
                            <i class="fa-regular fa-user"></i>
                        </span>

                        <input
                            type="text"
                            id="usernameField"
                            name="username"
                            class="form-control"
                            placeholder="Enter your username"
                            autocomplete="username"
                            maxlength="100"
                            required
                            autofocus
                            value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        >
                    </div>
                </div>

                <div class="mb-4">
                    <label for="passwordField" class="login-label">Password</label>

                    <div class="login-input-group has-toggle">
                        <span class="input-icon">
                            <i class="fa-solid fa-lock"></i>
                        </span>

                        <input
                            type="password"
                            id="passwordField"
                            name="password"
                            class="form-control"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            id="togglePassword"
                            aria-label="Show password"
                            aria-pressed="false"
                        >
                            <i class="fa-regular fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary login-submit w-100">
                    Sign In
                    <i class="fa-solid fa-arrow-right ms-2"></i>
                </button>
            </form>

            <div class="security-note">
                <i class="fa-solid fa-shield-halved me-1"></i>
                Authorized users only. Your access depends on your assigned role.
            </div>

        </div>

    </section>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Show or hide the password.
const toggleBtn = document.getElementById('togglePassword');
const passField = document.getElementById('passwordField');
const eyeIcon = document.getElementById('eyeIcon');

toggleBtn.addEventListener('click', function () {
    const showPassword = passField.type === 'password';

    passField.type = showPassword ? 'text' : 'password';
    eyeIcon.classList.toggle('fa-eye', !showPassword);
    eyeIcon.classList.toggle('fa-eye-slash', showPassword);

    toggleBtn.setAttribute('aria-pressed', String(showPassword));
    toggleBtn.setAttribute(
        'aria-label',
        showPassword ? 'Hide password' : 'Show password'
    );
});
</script>

</body>
</html>