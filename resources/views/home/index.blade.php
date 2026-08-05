{{--
    Homepage — cut from the theme's index-2.html.

    Section order is hardcoded here for now. In phase 3 this becomes a loop over
    the `homepage_sections` table so staff can reorder, hide, and retitle them
    (see the schema plan, §5). The include list below is deliberately in the
    theme's original order so the page is byte-comparable against the original.
--}}
@extends('layouts.storefront')

@section('title', 'Brator Auto Parts')

@push('styles')
<link rel="stylesheet" type="text/css" href="/assets/css/theme-style-home-two.css" />
@endpush

@section('content')
@include('home.sections.hero-banner')
@include('home.sections.categories-strip')
@include('home.sections.whats-hot')
@include('home.sections.featured-makes')
@include('home.sections.best-sellers')
@include('home.sections.essential-items')
@include('home.sections.new-arrivals')
@include('home.sections.articles')
@include('home.sections.featured-brands')
@include('home.sections.newsletter')
@endsection
