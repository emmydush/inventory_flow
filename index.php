<?php
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
    <title>DUSH teck - Smart Inventory Management System</title>
    <link rel="stylesheet" href="/public/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Landing Page Specific Styles */
        .landing-page {
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Navigation Bar */
        .navbar {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.3rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--text-primary);
        }

        .nav-brand img {
            height: 32px;
            width: auto;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
            align-items: center;
        }

        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.95rem;
            transition: color 0.3s ease;
            font-weight: 500;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .nav-buttons .btn {
            padding: 0.625rem 1.5rem;
            font-size: 0.9rem;
            border-radius: var(--border-radius-sm);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-login {
            color: var(--primary);
            border: 1px solid var(--primary);
            background: transparent;
        }

        .btn-login:hover {
            background: var(--primary);
            color: white;
        }

        .btn-signup {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
        }

        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }

        /* Hero Section */
        .hero-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 5rem 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
            min-height: calc(100vh - 80px);
        }

        .hero-content h1 {
            font-size: 3.5rem;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        .hero-content p {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.8;
        }

        .hero-features {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2.5rem;
        }

        .hero-feature-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .hero-feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(99, 102, 241, 0.2);
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            flex-shrink: 0;
            margin-top: 2px;
        }

        .hero-feature-icon svg {
            width: 20px;
            height: 20px;
        }

        .hero-feature-text h3 {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .hero-feature-text p {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .hero-btn {
            padding: 0.875rem 2rem;
            font-size: 1rem;
            border-radius: var(--border-radius-sm);
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .hero-btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .hero-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(99, 102, 241, 0.4);
        }

        .hero-btn-secondary {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .hero-btn-secondary:hover {
            background: var(--primary);
            color: white;
        }

        .hero-visual {
            position: relative;
            height: 500px;
        }

        .hero-image {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: rgba(99, 102, 241, 0.3);
            animation: float 3s ease-in-out infinite;
            overflow: hidden;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Features Section */
        .features-section {
            background: linear-gradient(180deg, rgba(99, 102, 241, 0.1) 0%, transparent 100%);
            padding: 5rem 2rem;
            border-top: 1px solid var(--border-color);
        }

        .section-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-header h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .section-header p {
            font-size: 1.1rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.15);
        }

        .feature-card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .feature-card h3 {
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
        }

        .feature-card p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Pricing Section */
        .pricing-section {
            padding: 5rem 2rem;
            border-top: 1px solid var(--border-color);
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
        }

        .pricing-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 2.5rem 2rem;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .pricing-card.featured {
            border-color: var(--primary);
            transform: scale(1.05);
        }

        .pricing-card.featured::before {
            content: 'POPULAR';
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 0.375rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .pricing-card:hover {
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.2);
        }

        .pricing-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .pricing-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .pricing-amount {
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .pricing-amount .amount {
            font-size: 2.5rem;
            font-weight: 700;
        }

        .pricing-amount .period {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .pricing-features {
            list-style: none;
            padding: 0;
            margin: 0 0 2rem 0;
            flex: 1;
        }

        .pricing-features li {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .pricing-features li:last-child {
            border-bottom: none;
        }

        .pricing-features li::before {
            content: '✓';
            color: var(--success);
            font-weight: 700;
            min-width: 20px;
        }

        .pricing-btn {
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
        }

        .pricing-card.featured .pricing-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .pricing-card.featured .pricing-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }

        .pricing-card:not(.featured) .pricing-btn {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--primary);
        }

        .pricing-card:not(.featured) .pricing-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Testimonials Section */
        .testimonials-section {
            background: linear-gradient(180deg, transparent 0%, rgba(99, 102, 241, 0.1) 100%);
            padding: 5rem 2rem;
            border-top: 1px solid var(--border-color);
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .testimonial-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .testimonial-rating {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 1rem;
        }

        .testimonial-rating svg {
            width: 16px;
            height: 16px;
            fill: var(--warning);
        }

        .testimonial-text {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            line-height: 1.6;
            font-style: italic;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .testimonial-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            flex-shrink: 0;
        }

        .testimonial-info h4 {
            font-size: 0.9rem;
            margin: 0 0 0.25rem 0;
            font-weight: 600;
        }

        .testimonial-info p {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin: 0;
        }

        /* FAQ Section */
        .faq-section {
            padding: 5rem 2rem;
            border-top: 1px solid var(--border-color);
        }

        .faq-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
        }

        .faq-item {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.1);
        }

        .faq-header {
            width: 100%;
            padding: 1.5rem;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .faq-header:hover {
            background: var(--bg-tertiary);
        }

        .faq-header:focus {
            outline: none;
            background: var(--bg-tertiary);
        }

        .faq-question {
            text-align: left;
            flex: 1;
        }

        .faq-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            flex-shrink: 0;
            transition: transform 0.3s ease;
        }

        .faq-header[aria-expanded="true"] .faq-icon {
            transform: rotate(180deg);
        }

        .faq-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            padding: 0 1.5rem;
        }

        .faq-header[aria-expanded="true"] + .faq-content {
            max-height: 500px;
            padding: 0 1.5rem 1.5rem 1.5rem;
        }

        .faq-content p {
            color: var(--text-secondary);
            line-height: 1.6;
            margin: 0;
            font-size: 0.95rem;
        }

        /* CTA Section */
        .cta-section {
            padding: 5rem 2rem;
            text-align: center;
            border-top: 1px solid var(--border-color);
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
        }

        .cta-content {
            max-width: 700px;
            margin: 0 auto;
        }

        .cta-content h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .cta-content p {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Footer */
        .footer {
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-col h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .footer-col ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-col ul li {
            margin-bottom: 0.75rem;
        }

        .footer-col ul li a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s ease;
            font-size: 0.9rem;
        }

        .footer-col ul li a:hover {
            color: var(--primary);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero-section {
                grid-template-columns: 1fr;
                padding: 3rem 2rem;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .hero-visual {
                height: 400px;
            }

            .nav-links {
                gap: 1rem;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 0;
            }

            .nav-container {
                padding: 0 1rem;
            }

            .nav-links {
                display: none;
            }

            .hero-section {
                padding: 2rem 1rem;
                min-height: auto;
            }

            .hero-content h1 {
                font-size: 2rem;
            }

            .hero-content p {
                font-size: 1rem;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .hero-btn {
                width: 100%;
                justify-content: center;
            }

            .hero-visual {
                height: 300px;
            }

            .section-header h2 {
                font-size: 2rem;
            }

            .pricing-card.featured {
                transform: scale(1);
            }

            .features-grid,
            .pricing-grid,
            .testimonials-grid,
            .faq-grid {
                grid-template-columns: 1fr;
            }

            .cta-content h2 {
                font-size: 2rem;
            }

            .footer-container {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 1.75rem;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .nav-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .nav-buttons .btn {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body class="landing-page">
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="/" class="nav-brand">
                <img src="/public/images/logo2.png" alt="DUSH teck Logo">
                DUSH teck
            </a>
            <ul class="nav-links">
                <li><a href="#features">Features</a></li>
                <li><a href="#pricing">Pricing</a></li>
                <li><a href="#testimonials">Testimonials</a></li>
                <li><a href="#faq">FAQ</a></li>
            </ul>
            <div class="nav-buttons">
                <a href="/login.php" class="btn btn-login">Login</a>
                <a href="/signup.php" class="btn btn-signup">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1>Smart Inventory Management System</h1>
            <p>Streamline your inventory operations with InventoryPro. Real-time tracking, automated alerts, and powerful analytics to help your business grow.</p>
            
            <div class="hero-features">
                <div class="hero-feature-item">
                    <div class="hero-feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                            <line x1="7" y1="7" x2="7.01" y2="7"/>
                        </svg>
                    </div>
                    <div class="hero-feature-text">
                        <h3>Real-Time Tracking</h3>
                        <p>Monitor inventory levels across all locations instantly</p>
                    </div>
                </div>
                <div class="hero-feature-item">
                    <div class="hero-feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </div>
                    <div class="hero-feature-text">
                        <h3>Smart Alerts</h3>
                        <p>Get notified about low stock and critical inventory levels</p>
                    </div>
                </div>
                <div class="hero-feature-item">
                    <div class="hero-feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </div>
                    <div class="hero-feature-text">
                        <h3>Advanced Analytics</h3>
                        <p>Get detailed insights with comprehensive reports and dashboards</p>
                    </div>
                </div>
            </div>

            <div class="hero-buttons">
                <a href="/signup.php" class="hero-btn hero-btn-primary">
                    <span>Get Started Free</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </a>
                <a href="#features" class="hero-btn hero-btn-secondary">Learn More</a>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-image">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="section-container">
            <div class="section-header">
                <h2>Powerful Features</h2>
                <p>Everything you need to manage your inventory efficiently and grow your business</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-card-icon">📦</div>
                    <h3>Inventory Management</h3>
                    <p>Manage products, categories, and stock levels with an intuitive interface. Track quantities, set minimum thresholds, and automate reordering.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-card-icon">💰</div>
                    <h3>Sales & POS</h3>
                    <p>Process sales quickly with our Point of Sale system. Support multiple payment methods and track transactions in real-time.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-card-icon">🏢</div>
                    <h3>Multi-Location</h3>
                    <p>Manage inventory across multiple locations simultaneously. Sync data in real-time and generate consolidated reports.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-card-icon">📊</div>
                    <h3>Analytics & Reports</h3>
                    <p>Gain insights with comprehensive reports on sales, inventory, and financial performance. Export data in multiple formats.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-card-icon">👥</div>
                    <h3>Customer Management</h3>
                    <p>Manage customer information, track credit sales, and maintain customer history. Build loyalty with personalized service.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-card-icon">🔒</div>
                    <h3>Security & Access Control</h3>
                    <p>Role-based access control with detailed audit logs. Keep your data secure with enterprise-grade security features.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing-section">
        <div class="section-container">
            <div class="section-header">
                <h2>Simple, Transparent Pricing</h2>
                <p>Choose the perfect plan for your business needs</p>
            </div>

            <div class="pricing-grid">
                <div class="pricing-card">
                    <h3 class="pricing-name">Starter</h3>
                    <p class="pricing-description">Perfect for small businesses</p>
                    <div class="pricing-amount">
                        <span class="amount">$29</span>
                        <span class="period">/month</span>
                    </div>
                    <ul class="pricing-features">
                        <li>Up to 1,000 products</li>
                        <li>Single location</li>
                        <li>Basic reports</li>
                        <li>Customer support</li>
                        <li>Email notifications</li>
                    </ul>
                    <a href="/signup.php" class="pricing-btn">Get Started</a>
                </div>

                <div class="pricing-card featured">
                    <h3 class="pricing-name">Professional</h3>
                    <p class="pricing-description">Most popular for growing businesses</p>
                    <div class="pricing-amount">
                        <span class="amount">$79</span>
                        <span class="period">/month</span>
                    </div>
                    <ul class="pricing-features">
                        <li>Unlimited products</li>
                        <li>Up to 5 locations</li>
                        <li>Advanced analytics</li>
                        <li>Priority support</li>
                        <li>API access</li>
                        <li>Custom reports</li>
                    </ul>
                    <a href="/signup.php" class="pricing-btn">Start Free Trial</a>
                </div>

                <div class="pricing-card">
                    <h3 class="pricing-name">Enterprise</h3>
                    <p class="pricing-description">For large organizations</p>
                    <div class="pricing-amount">
                        <span class="amount">Custom</span>
                        <span class="period">/month</span>
                    </div>
                    <ul class="pricing-features">
                        <li>Unlimited everything</li>
                        <li>Unlimited locations</li>
                        <li>Custom integration</li>
                        <li>Dedicated support</li>
                        <li>SLA guaranteed</li>
                        <li>White-label option</li>
                    </ul>
                    <a href="/signup.php" class="pricing-btn">Contact Sales</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="testimonials-section">
        <div class="section-container">
            <div class="section-header">
                <h2>What Our Customers Say</h2>
                <p>Join thousands of satisfied businesses using DUSH teck</p>
            </div>

            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-rating">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    </div>
                    <p class="testimonial-text">"InventoryPro transformed how we manage inventory. The real-time tracking has reduced our stockouts by 80% and saved us thousands."</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">JD</div>
                        <div class="testimonial-info">
                            <h4>John Doe</h4>
                            <p>Retail Manager at StyleCo</p>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="testimonial-rating">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    </div>
                    <p class="testimonial-text">"The support team is amazing. We went from manual spreadsheets to a professional system in just one week. Highly recommended!"</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">SM</div>
                        <div class="testimonial-info">
                            <h4>Sarah Miller</h4>
                            <p>Operations Director at TechHub</p>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="testimonial-rating">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    </div>
                    <p class="testimonial-text">"The ROI has been incredible. We've cut our inventory costs by 35% and improved our cash flow significantly."</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">RS</div>
                        <div class="testimonial-info">
                            <h4>Robert Singh</h4>
                            <p>Founder at ElectroMart</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="faq-section">
        <div class="section-container">
            <div class="section-header">
                <h2>Frequently Asked Questions</h2>
                <p>Find answers to common questions about InventoryPro</p>
            </div>

            <div class="faq-grid">
                <div class="faq-item">
                    <button class="faq-header" aria-expanded="false">
                        <span class="faq-question">What is DUSH teck?</span>
                        <span class="faq-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </span>
                    </button>
                    <div class="faq-content">
                        <p>DUSH teck is a comprehensive inventory management system designed to help businesses track products, monitor stock levels, manage sales, and gain valuable insights with detailed analytics and reporting.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header" aria-expanded="false">
                        <span class="faq-question">How does real-time tracking work?</span>
                        <span class="faq-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </span>
                    </button>
                    <div class="faq-content">
                        <p>DUSH teck updates inventory levels in real-time as you process sales, receive purchases, and make stock adjustments. All changes are instantly reflected across all locations, keeping your data synchronized and accurate.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header" aria-expanded="false">
                        <span class="faq-question">Can I manage multiple locations?</span>
                        <span class="faq-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </span>
                    </button>
                    <div class="faq-content">
                        <p>Yes! Depending on your plan, you can manage inventory across multiple locations with DUSH teck. Track stock levels separately for each location or view consolidated reports across all your locations.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header" aria-expanded="false">
                        <span class="faq-question">What payment methods are supported?</span>
                        <span class="faq-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </span>
                    </button>
                    <div class="faq-content">
                        <p>DUSH teck supports multiple payment methods including Cash, Credit Card, Bank Transfer, and Credit Sales. You can customize payment options based on your business needs and customer preferences.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header" aria-expanded="false">
                        <span class="faq-question">Is there a free trial available?</span>
                        <span class="faq-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </span>
                    </button>
                    <div class="faq-content">
                        <p>Yes! We offer a 14-day free trial for the Professional plan. No credit card required. You can explore all features and see if DUSH teck is right for your business before committing.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header" aria-expanded="false">
                        <span class="faq-question">How do I get customer support?</span>
                        <span class="faq-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </span>
                    </button>
                    <div class="faq-content">
                        <p>All DUSH teck plans include customer support. Starter plan includes email support, Professional plan includes priority support, and Enterprise plan includes dedicated support with guaranteed response times (SLA).</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header" aria-expanded="false">
                        <span class="faq-question">Can I export my data?</span>
                        <span class="faq-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </span>
                    </button>
                    <div class="faq-content">
                        <p>Yes! DUSH teck allows you to export your data in multiple formats including CSV, Excel, and PDF. Professional and Enterprise plans also include custom reports and advanced export options.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-header" aria-expanded="false">
                        <span class="faq-question">What about data security?</span>
                        <span class="faq-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </span>
                    </button>
                    <div class="faq-content">
                        <p>We take security seriously. DUSH teck uses enterprise-grade encryption, regular backups, role-based access control, and detailed audit logs. All data is securely stored and encrypted in transit.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="cta-content">
            <h2>Ready to Transform Your Inventory?</h2>
            <p>Join thousands of businesses that are already using DUSH teck. Start your free trial today, no credit card required.</p>
            <div class="cta-buttons">
                <a href="/signup.php" class="hero-btn hero-btn-primary">Start Free Trial</a>
                <a href="/login.php" class="hero-btn hero-btn-secondary">I Already Have an Account</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-col">
                <h3>DUSH teck</h3>
                <p>Smart inventory management for modern businesses</p>
            </div>
            <div class="footer-col">
                <h3>Product</h3>
                <ul>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#pricing">Pricing</a></li>
                    <li><a href="#">Updates</a></li>
                    <li><a href="#">Roadmap</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Company</h3>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Legal</h3>
                <ul>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Cookie Policy</a></li>
                    <li><a href="#">Security</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 DUSH teck. All rights reserved. Made with ❤️</p>
        </div>
    </footer>

    <script>
        // FAQ Accordion Functionality
        document.addEventListener('DOMContentLoaded', () => {
            const faqHeaders = document.querySelectorAll('.faq-header');

            faqHeaders.forEach(header => {
                header.addEventListener('click', () => {
                    const isExpanded = header.getAttribute('aria-expanded') === 'true';

                    // Close all other items
                    faqHeaders.forEach(otherHeader => {
                        if (otherHeader !== header) {
                            otherHeader.setAttribute('aria-expanded', 'false');
                        }
                    });

                    // Toggle current item
                    header.setAttribute('aria-expanded', !isExpanded);
                });

                // Keyboard support
                header.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        header.click();
                    }
                });
            });
        });
    </script>
</body>
</html>
