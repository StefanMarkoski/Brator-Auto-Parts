<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>@yield("title", "Brator Auto Parts")</title>
    <!-- Meta Data        -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    {{-- The theme shipped `maximum-scale=1.0, user-scalable=0` (and a malformed attribute
         list — no comma before shrink-to-fit). Pinch-zoom was therefore blocked on the whole
         shop, on a site where people zoom in to read a part number off a photograph. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Favicons-->
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/png" />
    {{--
        A place for a page to ask for something EARLY, from a partial buried in its content.

        The hero uses it for a rel=preload on its first picture. That picture is not an <img>
        and not a CSS url() — the theme lazy-loads it from a data-bg attribute — so nothing in
        the document told the browser it existed, and the request could not start until every
        one of the sixteen blocking scripts below had downloaded. Measured on a cold 400 kbps
        load: the hero picture was not so much as REQUESTED until 28.5 seconds in.

        Placed above the stylesheets on purpose, so the preload scanner sees it in the first
        chunk of the document rather than after 1.1 MB of CSS and script references.

        Sections are captured before the layout renders, so an @push from inside @section
        ('content') — even from a nested @include — still lands here. Verified in the rendered
        HTML rather than assumed, because "it should work" is how the last two head bugs got in.
    --}}
    @stack('head')
    <!-- Google Font-->
    <link href="https://fonts.googleapis.com/css?display=swap&amp;family=Inter:300,400,500,600,700,800" rel="stylesheet" />
    <!-- bootstrap grid-->
    <link rel="stylesheet" type="text/css" href="/assets/css/bootstrap-grid.min.css" />
    <link rel="stylesheet" type="text/css" href="/assets/css/splide.min.css" />
    <link rel="stylesheet" type="text/css" href="/assets/css/splide-core.min.css" />
    <link rel="stylesheet" type="text/css" href="/assets/css/nouislider.css" />
    <link rel="stylesheet" type="text/css" href="/assets/css/select2.min.css" />
    <!-- Theme style-->
    <link rel="stylesheet" type="text/css" href="/assets/css/theme-style.css" />
    {{--
        WITH JAVASCRIPT OFF, THE SHOP WAS A BLANK WHITE PAGE.

        The theme's .preloader-area is a fixed, full-screen, white, z-index:11 sheet, and the
        only thing that ever removes it is $(window).load(... fadeOut()) in brator-script.js.
        No script, no fade — so every page was a white rectangle with a logo, forever, and
        every form underneath it that this project was careful to keep working was unreachable.

        This targets the theme's own class and only applies when scripts are off, so the
        purchased stylesheet is untouched and the preloader still behaves exactly as designed
        for everybody else.
    --}}
    <noscript>
        <style>.preloader-area { display: none !important; }</style>
    </noscript>
    @stack('styles')
    <link rel="stylesheet" type="text/css" href="/assets/css/url.css" />
<!--    <link rel="stylesheet" type="text/css" href="/assets/css/rtl.css" />-->
</head>

<body class="boxed_wrapper ltr">
    @include('partials.topbar')
    @include('partials.header')

@yield('content')
    @include('partials.footer-top')
    @include('partials.footer')

    <button class="scroll-top scroll-to-target" data-target="html">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"></path>
        </svg><span>top</span>
    </button>
    <script src="/assets/js/jquery.js"></script>
    <script src="/assets/js/waypoints.min.js"></script>
    <script src="/assets/js/counterup.min.js"></script>
    <script src="/assets/js/nouislider.js"></script>
    <script src="/assets/js/splide.min.js"></script>
    <script src="/assets/js/lazysizes.min.js"></script>
    <script src="/assets/js/ls.bgset.min.js"></script>
    <script src="/assets/js/tab.js"></script>
    <script src="/assets/js/img-zoom.js"></script>
    <script src="/assets/js/gsap-core.js"></script>
    <script src="/assets/js/scroll-trigger.js"></script>
    <script src="/assets/js/select2.min.js"></script>
    <script src="/assets/js/addIndicators.js"></script>
    <script src="/assets/js/animation.gsap.js"></script>
    <script src="/assets/js/brator-script.js"></script>
    {{-- Storefront enhancement. Served off disk like the theme's own assets: no
         bundler touches this side. Everything degrades to a real submit button.

         The ?v= is the file's own modified time, and it is not decoration. Without it
         the browser keeps serving whatever copy it cached, so a fix ships and returning
         visitors carry on running the old script — which is exactly how the hero
         cross-fade appeared not to work while the correct file was already on the
         server. No bundler touches this file, so nothing else would fingerprint it. --}}
    <script src="{{ \App\Support\Assets::version('app/storefront.js') }}" defer></script>
</body>

</html>
