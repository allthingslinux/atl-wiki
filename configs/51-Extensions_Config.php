<?php
/**
 * Extension Configuring
 *
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://atl.wiki
 */

//#################################################################// TextExtracts
// https://www.mediawiki.org/wiki/Extension:TextExtracts

$wgExtractsRemoveClasses = [
    'ul.gallery',
    'gallery',
    'code',
    '.metadata'
];

//######################################################// VisualEditor
// https://www.mediawiki.org/wiki/Extension:VisualEditor

$wgVisualEditorAvailableNamespaces = [
    'Guides' => true,
    'Project' => true
];
$wgVisualEditorEnableDiffPageBetaFeature = true;
$wgVisualEditorUseSingleEditTab = true;
$wgDefaultUserOptions['visualeditor-enable'] = 1;
$wgDefaultUserOptions['visualeditor-editor'] = "visualeditor";

//######################################################// Interwiki
// https://www.mediawiki.org/wiki/Extension:Interwiki

// https://www.mediawiki.org/wiki/Manual:$wgUserrightsInterwikiDelimiter
$wgUserrightsInterwikiDelimiter = '#';

//######################################################// ConfirmEdit
// https://www.mediawiki.org/wiki/Extension:ConfirmEdit

$wgTurnstileSiteKey= $_SERVER['TURNSTILE_SITE_KEY'];
$wgTurnstileSecretKey= $_SERVER['TURNSTILE_SECRET_KEY'];

//######################################################// AWS
// https://www.mediawiki.org/wiki/Extension:AWS

$wgAWSRegion = 'auto';
$wgAWSBucketName = 'atl-wiki';
$wgAWSBucketDomain = 'images.atl.wiki';
$wgAWSCredentials = [
    'key' => $_SERVER['ACCESS_KEY_ID'],
    'secret' => $_SERVER['SECRET_ACCESS_KEY'],
];
$accountID = '53d9d9e6ebc5a0dddeeb59477445ea0c';
$wgFileBackends['s3'] = [
    'class' => 'AmazonS3FileBackend',
    'bucket' => $wgAWSBucketName,
    'region' => $wgAWSRegion,
    'endpoint' => 'https://'.$accountID.'.r2.cloudflarestorage.com',
    'use_path_style_endpoint' => true,
];

//######################################################// Approved_Revs
// https://www.mediawiki.org/wiki/Extension:Approved_Revs

$egApprovedRevsAutomaticApprovals = true;
$egApprovedRevsShowNotApprovedMessage = true;
$egApprovedRevsEnabledNamespaces[NS_GUIDES] = true;
$egApprovedRevsEnabledNamespaces[NS_MAIN] = false;
$egApprovedRevsEnabledNamespaces[NS_USER] = false;
$egApprovedRevsEnabledNamespaces[NS_FILE] = false;
$egApprovedRevsEnabledNamespaces[NS_TEMPLATE] = false;
$egApprovedRevsEnabledNamespaces[NS_HELP] = false;
$egApprovedRevsEnabledNamespaces[NS_PROJECT] = false;

//######################################################// Discord
// https://www.mediawiki.org/wiki/Extension:Discord

$wgDiscordWebhookURL = [ $_SERVER['DISCORD_WEBHOOK_URL'] ];
$wgDiscordUseEmojis = true;
$wgDiscordDisabledHooks = [
    'ApprovedRevsRevisionApproved',
    'ApprovedRevsRevisionUnapproved',
    'ApprovedRevsFileRevisionApproved',
    'ApprovedRevsFileRevisionUnapproved',
    'BlockIpComplete',
    'UnblockUserComplete',
    'FileDeleteComplete',
    'FileUndeleteComplete',
    'ArticleRevisionVisibilitySet',
];

//######################################################// CheckUser
// https://www.mediawiki.org/wiki/Extension:CheckUser

$wgCheckUserLogSuccessfulBotLogins = false;
$wgCheckUserLogLogins = true;

//######################################################// PluggableAuth
// https://www.mediawiki.org/wiki/Extension:PluggableAuth

$wgPluggableAuth_Config["Staff Login via All Things Linux (SSO)"] = [
    "plugin" => "OpenIDConnect",
    "data" => [
        "providerURL" => "https://sso.allthingslinux.org",
        "clientID" => $_SERVER['OPENID_CLIENT_ID'],
        "clientsecret" => $_SERVER['OPENID_CLIENT_SECRET'],
    ]
];
$wgPluggableAuth_EnableLocalLogin = true;
$wgPluggableAuth_EnableLocalProperties = true;

//######################################################// OpenID_Connect
// https://www.mediawiki.org/wiki/Extension:OpenID_Connect

$wgOpenIDConnect_MigrateUsersByEmail = true;
$wgOpenIDConnect_UseRealNameAsUserName = true;

//######################################################// Description2
// https://www.mediawiki.org/wiki/Extension:Description2

$wgEnableMetaDescriptionFunctions = true;

//######################################################// CodeMirror
// https://www.mediawiki.org/wiki/Extension:CodeMirror

$wgDefaultUserOptions['usecodemirror'] = true;

//######################################################// Drafts
// https://www.mediawiki.org/wiki/Extension:Drafts

$egDraftsAutoSaveInputBased = true;
$egDraftsAutoSaveWait = 15;
