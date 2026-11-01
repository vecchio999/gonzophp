<?php include __DIR__ . '/shared/header.php'; ?>

<h1><?= $app->escape($data['title']) ?></h1>
<p>This form checks a session CSRF token. Feedback is stored temporarily in your session.</p>

<?php if ($data['message'] !== null): ?>
    <p role="status"><?= $app->escape($data['message']) ?></p>
<?php endif; ?>

<form method="post" action="/csrf">
    <input type="hidden" name="_csrf" value="<?= $app->escape($data['csrfToken']) ?>">
    <label for="name">Name</label>
    <input id="name" name="name" type="text" value="<?= $app->escape($data['name']) ?>" required maxlength="100">
    <button type="submit">Submit</button>
</form>

<p><a href="/">Back to home</a></p>

<?php include __DIR__ . '/shared/footer.php'; ?>
