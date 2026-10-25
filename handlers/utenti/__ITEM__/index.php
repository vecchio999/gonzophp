<?php

$app->render('utenti/profilo', [
    'nomeUtente' => $app->item(0),
    'emailUtente' => 'mario.rossi@example.com',
    'dataRegistrazione' => '2023-01-01'
]);