<?php

namespace FieldPermissions\Model;

class VisibilityLevel {
	private int $id;
	private string $name;
	private int $numericLevel;
	private ?string $pageTitle;

	public function __construct( int $id, string $name, int $numericLevel, ?string $pageTitle = null ) {
		$this->id = $id;
		$this->name = $name;
		$this->numericLevel = $numericLevel;
		$this->pageTitle = $pageTitle;
	}

	public function getId(): int {
		return $this->id;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getNumericLevel(): int {
		return $this->numericLevel;
	}

	public function getPageTitle(): ?string {
		return $this->pageTitle;
	}
}

