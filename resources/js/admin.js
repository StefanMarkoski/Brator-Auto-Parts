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

/*
 | The fitment picker on the product screens: which cars a part fits.
 |
 | Deliberately a cascade rather than one list of every vehicle. 82 engine variants here, tens
 | of thousands in a real catalogue — a single multi-select of all of them cannot be used, and
 | staff look for a car in the same order a shopper does.
 |
 | Each level is fetched when the one above changes, so the browser never holds the whole
 | vehicle tree. Year is optional and only narrows.
 */
Alpine.data('fitmentPicker', (config = {}) => ({
    fieldName: (config.name || 'fitment_variant_ids') + '[]',
    chosen: config.chosen || [],

    /*
     | The list as the page loaded it, so the control can say it holds UNSAVED changes.
     |
     | Adding a car here writes nothing by itself — the ids post with the product's own Save —
     | and without this the screen looks identical whether or not that ever happened. That is
     | precisely the confusion this cost: a vehicle added, the shop still not listing the part,
     | and nothing on the page to say which half had gone wrong.
    */
    initial: (config.chosen || []).map((v) => String(v.id)).sort().join(','),

    get dirty() {
        return this.chosen.map((v) => String(v.id)).sort().join(',') !== this.initial;
    },

    years: [],
    makes: [],
    models: [],
    subModels: [],
    engines: [],

    year: '',
    make: '',
    model: '',
    subModel: '',
    engine: '',
    error: '',

    async boot() {
        await Promise.all([this.load('years'), this.load('makes')]);
    },

    /*
     | One place that talks to the server, so a failure is reported the same way wherever it
     | happens. A silently empty dropdown is the worst outcome here: it reads as "no vehicles
     | match" when it actually means the request failed.
     */
    async load(what, params = {}) {
        const paths = {
            years: 'years',
            makes: 'makes',
            models: 'models/' + params.make,
            subModels: 'sub-models/' + params.model,
            engines: 'engines/' + params.model,
        };

        const query = new URLSearchParams();
        if (this.year) query.set('year', this.year);
        if (params.name) query.set('name', params.name);

        try {
            const url = '/admin/vehicles/' + paths[what] + (query.toString() ? '?' + query : '');
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) throw new Error(String(response.status));

            this[what] = await response.json();
            this.error = '';
        } catch (e) {
            this[what] = [];
            this.error = 'Could not load vehicles. Reload the page and try again.';
        }
    },

    /*
     | Choosing a level clears everything below it. Without this, narrowing to a Golf V and then
     | switching Make to BMW would leave "Golf V" sitting in the Model box, and Add would record
     | a car nobody chose.
     */
    async onYear() {
        this.make = this.model = this.subModel = this.engine = '';
        this.models = [];
        this.subModels = [];
        this.engines = [];
        await this.load('makes');
    },

    async onMake() {
        this.model = this.subModel = this.engine = '';
        this.models = [];
        this.subModels = [];
        this.engines = [];

        if (this.make) await this.load('models', { make: this.make });
    },

    async onModel() {
        this.subModel = this.engine = '';
        this.subModels = [];
        this.engines = [];

        if (this.model) await this.load('subModels', { model: this.model });
    },

    async onSubModel() {
        this.engine = '';
        this.engines = [];

        if (this.subModel) {
            await this.load('engines', { model: this.model, name: this.subModel });
        }
    },

    addLabel() {
        if (!this.engines.length) return 'Add vehicle';
        if (this.engine) return 'Add this engine';

        return 'Add all ' + this.engines.length + (this.engines.length === 1 ? ' engine' : ' engines');
    },

    /*
     | Down to the engine, because the engine decides the part — a 2.0 in the same body shell
     | gets bigger brakes than a 1.4. "All engines of this sub model" stays available because
     | plenty of parts (a cabin filter, a bulb) genuinely do not care which engine is fitted.
     */
    add() {
        const picked = this.engine
            ? this.engines.filter((e) => String(e.id) === String(this.engine))
            : this.engines;

        const label = [
            this.makes.find((m) => String(m.id) === String(this.make))?.name,
            this.models.find((m) => String(m.id) === String(this.model))?.name,
            this.subModel,
        ].filter(Boolean).join(' ');

        picked.forEach((engine) => {
            // Already listed: adding it again would post a duplicate id and show two chips for
            // one car.
            if (this.chosen.some((v) => String(v.id) === String(engine.id))) return;

            this.chosen.push({ id: engine.id, label: (label + ' ' + engine.label).trim() });
        });

        this.chosen.sort((a, b) => a.label.localeCompare(b.label));
    },

    remove(id) {
        this.chosen = this.chosen.filter((v) => String(v.id) !== String(id));
    },
}));

Alpine.start();
