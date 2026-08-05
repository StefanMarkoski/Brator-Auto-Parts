# Content / Queries / Internal

Empty on purpose, and required by the DDD spec.

Content's reads are all cross-context today (the storefront asking for posts and
banners), so they live in `Public/`. Anything Content needs to read for its own
purposes — an admin listing, a publishing check — belongs here.
