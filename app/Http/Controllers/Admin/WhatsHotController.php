<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Content\Actions\SaveWhatsHotBoxAction;
use App\Domain\Content\Models\Banner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The homepage's "What's Hot" promo boxes.
 *
 * Every box's link is a CATEGORY, picked from a list, never typed. The four boxes shipped
 * pointing at /shop whatever they said — the "Alloy Wheels" box did not go to wheels — and a
 * free-text URL field is how a homepage ends up advertising a department that does not exist.
 */
final class WhatsHotController
{
    public function __construct(private SaveWhatsHotBoxAction $boxes) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'string', 'exists:categories,id'],
            'headline' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'link_label' => ['nullable', 'string', 'max:40'],
            'image_url' => ['nullable', 'string', 'url:http,https', 'max:1024'],
        ]);

        try {
            $box = $this->boxes->create([
                'category_id' => $validated['category_id'],
                'headline' => $validated['headline'],
                'tagline' => $validated['tagline'] ?? null,
                'link_label' => $validated['link_label'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return $this->back()->with('error', $e->getMessage());
        }

        return $this->back()->with('status', "\"{$box->title}\" was added to What's Hot"
            .(($validated['image_url'] ?? null) === null
                // Said out loud: a box with no picture is grey, and staff should know that is the
                // reason rather than thinking the upload failed.
                ? ', with no image yet — it will show the theme\'s grey placeholder until you give it one.'
                : '.'));
    }

    public function update(Request $request, string $box): RedirectResponse
    {
        $model = $this->find($box);

        /*
         | One control per request, decided by which field was posted, so a row's several buttons
         | cannot overwrite each other's values. The same rule the coupon screen follows.
        */
        if ($request->has('is_active')) {
            $this->boxes->setActive($model, $request->boolean('is_active'));

            return $this->back()->with('status', $model->is_active
                ? "\"{$model->title}\" is showing on the homepage again."
                : "\"{$model->title}\" is hidden. It is not deleted — switch it back on any time.");
        }

        if ($request->has('direction')) {
            $validated = $request->validate(['direction' => ['required', 'in:up,down']]);
            $this->boxes->move($model, $validated['direction']);

            return $this->back()->with('status', 'The order was changed.');
        }

        $validated = $request->validate([
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'headline' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'link_label' => ['nullable', 'string', 'max:40'],
            'image_url' => ['nullable', 'string', 'url:http,https', 'max:1024'],
        ]);

        try {
            $this->boxes->update($model, [
                'category_id' => $validated['category_id'] ?? null,
                'headline' => $validated['headline'] ?? null,
                // Passed as a key even when blank, because clearing a tagline is a real edit here.
                'tagline' => $request->input('tagline'),
                'link_label' => $validated['link_label'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return $this->back()->with('error', $e->getMessage());
        }

        return $this->back()->with('status', "\"{$model->fresh()->title}\" was updated.");
    }

    public function destroy(string $box): RedirectResponse
    {
        $model = $this->find($box);
        $title = $model->title;

        $this->boxes->delete($model);

        return $this->back()->with('status', "\"{$title}\" was removed from What's Hot.");
    }

    /** Scoped to this placement, so the route cannot be used to delete a hero image by id. */
    private function find(string $box): Banner
    {
        return Banner::query()
            ->where('placement', SaveWhatsHotBoxAction::PLACEMENT)
            ->findOrFail($box);
    }

    private function back(): RedirectResponse
    {
        return redirect()->route('admin.homepage.index');
    }
}
