<?php
// pages/logout.php

// This file is included by index.php, so config.php (with session_start) is already loaded.

// Set the flash message before clearing the authentication details.
// The message will be preserved in the newly regenerated anonymous session.
flash('success', 'You have been logged out successfully.');

// Unset all authentication-related session variables to log the user out.
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_role']);
unset($_SESSION['last_activity']);

// Regenerate the session ID. This is a crucial security step.
// It invalidates the old session ID, effectively logging out any other open tabs,
// and carries the remaining session data (like the flash message) to a new session ID.
session_regenerate_id(true);

// Redirect to the login page with a success message.
header('Location: index.php?page=login');
exit;