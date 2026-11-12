// Gig admin: shared Chart.js look (fonts, grid, tooltips, legends).
// Loaded after Chart.js and before any page script that creates charts.
// Visual defaults only — charts keep their own data and options.
(function (window) {
    'use strict';

    window.GigChartColors = {
        red: '#fa0101',
        orange: '#ffa500',
        redSoft: 'rgba(250, 1, 1, 0.10)',
        orangeSoft: 'rgba(255, 165, 0, 0.14)',
        ink: '#111827',
        muted: '#6b7280',
        grid: 'rgba(17, 24, 39, 0.06)'
    };

    var Chart = window.Chart;
    if (!Chart || !Chart.defaults) {
        return;
    }

    try {
        var d = Chart.defaults;
        var c = window.GigChartColors;

        d.font = d.font || {};
        d.font.family = "'Inter', Roboto, system-ui, -apple-system, 'Segoe UI', sans-serif";
        d.font.size = 12;
        d.color = c.muted;
        d.borderColor = c.grid;

        if (d.scale && d.scale.grid) {
            d.scale.grid.color = c.grid;
            d.scale.grid.drawBorder = false;
            d.scale.grid.tickColor = 'transparent';
        }
        if (d.scale && d.scale.border) {
            d.scale.border.display = false;
        }

        var plugins = d.plugins || {};
        if (plugins.legend && plugins.legend.labels) {
            plugins.legend.labels.usePointStyle = true;
            plugins.legend.labels.pointStyle = 'circle';
            plugins.legend.labels.boxWidth = 8;
            plugins.legend.labels.boxHeight = 8;
            plugins.legend.labels.padding = 16;
            plugins.legend.labels.color = c.ink;
            plugins.legend.labels.font = { weight: '500' };
        }
        if (plugins.tooltip) {
            plugins.tooltip.backgroundColor = 'rgba(17, 24, 39, 0.94)';
            plugins.tooltip.titleColor = '#fff';
            plugins.tooltip.bodyColor = '#e5e7eb';
            plugins.tooltip.padding = 10;
            plugins.tooltip.cornerRadius = 10;
            plugins.tooltip.displayColors = true;
            plugins.tooltip.usePointStyle = true;
            plugins.tooltip.boxPadding = 4;
            plugins.tooltip.titleFont = { weight: '600' };
        }

        var el = d.elements || {};
        if (el.bar) {
            el.bar.borderRadius = 8;
            el.bar.borderSkipped = false;
        }
        if (el.line) {
            el.line.borderWidth = 2.5;
            el.line.tension = 0.35;
        }
        if (el.point) {
            el.point.radius = 0;
            el.point.hoverRadius = 5;
            el.point.hitRadius = 12;
            el.point.backgroundColor = '#fff';
            el.point.borderWidth = 2;
        }
        if (el.arc) {
            el.arc.borderWidth = 3;
            el.arc.borderColor = '#fff';
        }
    } catch (e) {
        // Styling is optional; never block the page's own charts.
    }
})(window);
