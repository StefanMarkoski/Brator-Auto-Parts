{{--
    The dim sheet behind the mobile sidebar.

    TWO BUGS, both from the same cause: every bit of this element's state lived in Alpine's
    `:class` while the static class list said "cover the whole screen in 50% black".

    1. Alpine ships through Vite as a deferred module, so between first paint and Alpine
       booting, EVERY admin page was covered by a full-screen black sheet. `hidden` only
       arrived once Alpine ran. x-cloak fixes it properly — admin.css already declares
       [x-cloak] { display: none !important } once for the whole panel — because it hides the
       element until Alpine is in charge, rather than racing it with a class.

    2. The click handler was lost. The commented-out original above it had
       @click="$store.sidebar.toggleMobileOpen()"; the live version had nothing, so on a phone
       the drawer could not be dismissed by tapping outside it — the only way out was the
       hamburger, which the open drawer partly covers.

    aria-hidden because it is decoration: the drawer it dims is the thing to interact with.
--}}
<div
  x-cloak
  :class="$store.sidebar.isMobileOpen ? 'block xl:hidden' : 'hidden'"
  x-on:click="$store.sidebar.toggleMobileOpen()"
  aria-hidden="true"
  class="fixed z-50 h-screen w-full bg-gray-900/50"
></div>
