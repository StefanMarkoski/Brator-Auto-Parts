{{--
    The homepage, driven by the homepage_sections table.

    Staff control which sections appear, in what order, their headings, and which
    collection feeds each one. They cannot introduce a new SECTION TYPE, because each
    type maps to a partial cut from the theme's existing markup and a new type would
    mean writing new markup — the styling change that is forbidden.

    @param  \Illuminate\Support\Collection  $sections
--}}
@extends('layouts.storefront')

@section('title', 'Brator Auto Parts')

@push('styles')
<link rel="stylesheet" type="text/css" href="/assets/css/theme-style-home-two.css" />
@endpush

@section('content')
@foreach ($sections as $section)
@include($section->view, ['section' => $section])
@endforeach
@endsection
