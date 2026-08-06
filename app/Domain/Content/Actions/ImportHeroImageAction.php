<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\Banner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Hero images for the homepage, added by pasting a URL.
 *
 * THE FILE IS FETCHED AND KEPT, NOT HOT-LINKED.
 *
 * Pointing the homepage's background straight at somebody else's server means the shop's first
 * impression depends on a host we do not control: it can start refusing us (one of the URLs
 * this was built against already refuses a plain server-side request), rate-limit us, change
 * the picture underneath us, or simply go away. Google's image-search URLs in particular are
 * cache links with an expiry. Fetching once and serving from our own disk turns a permanent
 * dependency into a one-off download, and it is also the only way to know what we actually got.
 *
 * The stored path is ORIGIN-RELATIVE — "storage/hero/…", no scheme, no host — for the same
 * reason product images are: Storage::url() bakes APP_URL into the value, so a shop reached on
 * a LAN IP or a staging domain would serve backgrounds pointing at wherever APP_URL happened to
 * be when the image was added.
 */
final class ImportHeroImageAction
{
    public const PLACEMENT = 'home_hero';

    /**
     * Below this width the hero visibly softens.
     *
     * The banner is a full-bleed background, so on an ordinary desktop it is painted about
     * 1900px wide. Anything narrower is being upscaled. This is not a rejection threshold —
     * staff may well want a picture we can only get small — it is what the admin warns about
     * so nobody discovers it on the homepage.
     */
    public const COMFORTABLE_WIDTH = 1600;

    private const DIRECTORY = 'hero';

    /** 10 MB. A hero background bigger than this is a mistake, not a photograph. */
    private const MAX_BYTES = 10 * 1024 * 1024;

    private const TIMEOUT_SECONDS = 15;

    private const MAX_REDIRECTS = 3;

    /**
     * Formats every browser we support can paint as a CSS background.
     *
     * WebP is deliberately included: it needs no special handling — every current engine
     * decodes it, and because we serve the file ourselves the Content-Type is ours to get
     * right rather than a third party's to get wrong.
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
     * Fetch one URL and add it as the last hero image.
     *
     * @throws RuntimeException with a message written for the person who pasted the URL
     */
    public function import(string $url): Banner
    {
        $url = trim($url);

        [$body, $finalUrl] = $this->fetch($url);

        $info = @getimagesizefromstring($body);

        if ($info === false || ! isset(self::ALLOWED_TYPES[$info['mime']])) {
            /*
             | Checked by DECODING the bytes, not by trusting the Content-Type header or the
             | file extension. A URL ending in .jpg that serves an HTML error page is the
             | normal failure here — one of the URLs this was built against does exactly that
             | to requests it does not like — and storing that would put a broken background on
             | the homepage with nothing to say why.
            */
            throw new RuntimeException(
                'That URL did not return an image we can use. It answered with '
                .($info === false ? 'something that is not an image at all' : $info['mime'])
                .'. Supported: JPEG, PNG, WebP, GIF and AVIF.'
            );
        }

        return DB::transaction(function () use ($body, $info, $url, $finalUrl): Banner {
            $path = self::DIRECTORY.'/'.Str::ulid()->toString().'.'.self::ALLOWED_TYPES[$info['mime']];

            if (! Storage::disk('public')->put($path, $body)) {
                throw new RuntimeException('The image downloaded but could not be saved. Try again.');
            }

            $banner = Banner::create([
                'placement' => self::PLACEMENT,
                'image_path' => 'storage/'.$path,
                'source_url' => Str::limit($finalUrl, 1_024, ''),
                'image_width' => $info[0],
                'image_height' => $info[1],
                // Appended, so adding an image never reshuffles the rotation staff already
                // arranged. The null check is not decoration: max() on an empty table returns
                // null, and (int) null + 1 would start the very first picture at 1 and leave
                // position 0 permanently empty — out of step with the dense renumbering that
                // delete() does.
                'position' => $this->nextPosition(),
                'is_active' => true,
            ]);

            Log::info('content.import_hero_image.success', [
                'banner_id' => $banner->id,
                'source' => $url,
                'dimensions' => $info[0].'x'.$info[1],
                'bytes' => strlen($body),
            ]);

            return $banner;
        });
    }

    private function nextPosition(): int
    {
        $highest = Banner::query()->where('placement', self::PLACEMENT)->max('position');

        return $highest === null ? 0 : (int) $highest + 1;
    }

    public function delete(Banner $banner): void
    {
        DB::transaction(function () use ($banner): void {
            // Only files WE fetched are removed from disk. The seeded rows point at the
            // purchased theme's own slider assets, which are shared and not ours to delete.
            if (str_starts_with($banner->image_path, 'storage/')) {
                Storage::disk('public')->delete(substr($banner->image_path, strlen('storage/')));
            }

            $banner->delete();

            // Renumbered densely, so "position" keeps meaning "nth in the rotation" after a
            // removal from the middle.
            $remaining = Banner::query()
                ->where('placement', self::PLACEMENT)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            foreach ($remaining as $index => $row) {
                if ((int) $row->position !== $index) {
                    $row->update(['position' => $index]);
                }
            }

            Log::info('content.delete_hero_image.success', ['banner_id' => $banner->id]);
        });
    }

    /**
     * Download the URL, refusing to be used as a probe of our own network.
     *
     * This endpoint takes a URL from a form and makes the SERVER open it, which is the exact
     * shape of a request-forgery hole: left unguarded, "http://127.0.0.1:3306" or a cloud
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
                        // Pinned to the address that was just validated.
                        CURLOPT_RESOLVE => [$pin],
                        // Refuses part-way through rather than buffering a huge file into
                        // memory and only then discovering how big it was.
                        CURLOPT_NOPROGRESS => false,
                        CURLOPT_PROGRESSFUNCTION => fn ($h, $expected, $sofar): int => $sofar > self::MAX_BYTES ? 1 : 0,
                    ],
                ])
                    /*
                     | A browser user-agent, because some image hosts answer a default client
                     | string with an HTML block page — img.sixt.com among them, which is how
                     | this was found. We fetch a file an operator pointed us at, once.
                    */
                    ->withUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '
                        .'(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36')
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->connectTimeout(8)
                    // Followed by hand, one hop at a time, so every destination is checked.
                    // Letting the client follow would let a redirect land anywhere, guard
                    // bypassed.
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

    /**
     * @return string a CURLOPT_RESOLVE entry pinning the host to the validated address
     */
    private function assertFetchable(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('That does not look like a full URL. It needs to start with https://.');
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            // file://, gopher:// and friends are how this kind of fetcher gets turned into a
            // way to read our own disk.
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
        // Laravel's own reserved-range flags cover loopback, the private blocks and
        // link-local — including the 169.254.169.254 metadata address every cloud provider
        // exposes, which is the first thing anybody tries against a fetcher like this.
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
