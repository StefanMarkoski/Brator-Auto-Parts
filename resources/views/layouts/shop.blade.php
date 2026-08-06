<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>@yield("title", "Brator Auto Parts")</title>
    <!-- Meta Data        -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=0 shrink-to-fit=no" />
    <!-- Favicons-->
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/png" />
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
    @stack('styles')
    <link rel="stylesheet" type="text/css" href="/assets/css/url.css" />
<!--    <link rel="stylesheet" type="text/css" href="/assets/css/rtl.css" />-->
</head>

<body class="boxed_wrapper ltr">
    @include('partials.topbar-shop')
    @include('partials.header-shop')
@yield('content')
    @include('partials.footer-shop')
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
         ?v= is the file's modified time — nothing fingerprints this file, so without
         it a browser keeps running whatever copy it already cached. --}}
    <script src="{{ \App\Support\Assets::version('app/storefront.js') }}" defer></script>
</body>

</html>
