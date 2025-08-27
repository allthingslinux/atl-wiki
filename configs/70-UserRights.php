<?php
/**
 * Mediawiki Permissions Configuration
 * https://www.mediawiki.org/wiki/Manual:User_rights
 * bureaucrat, checkuser, supress & push-subscription-manager are dead
 *
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @link     https://atl.wiki
 */

// * ~ Everyone
$wgGroupPermissions['*']['createaccount'] = true;
$wgGroupPermissions['*']['edit'] = false;

// user ~ All registered users
$wgGroupPermissions['user']['edit'] = true;
$wgGroupPermissions['user']['writeapi'] = true; // For visual editor
$wgGroupPermissions['user']['move'] = false;
$wgGroupPermissions['user']['move-subpages'] = false;
$wgGroupPermissions['user']['move-categorypages'] = false;
$wgGroupPermissions['user']['move-rootuserpages'] = false;
$wgGroupPermissions['user']['movefile'] = false;
$wgGroupPermissions['user']['viewapprover'] = true;

// moderator ~ Wiki Only Moderators (not used yet)
$wgGroupPermissions['moderator']['move'] = true;
$wgGroupPermissions['moderator']['move-subpages'] = true;
$wgGroupPermissions['moderator']['move-categorypages'] = true;
$wgGroupPermissions['moderator']['move-rootuserpages'] = true;
$wgGroupPermissions['moderator']['editsemiprotected'] = true;
$wgGroupPermissions['moderator']['movefile'] = true;
$wgGroupPermissions['moderator']['block'] = true;
$wgGroupPermissions['moderator']['rollback'] = true;
$wgGroupPermissions['moderator']['approverevisions'] = true;

// autoconfirmed ~ Given for wiki author role sync on discord
$wgAutoConfirmAge = 259200;
$wgAutoConfirmCount = 10;
$wgGroupPermissions['autoconfirmed']['autoconfirmed'] = false;
$wgGroupPermissions['autoconfirmed']['editsemiprotected'] = false;

// staff ~ For all ATL Staff
$wgGroupPermissions['staff']['block'] = true;
$wgGroupPermissions['staff']['rollback'] = true;
$wgGroupPermissions['staff']['move'] = true;
$wgGroupPermissions['staff']['move-subpages'] = true;
$wgGroupPermissions['staff']['move-categorypages'] = true;
$wgGroupPermissions['staff']['move-rootuserpages'] = true;
$wgGroupPermissions['staff']['editsemiprotected'] = true;
$wgGroupPermissions['staff']['approverevisions'] = true;

// template-editor ~ Grants edit permissions for templates and modules
$wgGroupPermissions['template-editor']['template-editing'] = true;
$wgGroupPermissions['interface-admin']['module-editing'] = true;

// interface-admin ~ Grants interface edit permissions
$wgGroupPermissions['interface-admin']['template-editing'] = true;
$wgGroupPermissions['interface-admin']['module-editing'] = true;

// sysop ~ Only for Wiki Admins, gives ALL permissions
$wgGroupPermissions['sysop']['checkuser'] = true;
$wgGroupPermissions['sysop']['checkuser-log'] = true;
$wgGroupPermissions['sysop']['investigate'] = true;
$wgGroupPermissions['sysop']['userrights'] = true;
$wgGroupPermissions['sysop']['renameuser'] = true;
$wgGroupPermissions['sysop']['userrights-interwiki'] = true;
$wgGroupPermissions['sysop']['editusercss'] = true;
$wgGroupPermissions['sysop']['edituserjson'] = true;
$wgGroupPermissions['sysop']['edituserjs'] = true;
$wgGroupPermissions['sysop']['editsitecss'] = true;
$wgGroupPermissions['sysop']['editsitejson'] = true;
$wgGroupPermissions['sysop']['editsitejs'] = true;
$wgGroupPermissions['sysop']['editinterface'] = true;
$wgGroupPermissions['sysop']['template-editing'] = true;
$wgGroupPermissions['sysop']['module-editing'] = true;
$wgGroupPermissions['sysop']['meta-editing'] = true;
$wgGroupPermissions['sysop']['hideuser'] = true;
$wgGroupPermissions['sysop']['deletelogentry'] = true;
$wgGroupPermissions['sysop']['deleterevision'] = true;
$wgGroupPermissions['sysop']['suppressionlog'] = true;
$wgGroupPermissions['sysop']['viewsuppressed'] = true;
$wgGroupPermissions['sysop']['suppressrevision'] = true;
$wgGroupPermissions['sysop']['usermerge'] = true;
$wgGroupPermissions['sysop']['mwoauthproposeconsumer'] = true;
$wgGroupPermissions['sysop']['mwoauthupdateownconsumer'] = true;
$wgGroupPermissions['sysop']['mwoauthmanageconsumer'] = true;
$wgGroupPermissions['sysop']['mwoauthsuppress'] = true;
$wgGroupPermissions['sysop']['mwoauthviewsuppressed'] = true;
$wgGroupPermissions['sysop']['mwoauthviewprivate'] = true;
$wgGroupPermissions['sysop']['mwoauthmanagemygrants'] = true;
$wgGroupPermissions['sysop']['import'] = false;
