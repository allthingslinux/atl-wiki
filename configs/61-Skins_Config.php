<?php
/**
 * Skin Configuration
 *
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @link     https://atl.wiki
 */

// https://www.mediawiki.org/wiki/Skin:Citizen
wfLoadSkin('Citizen');
$wgCitizenEnablePreferences = true;
$wgCitizenSearchDescriptionSource = "wikidata";

// https://www.mediawiki.org/wiki/Manual:$wgDefaultSkin
$wgDefaultSkin = "Citizen";

// https://www.mediawiki.org/wiki/Manual:$wgEdititis
$wgEdititis = true;

// https://www.mediawiki.org/wiki/Manual:$wgAllowUserCss
$wgAllowUserCss = true;
