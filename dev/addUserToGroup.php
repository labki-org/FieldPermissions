<?php

use MediaWiki\MediaWikiServices;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class AddUserToGroupMaintenance extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription( 'Add a user to a group' );
        $this->addArg( 'username', 'User to modify' );
        $this->addArg( 'group', 'Group to add' );
    }

    public function execute() {
        $username = $this->getArg(0);
        $group    = $this->getArg(1);

        $user = \User::newFromName( $username );
        if ( !$user || !$user->getId() ) {
            $this->error( "User not found: $username", 1 );
        }

        $services     = MediaWikiServices::getInstance();
        $groupManager = $services->getUserGroupManager();

        $groupManager->addUserToGroup( $user, $group );

        $this->output( "Added $username to $group\n" );
    }
}

$maintClass = AddUserToGroupMaintenance::class;
require_once RUN_MAINTENANCE_IF_MAIN;

