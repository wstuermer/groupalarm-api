<?php

declare(strict_types=1);

require __DIR__ . '/../inc/bootstrap.php';

logout();
header('Location: login.php');
exit;
