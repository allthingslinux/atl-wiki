<?php
/**
 * The root setting for all things Mediawiki
 *
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @link     https://atl.wiki
 */

if (!defined('MEDIAWIKI')) {
    exit;
}

// Loads the config files in order
$configFiles = glob('/var/www/wiki/configs/*.php');
sort($configFiles);
foreach ($configFiles as $configFile) {
    include_once $configFile;
}
