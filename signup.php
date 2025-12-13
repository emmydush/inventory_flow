<?php
require_once 'config/database.php';

session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - DUSH teck</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Auth Page Styles */
        .auth-page {
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .auth-header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem 2rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .auth-header-container {
            max-width: 500px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .auth-header-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--text-primary);
        }

        .auth-header-brand img {
            height: 32px;
            width: auto;
        }

        .auth-header-brand span {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .auth-header-nav {
            display: flex;
            gap: 1rem;
        }

        .auth-header-nav a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .auth-header-nav a:hover {
            color: var(--primary);
        }

        /* Main Content */
        .auth-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
        }

        /* Auth Container */
        .auth-container-centered {
            width: 100%;
            max-width: 450px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 3rem 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-form-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .auth-form-subtitle {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        /* Form Group */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 100%;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: 0.75rem 1rem;
            color: var(--text-primary);
            font-size: 0.9rem;
            font-family: inherit;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
            transform: translateY(-2px);
        }

        .form-group input::placeholder {
            color: var(--text-muted);
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: var(--border-radius-sm);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }

        /* Social Login */
        .social-login {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .social-btn {
            flex: 1;
            padding: 0.75rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .social-btn:hover {
            background: var(--bg-primary);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        /* Form Footer */
        .form-footer {
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            cursor: pointer;
        }

        .form-footer a:hover {
            color: var(--primary-light);
            text-decoration: underline;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: 1rem 1.25rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            z-index: 2000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
            max-width: 400px;
        }

        .toast.active {
            transform: translateY(0);
            opacity: 1;
        }

        .toast.success {
            border-left: 4px solid var(--success);
            background: linear-gradient(to right, rgba(16, 185, 129, 0.1), var(--bg-secondary));
        }

        .toast.error {
            border-left: 4px solid var(--danger);
            background: linear-gradient(to right, rgba(239, 68, 68, 0.1), var(--bg-secondary));
        }

        /* Spinner */
        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .auth-header {
                padding: 1rem 1.5rem;
            }

            .auth-header-container {
                max-width: 100%;
            }

            .auth-header-nav {
                gap: 0.5rem;
            }

            .auth-main {
                padding: 2rem 1rem;
            }

            .auth-container-centered {
                padding: 2rem 1.5rem;
                max-width: 100%;
            }

            .auth-form-title {
                font-size: 1.5rem;
            }

            .form-group input {
                padding: 0.625rem 0.875rem;
                font-size: 0.85rem;
            }

            .btn-submit {
                padding: 0.75rem;
                font-size: 0.95rem;
            }

            .toast {
                left: 1rem;
                right: 1rem;
                bottom: 1rem;
            }
        }

        @media (max-width: 480px) {
            .auth-header {
                padding: 1rem;
            }

            .auth-header-nav {
                gap: 0.25rem;
                font-size: 0.8rem;
            }

            .auth-main {
                padding: 1.5rem 1rem;
            }

            .auth-container-centered {
                padding: 1.5rem 1rem;
                border-radius: 8px;
            }

            .auth-form-title {
                font-size: 1.25rem;
            }

            .auth-form-subtitle {
                font-size: 0.85rem;
                margin-bottom: 1.5rem;
            }

            .social-login {
                gap: 0.5rem;
            }

            .social-btn {
                padding: 0.625rem;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body class="auth-page">
    <!-- Header -->
    <header class="auth-header">
        <div class="auth-header-container">
            <a href="/" class="auth-header-brand">
                <img src="/public/images/logo2.png" alt="DUSH teck Logo">
                <span>DUSH teck</span>
            </a>
            <nav class="auth-header-nav">
                <a href="/">Home</a>
                <a href="/login.php">Login</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="auth-main">
        <div class="auth-container-centered">
            <h1 class="auth-form-title">Create Account</h1>
            <p class="auth-form-subtitle">Sign up for a new account</p>

            <form id="registerForm">
                <div class="form-group">
                    <label for="registerFullName">Full Name</label>
                    <input type="text" id="registerFullName" placeholder="Enter your full name" required>
                </div>

                <div class="form-group">
                    <label for="registerUsername">Username</label>
                    <input type="text" id="registerUsername" placeholder="Choose a username" required>
                </div>

                <div class="form-group">
                    <label for="registerEmail">Email Address</label>
                    <input type="email" id="registerEmail" placeholder="Enter your email" required>
                </div>

                <div class="form-group">
                    <label for="registerPassword">Password</label>
                    <input type="password" id="registerPassword" placeholder="Create a password" required>
                </div>

                <div class="form-group">
                    <label for="registerConfirmPassword">Confirm Password</label>
                    <input type="password" id="registerConfirmPassword" placeholder="Confirm your password" required>
                </div>

                <button type="submit" class="btn-submit">Create Account</button>

                <div class="divider">
                    <span>or sign up with</span>
                </div>

                <div class="social-login">
                    <button type="button" class="social-btn" title="Google">
                        <i class="fab fa-google"></i>
                    </button>
                    <button type="button" class="social-btn" title="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </button>
                    <button type="button" class="social-btn" title="LinkedIn">
                        <i class="fab fa-linkedin-in"></i>
                    </button>
                </div>

                <div class="form-footer">
                    <p>Already have an account? <a href="/login.php">Sign in here</a></p>
                </div>
            </form>
        </div>
    </main>

    <div class="toast" id="toast"><span id="toastMessage"></span></div>

    <script src="/public/js/auth.js"></script>
</body>
</html>
