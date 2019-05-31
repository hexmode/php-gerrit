<?php

/*
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

use Fduch\Netrc\Netrc;
use Exception;

class GerritRestAPI {
    /**
     * Interface to the Gerrit REST API.
     *
     * @param string $url The full URL to the server, including the
     *  `http(s)://` prefix. If `auth` is given, `url` will be
     *  automatically adjusted to include Gerrit's authentication
     *  suffix.
     * @param Auth $auth (optional) Auth handler
     * @param bool $verify (optional) Set to false to disable
     *  verification of SSL certificates
     */
    public function __construct(
        string $url,
        string $auth = null,
        bool $verify = true
    ) {
        if ( !$auth ) {
            try {
                $auth = Netrc::parse();
                var_dump( $auth );
            } catch ( Exception $e ) {
            }
        }
    }
}
