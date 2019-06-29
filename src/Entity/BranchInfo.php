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

/** See https://gerrit.wikimedia.org/r/Documentation/rest-api-projects.html#branch-info */
class BranchInfo extends Entity {
	/** var string ref of branch*/
	protected $ref;
	/** var string revision to which the branch points */
	protected $revision;
	/** var bool Whether the calling user can delete this branch. */
	protected $canDelete;
	/** var WebLinkInfo[] Links to the branch in external sites as a
	 * list of WebLinkInfo entries. ** NOT YET IMPLEMENTED ** */
	protected $webLinks;
}
