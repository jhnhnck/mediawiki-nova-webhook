<?php
/**
 * NovaWebhook - MediaWiki Local Settings file for integration tests
 * This file is licensed under the MIT License; See LICENSE for full text.
 */

# Protect against web entry
if (!defined('MEDIAWIKI')) {
    exit;
}

# Basic site identity
$wgSitename = 'NovaWebhook';
$wgMetaNamespace = 'NovaWebhook';

# URL configuration
$wgScriptPath = '';
$wgServer = 'https://novawebhook.local';
$wgResourceBasePath = $wgScriptPath;
$wgArticlePath = '/wiki/$1';
$wgUsePathInfo = true;
$wgForceHTTPS = true;

# Language and time
$wgLanguageCode = 'en';
$wgLocaltimezone = 'America/New_York';

# Email settings
$wgEnableEmail = false;
$wgEnableUserEmail = false;

# Database settings
$wgDBtype = 'mysql';
$wgDBserver = 'database';
$wgDBname = 'nova_wiki';
$wgDBuser = 'nova';
$wgDBpassword = "{$_ENV['NOVA_DB_PASSWORD']}";
$wgDBprefix = '';
$wgDBTableOptions = 'ENGINE=InnoDB, DEFAULT CHARSET=binary';
$wgSharedTables[] = 'actor';

# Uploads and media
$wgEnableUploads = true;
$wgTmpDirectory = "/tmp";

# Security and authentication
$wgSecretKey = "{$_ENV['NOVA_SECRET_KEY']}";
$wgUpgradeKey = "{$_ENV['NOVA_UPGRADE_KEY']}";
$wgAuthenticationTokenVersion = '1';
$wgGroupPermissions['*']['edit'] = false;
$wgGroupPermissions['user']['move-rootuserpages'] = true;
$wgGroupPermissions['autoconfirmed']['skipcaptcha'] = true;
$wgGroupPermissions['sysop']['tboverride'] = false;
$wgUsePrivateIPs = true;
$wgCdnServersNoPurge = ['172.16.0.0/12', '10.22.0.254'];
$wgAutoblockExemptions = ['172.16.0.0/12', '10.22.0.0/22'];
$wgUseCdn = false;
$wgShowExceptionDetails = true;

# Skins
wfLoadSkin('Vector');

# Extensions
wfLoadExtension('NovaWebhook');
if (getenv('NOVA_TEST_LEGACY_CONFIG')) {
    $wgDiscordWebhooks = [['url' => "{$_ENV['NOVA_WEBHOOK_URL']}"]];
    $wgDiscordNoBots = false;
} else {
    $wgNovaWebhookEndpoints = [['url' => "{$_ENV['NOVA_WEBHOOK_URL']}"]];
    $wgNovaWebhookNoBots = false;
}

# Misc
$wgPingback = true;
$wgRightsPage = '';
$wgRightsUrl = '';
$wgRightsText = '';
$wgRightsIcon = '';
$wgDiff3 = '/usr/bin/diff3';
ini_set('post_max_size', '100M');
ini_set('upload_max_filesize', '100M');

$attuIsWikiDiff2Enabled = extension_loaded('wikidiff2');
if ( $attuIsWikiDiff2Enabled ) {
    $wgDiffEngine = 'wikidiff2';
}
