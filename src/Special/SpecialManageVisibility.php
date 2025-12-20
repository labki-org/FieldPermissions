<?php

namespace FieldPermissions\Special;

use FieldPermissions\Config\GroupLevelStore;
use FieldPermissions\Config\VisibilityLevelStore;
use HTMLForm;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;

/**
 * Special:ManageVisibility
 *
 * Provides an administrative interface for:
 *   (1) Managing visibility levels
 *   (2) Assigning max visibility levels to user groups
 *
 * Permissions:
 *   Users must hold right: fp-manage-visibility
 */
class SpecialManageVisibility extends SpecialPage {

	public function __construct() {
		parent::__construct( 'ManageVisibility', 'fp-manage-visibility' );
	}

	/**
	 * Entry point.
	 */

	/**
	 * Entry point.
	 *
	 * @param string|null $sub
	 */
	public function execute( $sub ) {
		$this->setHeaders();
		$this->checkPermissions();

		$out = $this->getOutput();
		$out->addModules( 'mediawiki.special' );

		$this->showLevelsSection();
		$out->addHtml( '<hr>' );
		$this->showGroupsSection();
	}

	/* -----------------------------------------------------------------------
	 * SECTION 1 — Visibility Levels (CRUD)
	 * -------------------------------------------------------------------- */

	private function showLevelsSection(): void {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$appCtx = $this->getContext();
		$services = MediaWikiServices::getInstance();

		/** @var VisibilityLevelStore $store */
		$store = $services->get( 'FieldPermissions.VisibilityLevelStore' );

		$out->addWikiMsg( 'fieldpermissions-manage-levels-header' );

		/* -------------------------------------------------------------
		   Handle deletion (POST)
		-------------------------------------------------------------- */
		if ( $request->wasPosted() && $request->getVal( 'action' ) === 'delete_level' ) {
			$id = $request->getInt( 'id' );
			if ( $id ) {
				$store->deleteLevel( $id );
				$out->addWikiMsg( 'fieldpermissions-level-deleted' );
			}
			$out->redirect( $this->getPageTitle()->getFullURL() );
			return;
		}

		/* -------------------------------------------------------------
		   Add New Visibility Level Form
		-------------------------------------------------------------- */
		$formDescriptor = [
			'vl_name' => [
				'type' => 'text',
				'label-message' => 'fieldpermissions-level-name',
				'required' => true,
				'validation-callback' => function ( $val ) {
					return preg_match( '/^[a-zA-Z0-9_]+$/', $val )
						? true
						: $this->msg( 'fieldpermissions-invalid-level-name' )->text();
				},
			],
			'vl_numeric_level' => [
				'type' => 'int',
				'label-message' => 'fieldpermissions-numeric-level',
				'required' => true,
			],
			'vl_page_title' => [
				'type' => 'text',
				'label-message' => 'fieldpermissions-page-title',
				'help-message' => 'fieldpermissions-page-title-help',
			],
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $appCtx );
		$htmlForm->setSubmitTextMsg( 'fieldpermissions-add-level' );

		$htmlForm->setSubmitCallback( static function ( $data ) use ( $store ) {
			try {
				$store->addLevel(
					$data['vl_name'],
					(int)$data['vl_numeric_level'],
					$data['vl_page_title'] ?: null
				);
				return true;
			} catch ( \Exception $e ) {
				return $e->getMessage();
			}
		} );

		$htmlForm->show();

		/* -------------------------------------------------------------
		   Display Existing Visibility Levels
		-------------------------------------------------------------- */
		$levels = $store->getAllLevels();
		$out->addHtml( $this->buildLevelsTable( $levels ) );
	}

	/**
	 * Render visibility levels in a table.
	 *
	 * @param array $levels List of VisibilityLevel
	 * @return string HTML
	 */
	private function buildLevelsTable( array $levels ): string {
		$rows = [];

		$rows[] = Html::rawElement(
			'tr',
			[],
			Html::element( 'th', [], 'ID' ) .
			Html::element( 'th', [], 'Name' ) .
			Html::element( 'th', [], 'Numeric Level' ) .
			Html::element( 'th', [], 'Page Title' ) .
			Html::element( 'th', [], 'Actions' )
		);

		foreach ( $levels as $level ) {
			$deleteForm = Html::rawElement(
				'form',
				[
					'method' => 'post',
					'action' => $this->getPageTitle()->getLocalURL( [ 'action' => 'delete_level' ] )
				],
				Html::element(
					'button',
					[
						'type' => 'submit',
						'name' => 'id',
						'value' => $level->getId(),
						'onclick' => "return confirm('Are you sure you want to delete this level?');"
					],
					'Delete'
				)
			);

			$rows[] = Html::rawElement(
				'tr',
				[],
				Html::element( 'td', [], $level->getId() ) .
				Html::element( 'td', [], $level->getName() ) .
				Html::element( 'td', [], $level->getNumericLevel() ) .
				Html::element( 'td', [], $level->getPageTitle() ?: '-' ) .
				Html::rawElement( 'td', [], $deleteForm )
			);
		}

		return Html::rawElement( 'table', [ 'class' => 'wikitable' ], implode( "\n", $rows ) );
	}

	/* -----------------------------------------------------------------------
	 * SECTION 2 — Group Configuration
	 * -------------------------------------------------------------------- */

	private function showGroupsSection(): void {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$appCtx = $this->getContext();
		$services = MediaWikiServices::getInstance();

		/** @var GroupLevelStore $groupStore */
		$groupStore = $services->get( 'FieldPermissions.GroupLevelStore' );

		/** @var VisibilityLevelStore $levelStore */
		$levelStore = $services->get( 'FieldPermissions.VisibilityLevelStore' );

		$out->addWikiMsg( 'fieldpermissions-manage-groups-header' );

		/* -------------------------------------------------------------
		   Handle group deletion
		-------------------------------------------------------------- */
		if ( $request->wasPosted() && $request->getVal( 'action' ) === 'delete_group' ) {
			$g = $request->getVal( 'group' );
			if ( $g ) {
				$groupStore->removeGroupMapping( $g );
			}
			$out->redirect( $this->getPageTitle()->getFullURL() );
			return;
		}

		/* -------------------------------------------------------------
		   Group assignment form
		-------------------------------------------------------------- */
		$levels = $levelStore->getAllLevels();
		$levelOptions = [];

		foreach ( $levels as $l ) {
			$label = $l->getName() . " (" . $l->getNumericLevel() . ")";
			$levelOptions[$label] = $l->getNumericLevel();
		}

		$formDescriptor = [
			'gl_group_name' => [
				'type' => 'text',
				'label-message' => 'fieldpermissions-group-name',
				'required' => true,
			],
			'gl_max_level' => [
				'type' => 'select',
				'options' => $levelOptions,
				'label-message' => 'fieldpermissions-max-level',
				'required' => true,
			],
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $appCtx );
		$htmlForm->setSubmitTextMsg( 'fieldpermissions-set-group-level' );

		$htmlForm->setSubmitCallback( static function ( $data ) use ( $groupStore ) {
			try {
				$groupStore->setGroupMaxLevel(
					$data['gl_group_name'],
					(int)$data['gl_max_level']
				);
				return true;
			} catch ( \Exception $e ) {
				return $e->getMessage();
			}
		} );

		$htmlForm->show();

		/* -------------------------------------------------------------
		   Display group mappings
		-------------------------------------------------------------- */
		$groups = $groupStore->getAllGroupLevels();
		$out->addHtml( $this->buildGroupsTable( $groups ) );
	}

	/**
	 * Render group mappings in a table.
	 *
	 * @param array<string,int> $mappings
	 * @return string
	 */
	private function buildGroupsTable( array $mappings ): string {
		$rows = [];

		$rows[] = Html::rawElement(
			'tr',
			[],
			Html::element( 'th', [], 'Group Name' ) .
			Html::element( 'th', [], 'Max Level' ) .
			Html::element( 'th', [], 'Actions' )
		);

		foreach ( $mappings as $group => $level ) {

			$deleteForm = Html::rawElement(
				'form',
				[
					'method' => 'post',
					'action' => $this->getPageTitle()->getLocalURL( [ 'action' => 'delete_group' ] ),
				],
				Html::element(
					'button',
					[
						'type' => 'submit',
						'name' => 'group',
						'value' => $group,
						'onclick' => "return confirm('Remove this group mapping?');",
					],
					'Remove'
				)
			);

			$rows[] = Html::rawElement(
				'tr',
				[],
				Html::element( 'td', [], $group ) .
				Html::element( 'td', [], $level ) .
				Html::rawElement( 'td', [], $deleteForm )
			);
		}

		return Html::rawElement( 'table', [ 'class' => 'wikitable' ], implode( "\n", $rows ) );
	}
}
