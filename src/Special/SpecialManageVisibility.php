<?php

namespace FieldPermissions\Special;

use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\MediaWikiServices;
use FieldPermissions\Config\VisibilityLevelStore;
use FieldPermissions\Config\GroupLevelStore;
use HTMLForm;
use MediaWiki\Html\Html;

class SpecialManageVisibility extends SpecialPage {
	public function __construct() {
		parent::__construct( 'ManageVisibility', 'fp-manage-visibility' );
	}

	public function execute( $sub ) {
		$this->setHeaders();
		$this->checkPermissions();
		
		$out = $this->getOutput();
		$out->addModules( 'mediawiki.special' );
		
		$this->showLevelsConfiguration();
		$out->addHtml( '<hr>' );
		$this->showGroupConfiguration();
	}

	private function showLevelsConfiguration() {
		$out = $this->getOutput();
		$services = MediaWikiServices::getInstance();
		/** @var VisibilityLevelStore */
		$store = $services->get( 'FieldPermissions.VisibilityLevelStore' );
		$request = $this->getRequest();

		$out->addWikiMsg( 'fieldpermissions-manage-levels-header' );

		// Handle delete
		if ( $request->wasPosted() && $request->getVal( 'action' ) === 'delete_level' ) {
			$id = $request->getInt( 'id' );
			if ( $id ) {
				$store->deleteLevel( $id );
				$out->addWikiMsg( 'fieldpermissions-level-deleted' );
			}
		}

		// Add/Edit Form
		$formDescriptor = [
			'vl_name' => [
				'type' => 'text',
				'label-message' => 'fieldpermissions-level-name',
				'required' => true,
				'validation-callback' => function( $val ) {
					return preg_match( '/^[a-zA-Z0-9_]+$/', $val ) ? true : 'Invalid name';
				}
			],
			'vl_numeric_level' => [
				'type' => 'int',
				'label-message' => 'fieldpermissions-numeric-level',
				'required' => true,
			],
			'vl_page_title' => [
				'type' => 'text',
				'label-message' => 'fieldpermissions-page-title',
				'help-message' => 'fieldpermissions-page-title-help'
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitTextMsg( 'fieldpermissions-add-level' );
		$htmlForm->setSubmitCallback( function( $data ) use ( $store ) {
			// Check if exists to decide update vs insert? 
			// Simplified: just add for now, duplicate name check via DB constraint or logic
			// For full CRUD we'd need hidden ID field.
			// Let's just support Adding for simplicity in this pass, users can delete and re-add.
			
			try {
				$store->addLevel( $data['vl_name'], (int)$data['vl_numeric_level'], $data['vl_page_title'] ?: null );
				return true;
			} catch ( \Exception $e ) {
				return $e->getMessage();
			}
		} );
		
		$htmlForm->show();

		// List Levels
		$levels = $store->getAllLevels();
		
		$rows = [];
		$rows[] = Html::rawElement( 'tr', [],
			Html::element( 'th', [], 'ID' ) .
			Html::element( 'th', [], 'Name' ) .
			Html::element( 'th', [], 'Numeric Level' ) .
			Html::element( 'th', [], 'Page Title' ) .
			Html::element( 'th', [], 'Actions' )
		);

		foreach ( $levels as $level ) {
			$deleteLink = Html::element( 'button', [
				'type' => 'submit',
				'name' => 'id',
				'value' => $level->getId(),
				'onclick' => "return confirm('Are you sure?');"
			], 'Delete' );
			
			$form = Html::rawElement( 'form', [ 'method' => 'post', 'action' => $this->getPageTitle()->getLocalURL( 'action=delete_level' ) ],
				$deleteLink
			);

			$rows[] = Html::rawElement( 'tr', [],
				Html::element( 'td', [], $level->getId() ) .
				Html::element( 'td', [], $level->getName() ) .
				Html::element( 'td', [], $level->getNumericLevel() ) .
				Html::element( 'td', [], $level->getPageTitle() ?: '-' ) .
				Html::rawElement( 'td', [], $form )
			);
		}

		$out->addHtml( Html::rawElement( 'table', [ 'class' => 'wikitable' ], implode( "\n", $rows ) ) );
	}

	private function showGroupConfiguration() {
		$out = $this->getOutput();
		$services = MediaWikiServices::getInstance();
		/** @var GroupLevelStore */
		$groupStore = $services->get( 'FieldPermissions.GroupLevelStore' );
		/** @var VisibilityLevelStore */
		$levelStore = $services->get( 'FieldPermissions.VisibilityLevelStore' );
		
		$out->addWikiMsg( 'fieldpermissions-manage-groups-header' );

		$allLevels = $levelStore->getAllLevels();
		$levelOptions = [];
		foreach ( $allLevels as $l ) {
			$levelOptions[$l->getName() . " (" . $l->getNumericLevel() . ")"] = $l->getNumericLevel();
		}
		// Add "No limit / Default" option? Actually we store max level as int.
		// Let's assume user MUST pick a level defined in system or we interpret based on numeric.
		
		$formDescriptor = [
			'gl_group_name' => [
				'type' => 'text', // or select from system groups
				'label-message' => 'fieldpermissions-group-name',
				'required' => true
			],
			'gl_max_level' => [
				'type' => 'select',
				'options' => $levelOptions,
				'label-message' => 'fieldpermissions-max-level',
				'required' => true
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitTextMsg( 'fieldpermissions-set-group-level' );
		$htmlForm->setSubmitCallback( function( $data ) use ( $groupStore ) {
			try {
				$groupStore->setGroupMaxLevel( $data['gl_group_name'], (int)$data['gl_max_level'] );
				return true;
			} catch ( \Exception $e ) {
				return $e->getMessage();
			}
		} );
		
		$htmlForm->show();

		// List Groups
		$mappings = $groupStore->getAllGroupLevels();
		
		$rows = [];
		$rows[] = Html::rawElement( 'tr', [],
			Html::element( 'th', [], 'Group Name' ) .
			Html::element( 'th', [], 'Max Level' ) .
			Html::element( 'th', [], 'Actions' )
		);

		foreach ( $mappings as $group => $level ) {
			$deleteLink = Html::element( 'button', [
				'type' => 'submit',
				'name' => 'group',
				'value' => $group,
				'onclick' => "return confirm('Are you sure?');"
			], 'Remove' );
			
			$form = Html::rawElement( 'form', [ 'method' => 'post', 'action' => $this->getPageTitle()->getLocalURL( 'action=delete_group' ) ],
				$deleteLink
			);

			$rows[] = Html::rawElement( 'tr', [],
				Html::element( 'td', [], $group ) .
				Html::element( 'td', [], $level ) .
				Html::rawElement( 'td', [], $form )
			);
		}
		
		// Handle delete for groups
		$request = $this->getRequest();
		if ( $request->wasPosted() && $request->getVal( 'action' ) === 'delete_group' ) {
			$g = $request->getVal( 'group' );
			if ( $g ) {
				$groupStore->removeGroupMapping( $g );
				$this->getOutput()->redirect( $this->getPageTitle()->getFullURL() );
			}
		}

		$out->addHtml( Html::rawElement( 'table', [ 'class' => 'wikitable' ], implode( "\n", $rows ) ) );
	}
}

