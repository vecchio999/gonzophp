<?php

$csrfToken = $app->csrfToken();
$feedback = $_SESSION['csrf_example_feedback'] ?? [];

// HEAD must not consume feedback intended for the next GET.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    unset($_SESSION['csrf_example_feedback']);
}

$app->render('csrf', [
    'title' => 'CSRF example',
    'csrfToken' => $csrfToken,
    'message' => $feedback['message'] ?? null,
    'name' => $feedback['name'] ?? '',
]);
