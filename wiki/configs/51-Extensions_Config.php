<?php

/**
 * Extension Configuring
 *
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @link     https://atl.wiki
 */

/*
 * Helper reads to avoid undefined index warnings when env vars are empty.
 */
$env = static function (string $key, string $default = ''): string {
    return isset($_ENV[$key]) && $_ENV[$key] !== '' ? (string)$_ENV[$key] : $default;
};

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
$wgDefaultUserOptions['visualeditor-editor'] = 'visualeditor';

//######################################################// Interwiki
// https://www.mediawiki.org/wiki/Extension:Interwiki

// https://www.mediawiki.org/wiki/Manual:$wgUserrightsInterwikiDelimiter
$wgUserrightsInterwikiDelimiter = '#';

//######################################################// ConfirmEdit
// https://www.mediawiki.org/wiki/Extension:ConfirmEdit

$turnstileSiteKey = $env('TURNSTILE_SITE_KEY');
$turnstileSecretKey = $env('TURNSTILE_SECRET_KEY');

if ($turnstileSiteKey !== '' && $turnstileSecretKey !== '') {
    $wgCaptchaClass = MediaWiki\Extension\ConfirmEdit\Turnstile\Turnstile::class;
    $wgTurnstileSiteKey = $turnstileSiteKey;
    $wgTurnstileSecretKey = $turnstileSecretKey;
}

//######################################################// AWS
// https://www.mediawiki.org/wiki/Extension:AWS

$s3BucketName = $env('S3_BUCKET_NAME');
$s3AccessKey = $env('S3_ACCESS_KEY_ID');
$s3SecretKey = $env('S3_SECRET_ACCESS_KEY');
$s3Endpoint = $env('S3_ENDPOINT');
$s3BucketDomain = $env('S3_BUCKET_DOMAIN');

if ($s3BucketName !== '' && $s3AccessKey !== '' && $s3SecretKey !== '' && $s3Endpoint !== '' && $s3BucketDomain !== '') {
    $wgAWSRegion = 'auto';
    $wgAWSBucketName = $s3BucketName;
    $wgAWSBucketDomain = $s3BucketDomain;
    $wgAWSCredentials = [
        'key' => $s3AccessKey,
        'secret' => $s3SecretKey,
    ];

    if (!isset($wgFileBackends) || !is_array($wgFileBackends)) {
        $wgFileBackends = [];
    }

    $wgFileBackends['s3'] = [
        'class' => 'AmazonS3FileBackend',
        'bucket' => $wgAWSBucketName,
        'region' => 'auto',
        'endpoint' => $s3Endpoint,
        'use_path_style_endpoint' => true,
    ];
}

//######################################################// Discord
// https://www.mediawiki.org/wiki/Extension:Discord

$discordWebhook = $env('DISCORD_WEBHOOK_URL');
if ($discordWebhook !== '') {
    $wgDiscordWebhookURL = [ $discordWebhook ];
    $wgDiscordUseEmojis = true;
    $wgDiscordDisabledHooks = [
        'BlockIpComplete',
        'UnblockUserComplete',
        'FileDeleteComplete',
        'FileUndeleteComplete',
        'ArticleRevisionVisibilitySet',
    ];
}

//######################################################// CheckUser
// https://www.mediawiki.org/wiki/Extension:CheckUser

$wgCheckUserLogSuccessfulBotLogins = false;
$wgCheckUserLogLogins = true;

//######################################################// PluggableAuth
// https://www.mediawiki.org/wiki/Extension:PluggableAuth

$openidClientId = $env('OPENID_CLIENT_ID');
$openidClientSecret = $env('OPENID_CLIENT_SECRET');

if ($openidClientId !== '' && $openidClientSecret !== '') {
    $wgPluggableAuth_Config['Staff Login via All Things Linux (SSO)'] = [
        'plugin' => 'OpenIDConnect',
        'data' => [
            'providerURL' => 'https://sso.allthingslinux.org',
            'clientID' => $openidClientId,
            'clientsecret' => $openidClientSecret,
        ]
    ];
}

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

//######################################################// Scribunto
// https://www.mediawiki.org/wiki/Extension:Scribunto

$wgScribuntoDefaultEngine = 'luasandbox';

//######################################################// SimpleBatchUpload
// https://www.mediawiki.org/wiki/Extension:SimpleBatchUpload

$wgSimpleBatchUploadMaxFilesPerBatch = [
    '*' => 10,
];

//######################################################// CirrusSearch
// https://www.mediawiki.org/wiki/Extension:CirrusSearch

$wgSearchType = 'CirrusSearch';
$wgCirrusSearchServers = [
    [ 'host' => 'opensearch', 'port' => 9200 ],
];

// Keep search updates enabled so the job queue can index new edits.
$wgDisableSearchUpdate = false;
