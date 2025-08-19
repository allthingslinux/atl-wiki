<?php
// All other configs: https://www.mediawiki.org/wiki/Manual:Configuration_settings

// Load environment variables from .env file using phpdotenv
if (file_exists('/var/www/atlwiki/vendor/autoload.php')) {
    require_once '/var/www/atlwiki/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable('/var/www/atlwiki');
    $dotenv->safeLoad();
}

#################################################################### Error Logging & Troubleshooting

// https://www.mediawiki.org/wiki/Manual:$wgShowExceptionDetails
$wgShowExceptionDetails = true;
if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

#################################################################### URL and CDN
// https://www.mediawiki.org/wiki/Manual:Short_URL1

// https://www.mediawiki.org/wiki/Manual:$wgSitename
$wgSitename = "atl.wiki";

// https://www.mediawiki.org/wiki/Manual:$wgMetaNamespace
$wgMetaNamespace = "ATL";

// https://www.mediawiki.org/wiki/Manual:$wgUpgradeKey
$wgUpgradeKey = $_SERVER['UPGRADE_KEY'];

// https://www.mediawiki.org/wiki/Manual:$wgSecretKey
$wgSecretKey = $_SERVER['SECRET_KEY'];

// https://www.mediawiki.org/wiki/Manual:$wgAuthenticationTokenVersion
$wgAuthenticationTokenVersion = "1"; # Changing this will log out all existing sessions.

#################################################################### URL and CDN

// https://www.mediawiki.org/wiki/Manual:$wgServer
$wgServer = "https://atl.wiki";

// https://www.mediawiki.org/wiki/Manual:$wgMainPageIsDomainRoot
$wgMainPageIsDomainRoot = true;

// https://www.mediawiki.org/wiki/Manual:$wgUseCdn
$wgUseCdn = true;

// https://www.mediawiki.org/wiki/Manual:$wgCdnServersNoPurge
$wgCdnServersNoPurge = [
  '10.0.0.2',
  '173.245.48.0/20',
  '103.21.244.0/22',
  '103.22.200.0/22',
  '103.31.4.0/22',
  '141.101.64.0/18',
  '108.162.192.0/18',
  '190.93.240.0/20',
  '188.114.96.0/20',
  '197.234.240.0/22',
  '198.41.128.0/17',
  '162.158.0.0/15',
  '104.16.0.0/13',
  '104.24.0.0/14',
  '172.64.0.0/13',
  '131.0.72.0/22',
  '2400:cb00::/32',
  '2606:4700::/32',
  '2803:f800::/32',
  '2405:b500::/32',
  '2405:8100::/32',
  '2a06:98c0::/29',
  '2c0f:f248::/32',
  '172.21.0.0/16',
  '127.0.0.1',
  '::1'
];

$wgCdnServers = [
	'10.0.0.2',
];

$wgUsePrivateIPs = true;

// Trust the IP forwarded by the proxy (NPM and Cloudflare)
if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
    $forwardedIps = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
    // The first IP in the list is the original client IP.
    $_SERVER['REMOTE_ADDR'] = trim( $forwardedIps[0] );
}

// https://www.mediawiki.org/wiki/Manual:$wgUsePathInfo
$wgUsePathInfo = true;

// https://www.mediawiki.org/wiki/Manual:$wgScript
$wgScript = "/index.php";

// https://www.mediawiki.org/wiki/Manual:$wgScriptPath
$wgScriptPath = "";

// https://www.mediawiki.org/wiki/Manual:$wgArticlePath
$wgArticlePath = "/$1"; # Pretty URL

// https://www.mediawiki.org/wiki/Manual:$wgActionPaths
$actions = [
	'view',
	'edit',
	'watch',
	'unwatch',
	'delete',
	'revert',
	'rollback',
	'protect',
	'unprotect',
	'markpatrolled',
	'render',
	'submit',
	'history',
	'purge',
	'info',
];
foreach ( $actions as $action ) {
  $wgActionPaths[$action] = "/$action/$1";
}

// https://www.mediawiki.org/wiki/Manual:$wgForceHTTPS
$wgForceHTTPS = true;

#################################################################### DB Config - Do not change this

// https://www.mediawiki.org/wiki/Manual:$wgDBtype
$wgDBtype = "mysql";

// https://www.mediawiki.org/wiki/Manual:$wgDBprefix
$wgDBprefix = "mw_";

// https://www.mediawiki.org/wiki/Manual:$wgDBssl
$wgDBssl = false;

// https://www.mediawiki.org/wiki/Manual:$wgDBTableOptions
$wgDBTableOptions = "ENGINE=InnoDB, DEFAULT CHARSET=binary";

// https://www.mediawiki.org/wiki/Manual:$wgSharedTables
$wgSharedTables[] = "actor";

$wgDBserver = $_SERVER['DB_SERVER'];
$wgDBname = $_SERVER['DB_NAME'];
$wgDBuser = $_SERVER['DB_USER'];
$wgDBpassword = $_SERVER['DB_PASSWORD'];

$wgSMTP = [
    "host"      => "smtp.gmail.com",
    "IDHost"    => "allthingslinux.org",
    "localhost" => "allthingslinux.org",
    "port"      => 587,
    "auth"      => true,
    "username"  => "services@allthingslinux.org",
    "password"  => $_SERVER['SMTP_PASSWORD'],
];

#################################################################### Caching

// https://www.mediawiki.org/wiki/Manual:$wgMainCacheType
$wgMainCacheType = CACHE_ACCEL;

// https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:$wgEnableSidebarCache
$wgEnableSidebarCache = true;

// https://www.mediawiki.org/wiki/Manual:$wgCacheDirectory
$wgCacheDirectory = "/var/www/atlwiki/cache";

#################################################################### Misc

// https://www.mediawiki.org/wiki/Manual:$wgPingback
$wgPingback = true;

// https://www.mediawiki.org/wiki/Manual:$wgLanguageCode
$wgLanguageCode = "en";

// https://www.mediawiki.org/wiki/Manual:$wgLocaltimezone
$wgLocaltimezone = "UTC";

// https://www.mediawiki.org/wiki/Manual:$wgDiff3
$wgDiff3 = "/usr/bin/diff3";

// https://www.mediawiki.org/wiki/Manual:$wgUseRCPatrol
$wgUseRCPatrol = false;

// https://www.mediawiki.org/wiki/Manual:$wgUseNPPatrol
$wgUseNPPatrol = false;

// https://www.mediawiki.org/wiki/Manual:$wgUseFilePatrol
$wgUseFilePatrol = false;

// https://www.mediawiki.org/wiki/Manual:$wgEnableCanonicalServerLink
$wgEnableCanonicalServerLink = true;

// https://www.mediawiki.org/wiki/Manual:$wgEnableEditRecovery
$wgEnableEditRecovery = true;

// https://www.mediawiki.org/wiki/Manual:$wgEditRecoveryExpiry
$wgEditRecoveryExpiry = 604800; # 7 Days

// https://www.mediawiki.org/wiki/Manual:$wgRestrictDisplayTitle
$wgRestrictDisplayTitle = false;

// https://www.mediawiki.org/wiki/Manual:$wgUsePrivateIPs
$wgUsePrivateIPs = true;
