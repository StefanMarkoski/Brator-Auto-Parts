    <!-- Brator newsletter area start -->
    <div class="brator-newsletter-area design-one design-for-home-two gray-bg border-top-1px-dark-shallow">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-5">
                    {{--
                        From the homepage editor, like every other section. Both lines were Blade
                        literals while the editor offered boxes for them and printed neither.

                        The subheading keeps a default here rather than disappearing when blank:
                        this one is the promise about what subscribing signs you up for, and the
                        theme's design puts an empty gap where it used to be.
                    --}}
                    <div class="brator-newsletter-content">
                        <h2>{{ $section->heading ?? 'Subscribe To Our Newsletter' }}</h2>
                        <p>{{ $section->subheading ?? 'Get new arrivals and discount codes by email. No more than one message a week, and you can stop any time.' }}</p>
                    </div>
                </div>
                <div class="col-lg-1"></div>
                <div class="col-lg-6">
                    <div class="brator-newsletter-form design-one">
                        <form class="news-letter-form" method="post" action="{{ route('newsletter.subscribe', [], false) }}">
                            @csrf
                            @if (session('newsletter_status'))<span class="brator-name">{{ session('newsletter_status') }}</span>@endif
                            @error('email')<span class="brator-name">{{ $message }}</span>@enderror<span class="wpcf7-form-control-wrap email">
                                <input class="wpcf7-form-control wpcf7-text wpcf7-email wpcf7-validates-as-required wpcf7-validates-as-email" type="email" name="email" value="" size="40" aria-required="true" aria-invalid="false" placeholder="Enter your email" /></span>
                            <button class="wpcf7-form-control wpcf7-submit" type="submit">Subscribe</button><span class="ajax-loader"></span>
                        </form>
                        <p>By subscribing you accept our<a href="{{ route('about', [], false) }}">terms</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Brator newsletter area end -->

