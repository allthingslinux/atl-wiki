<?php

/**
 * Core Wiki Configuration
 *
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @link     https://atl.wiki
 */

// Load environment variables from .env file using phpdotenv
if (file_exists('/var/www/wiki/vendor/autoload.php')) {
    include_once '/var/www/wiki/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable('/var/www/wiki');
    $dotenv->safeLoad();
}

//######################################################// URL and CDN
// https://www.mediawiki.org/wiki/Manual:Short_URL1

// https://www.mediawiki.org/wiki/Manual:$wgSitename
$wgSitename = $_ENV['SITENAME'];

// https://www.mediawiki.org/wiki/Manual:$wgMetaNamespace
$wgMetaNamespace = "ATL";

// https://www.mediawiki.org/wiki/Manual:$wgUpgradeKey
$wgUpgradeKey = $_ENV['UPGRADE_KEY'];

// https://www.mediawiki.org/wiki/Manual:$wgSecretKey
$wgSecretKey = $_ENV['SECRET_KEY'];

// https://www.mediawiki.org/wiki/Manual:$wgAuthenticationTokenVersion
$wgAuthenticationTokenVersion = "1"; // Changing this will log out all sessions

//######################################################// URL and CDN

// https://www.mediawiki.org/wiki/Manual:$wgServer
$wgServer = $_ENV['WG_SERVER'];

// https://www.mediawiki.org/wiki/Manual:$wgMainPageIsDomainRoot
$wgMainPageIsDomainRoot = true;

// https://www.mediawiki.org/wiki/Manual:$wgUseCdn
$wgUseCdn = true;

// https://www.mediawiki.org/wiki/Manual:$wgCdnMaxAge
$wgCdnMaxAge = 259200;

// https://www.mediawiki.org/wiki/Manual:$wgCdnMatchParameterOrder
$wgCdnMatchParameterOrder = false;

// https://www.mediawiki.org/wiki/Manual:$wgCdnServersNoPurge
$wgCdnServersNoPurge = [
  // Cloudflare IP Ranges
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
];

// https://www.mediawiki.org/wiki/Manual:$wgCdnServers
$wgCdnServers = [
    // nginx container (local reverse proxy)
    'nginx',
    // Wiki server on Tailscale
    '100.64.3.0',
    // NPM server on Tailscale
    '100.64.1.0',
];

// https://www.mediawiki.org/wiki/Manual:$wgUsePrivateIPs
$wgUsePrivateIPs = true;

// Trust the IP forwarded by the proxy (NPM and Cloudflare)
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $forwardedIps = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    // The first IP in the list is the original client IP
    $_SERVER['REMOTE_ADDR'] = trim($forwardedIps[0]);
}

// https://www.mediawiki.org/wiki/Manual:$wgCookieSameSite
$wgCookieSameSite = 'Lax';

// https://www.mediawiki.org/wiki/Manual:$wgCookieSecure
// Only use secure cookies if HTTPS is enabled
$wgCookieSecure = (strpos($_ENV['WG_SERVER'], 'https://') === 0);

// https://www.mediawiki.org/wiki/Manual:$wgExternalLinkTarget
$wgExternalLinkTarget = '_blank';

// https://www.mediawiki.org/wiki/Manual:$wgUsePathInfo
$wgUsePathInfo = true;

// https://www.mediawiki.org/wiki/Manual:$wgScript
$wgScript = "/index.php";

// https://www.mediawiki.org/wiki/Manual:$wgScriptPath
$wgScriptPath = "";

// https://www.mediawiki.org/wiki/Manual:$wgArticlePath
$wgArticlePath = "/$1";

// https://www.mediawiki.org/wiki/Manual:$wgForceHTTPS
// Only force HTTPS if the server URL uses HTTPS
$wgForceHTTPS = (strpos($_ENV['WG_SERVER'], 'https://') === 0);

//######################################################// DB Config

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

$wgDBserver = $_ENV['DB_SERVER'];
$wgDBname = $_ENV['DB_NAME'];
$wgDBuser = $_ENV['DB_USER'];
$wgDBpassword = $_ENV['DB_PASSWORD'];

//######################################################// SMTP

$wgSMTP = [
  "host"      => $_ENV['SMTP_HOST'],
  "IDHost"    => $_ENV['SMTP_DOMAIN'],
  "localhost" => $_ENV['SMTP_DOMAIN'],
  "port"      => $_ENV['SMTP_PORT'],
  "auth"      => true,
  "username"  => $_ENV['SMTP_USERNAME'],
  "password"  => $_ENV['SMTP_PASSWORD'] ?? '',
];

//######################################################// Caching

// https://www.mediawiki.org/wiki/Manual:$wgCacheDirectory
$wgCacheDirectory = "/var/www/wiki/cache";

// https://www.mediawiki.org/wiki/Manual:$wgGitInfoCacheDirectory
$wgGitInfoCacheDirectory = "/var/www/wiki/cache/gitinfo";

// https://www.mediawiki.org/wiki/Manual:$wgObjectCaches
$wgObjectCaches['redis'] = [
  'class'                => 'RedisBagOStuff',
  'servers'              => [ 'valkey:6379' ],
  'persistent'           => false,
  'automaticFailOver'    => false,
];

// https://www.mediawiki.org/wiki/Manual:$wgMainStash
$wgMainStash = 'redis';

// https://www.mediawiki.org/wiki/Manual:$wgMainCacheType
$wgMainCacheType = 'redis';

// https://www.mediawiki.org/wiki/Manual:$wgParserCacheType
$wgParserCacheType  = 'redis';

// https://www.mediawiki.org/wiki/Manual:$wgSessionCacheType
$wgSessionCacheType = 'redis';

// https://www.mediawiki.org/wiki/Manual:$wgUseLocalMessageCache
$wgUseLocalMessageCache = true;

// https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:$wgEnableSidebarCache
$wgEnableSidebarCache = true;

// https://www.mediawiki.org/wiki/Manual:$wgParserCacheExpireTime
$wgParserCacheExpireTime = 259200;

// https://www.mediawiki.org/wiki/Manual:$wgSearchSuggestCacheExpiry
$wgSearchSuggestCacheExpiry = 10800;

//######################################################// Misc

// https://www.mediawiki.org/wiki/Manual:$wgPingback
$wgPingback = true;

// https://www.mediawiki.org/wiki/Manual:$wgLanguageCode
$wgLanguageCode = "en";

// https://www.mediawiki.org/wiki/Manual:$wgLocaltimezone
$wgLocaltimezone = "UTC";

// https://www.mediawiki.org/wiki/Manual:$wgDiffEngine
$wgDiffEngine = 'wikidiff2';

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
$wgEditRecoveryExpiry = 604800; // 7 Days

// https://www.mediawiki.org/wiki/Manual:$wgRestrictDisplayTitle
$wgRestrictDisplayTitle = false;
