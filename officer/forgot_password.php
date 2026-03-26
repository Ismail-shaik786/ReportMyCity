<?php
/**
 * ReportMyCity — Officer Forgot Password
 */
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — Officer Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Serif:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-page-wrapper">

    <div class="auth-gov-header">
        <img src="../assets/images/govt_emblem.png" alt="Government Emblem" class="emblem">
        <div class="portal-text">
            <h1>ReportMyCity — Field Officer Portal</h1>
            <p>Municipal Field Operations Division · Government of India</p>
        </div>
    </div>

    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="logo">
                <img src="../assets/images/govt_emblem.png" alt="ReportMyCity Emblem" class="gov-emblem-sm">
                <h1>Reset Password</h1>
                <p>Enter your officer email to receive a reset link</p>
            </div>

            <div id="alert-container"></div>

            <form id="forgotPasswordForm">
                <div class="form-group">
                    <label for="email">Officer Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="officer@reportmycity.gov" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="submit-btn" style="padding: 0.75rem;">
                    <i class="la la-rocket"></i> Send Reset Link
                </button>
            </form>

            <div class="auth-footer">
                <a href="officer_login.php">← Back to Officer Login</a>
            </div>
        </div>
    </div>

    <div class="auth-gov-footer">
        © 2026 ReportMyCity — Field Officer Portal. Government of India.
    </div>

</div>

<script>
document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    const email = document.getElementById('email').value;
    const alertBox = document.getElementById('alert-container');

    btn.disabled = true;
    btn.innerText = '⌛ Processing...';

    fetch('../api/reset_password_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email, role: 'officer' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alertBox.innerHTML = `<div class="alert alert-success"><i class="la la-check-square-o"></i> ${data.message}</div>`;
            document.getElementById('forgotPasswordForm').style.display = 'none';
        } else {
            alertBox.innerHTML = `<div class="alert alert-error"><i class="la la-times"></i> ${data.message}</div>`;
            btn.disabled = false;
            btn.innerText = '<i class="la la-rocket"></i> Send Reset Link';
        }
    })
    .catch(() => {
        alertBox.innerHTML = '<div class="alert alert-error"><i class="la la-times"></i> Connection error. Please try again.</div>';
        btn.disabled = false;
        btn.innerText = '<i class="la la-rocket"></i> Send Reset Link';
    });
});
</script>
</body>
</html>
