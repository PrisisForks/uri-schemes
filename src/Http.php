<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Schemes
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @copyright  2016 Ignace Nyamagana Butera
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.0.0
 * @link       https://github.com/thephpleague/uri-components
 */
declare(strict_types=1);

namespace League\Uri\Schemes;

use Psr\Http\Message\UriInterface;

/**
 * Immutable Value object representing a HTTP(s) Uri.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Schemes
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 */
class Http extends AbstractUri implements UriInterface
{
    /**
     * @inheritdoc
     */
    protected static $supported_schemes = [
        'http' => 80,
        'https' => 443,
    ];

    /**
     * Tell whether the Http(s) URI is in valid state.
     *
     * A valid HTTP(S) URI:
     *
     * <ul>
     * <li>can be schemeless or supports only 'http' and 'https' schemes
     * <li>Host can not be an empty string
     * <li>If a scheme is defined an authority must be present
     * </ul>
     *
     * @see https://tools.ietf.org/html/rfc6455#section-3
     *
     * @return bool
     */
    protected function isValidUri(): bool
    {
        return '' !== $this->host
            && (null === $this->scheme || isset(static::$supported_schemes[$this->scheme]))
            && !('' != $this->scheme && null === $this->host);
    }

    /**
     * Create a new instance from the environment
     *
     * @param array $server the server and execution environment information array typically ($_SERVER)
     *
     * @return static
     */
    public static function createFromServer(array $server): self
    {
        list($user, $pass) = static::fetchUserInfo($server);
        list($host, $port) = static::fetchHostname($server);
        list($path, $query) = static::fetchRequestUri($server);

        $port = $port !== null ? (int) $port : $port;

        return new static(static::fetchScheme($server), $user, $pass, $host, $port, $path, $query);
    }

    /**
     * Returns the environment scheme
     *
     * @param array $server the environment server typically $_SERVER
     *
     * @return string
     */
    protected static function fetchScheme(array $server): string
    {
        $server += ['HTTPS' => ''];
        $res = filter_var($server['HTTPS'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $res !== false ? 'https' : 'http';
    }

    /**
     * Returns the environment user info
     *
     * @param array $server the environment server typically $_SERVER
     *
     * @return array
     */
    protected static function fetchUserInfo(array $server): array
    {
        $server += ['PHP_AUTH_USER' => null, 'PHP_AUTH_PW' => null, 'HTTP_AUTHORIZATION' => ''];
        $user = $server['PHP_AUTH_USER'];
        $pass = $server['PHP_AUTH_PW'];
        if (0 === strpos(strtolower($server['HTTP_AUTHORIZATION']), 'basic')) {
            $res = explode(':', base64_decode(substr($server['HTTP_AUTHORIZATION'], 6)), 2);
            $user = array_shift($res);
            $pass = array_shift($res);
        }

        if (null !== $user) {
            $user = rawurlencode($user);
        }

        if (null !== $pass) {
            $pass = rawurlencode($pass);
        }

        return [$user, $pass];
    }

    /**
     * Returns the environment host
     *
     * @param array $server the environment server typically $_SERVER
     *
     * @throws UriException If the host can not be detected
     *
     * @return array
     */
    protected static function fetchHostname(array $server): array
    {
        $server += ['SERVER_PORT' => null];
        if (isset($server['HTTP_HOST'])) {
            preg_match(',^(?<host>(\[.*\]|[^:])*)(\:(?<port>[^/?\#]*))?$,x', $server['HTTP_HOST'], $matches);

            return [
                $matches['host'],
                isset($matches['port']) ? (int) $matches['port'] : $server['SERVER_PORT'],
            ];
        }

        if (!isset($server['SERVER_ADDR'])) {
            throw new UriException('Hostname could not be detected');
        }

        if (!filter_var($server['SERVER_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $server['SERVER_ADDR'] = '['.$server['SERVER_ADDR'].']';
        }

        return [$server['SERVER_ADDR'], $server['SERVER_PORT']];
    }

    /**
     * Returns the environment path
     *
     * @param array $server the environment server typically $_SERVER
     *
     * @return array
     */
    protected static function fetchRequestUri(array $server): array
    {
        $server += ['PHP_SELF' => '', 'QUERY_STRING' => null];

        if (isset($server['REQUEST_URI'])) {
            $parts = explode('?', $server['REQUEST_URI'], 2);

            return [array_shift($parts), $server['QUERY_STRING']];
        }

        return [$server['PHP_SELF'], $server['QUERY_STRING']];
    }
}
