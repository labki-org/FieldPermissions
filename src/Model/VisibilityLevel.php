<?php

namespace PropertyPermissions\Model;

/**
 * VisibilityLevel
 *
 * Represents a visibility level definition as stored in the
 * `fp_visibility_levels` table.
 *
 * A visibility level consists of:
 *   - An internal numeric ID
 *   - A human-readable name (e.g., "Public", "Lab Members Only")
 *   - A numeric ordering value (higher = more restrictive)
 *   - An optional wiki page title describing the level
 *
 * Instances are immutable value objects and are used by:
 *   - VisibilityResolver (to resolve property visibility)
 *   - PermissionEvaluator (to enforce view permissions)
 *   - Special:ManageVisibility (admin UI)
 */
class VisibilityLevel {

	/** @var int Internal primary key ID */
	private int $id;

	/** @var string Human-readable display name */
	private string $name;

	/** @var int Numeric ordering value; higher = more restrictive */
	private int $numericLevel;

	/** @var string|null Optional: title of a page documenting this level */
	private ?string $pageTitle;

	/**
	 * @param int $id Internal DB ID
	 * @param string $name Display name
	 * @param int $numericLevel Restriction level (0 = public, higher = restricted)
	 * @param string|null $pageTitle Optional wiki page where level details are explained
	 */
	public function __construct( int $id, string $name, int $numericLevel, ?string $pageTitle = null ) {
		$this->id = $id;
		$this->name = $name;
		$this->numericLevel = $numericLevel;
		$this->pageTitle = $pageTitle;
	}

	/** @return int Internal database ID */
	public function getId(): int {
		return $this->id;
	}

	/** @return string The display name for this visibility level */
	public function getName(): string {
		return $this->name;
	}

	/** @return int Numeric restriction level (0 = public, higher = restricted) */
	public function getNumericLevel(): int {
		return $this->numericLevel;
	}

	/** @return string|null Optional wiki page title linked to this level */
	public function getPageTitle(): ?string {
		return $this->pageTitle;
	}
}
