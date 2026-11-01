<?php include __DIR__ . '/shared/header.php'; ?>

<h1><?= $app->escape($data['title']) ?></h1>
<p><?= $app->escape($data['message']) ?></p>

<?php include __DIR__ . '/shared/footer.php'; ?>