<?php

$app->requireCsrf();

// Validate application input only after the CSRF check has passed.
$name = $_POST['name'] ?? null;

if (!is_string($name) || trim($name) === '' || strlen($name) > 100) {
    $_SESSION['csrf_example_feedback'] = [
        'message' => 'Please enter a name between 1 and 100 bytes.',
        'name' => is_string($name) ? $name : '',
    ];
} else {
    // This demonstration does not save application data or send messages.
    $_SESSION['csrf_example_feedback'] = [
        'message' => 'Hello, ' . trim($name) . '! Your CSRF token is valid.',
        'name' => '',
    ];
}

$app->redirect('/csrf', 303);
