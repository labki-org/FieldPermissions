<?php
// Server Config
$wgServer = 'http://localhost:8888';

// Load Platform Extensions Manualy (since we disabled auto-loading)

// Load SMW
wfLoadExtension( 'SemanticMediaWiki' );
// Required to activate SMW
enableSemantics( 'localhost' );

// SMW Satellites
wfLoadExtension( 'SemanticResultFormats' );
// SemanticCompoundQueries might be autoloaded by SMW or Composer, but explicit load is safe if in vendor
wfLoadExtension( 'SemanticCompoundQueries' );
wfLoadExtension( 'SemanticExtraSpecialProperties' );

// Core/Utility Extensions
wfLoadExtension( 'PageForms' );
wfLoadExtension( 'ParserFunctions' );
wfLoadExtension( 'Maps' );
wfLoadExtension( 'Bootstrap' );

// Labki Extensions (Git Cloned into extensions/)
wfLoadExtension( 'MsUpload' );
wfLoadExtension( 'PageSchemas' );
wfLoadExtension( 'Lockdown' );

// Load PropertyPermissions (mounted as PropertyPermissions)
wfLoadExtension( 'PropertyPermissions', '/mw-user-extensions/PropertyPermissions/extension.json' );

// Configuration
$wgDebugLogGroups['propertypermissions'] = '/var/log/mediawiki/propertypermissions.log';
$wgShowExceptionDetails = true;
$wgDebugDumpSql = false;
// Send other logs to file instead of stdout
$wgDebugLogFile = '/var/log/mediawiki/debug.log';

// Define custom user groups for PropertyPermissions (from old test setup)
$wgGroupPermissions['lab_member'] = $wgGroupPermissions['user'];
$wgGroupPermissions['pi'] = $wgGroupPermissions['user'];

// SMW Configuration
$smwgChangePropagationProtection = false;
$smwgEnabledDeferredUpdate = false;
$smwgAutoSetupStore = false;
$smwgQMaxInlineLimit = 500;

// Cache
$wgCacheDirectory = "$IP/cache-propertypermissions";

// skin
wfLoadSkin( 'Citizen' );
wfLoadSkin( 'Vector' );
$wgDefaultSkin = 'vector';
