<?php $app->render('shared/header', ['title' => $data['title']]); ?>

<h1><?= $app->escape($data['title']) ?></h1>
<p><?= $app->escape($data['message']) ?></p>

<?php $app->render('shared/footer'); ?>