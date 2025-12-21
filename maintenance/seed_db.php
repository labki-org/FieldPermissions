<?php

/**
 * Maintenance script to seed PropertyPermissions DB tables with test data.
 */

use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../../';
}
require_once "$IP/maintenance/Maintenance.php";

// phpcs:disable MediaWiki.Files.ClassMatchesFilename.NotMatch
class SeedPropertyPermissions extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Seeds PropertyPermissions tables with test data' );
	}

	public function execute() {
		$services = MediaWikiServices::getInstance();
		$levelStore = $services->get( 'PropertyPermissions.VisibilityLevelStore' );
		$groupStore = $services->get( 'PropertyPermissions.GroupLevelStore' );

		// Clean existing
		// Assuming simple delete if needed, but let's just add if not exists
		// Actually, since it's test env, we might want to truncate?
		// The stores don't expose truncate. We'll just add/update.

		$this->output( "Seeding visibility levels...\n" );
		$levels = [
			[ 'public', 0, 'Visibility:Public' ],
			[ 'internal', 10, 'Visibility:Internal' ],
			[ 'private', 20, 'Visibility:Private' ],
			[ 'pi_only', 30, 'Visibility:PI_Only' ]
		];

		foreach ( $levels as $l ) {
			try {
				$existing = $levelStore->getLevelByName( $l[0] );
				if ( $existing ) {
					$levelStore->updateLevel( $existing->getId(), $l[0], $l[1], $l[2] );
					$this->output( "Updated level: {$l[0]}\n" );
				} else {
					$levelStore->addLevel( $l[0], $l[1], $l[2] );
					$this->output( "Added level: {$l[0]}\n" );
				}
			} catch ( Exception $e ) {
				$this->output( "Error adding level {$l[0]}: " . $e->getMessage() . "\n" );
			}
		}

		$this->output( "Seeding group levels...\n" );
		$groups = [
			// Public
			'user' => 0,
			// Internal
			'lab_member' => 10,
			// PI Only
			'pi' => 30,
			// Admins see everything
			'sysop' => 30
		];

		foreach ( $groups as $group => $level ) {
			try {
				$groupStore->setGroupMaxLevel( $group, $level );
				$this->output( "Set group $group to level $level\n" );
			} catch ( Exception $e ) {
				$this->output( "Error setting group $group: " . $e->getMessage() . "\n" );
			}
		}

		$this->output( "Done.\n" );
	}
}

$maintClass = SeedPropertyPermissions::class;
require_once RUN_MAINTENANCE_IF_MAIN;
