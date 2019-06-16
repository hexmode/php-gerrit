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
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Uri;
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
		$this->logger = new NullLogger();

		$this->auth = $auth;
		if ( $this->auth === null) {
			$this->auth = Netrc::Parse();
		}

		$this->client = new GuzzleClient(
			[
				'headers' => [
					'Accept' => 'application/json',
					'Accept-Encoding' => 'gzip'
				],
				'cookies' => true
			]
		);

		if ( substr( $this->url, -1 ) !== "/" ) {
			$this->url .= "/";
		}
    }

	protected function ensureLoggedIn() {
		$parts = $this->getParts();
		$host = $parts['host'];
		if ( !isset( $this->auth[$host] ) ) {
			throw new Exception( "No auth for $host!" );
		}
		$auth = $this->auth[$host];
		$this->client->request(
			'POST', $this->makeUrl( 'login/' ), [
				'debug' => true,
				'form_params' => [
					'username' => $auth['login'],
					'password' => $auth['password']
				]
			]
		);
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
	protected function makeUrl( string $endpoint ) :Uri {
		$parts = $this->getParts();
		$eparts = parse_url( $endpoint );
		if ( isset( $eparts['path'] ) ) {
			$parts['path'] .= ltrim( $eparts['path'], '/' );
			unset( $eparts['path'] );
		}
		$parts += $eparts;
		return Uri::fromParts( $parts );
	}

	protected function getParts() :array {
		if ( !$this->parts ) {
			$this->parts = parse_url( $this->url );
		}
		return $this->parts;
	}

	/**
	 * Sent HTTP GET to the endpoint.
	 *
	 * @param string $endpoint to send to.
	 * @return array JSON decoded result
	 *
	 * @throw GuzzleHttp\Exception if the response contains an HTTP
	 *   error status code.
	 */
	public function get( string $endpoint ) :array {
		$this->ensureLoggedIn();
        $this->response = $this->client->request(
			'GET', $this->makeUrl( $endpoint ), ['debug' => true]
		);

        return $this->decodeResponse();
	}

	/**
	 * Parse out the Content-Type header
	 *
	 * @param array $headers
	 * @return array
	 */
	protected function parseContentType( array $headers ) {
		$contentType['charset'] = 'unknown';
		if ( isset( $headers['content-type'] ) ) {
			$args = array_map(
				'trim', explode( ";", $headers['content-type'][0] )
			);
			$contentType['media-type'] = array_shift( $args );
			if ( count( $args ) > 0 ) {
				array_map( function( $arg ) use ( &$contentType ) {
					list($name, $value) = array_map(
						'trim', explode( "=", $arg, 2 )
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
	 * @return array
	 */
	protected function parseContentEncoding( array $headers ) {
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
	 * @throw JsonException if problem occurs during JSON parsing.
	 */
	public function decodeResponse() :array {
		if ( !$this->response ) {
			return [];
		}
		$headers = array_change_key_case( $this->response->getHeaders() );
		$contentType = $this->parseContentType( $headers );
		#$contentEncoding = $this->parseContentEncoding( $headers );

		$this->logger->debug(
			sprintf(
				"status[%s] content_type[%s] encoding[%s]",
				$this->response->getStatusCode(),
				$contentType['media-type'], $contentType['charset']
			)
		);
		$stream = $this->response->getBody()->getContents();
		if (
			substr( $stream, 0, strlen( self::MAGIC_JSON_PREFIX ) ) ===
			self::MAGIC_JSON_PREFIX
		) {
			$stream = substr( $stream, strlen( self::MAGIC_JSON_PREFIX ) );
		}
		return json_decode( $stream, true, 512, JSON_THROW_ON_ERROR );
	}
}
