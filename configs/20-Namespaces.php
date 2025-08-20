<?php
/**
 * Namespaces Configuration
 * https://www.mediawiki.org/wiki/Manual:$wgNamespaceProtection
 * 
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://atl.wiki
 */

// https://www.mediawiki.org/wiki/Manual:$wgAvailableRights
$wgAvailableRights[] = 'template-editing';
$wgAvailableRights[] = 'module-editing';
$wgAvailableRights[] = 'meta-editing';

$wgNamespaceProtection[10] = ['template-editing']; // Template:
$wgNamespaceProtection[828] = ['module-editing']; // Module:
$wgNamespaceProtection[4] = ['meta-editing']; // ATL:

define("NS_GUIDES", 3000);
define("NS_GUIDES_TALK", 3001);
$wgExtraNamespaces[NS_GUIDES] = "Guides";
$wgExtraNamespaces[NS_GUIDES_TALK] = "Guides_talk";
