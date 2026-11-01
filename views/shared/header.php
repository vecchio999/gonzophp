<?php

// Start or reuse the session before output so every page can show login state.
$navbarCsrfToken = $app->csrfToken();
$navbarUsername = $_SESSION['username'] ?? null;
?>
<!doctype html>
<html lang="<?= $app->escape($app->config('language')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $app->escape($data['title']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
</head>
<body>
<header class="container">
    <nav class="app-navbar" aria-label="Main navigation">
        <ul>
            <li><a href="/">Gonzo PHP</a></li>
        </ul>
        <ul class="account">
            <?php if ($navbarUsername === null): ?>
                <li><a href="/login">login</a></li>
            <?php else: ?>
                <li>hello, <?= $app->escape($navbarUsername) ?></li>
                <li>
                    <a href="/logout?_csrf=<?= $app->escape($navbarCsrfToken) ?>" onclick="return confirm('are you sure?')" rel="nofollow">logout</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
<main class="container">