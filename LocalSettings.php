<?php
/**
 * The root setting for all things Mediawiki
 *
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://atl.wiki
 */

// Loads the config files in order
$configFiles = glob('/var/www/atlwiki/configs/*.php');
sort($configFiles);
foreach ($configFiles as $configFile) {
    include_once $configFile;
}
