<?php
require_once __DIR__ . '/includes/config.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" href="./assets/headerIcon.png" type="image/x-icon">
<title>Access Denied — NPATS</title>
<script>
(function() {
    var theme = localStorage.getItem('npats_theme');
    if (theme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
    } else {
        document.documentElement.removeAttribute('data-theme');
    }
})();
</script>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ============================================================
   NPATS PREMIUM STYLES — Unauthorized Page
   ============================================================ */

:root {
    /* Dark Theme (Default) */
    --bg: #0A0F1A;
    --bg-alt: #111827;
    --surface: #1A2333;
    --surface-alt: #1F2A3E;
    --text: #E2E8F4;
    --text-soft: #CBD5E1;
    --muted: #6B7898;
    --border: #2A3A55;
    --border-mid: #3A4A6A;
    --navy: #0B2545;
    --navy-light: #3B82F6;
    --gold: #C8911A;
    --gold-light: #E8B040;
    --danger: #EF4444;
    --danger-bg: rgba(239,68,68,0.1);
    --warning: #F59E0B;
    --warning-bg: rgba(245,158,11,0.1);
    --success: #10B981;
    --success-bg: rgba(16,185,129,0.1);
    --info: #3B82F6;
    --info-bg: rgba(59,130,246,0.1);
    --radius: 12px;
    --radius-lg: 20px;
    --shadow: 0 20px 35px -10px rgba(0,0,0,0.3);
    --shadow-sm: 0 4px 12px rgba(0,0,0,0.15);
}

html[data-theme="light"] {
    --bg: #F3F6FC;
    --bg-alt: #FFFFFF;
    --surface: #F8FAFE;
    --surface-alt: #F0F4FA;
    --text: #1A2C3E;
    --text-soft: #2C3E50;
    --muted: #6C86A3;
    --border: #E2E8F0;
    --border-mid: #CBD5E1;
    --navy: #1E3A8A;
    --navy-light: #2563EB;
    --gold: #B8860B;
    --gold-light: #D4A017;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'DM Sans', system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1.5;
}

/* Animation Keyframes */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse-ring {
    0% { transform: scale(0.8); opacity: 0.5; }
    100% { transform: scale(1.4); opacity: 0; }
}

@keyframes rotateBorder {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

@keyframes gradientShift {
    0% {
        background-position: 0% 0%;
    }
    50% {
        background-position: 100% 100%;
    }
    100% {
        background-position: 0% 0%;
    }
}

/* Main Container */
.unauthorized-container {
    animation: fadeIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
    width: 100%;
    max-width: 480px;
    padding: 1rem;
}

/* Premium Card with Rotating Border */
.premium-card {
    position: relative;
    background: var(--bg-alt);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.premium-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 30px 45px -15px rgba(0,0,0,0.4);
}

/* Animated Border Container */
.animated-border {
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    border-radius: calc(var(--radius-lg) + 2px);
    overflow: hidden;
    z-index: 0;
}

.animated-border::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: conic-gradient(
        from 0deg,
        transparent 0deg,
        var(--danger) 80deg,
        transparent 160deg,
        var(--danger) 240deg,
        transparent 360deg
    );
    animation: rotateBorder 5s linear infinite;
}

/* Inner card content with higher z-index */
.card-inner {
    position: relative;
    z-index: 1;
    background: var(--bg-alt);
    border-radius: var(--radius-lg);
    margin: 2px;
    padding: 2.5rem 2rem;
    text-align: center;
}

/* Icon Section */
.icon-wrapper {
    position: relative;
    display: inline-block;
    margin-bottom: 1.5rem;
}

.icon-circle {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, var(--danger-bg), rgba(239,68,68,0.05));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    position: relative;
    border: 1px solid rgba(239,68,68,0.3);
}

.icon-circle i {
    font-size: 3.5rem;
    color: var(--danger);
}

.pulse-ring {
    position: absolute;
    width: 100%;
    height: 100%;
    background: rgba(239,68,68,0.3);
    border-radius: 50%;
    animation: pulse-ring 1.5s infinite;
}

/* Headings */
.access-denied {
    font-size: 2.2rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--danger), #F87171);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    margin-bottom: 0.5rem;
    letter-spacing: -0.02em;
}

.subtitle {
    font-size: 0.9rem;
    color: var(--muted);
    margin-bottom: 1.5rem;
}

/* Divider */
.divider {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.8rem;
    margin: 1.5rem 0;
}

.divider-line {
    width: 60px;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--border), transparent);
}

.divider-dot {
    width: 6px;
    height: 6px;
    background: var(--gold-light);
    border-radius: 50%;
}

/* Message Box */
.message-box {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 1rem 1.25rem;
    margin: 1.25rem 0;
    text-align: left;
}

.message-box i {
    color: var(--danger);
    margin-right: 0.5rem;
}

.message-box p {
    font-size: 0.85rem;
    color: var(--text-soft);
    line-height: 1.5;
}

/* Help Text */
.help-text {
    font-size: 0.8rem;
    color: var(--muted);
    margin: 1rem 0 1.5rem;
}

.help-text a {
    color: var(--navy-light);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}

.help-text a:hover {
    color: var(--gold-light);
    text-decoration: underline;
}

/* Button */
.btn-return {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    background: linear-gradient(135deg, var(--navy-light), #1D4ED8);
    border: none;
    padding: 0.85rem 1.8rem;
    border-radius: var(--radius);
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    text-decoration: none;
    width: 100%;
}

.btn-return:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(59,130,246,0.3);
}

.btn-return i {
    font-size: 1rem;
    transition: transform 0.2s;
}

.btn-return:hover i {
    transform: translateX(-3px);
}

/* Footer */
.footer-note {
    margin-top: 1.5rem;
    font-size: 0.7rem;
    color: var(--muted);
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.footer-note i {
    font-size: 0.6rem;
}

/* Responsive */
@media (max-width: 480px) {
    .card-inner {
        padding: 1.8rem 1.5rem;
    }
    
    .icon-circle {
        width: 80px;
        height: 80px;
    }
    
    .icon-circle i {
        font-size: 2.8rem;
    }
    
    .access-denied {
        font-size: 1.8rem;
    }
    
    .btn-return {
        padding: 0.7rem 1.5rem;
    }
}
</style>
</head>
<body>
<div class="unauthorized-container">
    <div class="premium-card">
        <!-- Animated rotating border -->
        <div class="animated-border"></div>
        
        <div class="card-inner">
            
            <!-- Animated Icon -->
            <div class="icon-wrapper">
                <div class="pulse-ring"></div>
                <div class="icon-circle">
                    <i class="fa fa-ban"></i>
                </div>
            </div>
            
            <!-- Title -->
            <h1 class="access-denied">Access Denied</h1>
            <p class="subtitle">You don't have permission to access this page</p>
            
            <!-- Divider -->
            <div class="divider">
                <span class="divider-line"></span>
                <span class="divider-dot"></span>
                <span class="divider-line"></span>
            </div>
            
            <!-- Message Box -->
            <div class="message-box">
                <i class="fa fa-shield-alt"></i>
                <p>Your account role does not have the required privileges to view this resource. If you believe this is an error, please contact your system administrator.</p>
            </div>
            
            <!-- Help Text -->
            <div class="help-text">
                <i class="fa fa-question-circle"></i> Need help? 
                <a href="<?= APP_URL ?>/index.php">Return to login page</a> or contact support.
            </div>
            
            <!-- Action Button -->
            <a href="<?= APP_URL ?>/index.php" class="btn-return">
                <i class="fa fa-home"></i>
                Return to Login
            </a>
            
            <!-- Footer -->
            <div class="footer-note">
                <i class="fa fa-lock"></i>
                <span>NPATS — National Passport Application Tracking System</span>
            </div>
            
        </div>
    </div>
</div>
</body>
</html>