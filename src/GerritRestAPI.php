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

use Exception;
use Fduch\Netrc\HTTPBasicAuth as NetrcAuth;
use Fduch\Netrc\Netrc;
use GuzzleHttp\Client as GuzzleClient;
use Hexmode\HTTPBasicAuth\Client as Auth;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

class GerritRestAPI implements LoggerAwareInterface {
	/** @param string $url */
	protected $url;
	/** @param Hexmode\HTTPBasicAuth\Client $auth */
	protected $auth;
	/** @param bool $verify ssl or not */
	protected $verify;
	/** @param GuzzleHttp\Client $client */
	protected $client;
	/** @param GuzzleHttp\Response $response */
	protected $response;
	/** @param LoggerInterface $logger */
	protected $logger;
	/** @param array $json */
	protected $json;

	const AUTH_SUFFIX = "/a";
	const MAGIC_JSON_PREFIX = ")]}'\n";
	const DEFAULT_HEADERS = [
		'Accept' => 'application/json',
		'Accept-Encoding' => 'gzip'
	];

    /**
     * Interface to the Gerrit REST API.
     *
     * @param string $url The full URL to the server, including the
     *  `http(s)://` prefix. If `auth` is given, `url` will be
     *  automatically adjusted to include Gerrit's authentication
     *  suffix.
     * @param Auth $auth (optional) Auth handler
     */
    public function __construct(
        string $url,
        Auth $auth = null
    ) {
		$this->url = rtrim( $url, "/" );
		$this->verify = true;
		$this->client = new GuzzleClient();
		$this->logger = new NullLogger();
		$isAuthURL = substr( $this->url, - strlen( self::AUTH_SUFFIX ) )
				   === self::AUTH_SUFFIX;

		$this->auth = $auth;
		if ( $this->auth === null) {
			$this->auth = new NetrcAuth( $this->url );
		}

		if ( $this->auth->hasCreds( $this->url ) ) {
			# Now point to the right url
			if ( !$isAuthURL ) {
				$this->url .= self::AUTH_SUFFIX;
			}
		} else {
			if ( $isAuthURL ) {
				$this->url = substr(
					$this->url, 0, - strlen( self::AUTH_SUFFIX )
				);
			}
		}

		if ( substr( $this->url, -1 ) !== "/" ) {
			$this->url .= "/";
		}
    }

    /**
     * Sets a logger instance on the object.
     *
     * @param LoggerInterface $logger
     * @return void
     */
    public function setLogger( LoggerInterface $logger ) :void {
		$this->logger = $logger;
	}

	/**
	 * Make the full url for the endpoint.
	 *
	 * @param string $endpoint
	 * @return string the full url
	 */
	protected function makeUrl( string $endpoint) :string {
		return $this->url . ltrim( $endpoint, '/' );
	}

	/**
	 * Sent HTTP GET to the endpoint.
	 *
	 * @param string $endpoint to send to.
	 * @return array JSON decoded result
	 *
	 * @throw GuzzleHttp\Exception if the response contains an HTTP error status
	 *   code.
	 */
	public function get( string $endpoint ) :array {
        $this->response = $this->client->request( 'GET', $this->makeUrl( $endpoint ) );

        return $this->decodeResponse();
	}

    /**
	 * Strip off Gerrit's magic prefix and decode a response.
	 *
	 * @return array<string, mixed> Decoded JSON content as a dict.  If a JsonException is
	 *   thrown, you can getBody() from the response.
	 * @throw JsonException if problem occurs during JSON parsing.
	 */
	public function decodeResponse() :array {
		$headers = array_change_key_case( $this->response->getHeaders() );
		$contentType['charset'] = 'unknown';
		if ( isset( $headers['content-type'] ) ) {
			$args = array_map(
				'trim', explode( ";", $headers['content-type'][0] )
			);
			$contentType['media-type'] = array_shift( $args );
			if ( count( $args ) > 0 ) {
				array_map( function( $arg ) use ( &$contentType ) {
					list($name, $value) = array_map( 'trim', explode( "=", $arg, 2 ) );
					$contentType[$name] = $value;
				}, $args );
			}
		}

		$contentEncoding = [];
		if ( isset( $headers['content-encoding'] ) ) {
			$contentEncoding = array_map(
				'trim', explode( ',', $this->response->getHeader( 'content-encoding' )[0] )
			);
			throw new \Extension( "what is this? " . var_export( $contentEncoding, true ) );
		}
		$this->logger->debug(
			sprintf(
				"status[%s] content_type[%s] encoding[%s]", $this->response->getStatusCode(),
				$contentType['media-type'], $contentType['charset']
			)
		);
		$stream = "" . $this->response->getBody();
		if (
			substr( $stream, 0, strlen( self::MAGIC_JSON_PREFIX ) ) ===
			self::MAGIC_JSON_PREFIX
		) {
			$stream = substr( $stream, strlen( self::MAGIC_JSON_PREFIX ) );
		}
		return json_decode( $stream, true, 512, JSON_THROW_ON_ERROR );
	}
}
