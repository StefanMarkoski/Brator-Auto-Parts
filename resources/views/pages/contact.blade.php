@extends('layouts.shop')

@section('title', 'Contact Us — Brator Auto Parts')

@section('content')
    {{--
        The map showed "Coffeyville Regional Med Center" in Kansas — the template author's own
        pin, left in place on the contact page of a shop in Skopje.

        Now it points at the configured address, through the keyless `?q=…&output=embed` form so
        there is no API key to hold. With no address configured the whole block is dropped: an
        empty map is better than a confident pin on the wrong continent, and the shop is not
        obliged to have a walk-in counter.
    --}}
    @if (config('shop.contact.address'))
        <!-- Header one start-->
        <div class="brator-map-area design-one">
            <div class="container-xxxl container-xxl container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="brator-map">
                            <iframe src="https://maps.google.com/maps?q={{ urlencode((string) config('shop.contact.address')) }}&output=embed"
                                width="600" height="450" style="border:0;" loading="lazy"
                                title="Where to find us"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Header one end-->
    @endif
    <!-- Header one start-->
    <div class="brator-contact-header-area design-one">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-md-12">
                    <div class="contact-header">
                        <h4>Contact</h4>
                        <p>Hi, we are always open for cooperation and suggestions, contact us in one of the ways below</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="brator-contact-area design-one">
        <div class="container-xxxl container-xxl container">
            <div class="row">
                <div class="col-lg-5 col-12">
                    <div class="brator-contact-list-area">
                        <div class="brator-contact-list-header">
                            <h2>Information</h2>
                        </div>
                        {{--
                            The real details, from config/shop.php — the same source the footer and
                            the top bar already read.

                            This block was the theme's invention twice over: "925 Bald Hill St,
                            Asheville, NC 28803" as both a "store" and a "warehouse 925", with
                            "(+005) 800 500 1234" and info@brator.com as dead `#_` links. So the
                            contact page told a customer to collect in North Carolina while the
                            footer of the same page gave the Skopje address — and config/shop.php
                            claimed these were centralised "so the footer, the header and the
                            contact page can never disagree". They can now.

                            One entry, not two: there is one shop. A detail with no value is left
                            out rather than shown as a plausible lie, and the phone and email are
                            real tel:/mailto: links instead of anchors to nowhere.
                        --}}
                        @if (config('shop.contact.address'))
                            <div class="brator-contact-sub-header">
                                <h3>Location</h3>
                            </div>
                            <div class="brator-contact-list-items">
                                <p>{{ config('shop.contact.address') }}</p>
                            </div>
                        @endif

                        <div class="brator-contact-sub-header">
                            <h3>Get in touch</h3>
                        </div>
                        <div class="brator-contact-list-items">
                            @if (config('shop.contact.phone'))
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) config('shop.contact.phone')) }}">{{ config('shop.contact.phone') }}</a>
                            @endif
                            <a href="mailto:{{ config('shop.contact.email') }}">{{ config('shop.contact.email') }}</a>
                        </div>
                        {{--
                            The Social block is gone. It was four icons pointing at `#-` and `#_` —
                            Twitter, Facebook, YouTube and Instagram accounts this shop does not
                            have. When it has them, this is where they go.
                        --}}
                    </div>
                </div>
                <div class="col-lg-7 col-12">
                    <div class="brator-contact-form-area">
                        <div class="brator-contact-form-header">
                            <h2>Drop Us A Line</h2>
                        </div>
                        <div class="brator-contact-form"><span class="info-text">Required fields are marked *</span></div>
                    </div>
                    {{-- The theme shipped these fields with no <form> around them, no action and
                        no CSRF — so POST /contact existed and was unreachable, and the page was
                        decoration. Field names corrected too: the theme had TWO inputs called
                        "name" (one of them the email) and a checkbox called "condcion". --}}
                    <form method="post" action="{{ route('contact.submit', [], false) }}">
                        @csrf
                        @if (session('status'))
                            <div class="brator-contact-form-field-info"><p>{{ session('status') }}</p></div>
                        @endif
                        @if ($errors->any())
                            <div class="brator-contact-form-field-info">
                                @foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach
                            </div>
                        @endif
                    <div class="brator-contact-form-fields">
                        <div class="brator-contact-form-field">
                            <input type="text" name="subject" value="{{ old('subject') }}" placeholder="Subject (Optional)" />
                        </div>
                        <div class="brator-contact-form-field">
                            {{-- old() here too. Subject, email and name all kept their value on a
                                 rejected submission; the message — the only field anybody spends
                                 real time on — came back empty. --}}
                            <textarea name="message" required placeholder="Write your message here">{{ old('message') }}</textarea>
                        </div>
                        <div class="brator-contact-form-field-two-items">
                            <div class="brator-contact-form-field">
                                <input type="email" name="email" value="{{ old('email') }}" placeholder="Your Email *" required />
                            </div>
                            <div class="brator-contact-form-field">
                                <input type="text" name="name" value="{{ old('name') }}" placeholder="Name *" required />
                            </div>
                        </div>
                        <div class="brator-contact-form-field-info">
                            <input type="checkbox" name="consent" value="1" /><span>Save my name & email in this browser for next time i comment</span>
                        </div>
                        <div class="brator-contact-form-field">
                            <button type="submit">Send Message</button>
                        </div>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Header one end-->

@endsection
