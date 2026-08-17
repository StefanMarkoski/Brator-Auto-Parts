<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use App\Support\ImageUrl;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fetch an image somebody pasted a URL for, and keep it.
 *
 * Extracted from the hero-banner importer once product photos needed the same thing. It was one
 * action's private methods, and copying 120 lines of request-forgery guard into a second caller
 * is how one of the two copies ends up missing a check.
 *
 * WHY WE KEEP THE FILE INSTEAD OF LINKING TO IT: pointing the shop at somebody else's server
 * makes our pages depend on a host we do not control. It can start refusing us — one of the URLs
 * this was first built against does exactly that to a plain server-side request — rate-limit us,
 * change the picture underneath us, or vanish. Fetching once turns a permanent dependency into a
 * one-off download, and it is the only way to know what we actually received.
 */
final class RemoteImage
{
    /** 10 MB. An image for a web page bigger than this is a mistake, not a photograph. */
    private const MAX_BYTES = 10 * 1024 * 1024;

    private const TIMEOUT_SECONDS = 15;

    private const MAX_REDIRECTS = 3;

    /**
     * Formats every browser we serve can paint.
     *
     * WebP is deliberately included and needs no special handling: every current engine decodes
     * it, and because we serve the file ourselves the Content-Type is ours to get right rather
     * than a third party's to get wrong.
     *
     * @var array<string, string>
     */
    private const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
    ];

    /**
     * Fetch the URL, check it really is an image, and store it under the given directory.
     *
     * The stored path is ORIGIN-RELATIVE ("storage/…"), with no scheme and no host, for the same
     * reason product images are: Storage::url() bakes APP_URL into the value, so a shop reached on
     * a LAN IP or a staging domain would serve images pointing at wherever APP_URL happened to be
     * when the file arrived.
     *
     * @return array{path: string, width: int, height: int, source: string, bytes: int}
     *
     * @throws RuntimeException with a message written for whoever pasted the URL
     */
    public function fetchInto(string $url, string $directory): array
    {
        [$body, $finalUrl] = $this->fetch(trim($url));

        $info = @getimagesizefromstring($body);

        if ($info === false || ! isset(self::ALLOWED_TYPES[$info['mime']])) {
            /*
             | Decided by DECODING the bytes, never by the Content-Type header or the extension in
             | the URL. A link ending in .jpg that answers with an HTML block page is the normal
             | failure here, and storing that would put a broken image on the shop with nothing
             | anywhere saying why.
            */
            throw new RuntimeException(
                'That URL did not return an image we can use. It answered with '
                .($info === false ? 'something that is not an image at all' : $info['mime'])
                .'. Supported: JPEG, PNG, WebP, GIF and AVIF.'
            );
        }

        $path = trim($directory, '/').'/'.Str::ulid()->toString().'.'.self::ALLOWED_TYPES[$info['mime']];

        if (! Storage::disk(ImageUrl::disk())->put($path, $body)) {
            throw new RuntimeException('The image downloaded but could not be saved. Try again.');
        }

        return [
            'path' => 'storage/'.$path,
            'width' => (int) $info[0],
            'height' => (int) $info[1],
            'source' => Str::limit($finalUrl, 1_024, ''),
            'bytes' => strlen($body),
        ];
    }

    /**
     * Download the URL, refusing to be used as a probe of our own network.
     *
     * An endpoint that takes a URL from a form and makes the SERVER open it is a
     * request-forgery hole by default: left unguarded, "http://127.0.0.1:3306" or a cloud
     * metadata address would be fetched from inside our network and the bytes handed back
     * through the admin. So every hop is checked, and the connection is PINNED to the address
     * that was checked — otherwise the name could resolve to something else between the check
     * and the connection.
     *
     * @return array{0: string, 1: string} the body and the URL it finally came from
     */
    private function fetch(string $url): array
    {
        $seen = [];

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (in_array($url, $seen, true)) {
                throw new RuntimeException('That URL redirects in a loop.');
            }

            $seen[] = $url;
            $pin = $this->assertFetchable($url);

            try {
                $response = Http::withOptions([
                    'curl' => [
                        CURLOPT_RESOLVE => [$pin],
                        // Refuses part-way through rather than buffering a huge file into memory
                        // and only then discovering how big it was.
                        CURLOPT_NOPROGRESS => false,
                        CURLOPT_PROGRESSFUNCTION => fn ($h, $expected, $sofar): int => $sofar > self::MAX_BYTES ? 1 : 0,
                    ],
                ])
                    /*
                     | A browser user-agent, because some image hosts answer a default client
                     | string with an HTML block page — img.sixt.com among them, which is how this
                     | was found. We fetch a file an operator pointed us at, once.
                    */
                    ->withUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '
                        .'(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36')
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->connectTimeout(8)
                    // Followed by hand, one hop at a time, so every destination is checked.
                    // Letting the client follow would let a redirect land anywhere, guard bypassed.
                    ->withoutRedirecting()
                    ->get($url);
            } catch (ConnectionException $e) {
                throw new RuntimeException('That URL could not be fetched: '.$e->getMessage().'.');
            }

            if ($response->redirect()) {
                $location = (string) $response->header('Location');

                if ($location === '') {
                    throw new RuntimeException('That URL redirected without saying where to.');
                }

                // Relative Location headers are legal and common.
                $url = str_contains($location, '://')
                    ? $location
                    : rtrim($this->originOf($url), '/').'/'.ltrim($location, '/');

                continue;
            }

            if ($response->status() !== 200) {
                throw new RuntimeException(
                    'That URL answered '.$response->status().' rather than serving an image.'
                );
            }

            $body = $response->body();

            if ($body === '') {
                throw new RuntimeException('That URL returned an empty response.');
            }

            if (strlen($body) > self::MAX_BYTES) {
                throw new RuntimeException('That image is larger than 10 MB.');
            }

            return [$body, $url];
        }

        throw new RuntimeException('That URL redirects too many times.');
    }

    private function originOf(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /** @return string a CURLOPT_RESOLVE entry pinning the host to the validated address */
    private function assertFetchable(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('That does not look like a full URL. It needs to start with https://.');
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            // file://, gopher:// and friends are how this kind of fetcher gets turned into a way
            // to read our own disk.
            throw new RuntimeException('Only http and https URLs can be fetched.');
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);

        $address = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? $host
            : gethostbyname($host);

        if ($address === $host && filter_var($host, FILTER_VALIDATE_IP) === false) {
            throw new RuntimeException("That host could not be found: {$host}.");
        }

        if (! $this->isPublicAddress($address)) {
            throw new RuntimeException(
                'That URL points inside our own network, so it will not be fetched. Use a public image URL.'
            );
        }

        return $host.':'.$port.':'.$address;
    }

    private function isPublicAddress(string $address): bool
    {
        // Laravel's own reserved-range flags cover loopback, the private blocks and link-local —
        // including the 169.254.169.254 metadata address every cloud provider exposes, which is
        // the first thing anybody tries against a fetcher like this.
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
