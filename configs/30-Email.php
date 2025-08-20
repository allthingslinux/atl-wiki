<?php
/**
 * Email System Configuration
 * 
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://atl.wiki
 */

// https://www.mediawiki.org/wiki/Manual:$wgEnableEmail
$wgEnableEmail = true;

// https://www.mediawiki.org/wiki/Manual:$wgEnableUserEmail
$wgEnableUserEmail = true;

// https://www.mediawiki.org/wiki/Manual:$wgEmergencyContact
$wgEmergencyContact = "atmois@allthingslinux.org";

// https://www.mediawiki.org/wiki/Manual:$wgPasswordSender
$wgPasswordSender = "services@allthingslinux.org";

// https://www.mediawiki.org/wiki/Manual:$wgEmailAuthentication
$wgEmailAuthentication = true;

// https://www.mediawiki.org/wiki/Manual:$wgEmailConfirmToEdit
$wgEmailConfirmToEdit = false;
