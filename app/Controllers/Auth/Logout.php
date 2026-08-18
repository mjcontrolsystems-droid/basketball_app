<?php
declare(strict_types=1);

auth_logout();
header('Location: ' . url('index.php'));
exit;
