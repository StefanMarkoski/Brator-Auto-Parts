    <!-- Brator newsletter area start -->
    <div class="brator-newsletter-area design-one design-for-home-two gray-bg border-top-1px-dark-shallow">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-5">
                    <div class="brator-newsletter-content">
                        <h2>Subscribe To Our Newsletter</h2>
                        <p>Register now to get latest updates on promotions & coupons. Don’t worry, we not spam!</p>
                    </div>
                </div>
                <div class="col-lg-1"></div>
                <div class="col-lg-6">
                    <div class="brator-newsletter-form design-one">
                        <form class="news-letter-form" method="post" action="{{ route('newsletter.subscribe', [], false) }}">
                            @csrf
                            @if (session('status'))<span class="brator-name">{{ session('status') }}</span>@endif
                            @error('email')<span class="brator-name">{{ $message }}</span>@enderror<span class="wpcf7-form-control-wrap email">
                                <input class="wpcf7-form-control wpcf7-text wpcf7-email wpcf7-validates-as-required wpcf7-validates-as-email" type="email" name="email" value="" size="40" aria-required="true" aria-invalid="false" placeholder="Enter your email" /></span>
                            <button class="wpcf7-form-control wpcf7-submit" type="submit">Subscribe</button><span class="ajax-loader"></span>
                        </form>
                        <p>By subscribing, you accepted the our<a href="#_">Policy</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Brator newsletter area end -->

