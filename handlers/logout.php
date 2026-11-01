<?php

// GET logout is an explicit starter-app choice; requireCsrf() remains POST-only.
// HEAD must never change authentication state through the GET fallback.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    return;
}

// Start or reuse the session before reading its stored token.
$app->csrfToken();
$expected = $_SESSION['gonzo_csrf_token'] ?? null;
$received = $_GET['_csrf'] ?? null;
$app->requireAccess(
    is_string($expected)
    && is_string($received)
    && hash_equals($expected, $received)
);

// Remove authentication and invalidate the previous session identifier and token.
unset($_SESSION['username'], $_SESSION['login_feedback'], $_SESSION['gonzo_csrf_token']);
if (!session_regenerate_id(true)) {
    throw new RuntimeException('Unable to regenerate the logout session.');
}

$app->redirect('/', 303);
