<?php
/**
 * ReportMyCity — Multi-Step User Registration Page
 */
session_start();
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user') {
    header('Location: user/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ReportMyCity — Create your citizen account to start reporting civic issues.">
    <title>Citizen Registration — ReportMyCity Official Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
    /* ===== RESET & BASE ===== */
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html { scroll-behavior:smooth; }

    body {
        font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
        background: #f0f4f8;
        color: #1a2540;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        overflow-x: hidden;
    }


    /* Particle canvas hidden for govt theme */
    #reg-canvas { display: none; }
    .ambient-glow { display: none; }

    /* ===== BACK LINK ===== */
    .reg-nav-back {
        margin-left: auto;
        padding-right: 2rem;
    }
    .reg-nav-back .back-link {
        font-size: 0.8rem;
        color: var(--gov-navy);
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 700;
        transition: color 0.2s;
    }
    .reg-nav-back .back-link:hover { color: var(--gov-gold); }

    /* ===== PAGE WRAPPER ===== */
    .reg-page {
        position: relative;
        z-index: 10;
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        min-height: calc(100vh - 70px);
        background: linear-gradient(135deg, rgba(10,37,88,0.04) 0%, transparent 60%), #f0f4f8;
    }

    /* ===== CARD ===== */
    .reg-card {
        background: #ffffff;
        border: 1px solid #d1dae8;
        border-top: 5px solid #0a2558;
        border-radius: 10px;
        padding: 40px 44px;
        width: 100%;
        max-width: 520px;
        box-shadow: 0 8px 32px rgba(10,37,88,0.12);
        position: relative;
    }

    /* ===== STEP PROGRESS BAR ===== */
    .step-progress {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 32px;
        gap: 0;
    }

    .step-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
    }

    .step-circle {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 700;
        border: 2px solid #d1dae8;
        background: #f8faff;
        color: #9ba8c0;
        transition: all 0.35s ease;
        z-index: 1;
    }

    .step-circle.active {
        background: #0a2558;
        border-color: #0a2558;
        color: white;
        box-shadow: 0 0 0 4px rgba(10,37,88,0.12);
    }

    .step-circle.done {
        background: #1a7f4b;
        border-color: #1a7f4b;
        color: white;
        box-shadow: 0 0 0 4px rgba(26,127,75,0.12);
    }

    .step-label {
        font-size: 10px;
        color: #9ba8c0;
        font-weight: 600;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        transition: color 0.3s;
    }

    .step-item.active .step-label  { color: #0a2558; }
    .step-item.done .step-label    { color: #1a7f4b; }

    .step-line {
        flex: 1;
        height: 2px;
        background: #d1dae8;
        margin: 0 4px;
        margin-bottom: 22px;
        min-width: 40px;
        transition: background 0.4s ease;
    }

    .step-line.done {
        background: #1a7f4b;
    }

    /* ===== CARD HEADER ===== */
    .card-header {
        text-align: center;
        margin-bottom: 28px;
        padding-bottom: 20px;
        border-bottom: 2px solid #f0f4f8;
    }

    .card-header .step-icon {
        font-size: 2.2rem;
        margin-bottom: 10px;
        display: block;
    }

    .card-header h2 {
        font-size: 1.35rem;
        font-weight: 700;
        color: #0a2558;
        margin-bottom: 6px;
        font-family: 'Noto Serif', serif;
    }

    .card-header p {
        font-size: 0.85rem;
        color: #6b7d9f;
        line-height: 1.6;
    }

    /* ===== FORM ELEMENTS ===== */
    .form-group {
        margin-bottom: 18px;
    }

    .form-group label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: #3a4a6b;
        margin-bottom: 6px;
        letter-spacing: 0.02em;
    }

    .form-group .input-wrap {
        position: relative;
    }

    .form-group .input-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 14px;
        pointer-events: none;
    }

    .form-group input {
        width: 100%;
        background: #ffffff;
        border: 1px solid #d1dae8;
        border-radius: 5px;
        padding: 11px 12px 11px 38px;
        font-size: 0.9rem;
        font-family: 'Inter', sans-serif;
        color: #1a2540;
        transition: border-color 0.25s, box-shadow 0.25s;
        outline: none;
    }

    .form-group input::placeholder { color: #9ba8c0; }

    .form-group input:focus {
        border-color: #0a2558;
        box-shadow: 0 0 0 3px rgba(10,37,88,0.1);
    }

    .form-group input.error-field {
        border-color: #b91c1c;
        box-shadow: 0 0 0 3px rgba(185,28,28,0.1);
    }

    .form-group .field-error {
        font-size: 0.75rem;
        color: #b91c1c;
        margin-top: 4px;
        display: none;
    }

    .form-group .field-hint {
        font-size: 0.72rem;
        color: #9ba8c0;
        margin-top: 4px;
    }

    /* Phone row */
    .phone-row {
        display: flex;
        gap: 8px;
    }

    .phone-prefix {
        background: #f0f4f8;
        border: 1px solid #d1dae8;
        border-radius: 5px;
        padding: 11px 12px;
        font-size: 0.85rem;
        color: #3a4a6b;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 5px;
        flex-shrink: 0;
        font-weight: 500;
    }

    .phone-row .form-group { flex: 1; margin-bottom: 0; }
    .phone-row .form-group input { padding-left: 12px; }

    /* ===== OTP INPUT ===== */
    .otp-row {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin: 8px 0 6px;
    }

    .otp-box {
        width: 52px;
        height: 56px;
        background: #ffffff;
        border: 2px solid #d1dae8;
        border-radius: 6px;
        text-align: center;
        font-size: 1.4rem;
        font-weight: 700;
        color: #0a2558;
        font-family: 'Inter', sans-serif;
        outline: none;
        transition: border-color 0.25s, box-shadow 0.25s;
        caret-color: #0a2558;
    }

    .otp-box:focus {
        border-color: #0a2558;
        box-shadow: 0 0 0 3px rgba(10,37,88,0.12);
    }

    .otp-box.filled { border-color: #1a7f4b; background: #e8f5ee; }

    .otp-info {
        font-size: 0.85rem;
        color: #6b7d9f;
        text-align: center;
        margin-bottom: 6px;
    }

    .otp-info .phone-highlight {
        color: #0a2558;
        font-weight: 700;
    }

    .resend-row {
        text-align: center;
        margin-top: 12px;
        font-size: 0.82rem;
        color: #9ba8c0;
    }

    .resend-btn {
        background: none;
        border: none;
        color: #0a2558;
        cursor: pointer;
        font-size: 0.82rem;
        font-family: 'Inter', sans-serif;
        font-weight: 700;
        padding: 0;
        transition: opacity 0.3s;
    }

    .resend-btn:disabled { opacity: 0.4; cursor: not-allowed; }

    #otp-timer { color: #9ba8c0; }

    /* ===== PASSWORD STRENGTH ===== */
    .strength-bar {
        height: 4px;
        border-radius: 4px;
        background: rgba(255,255,255,0.08);
        margin-top: 8px;
        overflow: hidden;
    }

    .strength-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.4s ease, background 0.4s ease;
        width: 0%;
    }

    .strength-label {
        font-size: 11px;
        margin-top: 4px;
        font-weight: 600;
    }

    /* Password toggle */
    .pw-wrap { position: relative; }
    .pw-wrap input { padding-right: 42px !important; }
    .pw-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        font-size: 16px;
        color: #64748b;
        transition: color 0.3s;
        padding: 0;
    }
    .pw-toggle:hover { color: #38bdf8; }

    /* ===== BUTTONS ===== */
    .btn-next {
        width: 100%;
        padding: 13px;
        border: none;
        border-radius: 5px;
        font-size: 0.9rem;
        font-weight: 700;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        background: #0a2558;
        color: white;
        border: 1px solid #071840;
        box-shadow: 0 2px 12px rgba(10,37,88,0.2);
        transition: all 0.25s ease;
        margin-top: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .btn-next:hover {
        background: #071840;
        transform: translateY(-1px);
        box-shadow: 0 4px 18px rgba(10,37,88,0.28);
    }

    .btn-next:active { transform: scale(0.99); }

    .btn-back {
        width: 100%;
        padding: 11px;
        border: 1px solid #d1dae8;
        border-radius: 5px;
        font-size: 0.85rem;
        font-weight: 600;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        background: transparent;
        color: #6b7d9f;
        transition: all 0.25s ease;
        margin-top: 8px;
    }

    .btn-back:hover {
        border-color: #0a2558;
        color: #0a2558;
        background: #f0f5ff;
    }

    /* ===== ALERT ===== */
    .alert {
        padding: 10px 14px;
        border-radius: 5px;
        font-size: 0.82rem;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
        border-left: 4px solid transparent;
        font-family: 'Inter', sans-serif;
    }

    .alert-error   { background: #fef2f2; border-left-color: #b91c1c; color: #b91c1c; border: 1px solid rgba(185,28,28,0.2); border-left: 4px solid #b91c1c; }
    .alert-success { background: #e8f5ee; border-left-color: #1a7f4b; color: #1a7f4b; border: 1px solid rgba(26,127,75,0.2); border-left: 4px solid #1a7f4b; }

    /* ===== SUCCESS STEP ===== */
    .success-content {
        text-align: center;
        padding: 20px 0;
    }

    .success-ring {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(26,127,75,0.12), rgba(10,37,88,0.12));
        border: 2px solid rgba(26,127,75,0.35);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 22px;
        font-size: 2.5rem;
        animation: popIn 0.6s cubic-bezier(0.175,0.885,0.32,1.275) both;
        box-shadow: 0 0 30px rgba(26,127,75,0.18);
    }

    @keyframes popIn {
        0%   { transform: scale(0); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }

    .success-content h2 {
        font-size: 1.5rem;
        font-weight: 800;
        color: #0a2558;
        margin-bottom: 10px;
        font-family: 'Noto Serif', serif;
    }

    .success-content p {
        font-size: 0.88rem;
        color: #6b7d9f;
        line-height: 1.7;
        margin-bottom: 24px;
    }

    .success-name {
        color: #0a2558;
        font-weight: 700;
    }

    /* ===== INFO CHIPS ===== */
    .info-chips {
        display: flex;
        gap: 10px;
        justify-content: center;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }

    .chip {
        padding: 5px 12px;
        border-radius: 3px;
        font-size: 0.72rem;
        font-weight: 700;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        color: #0a2558;
        display: flex;
        align-items: center;
        gap: 5px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    /* ===== FOOTER LINK ===== */
    .reg-footer-link {
        text-align: center;
        margin-top: 18px;
        font-size: 0.82rem;
        color: #9ba8c0;
    }

    .reg-footer-link a {
        color: #0a2558;
        text-decoration: none;
        font-weight: 700;
        transition: opacity 0.2s;
    }

    .reg-footer-link a:hover { opacity: 0.75; }

    /* ===== DIVIDER ===== */
    .or-divider {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 18px 0;
        color: #334155;
        font-size: 12px;
    }
    .or-divider::before, .or-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: rgba(255,255,255,0.06);
    }

    /* ===== STEP PANELS ===== */
    .step-panel { display: none; animation: fadeInUp 0.4s ease; }
    .step-panel.active { display: block; }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(18px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ===== SPINNER ===== */
    .spinner {
        width: 18px;
        height: 18px;
        border: 2.5px solid rgba(255,255,255,0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.7s linear infinite;
        display: none;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 560px) {
        .reg-card { padding: 32px 24px; }
        .reg-nav { padding: 12px 20px; }
        .otp-box { width: 42px; height: 50px; font-size: 18px; }
    }
    </style>
</head>
<body>

<canvas id="reg-canvas"></canvas>
<div class="ambient-glow"></div>

<!-- Government Header -->
<div class="auth-gov-header">
    <img src="assets/images/govt_emblem.png" alt="Government Emblem" class="emblem">

    <div class="portal-text">
        <h1>ReportMyCity — Citizen Registration</h1>
        <p>Ministry of Urban Development &amp; Civic Affairs · Government of India</p>
    </div>

    <div class="reg-nav-back">
        <a href="index.php" class="back-link">← Back to Portal Home</a>
    </div>
</div>

<!-- Page -->
<div class="reg-page">
    <div class="reg-card">

        <!-- ===== STEP PROGRESS ===== -->
        <div class="step-progress" id="stepProgress">
            <div class="step-item active" id="si-1">
                <div class="step-circle active" id="sc-1">1</div>
                <span class="step-label">Details</span>
            </div>
            <div class="step-line" id="sl-1"></div>
            <div class="step-item" id="si-2">
                <div class="step-circle" id="sc-2">2</div>
                <span class="step-label">Verify OTP</span>
            </div>
            <div class="step-line" id="sl-2"></div>
            <div class="step-item" id="si-3">
                <div class="step-circle" id="sc-3">3</div>
                <span class="step-label">Password</span>
            </div>
            <div class="step-line" id="sl-3"></div>
            <div class="step-item" id="si-4">
                <div class="step-circle" id="sc-4">✓</div>
                <span class="step-label">Done</span>
            </div>
        </div>

        <!-- ===== STEP 1: Name + Phone ===== -->
        <div class="step-panel active" id="panel-1">
            <div class="card-header">
                <span class="step-icon"><i class="la la-user-o"></i></span>
                <h2>Create Your Account</h2>
                <p>Enter your name and mobile number to get started as a citizen.</p>
            </div>

            <div id="alert-1"></div>

            <div class="form-group">
                <label for="inp-name">Full Name</label>
                <div class="input-wrap">
                    <span class="input-icon"><i class="la la-pencil-square-o"></i></span>
                    <input type="text" id="inp-name" placeholder="e.g. Rahul Sharma" autocomplete="name">
                </div>
                <span class="field-error" id="err-name">Please enter your full name.</span>
            </div>

            <div class="form-group">
                <label for="inp-state">State / UT</label>
                <div class="input-wrap">
                    <span class="input-icon"><i class="la la-map-marker"></i></span>
                    <select id="inp-state" style="width:100%; padding:11px 12px 11px 38px; border:1px solid #d1dae8; border-radius:5px; font-size:0.9rem; font-family:'Inter', sans-serif; height:45px; background:white; outline:none; appearance:none;">
                        <option value="">Select State / UT...</option>
                        <option value="Andaman and Nicobar Islands">Andaman and Nicobar Islands</option>
                        <option value="Andhra Pradesh">Andhra Pradesh</option>
                        <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                        <option value="Assam">Assam</option>
                        <option value="Bihar">Bihar</option>
                        <option value="Chandigarh">Chandigarh</option>
                        <option value="Chhattisgarh">Chhattisgarh</option>
                        <option value="Dadra and Nagar Haveli and Daman and Diu">Dadra and Nagar Haveli and Daman and Diu</option>
                        <option value="Delhi">Delhi</option>
                        <option value="Goa">Goa</option>
                        <option value="Gujarat">Gujarat</option>
                        <option value="Haryana">Haryana</option>
                        <option value="Himachal Pradesh">Himachal Pradesh</option>
                        <option value="Jammu and Kashmir">Jammu and Kashmir</option>
                        <option value="Jharkhand">Jharkhand</option>
                        <option value="Karnataka">Karnataka</option>
                        <option value="Kerala">Kerala</option>
                        <option value="Ladakh">Ladakh</option>
                        <option value="Lakshadweep">Lakshadweep</option>
                        <option value="Madhya Pradesh">Madhya Pradesh</option>
                        <option value="Maharashtra">Maharashtra</option>
                        <option value="Manipur">Manipur</option>
                        <option value="Meghalaya">Meghalaya</option>
                        <option value="Mizoram">Mizoram</option>
                        <option value="Nagaland">Nagaland</option>
                        <option value="Odisha">Odisha</option>
                        <option value="Puducherry">Puducherry</option>
                        <option value="Punjab">Punjab</option>
                        <option value="Rajasthan">Rajasthan</option>
                        <option value="Sikkim">Sikkim</option>
                        <option value="Tamil Nadu">Tamil Nadu</option>
                        <option value="Telangana">Telangana</option>
                        <option value="Tripura">Tripura</option>
                        <option value="Uttar Pradesh">Uttar Pradesh</option>
                        <option value="Uttarakhand">Uttarakhand</option>
                        <option value="West Bengal">West Bengal</option>
                    </select>
                </div>
                <span class="field-error" id="err-state">Please select your state.</span>
            </div>

            <div class="form-group">
                <label for="inp-district">District / City</label>
                <div class="input-wrap">
                    <span class="input-icon">🏙️</span>
                    <input type="text" id="inp-district" placeholder="e.g. Mumbai" autocomplete="address-level2">
                </div>
                <span class="field-error" id="err-district">Please enter your district or city.</span>
            </div>

            <div class="form-group">
                <label for="inp-phone">Mobile Number</label>
                <div class="phone-row">
                    <div class="phone-prefix">🇮🇳 +91</div>
                    <div class="form-group" style="flex:1; margin-bottom:0;">
                        <div class="input-wrap">
                            <input type="tel" id="inp-phone" placeholder="9876543210" maxlength="10" autocomplete="tel">
                        </div>
                    </div>
                </div>
                <span class="field-error" id="err-phone">Enter a valid 10-digit mobile number.</span>
                <span class="field-hint">An OTP will be sent to this number for verification.</span>
            </div>

            <div class="auth-buttons-grid">
                <button class="btn-next" id="btn-1" style="margin-top:0;">
                    <span>Send OTP</span>
                    <span><i class="la la-envelope-open-text"></i></span>
                    <div class="spinner" id="spin-1"></div>
                </button>
                <a href="api/google_auth.php" class="btn btn-outline btn-block btn-google" style="margin-bottom:0; font-size: 0.8rem; padding: 0.65rem 0.4rem;">
                    <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google Logo" class="auth-icon">
                    Continue with Google
                </a>
            </div>

            <div class="reg-footer-link">Already have an account? <a href="login.php">Sign in</a></div>
        </div>

        <!-- ===== STEP 2: OTP Verification ===== -->
        <div class="step-panel" id="panel-2">
            <div class="card-header">
                <span class="step-icon"><i class="la la-lock"></i></span>
                <h2>Verify Your Mobile</h2>
                <p class="otp-info">A 6-digit OTP was sent to <span class="phone-highlight" id="otp-phone-display"></span></p>
            </div>

            <div id="alert-2"></div>

            <div class="otp-row" id="otp-inputs">
                <input type="text" class="otp-box" maxlength="1" inputmode="numeric" id="otp0">
                <input type="text" class="otp-box" maxlength="1" inputmode="numeric" id="otp1">
                <input type="text" class="otp-box" maxlength="1" inputmode="numeric" id="otp2">
                <input type="text" class="otp-box" maxlength="1" inputmode="numeric" id="otp3">
                <input type="text" class="otp-box" maxlength="1" inputmode="numeric" id="otp4">
                <input type="text" class="otp-box" maxlength="1" inputmode="numeric" id="otp5">
            </div>

            <div class="resend-row">
                Didn't receive it?
                <button class="resend-btn" id="resend-btn" disabled>Resend OTP</button>
                <span id="otp-timer"> (30s)</span>
            </div>

            <button class="btn-next" id="btn-2" style="margin-top:22px;">
                <span>Verify OTP</span>
                <span><i class="la la-check-square-o"></i></span>
                <div class="spinner" id="spin-2"></div>
            </button>
            <button class="btn-back" onclick="goStep(1)">← Change Mobile Number</button>
        </div>

        <!-- ===== STEP 3: Email + Password ===== -->
        <div class="step-panel" id="panel-3">
            <div class="card-header">
                <span class="step-icon"><i class="la la-key"></i></span>
                <h2>Set Your Credentials</h2>
                <p>Enter your email address and create a secure password for your account.</p>
            </div>

            <div id="alert-3"></div>

            <div class="form-group">
                <label for="inp-email">Email Address</label>
                <div class="input-wrap">
                    <span class="input-icon">📧</span>
                    <input type="email" id="inp-email" placeholder="you@example.com" autocomplete="email">
                </div>
                <span class="field-error" id="err-email">Enter a valid email address.</span>
            </div>

            <div class="form-group">
                <label for="inp-password">Create Password</label>
                <div class="input-wrap pw-wrap">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="inp-password" placeholder="Min. 6 characters" autocomplete="new-password">
                    <button class="pw-toggle" type="button" onclick="togglePw('inp-password', this)"><i class="la la-eye"></i></button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                <span class="strength-label" id="strength-label" style="color:#64748b;"></span>
                <span class="field-error" id="err-password">Password must be at least 6 characters.</span>
            </div>

            <div class="form-group">
                <label for="inp-confirm">Confirm Password</label>
                <div class="input-wrap pw-wrap">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="inp-confirm" placeholder="Re-enter your password" autocomplete="new-password">
                    <button class="pw-toggle" type="button" onclick="togglePw('inp-confirm', this)"><i class="la la-eye"></i></button>
                </div>
                <span class="field-error" id="err-confirm">Passwords do not match.</span>
            </div>

            <button class="btn-next" id="btn-3">
                <span>Create Account</span>
                <span><i class="la la-rocket"></i></span>
                <div class="spinner" id="spin-3"></div>
            </button>
            <button class="btn-back" onclick="goStep(2)">← Back</button>
        </div>

        <!-- ===== STEP 4: SUCCESS ===== -->
        <div class="step-panel" id="panel-4">
            <div class="success-content">
                <div class="success-ring">🎉</div>
                <h2>Welcome to ReportMyCity!</h2>
                <p>Your citizen account has been created successfully, <span class="success-name" id="success-name"></span>. You can now report civic issues and track their resolution.</p>

                <div class="info-chips">
                    <div class="chip"><i class="la la-check-square-o"></i> Verified Mobile</div>
                    <div class="chip">📧 Email Linked</div>
                    <div class="chip"><i class="la la-shield"></i> Account Secured</div>
                </div>

                <a href="login.php">
                    <button class="btn-next" style="font-size:16px; padding:15px;">
                        Sign In to Your Account →
                    </button>
                </a>
            </div>
        </div>

    </div><!-- /.reg-card -->
</div><!-- /.reg-page -->

<script>
// ========== STATE ==========
let currentStep = 1;
let generatedOTP = '';
let timerInterval = null;
let formData = { name:'', phone:'', email:'', password:'' };

// ========== STEP NAVIGATION ==========
function goStep(n) {
    // Hide all panels
    document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-' + n).classList.add('active');
    currentStep = n;
    updateProgress(n);
    // Scroll card to top
    document.querySelector('.reg-card').scrollIntoView({ behavior:'smooth', block:'start' });
}

function updateProgress(n) {
    for (let i = 1; i <= 4; i++) {
        const circle = document.getElementById('sc-' + i);
        const item   = document.getElementById('si-' + i);
        if (i < n) {
            circle.classList.remove('active'); circle.classList.add('done');
            circle.textContent = '✓';
            item.classList.remove('active'); item.classList.add('done');
        } else if (i === n) {
            circle.classList.remove('done'); circle.classList.add('active');
            circle.textContent = i < 4 ? i : '✓';
            item.classList.remove('done'); item.classList.add('active');
        } else {
            circle.classList.remove('active','done');
            circle.textContent = i < 4 ? i : '✓';
            item.classList.remove('active','done');
        }
    }
    for (let i = 1; i <= 3; i++) {
        const line = document.getElementById('sl-' + i);
        if (i < n) line.classList.add('done');
        else line.classList.remove('done');
    }
}

// ========== VALIDATION HELPERS ==========
function showErr(id, show) {
    const el = document.getElementById(id);
    if (el) el.style.display = show ? 'block' : 'none';
    return !show;
}

function markField(id, ok) {
    const el = document.getElementById(id);
    if (!el) return;
    if (ok) el.classList.remove('error-field');
    else el.classList.add('error-field');
}

function showAlert(panelId, msg, type='error') {
    const el = document.getElementById('alert-' + panelId);
    if (!msg) { el.innerHTML = ''; return; }
    const icon = type === 'error' ? '<i class="la la-times"></i>' : '<i class="la la-check-square-o"></i>';
    el.innerHTML = `<div class="alert alert-${type}">${icon} ${msg}</div>`;
}

// ========== STEP 1: Send OTP via Twilio ==========
document.getElementById('btn-1').addEventListener('click', async () => {
    const name     = document.getElementById('inp-name').value.trim();
    const phone    = document.getElementById('inp-phone').value.trim();
    const state    = document.getElementById('inp-state').value.trim();
    const district = document.getElementById('inp-district').value.trim();

    let valid = true;
    if (!name || name.length < 2) { markField('inp-name', false); showErr('err-name', true); valid = false; }
    else { markField('inp-name', true); showErr('err-name', false); }

    if (!state || state.length < 2) { markField('inp-state', false); showErr('err-state', true); valid = false; }
    else { markField('inp-state', true); showErr('err-state', false); }

    if (!district || district.length < 2) { markField('inp-district', false); showErr('err-district', true); valid = false; }
    else { markField('inp-district', true); showErr('err-district', false); }

    if (!phone || !/^\d{10}$/.test(phone)) { markField('inp-phone', false); showErr('err-phone', true); valid = false; }
    else { markField('inp-phone', true); showErr('err-phone', false); }

    if (!valid) return;

    formData.name     = name;
    formData.phone    = phone;
    formData.state    = state;
    formData.district = district;

    const btn = document.getElementById('btn-1');
    const sp  = document.getElementById('spin-1');
    btn.disabled = true;
    sp.style.display = 'inline-block';
    showAlert(1, '');

    try {
        const res  = await fetch('api/send_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone })
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('otp-phone-display').textContent = '+91 ' + phone;
            goStep(2);
            startOTPTimer();
            showAlert(2, 'OTP sent to your mobile number.', 'success');
        } else {
            showAlert(1, data.error || 'Failed to send OTP. Try again.', 'error');
        }
    } catch (err) {
        showAlert(1, 'Network error. Please try again.', 'error');
    } finally {
        btn.disabled = false;
        sp.style.display = 'none';
    }
});


// ========== OTP TIMER ==========
function startOTPTimer() {
    clearInterval(timerInterval);
    let secs = 30;
    const timerEl = document.getElementById('otp-timer');
    const resendBtn = document.getElementById('resend-btn');
    resendBtn.disabled = true;
    timerEl.textContent = ` (${secs}s)`;

    timerInterval = setInterval(() => {
        secs--;
        if (secs <= 0) {
            clearInterval(timerInterval);
            timerEl.textContent = '';
            resendBtn.disabled = false;
        } else {
            timerEl.textContent = ` (${secs}s)`;
        }
    }, 1000);
}

document.getElementById('resend-btn').addEventListener('click', async () => {
    const phone = formData.phone;
    showAlert(2, '');
    document.querySelectorAll('.otp-box').forEach(b => { b.value=''; b.classList.remove('filled'); });

    try {
        const res  = await fetch('api/send_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone })
        });
        const data = await res.json();
        if (data.success) {
            showAlert(2, 'New OTP sent to your mobile.', 'success');
            startOTPTimer();
        } else {
            showAlert(2, data.error || 'Failed to resend OTP.', 'error');
        }
    } catch (err) {
        showAlert(2, 'Network error. Please try again.', 'error');
    }

    document.getElementById('otp0').focus();
});


// ========== OTP BOX AUTO-ADVANCE ==========
document.querySelectorAll('.otp-box').forEach((box, i, boxes) => {
    box.addEventListener('input', () => {
        box.value = box.value.replace(/\D/, '');
        if (box.value) {
            box.classList.add('filled');
            if (i < boxes.length - 1) boxes[i+1].focus();
        } else {
            box.classList.remove('filled');
        }
    });

    box.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !box.value && i > 0) {
            boxes[i-1].focus();
            boxes[i-1].value = '';
            boxes[i-1].classList.remove('filled');
        }
    });

    box.addEventListener('paste', e => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
        [...pasted].forEach((ch, idx) => {
            if (boxes[idx]) { boxes[idx].value = ch; boxes[idx].classList.add('filled'); }
        });
        const lastFilled = Math.min(pasted.length, boxes.length - 1);
        boxes[lastFilled].focus();
    });
});

// ========== STEP 2: Verify OTP via server ==========
document.getElementById('btn-2').addEventListener('click', async () => {
    const entered = [...document.querySelectorAll('.otp-box')].map(b => b.value).join('');
    if (entered.length !== 6) {
        showAlert(2, 'Please enter all 6 digits of the OTP.', 'error');
        return;
    }

    const btn = document.getElementById('btn-2');
    const sp  = document.getElementById('spin-2');
    btn.disabled = true;
    sp.style.display = 'inline-block';

    try {
        const res  = await fetch('api/verify_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ otp: entered })
        });
        const data = await res.json();

        if (data.success) {
            clearInterval(timerInterval);
            showAlert(2, '');
            goStep(3);
        } else {
            showAlert(2, data.error || 'Incorrect OTP. Please try again.', 'error');
            document.querySelectorAll('.otp-box').forEach(b => b.classList.add('error-field'));
            setTimeout(() => document.querySelectorAll('.otp-box').forEach(b => b.classList.remove('error-field')), 1200);
        }
    } catch (err) {
        showAlert(2, 'Network error. Please try again.', 'error');
    } finally {
        btn.disabled = false;
        sp.style.display = 'none';
    }
});


// ========== PASSWORD STRENGTH ==========
document.getElementById('inp-password').addEventListener('input', function() {
    const pw = this.value;
    let score = 0;
    if (pw.length >= 6)  score++;
    if (pw.length >= 10) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const fill = document.getElementById('strength-fill');
    const label = document.getElementById('strength-label');
    const pct  = (score / 5) * 100;
    fill.style.width = pct + '%';

    if (score <= 1)      { fill.style.background = '#f87171'; label.textContent = 'Weak';   label.style.color = '#f87171'; }
    else if (score <= 3) { fill.style.background = '#fbbf24'; label.textContent = 'Fair';   label.style.color = '#fbbf24'; }
    else if (score === 4){ fill.style.background = '#34d399'; label.textContent = 'Strong';  label.style.color = '#34d399'; }
    else                 { fill.style.background = '#00ffc3'; label.textContent = '💪 Very Strong'; label.style.color = '#00ffc3'; }
    if (!pw) { fill.style.width = '0%'; label.textContent = ''; }
});

// ========== TOGGLE PASSWORD ==========
function togglePw(inputId, btn) {
    const inp = document.getElementById(inputId);
    if (inp.type === 'password') { inp.type = 'text'; btn.textContent = '🙈'; }
    else { inp.type = 'password'; btn.textContent = '<i class="la la-eye"></i>'; }
}

// ========== STEP 3 ==========
document.getElementById('btn-3').addEventListener('click', () => {
    const email    = document.getElementById('inp-email').value.trim();
    const password = document.getElementById('inp-password').value;
    const confirm  = document.getElementById('inp-confirm').value;

    let valid = true;
    const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!emailRx.test(email)) { markField('inp-email', false); showErr('err-email', true); valid = false; }
    else { markField('inp-email', true); showErr('err-email', false); }

    if (password.length < 6) { markField('inp-password', false); showErr('err-password', true); valid = false; }
    else { markField('inp-password', true); showErr('err-password', false); }

    if (password !== confirm) { markField('inp-confirm', false); showErr('err-confirm', true); valid = false; }
    else { markField('inp-confirm', true); showErr('err-confirm', false); }

    if (!valid) return;

    formData.email    = email;
    formData.password = password;

    const btn = document.getElementById('btn-3');
    const sp  = document.getElementById('spin-3');
    btn.disabled = true; sp.style.display = 'inline-block';

    // Submit to backend
    const payload = new FormData();
    payload.append('name',             formData.name);
    payload.append('phone',            '+91' + formData.phone);
    payload.append('state',            formData.state);
    payload.append('district',         formData.district);
    payload.append('email',            formData.email);
    payload.append('password',         formData.password);
    payload.append('confirm_password', formData.password);

    fetch('api/register.php', { method:'POST', body: payload })
        .then(res => {
            // api/register.php redirects on success — catch the final URL
            return res.url;
        })
        .then(url => {
            btn.disabled = false; sp.style.display = 'none';
            if (url.includes('error=')) {
                const msg = decodeURIComponent(url.split('error=')[1]);
                showAlert(3, msg, 'error');
            } else {
                // Success
                document.getElementById('success-name').textContent = formData.name;
                goStep(4);
            }
        })
        .catch(() => {
            btn.disabled = false; sp.style.display = 'none';
            showAlert(3, 'Network error. Please try again.', 'error');
        });
});

// ========== PARTICLE CANVAS ==========
(function() {
    const canvas = document.getElementById('reg-canvas');
    const ctx    = canvas.getContext('2d');
    function resize() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
    resize();
    window.addEventListener('resize', resize);

    const pts = Array.from({length:90}, () => ({
        x: Math.random()*canvas.width,
        y: Math.random()*canvas.height,
        vx: (Math.random()-.5)*.4,
        vy: (Math.random()-.5)*.4
    }));

    function draw() {
        ctx.clearRect(0,0,canvas.width,canvas.height);
        pts.forEach((p,i) => {
            p.x += p.vx; p.y += p.vy;
            if(p.x<0) p.x=canvas.width; if(p.x>canvas.width) p.x=0;
            if(p.y<0) p.y=canvas.height; if(p.y>canvas.height) p.y=0;
            ctx.beginPath(); ctx.arc(p.x,p.y,1.5,0,Math.PI*2);
            ctx.fillStyle='rgba(123,108,255,0.7)'; ctx.fill();
            for(let j=i+1;j<pts.length;j++){
                const dx=p.x-pts[j].x, dy=p.y-pts[j].y;
                const d=Math.sqrt(dx*dx+dy*dy);
                if(d<110){ ctx.beginPath(); ctx.moveTo(p.x,p.y); ctx.lineTo(pts[j].x,pts[j].y);
                ctx.strokeStyle=`rgba(100,80,255,${.18*(1-d/110)})`; ctx.lineWidth=.7; ctx.stroke(); }
            }
        });
        requestAnimationFrame(draw);
    }
    draw();
})();
</script>
</body>
</html>
