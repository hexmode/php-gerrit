<?php

/**
 * Copyright (C) 2019  NicheWork, LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Mark A. Hershberger <mah@nichework.com>
 */
namespace Hexmode\PhpGerrit\Entity;

use Hexmode\PhpGerrit\Entity;

/** See https://gerrit.wikimedia.org/r/Documentation/rest-api-projects.html#branch-input */
class BranchInput extends Entity {
	/** var string The name of the branch. The prefix refs/heads/ can be omitted.
	 *	If set, must match the branch ID in the URL. */
	protected $ref;
	/** var string The base revision of the new branch.  If not set,
	 *	HEAD will be used as base revision. */
	protected $revision;

	/**
	 * Provide a shorter branch name as the key.
	 *
	 * @return string
	 */
	public function getKey() :string {
		if ( substr( $this->ref, 0, 11 ) === "refs/heads/" ) {
			return substr( $this->ref, 11 );
		}
		return $this->ref;
	}
}
