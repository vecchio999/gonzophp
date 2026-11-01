<?php

$csrfToken = $app->csrfToken();
$feedback = $_SESSION['login_feedback'] ?? [];

// HEAD must not consume feedback intended for the next GET.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    unset($_SESSION['login_feedback']);
}

$app->render('login', [
    'title' => 'Login',
    'csrfToken' => $csrfToken,
    'error' => $feedback['error'] ?? null,
    'username' => $feedback['username'] ?? '',
    'loggedInUsername' => $_SESSION['username'] ?? null,
]);
