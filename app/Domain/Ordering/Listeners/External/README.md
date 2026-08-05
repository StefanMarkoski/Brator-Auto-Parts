# Ordering / Listeners / External

Empty on purpose, and required by the DDD spec so the boundary is visible.

`Internal/` holds Ordering reacting to its own events (sending the receipt email).
Nothing outside Ordering raises an event Ordering needs to react to yet. When Catalog
starts publishing something Ordering cares about — a price change, a product
withdrawal — the listener belongs here, not in Internal.
