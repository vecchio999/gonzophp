<html>
    <head>
        <title>Profilo Utente</title>
    </head>
    <body>
        <h1>Profilo Utente</h1>
        <p>Benvenuto, <?= $app->escape($data['nomeUtente']) ?>!</p>
        <p>Email: <?= $app->escape($data['emailUtente']) ?></p>
        <p>Data di registrazione: <?= $app->escape($data['dataRegistrazione']) ?></p>
    </body>
</html>