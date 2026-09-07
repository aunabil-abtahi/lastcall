<?php

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/maintenance.php";

runMarketplaceMaintenance($pdo);

echo "Marketplace maintenance completed at " . date("Y-m-d H:i:s") . PHP_EOL;
