<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Content\Actions\SubscribeToNewsletterAction;
use App\Domain\Content\Http\Requests\StoreContactSubmissionRequest;
use App\Domain\Content\Http\Requests\SubscribeToNewsletterRequest;
use App\Domain\Content\Models\ContactSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class PageController
{
    public function __construct(private SubscribeToNewsletterAction $subscribe) {}

    public function about(): View
    {
        return view('pages.about');
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    /**
     * The newsletter signup. It was dead UI in the footer of every page — a theme input
     * with no form, no action and no route, and a NewsletterSubscriber model referenced
     * nowhere outside its own file. A subscribe box that does nothing is worse than none.
     */
    public function subscribe(SubscribeToNewsletterRequest $request): RedirectResponse
    {
        $isNew = $this->subscribe->execute($request->validated()['email'], $request->ip());

        return back()->with('status', $isNew
            ? 'Thanks — you are on the list.'
            : 'You are already subscribed with that address.');
    }

    public function submitContact(StoreContactSubmissionRequest $request): RedirectResponse
    {
        ContactSubmission::create($request->toArray());

        return redirect()
            ->route('contact')
            ->with('status', 'Thanks — we have your message and will be in touch.');
    }
}
