/*
 | Admin panel JS. Alpine only — TailAdmin's demo pages also pull ApexCharts, FullCalendar
 | and flatpickr. ApexCharts is loaded here because the dashboard's revenue chart is real;
 | the other two are still absent until a screen actually needs a calendar or a date picker.
 |
 | Never loaded on a storefront page: that side runs the theme's own jQuery, and two
 | frameworks fighting over the same DOM is a class of bug worth designing out.
 |
 | The two stores below used to live in a <script> block inside the admin layout. They are
 | JavaScript, not markup, and keeping them in the head meant the one genuinely
 | timing-sensitive script (the pre-paint dark-mode flip) was buried among code that has no
 | business running that early.
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine;

/*
 | ApexCharts is ~900kB minified — six times the rest of this bundle — and exactly one
 | screen draws a chart. Imported statically it was downloaded by every page in the panel,
 | including the login form. This loads it on demand instead, so only a page that actually
 | renders a chart pays for it.
 */
window.adminChart = async function (element, options) {
    const { default: ApexCharts } = await import('apexcharts');

    const chart = new ApexCharts(element, options);
    await chart.render();

    return chart;
};

/*
 | The single owner of the theme. The toggle button used to keep its own copy in x-data,
 | so the two disagreed: one of them put `dark` on <html> while this one also managed the
 | body classes, and a click updated half the page.
 */
Alpine.store('theme', {
    theme: 'light',

    init() {
        const saved = localStorage.getItem('theme');
        const system = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';

        this.theme = saved || system;
        this.apply();
    },

    toggle() {
        this.theme = this.theme === 'light' ? 'dark' : 'light';
        localStorage.setItem('theme', this.theme);
        this.apply();
    },

    apply() {
        const dark = this.theme === 'dark';

        // Safe here, unlike in <head>: Alpine boots from a deferred module, so there is
        // a body by now.
        document.documentElement.classList.toggle('dark', dark);
        document.body.classList.toggle('dark', dark);
        document.body.classList.toggle('bg-gray-900', dark);
    },
});

Alpine.store('sidebar', {
    isExpanded: window.innerWidth >= 1280,
    isMobileOpen: false,
    isHovered: false,

    toggleExpanded() {
        this.isExpanded = !this.isExpanded;
        // Toggling the desktop sidebar always closes the mobile drawer, or the two
        // states contradict each other on a resize.
        this.isMobileOpen = false;
    },

    toggleMobileOpen() {
        this.isMobileOpen = !this.isMobileOpen;
    },

    setMobileOpen(value) {
        this.isMobileOpen = value;
    },

    setHovered(value) {
        // Hover-to-expand only makes sense on a desktop with the rail collapsed.
        if (window.innerWidth >= 1280 && !this.isExpanded) {
            this.isHovered = value;
        }
    },
});

Alpine.start();
