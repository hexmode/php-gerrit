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

use Exception;
use Fduch\Netrc\Netrc;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Uri;
use Hexmode\HTTPBasicAuth\Client as Auth;
use Hexmode\PhpGerrit\Entity;
use Hexmode\PhpGerrit\Entity\BranchInfo;
use Hexmode\PhpGerrit\Entity\BranchInput;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class GerritRestAPI implements LoggerAwareInterface {
	/** @param string $url */
	protected $url;
	/** @param Hexmode\HTTPBasicAuth $auth */
	protected $auth;
	/** @param bool $verify ssl or not */
	protected $verify;
	/** @param GuzzleHttp\Client $client */
	protected $client;
	/** @param GuzzleHttp\HandlerStack $stack */
	protected $stack;
	/** @param GuzzleHttp\Response $response */
	protected $response;
	/** @param LoggerInterface $logger */
	protected $logger;
	/** @param array $json */
	protected $json;
	/** @param array $parts */
	protected $parts;
	/** @param GuzzleHttp\Cookie\CookieJar $jar */
	protected $jar;
	/** @param string $gerritAuth */
	protected $gerritAuth;
	/** @param bool $loggingIn */
	protected $loggingIn;
	/** @param bool $debug */
	protected $debug;

	const MAGIC_JSON_PREFIX = ")]}'\n";
	const DEFAULT_HEADERS = [
		'Accept' => 'application/json',
		'Accept-Encoding' => 'gzip'
	];
	const COOKIE_NAME = "GerritAccount";
	const XSRF_NAME = 'XSRF_TOKEN';

	/**
	 * Interface to the Gerrit REST API.
	 *
	 * @param string $url The full URL to the server, including the
	 *  `http(s)://` prefix. If `auth` is given, `url` will be
	 *  automatically adjusted to include Gerrit's authentication
	 *  suffix.
	 * @param null|string $auth (optional) netrc file
	 */
	public function __construct( string $url, ?string $auth = null ) {
		$this->url = rtrim( $url, "/" );
		$this->verify = true;
		$this->logger = new NullLogger();

		$this->auth = new Auth( Netrc::Parse( $auth ) );

		$this->debug = false;
		$this->jar = new CookieJar;
		$this->client = new GuzzleClient(
			[
				'headers' => [
					'Accept' => 'application/json',
					'Accept-Encoding' => 'gzip'
				]
			]
		);

		if ( substr( $this->url, -1 ) !== "/" ) {
			$this->url .= "/";
		}
	}

	/**
	 * Enable or disable debugging.
	 *
	 * @param bool $debug
	 */
	public function setDebug( bool $debug ) :void {
		$this->debug = $debug;
	}

	/**
	 * sugar for logged in or not.
	 *
	 * @return bool
	 */
	protected function isLoggedIn() :bool {
		return null !== $this->jar->getCookieByName( self::COOKIE_NAME );
	}

	/**
	 * Check to make sure we have a cookie, indicating that we're
	 * logged in and log in otherwise.
	 */
	protected function ensureLoggedIn() :void {
		if ( !$this->isLoggedIn() && !$this->loggingIn ) {
			$this->loggingIn = true;
			if ( !$this->auth->hasCreds( $this->url ) ) {
				throw new Exception( "No auth for {$this->url}!" );
			}
			$resp = $this->post(
				'login/', [
					'username' => $this->auth->getUsername( $this->url ),
					'password' => $this->auth->getPassword( $this->url )
				]
			);
			if ( !$this->isLoggedIn() ) {
				throw new Exception( "Couldn't log in!" );
			}
			$this->gerritAuth = $this->jar->getCookieByName( self::XSRF_NAME )
							 ->getValue();
			$this->loggingIn = false;
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
	 * @return \Psr\Http\Message\UriInterface the full url
	 */
	protected function makeUrl( string $endpoint ) :UriInterface {
		$parts = $this->getParts();
		$eparts = parse_url( $endpoint );
		if ( isset( $eparts['path'] ) ) {
			$parts['path'] .= ltrim( $eparts['path'], '/' );
			unset( $eparts['path'] );
		}
		$parts += $eparts;
		return Uri::fromParts( $parts );
	}

	/**
	 * Get the parts of a url.
	 *
	 * @return array
	 */
	protected function getParts() :array {
		if ( !$this->parts ) {
			$this->parts = parse_url( $this->url );
		}
		return $this->parts;
	}

	/**
	 * Get the standard parameters.
	 *
	 * @return array
	 */
	protected function getStdParams() :array {
		$headers = [];
		if ( $this->debug ) {
			$headers['debug'] = true;
		}
		$headers['cookies'] = $this->jar;
		if ( $this->gerritAuth ) {
			$headers['headers']['X-Gerrit-Auth'] = $this->gerritAuth;
		}
		return $headers;
	}

	/**
	 * Convenience function to get the branches for a project
	 *
	 * @param string $project name
	 * @param array $options branch options
	 * @return array<array-key, BranchInfo>
	 */
	public function getProjectBranches( string $project, array $options = [] ) :array {
		$project = urlencode( $project );
		$ret = [];

		return Entity::getList(
			$this->get( "/projects/$project/branches/" ), BranchInfo::class
		);
	}

	/**
	 * Convenience function to create a branch
	 *
	 * @param string $project name
	 * @param BranchInput $branch information for creating
	 * @return BranchInfo
	 *
	 * @psalm-suppress MoreSpecificReturnType
	 * @psalm-suppress LessSpecificReturnStatement
	 */
	public function createBranch( string $project, BranchInput $branch ) :BranchInfo {
		$project = urlencode( $project );
		$ret = [];

		return Entity::newFromDecodedJSON(
			$this->get( "/projects/$project/branches/" ), BranchInfo::class
		);
	}

	/**
	 * Send HTTP GET to the endpoint.
	 *
	 * @param string $endpoint to send to.
	 *
	 * @return array<string,mixed>
	 *
	 * @throws GuzzleHttp\Exception if the response contains an HTTP
	 *   error status code.
	 */
	public function get( string $endpoint ) {
		$this->ensureLoggedIn();
		$this->response = $this->client->request(
			'GET', $this->makeUrl( $endpoint ), $this->getStdParams()
		);

		return $this->decodeResponse();
	}

	/**
	 * Send HTTP PUT to the endpoint.
	 *
	 * @param string $endpoint to send to.
	 * @param mixed $body json-encodable content
	 *
	 * @return array<string,mixed>
	 *
	 * @throws GuzzleHttp\Exception if the response contains an HTTP
	 *   error status code.
	 */
	public function put( string $endpoint, $body ) {
		$this->ensureLoggedIn();
		$this->response = $this->client->request(
			'PUT', $this->makeUrl( $endpoint ), array_merge(
				$this->getStdParams(), [ 'json' => $body ]
			)
		);
		return $this->decodeResponse();
	}

	/**
	 * Perform an HTTP POST on the endpoint.
	 *
	 * @param string $endpoint to send to.
	 * @param array $params to post
	 *
	 * @return array<string,mixed>
	 *
	 * @throws GuzzleHttp\Exception if the response contains an HTTP
	 *   error status code.
	 */
	public function post( string $endpoint, array $params ) {
		$this->ensureLoggedIn();
		$this->response = $this->client->request(
			'POST', $this->makeUrl( $endpoint ), array_merge(
				$this->getStdParams(), [ 'form_params' => $params ]
			)
		);
		return $this->decodeResponse();
	}

	/**
	 * Parse out the Content-Type header
	 *
	 * @param array $headers
	 *
	 * @return array{charset:string, media-type?:string|null}
	 */
	protected function parseContentType( array $headers ): array {
		$contentType = [];
		$contentType['charset'] = 'unknown';
		if ( isset( $headers['content-type'] ) ) {
			$args = array_map(
				'trim', explode( ";", $headers['content-type'][0] )
			);
			$contentType['media-type'] = array_shift( $args );
			if ( count( $args ) > 0 ) {
				array_map( function ( $arg ) use ( &$contentType ) {
					list( $name, $value ) = array_map(
						function ( $val ) {
							return strtolower( trim( $val ) );
						}, explode( "=", $arg, 2 )
					);
					$contentType[$name] = $value;
				}, $args );
			}
		}
		return $contentType;
	}

	/**
	 * Grab the Content-Encoding headers
	 *
	 * @param array $headers
	 *
	 * @return array<empty, empty>
	 */
	protected function parseContentEncoding( array $headers ): array {
		$contentEncoding = [];
		if ( isset( $headers['content-encoding'] ) ) {
			$contentEncoding = array_map(
				'trim', explode(
					',', $this->response->getHeader( 'content-encoding' )[0]
				)
			);
			throw new \Exception(
				"what is this? " . var_export( $contentEncoding, true )
			);
		}

		return $contentEncoding;
	}

	/**
	 * Strip off Gerrit's magic prefix and decode a response.
	 *
	 * @return array<string, mixed> Decoded JSON content as a dict.
	 *   If a JsonException is thrown, you can getBody() from the
	 *   response.
	 * @throws JsonException if problem occurs during JSON parsing.
	 */
	public function decodeResponse() {
		if ( !$this->response ) {
			return [];
		}
		$headers = array_change_key_case( $this->response->getHeaders() );
		$contentType = $this->parseContentType( $headers );
		$mediaType = $contentType['media-type'] ?? 'no-media-type';

		$this->logger->debug(
			sprintf(
				"status[%s] content_type[%s] encoding[%s]",
				$this->response->getStatusCode(), $mediaType,
				$contentType['charset'] ?? 'no-charset'
			)
		);
		$stream = $this->response->getBody()->getContents();
		if (
			substr( $stream, 0, strlen( self::MAGIC_JSON_PREFIX ) ) ===
			self::MAGIC_JSON_PREFIX
		) {
			$stream = substr( $stream, strlen( self::MAGIC_JSON_PREFIX ) );
		}
		$ret = "";
		if ( $mediaType === "application/json" ) {
			$ret = json_decode( $stream, true, 512, JSON_THROW_ON_ERROR );
		}
		return $ret;
	}
}
