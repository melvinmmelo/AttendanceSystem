<?php
// ============================================================
//  AttendQR — Front Controller / Router
//  index.php
// ============================================================

// Start output buffering to prevent "headers already sent" errors.
// This allows header() calls (like redirects) to be made at any point before the script finishes.
ob_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

// Global Fatal Error Handler for AJAX requests
// This ensures that if a fatal error occurs (e.g., class not found, out of memory),
// a proper JSON response is sent instead of an empty response or HTML error page,
// which would cause an "invalid response" error on the client-side.
register_shutdown_function(function () {
    $error = error_get_last();
    // We only want to handle fatal errors
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR])) {
        // Check if it's an AJAX request and if headers haven't been sent yet
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            if (headers_sent() === false) {
                header('Content-Type: application/json');
                http_response_code(500); // Internal Server Error
            }
            // For security, you might not want to expose file paths in production,
            // but for debugging this is invaluable.
            echo json_encode([
                'success' => false,
                'message' => 'A fatal server error occurred: ' . $error['message']
            ]);
        }
    }
});

$page = preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['page'] ?? 'dashboard'));

// ---- Public pages ----
if ($page === 'login') {
    require __DIR__ . '/pages/login.php';
    exit;
}

if ($page === 'logout') {
    session_destroy();
    header('Location: index.php?page=login');
    exit;
}

// ---- All other pages require login ----
requireLogin();

// Valid page list
$valid_pages = ['dashboard','events','forms','scanner','attendees','qrcodes','reports','logs','settings', 'email_logs', 'notifications', 'api'];
if (!in_array($page, $valid_pages)) { $page = 'dashboard'; }

// ---- Render Layout + Page ----
// For AJAX POST requests, the page file is expected to handle the request and then exit.
// For standard form POST requests, the page file will handle the data and likely redirect.
// In both of these POST scenarios, we do not want to render the layout.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Special handling for the API endpoint, which is in the 'includes' directory.
    if ($page === 'api') {
        require __DIR__ . '/includes/api.php';
    } else {
        require __DIR__ . '/pages/' . $page . '.php';
    }
} else {
    // For standard GET requests, render the full page with the layout.
    require __DIR__ . '/includes/layout_header.php';
    require __DIR__ . '/pages/' . $page . '.php';
    require __DIR__ . '/includes/layout_footer.php';
}

// Send the buffered output to the browser
ob_end_flush();
