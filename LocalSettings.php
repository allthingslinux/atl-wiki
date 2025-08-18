<?php

// Load environment variables from .env file using phpdotenv
if (file_exists('/var/www/atlwiki/vendor/autoload.php')) {
    require_once '/var/www/atlwiki/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable('/var/www/atlwiki');
    $dotenv->safeLoad();
}

// Loads the config files in order
$configFiles = glob('/var/www/atlwiki/configs/*.php');
sort($configFiles);
foreach ($configFiles as $configFile) {
    require_once $configFile;
}
