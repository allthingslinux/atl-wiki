<?php

// Use to disable editing
#wgReadOnly = $adminTask ? false : 'Maintenance, see #atl-wiki for more info on discord.gg/linux';

// Loads the config files in order
$configFiles = glob('/var/www/atlwiki/configs/*.php');
sort($configFiles);
foreach ($configFiles as $configFile) {
    require_once $configFile;
}

require_once "/etc/mediawiki/secrets/Credentials.php";
