<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Content\Http\Requests\StoreContactSubmissionRequest;
use App\Domain\Content\Models\ContactSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class PageController
{
    public function about(): View
    {
        return view('pages.about');
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function submitContact(StoreContactSubmissionRequest $request): RedirectResponse
    {
        ContactSubmission::create($request->toArray());

        return redirect()
            ->route('contact')
            ->with('status', 'Thanks — we have your message and will be in touch.');
    }
}
