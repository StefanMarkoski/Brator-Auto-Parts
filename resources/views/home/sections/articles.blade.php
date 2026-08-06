{{--
    "Articles & Reviews" renders NOTHING, deliberately.

    The theme shipped this as ~190 lines of hardcoded blog posts — "Replace Brakes Guide",
    "Things to keep in mind when washing a car" — with seventeen dead links between them, and
    not one line of Blade logic. It was the largest block of pure fiction left on the
    homepage.

    Blog pages are out of scope by Stefan's decision, so there is no article data to render
    and no article page to link to. Rather than delete the section type (which would break
    the homepage_sections enum and any row using it), the partial degrades to nothing: the
    section can stay in the table, and it can be hidden or reordered from the homepage editor
    like any other.

    When blogs come into scope, this is where they render — the theme's markup is preserved
    in resources/theme-reference/ and in git history.
--}}
