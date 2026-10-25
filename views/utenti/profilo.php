<?php $app->render('shared/header', ['title' => 'Profilo Utente']); ?>

<h1>Profilo Utente</h1>
<p>Benvenuto, <?= $app->escape($data['nomeUtente']) ?>!</p>
<p>Email: <?= $app->escape($data['emailUtente']) ?></p>
<p>Data di registrazione: <?= $app->escape($data['dataRegistrazione']) ?></p>

<?php $app->render('shared/footer'); ?>
