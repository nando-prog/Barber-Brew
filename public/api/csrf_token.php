<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

jsonResponse(200, [
    'ok' => true,
    'csrf_token' => $_SESSION['csrf_token']
]);
