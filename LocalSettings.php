<?php

// Loads the config files in order
$configFiles = glob('/var/www/atlwiki/configs/*.php');
sort($configFiles);
foreach ($configFiles as $configFile) {
    require_once $configFile;
}
