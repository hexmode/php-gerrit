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
namespace Hexmode\PhpGerrit;

use Hexmode\PhpGerrit\Entity\Exception;

/** See https://gerrit.wikimedia.org/r/Documentation/rest-api-projects.html#json-entities */
class Entity {
	/**
	 * Translate a list from json to a list of objects.
	 *
	 * @param array $list translated json blob we have.
	 * @param string $class Class we want to put each item in.
	 * @return array
	 */
	public static function getList( array $list, string $class ) :array {
		$ret = [];

		foreach( $list as $item ) {
			/** @psalm-suppress InvalidStringClass */
			$ret[] = new $class( $item );
		}

		return $ret;
	}

	/**
	 * Convert a string to camelCase
	 */
	public static function translateToProperty( string $property ) :string {
		return preg_replace_callback(
			"/[-_]([a-z])/",
			function ( array $match ) {
				return ucfirst( $match[1] );
			},
			$property
		);
	}

	public function __construct( array $keyVals ) {
		foreach ( $keyVals as $key => $val ) {
			$property = self::translateToProperty( $key );
			if ( !property_exists( $this, $property ) ) {
				$class = get_class( $this );
				throw new Exception(
					"Non-existant property ($property/$key) for class ($class)!"
				);
			}
			$this->$property = $val;
		}
	}
}
