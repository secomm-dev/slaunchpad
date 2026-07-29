define([
    'jquery'
], function ($) {
    var chartJsPromise = null;

    function loadChartJs() {
        if (chartJsPromise) {
            return chartJsPromise;
        }

        chartJsPromise = $.get(require.toUrl('Mirasvit_SeoAudit/js/lib/Chart.min.js'), null, null, 'text')
            .then(function (source) {
                var savedDefine = window.define;

                window.define = undefined;
                try {
                    (0, eval)(source); // eslint-disable-line no-eval
                } finally {
                    window.define = savedDefine;
                }

                return window.Chart;
            });

        return chartJsPromise;
    }

    // Flat score-band color, by range — for the tooltip's color hint, which
    // must be a single solid swatch per score rather than a slice of the bar's
    // gradient (Chart.js would otherwise sample the canvas-space gradient into
    // the tiny swatch rect and show a height-dependent smear). Same three
    // colors and 50/90 tier boundaries as the columns: red (0-49), warm
    // yellow (50-89), green (90-100).
    function scoreBandColor(score) {
        if (score >= 90) {
            return '#0cce6a';
        }

        if (score >= 50) {
            return '#f5c518';
        }

        return '#ff4e43';
    }

    $.widget('mst.seoAuditChart', {
        options: {
            id: '',
            data: null,
            chartOptions: null,
        },

        _create: function () {
            var self = this;

            loadChartJs().done(function (Chart) {
                self._render(Chart);
            });
        },

        _render: function (Chart) {
            var id = this.options.id;
            var data = this.options.data;
            var chartOptions = this.options.chartOptions;
            var currentIndex = data.currentIndex;

            var canvas = document.getElementById(id);
            var ctx = canvas.getContext('2d');
            var height = canvas.clientHeight || 150;

            // Red (0-49) / yellow (50-89) / green (90-100). Red #ff4e43 and
            // green #0cce6a match the left section (the health score ring and
            // the pages split-bar); the middle band uses a warm yellow
            // #f5c518 rather than the ring's orange. Each band is one flat
            // base color, and the handoff between bands is a gradual blend of
            // identical width (0.15) confined to just below each boundary so
            // the color is already fully into the next hue by the time it
            // reaches that boundary. One gradient shared by every bar —
            // Chart.js clips it per-bar since it's canvas-space, not data-space.
            var solidGradient = ctx.createLinearGradient(0, height, 0, 0);
            solidGradient.addColorStop(0, '#ff4e43');
            solidGradient.addColorStop(0.33, '#ff4e43');
            solidGradient.addColorStop(0.48, '#f5c518');
            solidGradient.addColorStop(0.77, '#f5c518');
            solidGradient.addColorStop(0.92, '#0cce6a');
            solidGradient.addColorStop(1, '#0cce6a');

            // Same gradient at reduced alpha, used only for the bar
            // representing the job currently being viewed — inverted
            // emphasis, so history reads at full strength and the current
            // job is a quieter marker instead of the loudest bar.
            var fadedGradient = ctx.createLinearGradient(0, height, 0, 0);
            fadedGradient.addColorStop(0, 'rgba(255,78,67,0.55)');
            fadedGradient.addColorStop(0.33, 'rgba(255,78,67,0.55)');
            fadedGradient.addColorStop(0.48, 'rgba(245,197,24,0.55)');
            fadedGradient.addColorStop(0.77, 'rgba(245,197,24,0.55)');
            fadedGradient.addColorStop(0.92, 'rgba(12,206,106,0.55)');
            fadedGradient.addColorStop(1, 'rgba(12,206,106,0.55)');

            data.datasets[0].backgroundColor = function (context) {
                return context.dataIndex === currentIndex ? fadedGradient : solidGradient;
            };

            // The line dataset mirrors the bar dataset's values (it's a
            // visual overlay, not distinct data), so only the bar dataset
            // (index 0) should produce a tooltip row — otherwise every
            // score would appear twice.
            chartOptions.plugins.tooltip.filter = function (item) {
                return item.datasetIndex === 0;
            };
            chartOptions.plugins.tooltip.callbacks = {
                title: function (items) {
                    // The label is already the audit's coverage interval
                    // (e.g. "Jun 14 - Jun 15"), so no extra formatting needed.
                    return items.length ? data.labels[items[0].dataIndex] : '';
                },
                label: function (item) {
                    return item.formattedValue;
                },
                // Flat per-range swatch instead of the bar's gradient (see
                // scoreBandColor) — the hint reads as one solid band color.
                labelColor: function (item) {
                    var color = scoreBandColor(item.parsed.y);

                    return {
                        backgroundColor: color,
                        borderColor: color,
                        borderRadius: 2,
                    };
                },
            };

            new Chart(canvas, {
                data: data,
                options: chartOptions,
            });
        },
    });

    return $.mst.seoAuditChart;
});
