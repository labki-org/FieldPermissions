<?php
// Server Config
$wgServer = 'http://localhost:8888';

// Load Platform Extensions Manualy (since we disabled auto-loading)

// Load SMW
wfLoadExtension('SemanticMediaWiki');
enableSemantics('localhost'); // Required to activate SMW

// SMW Satellites
wfLoadExtension('SemanticResultFormats');
// SemanticCompoundQueries might be autoloaded by SMW or Composer, but explicit load is safe if in vendor
wfLoadExtension('SemanticCompoundQueries');
wfLoadExtension('SemanticExtraSpecialProperties');

// Core/Utility Extensions
wfLoadExtension('PageForms');
wfLoadExtension('ParserFunctions');
wfLoadExtension('Maps');
wfLoadExtension('Bootstrap');

// Labki Extensions (Git Cloned into extensions/)
wfLoadExtension('MsUpload');
wfLoadExtension('PageSchemas');
wfLoadExtension('Lockdown');

// Load FieldPermissions (mounted as FieldPermissions)
wfLoadExtension('FieldPermissions', '/mw-user-extensions/FieldPermissions/extension.json');

// Configuration
$wgDebugLogGroups['fieldpermissions'] = '/var/log/mediawiki/fieldpermissions.log';
$wgShowExceptionDetails = true;
$wgDebugDumpSql = false;
$wgDebugLogFile = '/var/log/mediawiki/debug.log'; // Send other logs to file instead of stdout

// Define custom user groups for FieldPermissions (from old test setup)
$wgGroupPermissions['lab_member'] = $wgGroupPermissions['user'];
$wgGroupPermissions['pi'] = $wgGroupPermissions['user'];

// SMW Configuration
$smwgChangePropagationProtection = false;
$smwgEnabledDeferredUpdate = false;
$smwgAutoSetupStore = false;
$smwgQMaxInlineLimit = 500;

// Cache
$wgCacheDirectory = "$IP/cache-fieldpermissions";

// skin
wfLoadSkin('Citizen');
wfLoadSkin('Vector');
$wgDefaultSkin = 'vector';
