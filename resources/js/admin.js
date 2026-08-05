/*
 | Admin panel JS. Alpine only — TailAdmin's demo pages also pull ApexCharts,
 | FullCalendar and flatpickr, which are large and not needed here yet. Add them back
 | deliberately if a screen actually calls for a chart or a date picker.
 |
 | Never loaded on a storefront page: that side runs the theme's own jQuery, and two
 | frameworks fighting over the same DOM is a class of bug worth designing out.
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
