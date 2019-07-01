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

/** See https://gerrit.wikimedia.org/r/Documentation/rest-api-changes.html#bfile-info */
class FileInfo extends Entity {
	/** var string The status of the file (“A”=Added, “D”=Deleted,
	 *  “R”=Renamed, “C”=Copied, “W”=Rewritten). Not set if the file
	 *  was Modified (“M”).
	 */
	protected $status;

	/** var bool Whether the file is binary. */
	protected $binary;

	/** var string the old file path. Only set if the file was renamed
	 * or copied. */
	protected $oldPath;

	/** var int Number of inserted lines. Not set for binary files or
	 *  if no lines were inserted.  An empty last line is not included
	 *  in the count and hence this number can differ by one from
	 *  details provided in <<#diff-info,DiffInfo>>.
	 */
	protected $linesInserted;

	/** var int Number of deleted lines. See $linesInserted */
	protected $linesDeleted;

	/** var int Number of bytes by which the file size
	 * increased/decreased. */
	protected $sizeDelta;

	/** var int File size in bytes. */
	protected $size;

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
