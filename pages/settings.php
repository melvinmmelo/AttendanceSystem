<?php
// pages/settings.php

// Handle POST for sending a test email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $a = $_REQUEST['action'] ?? '';

    if ($a === 'send_test_email') {
        // Fetch the current admin's email from the database
        $admin = db_row("SELECT email FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
        $to_email = $admin['email'] ?? null;

        if ($to_email) {
            $subject = "✅ Test Email from AttendQR";
            $content_html = "<p style='margin-top:0;'>This is a test email to confirm your SMTP configuration is working correctly.</p>"
                          . "<p>If you received this message, your settings in <code>config.php</code> are correct!</p>"
                          . "<p style='margin-bottom:0;'>This is a great sign that your attendees will receive their QR codes.</p>";

            $body_plain = "This is a test email to confirm your SMTP configuration is working correctly. "
                        . "If you received this message, your settings in config.php are correct!";

            $body_html = create_email_template($content_html, $subject);

            $result = send_email($to_email, $subject, $body_html, $body_plain);

            if ($result === true) {
                flash('success', "Test email sent successfully to " . htmlspecialchars($to_email));
            } else {
                $error_message = "Failed to send test email. Error: " . htmlspecialchars($result);
                // Provide a more helpful message for the most common SMTP error.
                if (strpos($result, 'Could not authenticate') !== false) {
                    $error_message .= " | Troubleshooting Tip: This usually means your email credentials in includes/config.php are incorrect. If using Gmail, make sure you are using a 16-character App Password, not your regular password.";
                }
                flash('error', $error_message);
            }
        } else {
            flash('error', "Could not find your email address in the database.");
        }
        header('Location: index.php?page=settings');
        exit;
    }
}
?>
<div class="page-hero"><h1>Settings</h1><p>Configure system settings and verify integrations.</p></div>

<div class="card" style="max-width: 680px;">
    <div class="card-header">
        <span><i class="bi bi-envelope-at"></i></span>
        <div class="card-title">Email (SMTP) Configuration</div>
    </div>
    <div class="card-body">
        <p class="text-muted text-sm" style="margin-bottom: 20px;">
            These settings are defined in <code>includes/config.php</code> and are read-only. To send emails, you must configure your Gmail account with an App Password.
        </p>
        <div class="form-group"><label class="form-label">SMTP Host</label><input type="text" class="form-input" value="<?= MAIL_HOST ?>" readonly></div>
        <div class="form-group"><label class="form-label">SMTP Username</label><input type="text" class="form-input" value="<?= MAIL_USERNAME ?>" readonly></div>
        <div class="form-group"><label class="form-label">From Address</label><input type="text" class="form-input" value="<?= MAIL_FROM ?>" readonly></div>
    </div>
    <div class="card-footer" style="justify-content: space-between; align-items: center;">
        <p class="text-xs text-muted">A test email will be sent to your logged-in email address.</p>
        <form method="POST" action="index.php?page=settings">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send_test_email">
            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Send Test Email</button>
        </form>
    </div>
</div>