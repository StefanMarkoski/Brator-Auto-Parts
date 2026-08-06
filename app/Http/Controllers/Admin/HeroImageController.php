<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Content\Actions\ImportHeroImageAction;
use App\Domain\Content\Models\Banner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The homepage hero's pictures.
 *
 * Separate from HomepageController because these are not a property of a section row: the hero
 * strip has many images and they are added and removed one at a time, which is a different
 * lifecycle from "edit this section's heading and save".
 */
final class HeroImageController
{
    public function __construct(private ImportHeroImageAction $import) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            /*
             | Validated as a URL here and fetched-and-verified in the action. Both, because
             | this rule only checks the SHAPE of the string — it happily passes a well-formed
             | URL that serves an HTML error page, and it knows nothing about whether the
             | address is one we should be opening from inside our network.
            */
            'url' => ['required', 'string', 'url:http,https', 'max:1024'],
        ]);

        try {
            $banner = $this->import->import($validated['url']);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.homepage.index')
                ->with('error', $e->getMessage());
        }

        $status = 'Hero image added at '.$banner->dimensions().'.';

        if (! $banner->isComfortableForHero()) {
            /*
             | Said at the moment of adding, not left to be discovered on the homepage. The
             | image is still stored — an operator may only be able to get a picture small, and
             | that is their call — but "it looks blurry" should never be a mystery.
            */
            $status .= ' It is narrower than '.ImportHeroImageAction::COMFORTABLE_WIDTH
                .'px, so it will be enlarged to fill the banner and may look soft.';
        }

        return redirect()->route('admin.homepage.index')->with('status', $status);
    }

    public function destroy(string $banner): RedirectResponse
    {
        $model = Banner::query()
            ->where('placement', ImportHeroImageAction::PLACEMENT)
            ->findOrFail($banner);

        $this->import->delete($model);

        return redirect()->route('admin.homepage.index')->with('status', 'Hero image removed.');
    }
}
