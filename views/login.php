<?php include __DIR__ . '/shared/header.php'; ?>

<h1><?= $app->escape($data['title']) ?></h1>

<?php if ($data['loggedInUsername'] !== null): ?>
    <p role="status">You are logged in as <strong><?= $app->escape($data['loggedInUsername']) ?></strong>.</p>
<?php else: ?>
    <?php if ($data['error'] !== null): ?>
        <mark><?= $app->escape($data['error']) ?></mark>
    <?php endif; ?>

    <form method="post" action="/login">
        <input type="hidden" name="_csrf" value="<?= $app->escape($data['csrfToken']) ?>">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" autocomplete="username" value="<?= $app->escape($data['username']) ?>" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Log in</button>
    </form>
<?php endif; ?>

<p><a href="/">Back to home</a></p>

<?php include __DIR__ . '/shared/footer.php'; ?>
