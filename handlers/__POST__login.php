<?php

$app->requireCsrf();

$username = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;

if (
    !is_string($username)
    || !is_string($password)
    || $username === ''
    || $password === ''
    || !$app->authenticate($username, $password)
) {
    // Never retain passwords as old input or distinguish unknown usernames.
    $_SESSION['login_feedback'] = [
        'error' => 'Invalid username or password.',
        'username' => is_string($username) ? $username : '',
    ];
    $app->redirect('/login', 303);
}

// Replace the session identifier before recording the authenticated user.
if (!session_regenerate_id(true)) {
    throw new RuntimeException('Unable to regenerate the login session.');
}

$_SESSION['username'] = $username;
unset($_SESSION['login_feedback'], $_SESSION['gonzo_csrf_token']);
$app->redirect('/login', 303);
