/**
 * Advanced Persian SEO - Admin V2 JavaScript
 * Handles Features 5-9 UI interactions
 *
 * @package AdvancedPersianSEO
 */

(function ($) {
    'use strict';

    const APSEOV2 = {
        init: function () {
            this.initOOS();
            this.initFaceted();
            this.initForecast();
            this.initDiscovery();
        },

        // ===================== OOS Protector =====================
        initOOS: function () {
            if (!$('#apseo-oos-tbody').length) return;
            this.loadOOSStats();
            this.loadOOSProducts();

            $(document).on('change', '#apseo-oos-filter', function () {
                APSEOV2.loadOOSProducts();
            });
        },

        loadOOSStats: function () {
            $.post(apseoAdmin.ajaxUrl, {
                action: 'apseo_get_oos_stats',
                nonce: apseoAdmin.nonce
            }, function (r) {
                if (!r.success) return;
                $('#oos-total').text(r.data.total_oos);
                $('#oos-with-traffic').text(r.data.with_traffic);
                $('#oos-redirected').text(r.data.redirected);
                $('#oos-pending').text(r.data.pending);
            });
        },

        loadOOSProducts: function (page) {
            $.post(apseoAdmin.ajaxUrl, {
                action: 'apseo_get_oos_products',
                nonce: apseoAdmin.nonce,
                page: page || 1,
                filter: $('#apseo-oos-filter').val()
            }, function (r) {
                if (!r.success) return;
                var $t = $('#apseo-oos-tbody').empty();
                r.data.products.forEach(function (p) {
                    var tc = p.has_traffic ? 'color:green' : 'color:red';
                    $t.append('<tr><td><a href="' + p.edit_url + '">' + p.title + '</a></td>' +
                        '<td style="' + tc + '">' + p.traffic_label + '</td>' +
                        '<td>' + p.action_label + '</td>' +
                        '<td>' + (p.redirect_url || '-') + '</td>' +
                        '<td>' + p.detected_at + '</td>' +
                        '<td><a href="' + p.view_url + '" target="_blank" class="button button-small">مشاهده</a></td></tr>');
                });
            });
        },


        // ===================== Faceted Navigation =====================
        initFaceted: function () {
            if (!$('#apseo-faceted-form').length) return;
            this.loadFacetedSettings();

            $('#apseo-faceted-form').on('submit', function (e) {
                e.preventDefault();
                var data = $(this).serializeArray();
                var payload = { action: 'apseo_save_faceted_settings', nonce: apseoAdmin.nonce };
                data.forEach(function (f) { payload[f.name] = f.value; });
                $.post(apseoAdmin.ajaxUrl, payload, function (r) {
                    APSEOV2.showNotice(r.success ? r.data.message : r.data, r.success ? 'success' : 'error');
                });
            });

            $('#apseo-test-url-btn').on('click', function () {
                $.post(apseoAdmin.ajaxUrl, {
                    action: 'apseo_test_faceted_url',
                    nonce: apseoAdmin.nonce,
                    test_url: $('#apseo-test-url').val()
                }, function (r) {
                    if (!r.success) { APSEOV2.showNotice(r.data, 'error'); return; }
                    var cls = r.data.would_noindex ? 'apseo-info-warning' : 'apseo-info-success';
                    $('#apseo-test-result').show().html(
                        '<div class="apseo-info-box ' + cls + '" style="margin-top:12px;">' +
                        '<p><strong>' + r.data.result_label + '</strong><br>' +
                        'فیلترهای شناسایی‌شده: ' + r.data.detected_filters.join(', ') +
                        '</p></div>'
                    );
                });
            });
        },

        loadFacetedSettings: function () {
            $.post(apseoAdmin.ajaxUrl, {
                action: 'apseo_get_faceted_settings',
                nonce: apseoAdmin.nonce
            }, function (r) {
                if (!r.success) return;
                var s = r.data;
                $('#faceted-enabled').prop('checked', s.enabled);
                $('#faceted-max-params').val(s.max_params_allowed);
                $('#faceted-filter-params').val(s.filter_params_text);
                $('#faceted-safe-params').val(s.safe_params_text);
                $('#faceted-prefix').val(s.custom_filter_prefix);
                $('[name="noindex_sorting"]').prop('checked', s.noindex_sorting);
                $('[name="add_canonical"]').prop('checked', s.add_canonical);
            });
        },

        // ===================== Traffic Forecaster =====================
        initForecast: function () {
            if (!$('#apseo-forecast-tbody').length) return;
            this.loadForecastSummary();

            $('#apseo-fc-calculate').on('click', function () {
                APSEOV2.loadForecast();
            });
        },

        loadForecastSummary: function () {
            $.post(apseoAdmin.ajaxUrl, {
                action: 'apseo_get_forecast_summary',
                nonce: apseoAdmin.nonce
            }, function (r) {
                if (!r.success) return;
                var d = r.data;
                $('#fc-total-kw').text(d.total_keywords_fa);
                $('#fc-optimistic').text('+' + d.scenarios.optimistic.growth);
                $('#fc-moderate').text('+' + d.scenarios.moderate.growth);
                $('#fc-conservative').text('+' + d.scenarios.conservative.growth);

                // Render CTR curve chart
                if (d.ctr_curve && window.Chart) {
                    var ctx = document.getElementById('apseo-ctr-curve-chart');
                    if (ctx) {
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: d.ctr_curve.labels,
                                datasets: [{
                                    label: 'CTR (%)',
                                    data: d.ctr_curve.values,
                                    borderColor: '#2563eb',
                                    backgroundColor: 'rgba(37,99,235,0.1)',
                                    fill: true, tension: 0.3
                                }]
                            },
                            options: { responsive: true, scales: { y: { beginAtZero: true } } }
                        });
                    }
                }
            });
        },

        loadForecast: function (page) {
            var $btn = $('#apseo-fc-calculate').prop('disabled', true);
            $.post(apseoAdmin.ajaxUrl, {
                action: 'apseo_get_traffic_forecast',
                nonce: apseoAdmin.nonce,
                page: page || 1,
                target_position: $('#apseo-fc-target').val(),
                min_position: $('#apseo-fc-min-pos').val(),
                max_position: $('#apseo-fc-max-pos').val(),
                min_impressions: $('#apseo-fc-min-imp').val(),
                sort_by: $('#apseo-fc-sort').val()
            }, function (r) {
                $btn.prop('disabled', false);
                if (!r.success) return;
                var $t = $('#apseo-forecast-tbody').empty();
                r.data.forecasts.forEach(function (f) {
                    $t.append('<tr>' +
                        '<td><strong>' + f.keyword + '</strong><br><small>' + (f.post_title || '') + '</small></td>' +
                        '<td>' + f.current_position + '</td>' +
                        '<td>' + f.impressions + '</td>' +
                        '<td>' + f.current_clicks + '</td>' +
                        '<td>' + f.potential_clicks + '</td>' +
                        '<td style="color:green;font-weight:bold;">+' + f.traffic_growth + '</td>' +
                        '<td>' + f.effort_label + '</td>' +
                        '<td>' + f.priority_label + '</td></tr>');
                });
            });
        },


        // ===================== Keyword Discovery =====================
        initDiscovery: function () {
            if (!$('#apseo-seed-keyword').length) return;
            this.loadDiscoveryHistory();

            // Start discovery
            $('#apseo-start-discovery').on('click', function () {
                var kw = $('#apseo-seed-keyword').val().trim();
                if (!kw) return;
                var $btn = $(this).prop('disabled', true);
                $('#apseo-discovery-progress').show();

                $.post(apseoAdmin.ajaxUrl, {
                    action: 'apseo_discover_keywords',
                    nonce: apseoAdmin.nonce,
                    seed_keyword: kw
                }, function (r) {
                    $btn.prop('disabled', false);
                    if (!r.success) {
                        $('#apseo-discovery-progress').hide();
                        APSEOV2.showNotice(r.data, 'error');
                        return;
                    }
                    if (r.data.status === 'already_exists') {
                        $('#apseo-discovery-progress').hide();
                        APSEOV2.currentDiscoveryId = r.data.discovery_id;
                        APSEOV2.loadDiscoveryIdeas();
                    } else {
                        APSEOV2.currentDiscoveryId = r.data.discovery_id;
                        APSEOV2.pollDiscovery();
                    }
                });
            });

            // Select all checkbox
            $(document).on('change', '#apseo-disc-select-all', function () {
                $('.apseo-disc-checkbox').prop('checked', $(this).is(':checked'));
                APSEOV2.updateBriefButton();
            });
            $(document).on('change', '.apseo-disc-checkbox', function () {
                APSEOV2.updateBriefButton();
            });

            // Show brief options
            $('#apseo-generate-brief-btn').on('click', function () {
                $('#apseo-brief-options').toggle();
            });

            // Confirm brief generation
            $('#apseo-confirm-brief').on('click', function () {
                var ids = [];
                $('.apseo-disc-checkbox:checked').each(function () {
                    ids.push($(this).val());
                });
                if (!ids.length) return;

                $.post(apseoAdmin.ajaxUrl, {
                    action: 'apseo_generate_content_brief',
                    nonce: apseoAdmin.nonce,
                    selected_ids: ids,
                    primary_keyword: $('#apseo-brief-primary').val(),
                    post_type: $('#apseo-brief-posttype').val()
                }, function (r) {
                    if (r.success) {
                        APSEOV2.showNotice(
                            r.data.message + ' <a href="' + r.data.edit_url + '" target="_blank">ویرایش پیش‌نویس</a>',
                            'success'
                        );
                        APSEOV2.loadDiscoveryIdeas();
                    } else {
                        APSEOV2.showNotice(r.data, 'error');
                    }
                });
            });

            // Dismiss idea
            $(document).on('click', '.apseo-dismiss-idea', function () {
                var id = $(this).data('id');
                $.post(apseoAdmin.ajaxUrl, {
                    action: 'apseo_dismiss_keyword_idea',
                    nonce: apseoAdmin.nonce,
                    idea_id: id
                }, function () {
                    APSEOV2.loadDiscoveryIdeas();
                });
            });

            // Filter change
            $(document).on('change', '#apseo-disc-source, #apseo-disc-status', function () {
                APSEOV2.loadDiscoveryIdeas();
            });
        },

        currentDiscoveryId: null,

        pollDiscovery: function () {
            var interval = setInterval(function () {
                $.post(apseoAdmin.ajaxUrl, {
                    action: 'apseo_get_keyword_ideas',
                    nonce: apseoAdmin.nonce,
                    discovery_id: APSEOV2.currentDiscoveryId
                }, function (r) {
                    if (!r.success) return;
                    if (r.data.status === 'completed') {
                        clearInterval(interval);
                        $('#apseo-discovery-progress').hide();
                        APSEOV2.loadDiscoveryIdeas();
                    }
                });
            }, 4000);
        },

        loadDiscoveryIdeas: function (page) {
            if (!APSEOV2.currentDiscoveryId) return;
            $('#apseo-discovery-results').show();

            $.post(apseoAdmin.ajaxUrl, {
                action: 'apseo_get_keyword_ideas',
                nonce: apseoAdmin.nonce,
                discovery_id: APSEOV2.currentDiscoveryId,
                page: page || 1,
                source: $('#apseo-disc-source').val() || '',
                status: $('#apseo-disc-status').val() || 'active'
            }, function (r) {
                if (!r.success || r.data.status !== 'completed') return;
                var d = r.data;
                $('#disc-total').text(d.summary.total_ideas);
                $('#disc-autocomplete').text(d.summary.autocomplete);
                $('#disc-related').text(d.summary.related_searches);
                $('#disc-questions').text(d.summary.questions);

                var $t = $('#apseo-disc-tbody').empty();
                d.ideas.forEach(function (i) {
                    var qMark = i.is_question ? '؟' : '';
                    $t.append('<tr>' +
                        '<td class="check-column"><input type="checkbox" class="apseo-disc-checkbox" value="' + i.id + '" /></td>' +
                        '<td><strong>' + i.keyword + '</strong></td>' +
                        '<td><span class="apseo-badge">' + i.source_label + '</span></td>' +
                        '<td>' + i.relevance_score + '%</td>' +
                        '<td>' + qMark + '</td>' +
                        '<td><button class="button button-small apseo-dismiss-idea" data-id="' + i.id + '">رد</button></td></tr>');
                });
            });
        },

        updateBriefButton: function () {
            var count = $('.apseo-disc-checkbox:checked').length;
            $('#apseo-generate-brief-btn').prop('disabled', count === 0)
                .text(count > 0 ? 'تولید پیش‌نویس (' + count + ' کلمه)' : 'تولید پیش‌نویس از انتخاب‌شده‌ها');
        },

        loadDiscoveryHistory: function () {
            $.post(apseoAdmin.ajaxUrl, {
                action: 'apseo_get_discovery_history',
                nonce: apseoAdmin.nonce
            }, function (r) {
                if (!r.success) return;
                var $t = $('#apseo-disc-history-tbody').empty();
                r.data.discoveries.forEach(function (d) {
                    var btn = d.status === 'completed'
                        ? '<button class="button button-small apseo-load-discovery" data-id="' + d.discovery_id + '">بارگذاری</button>'
                        : '';
                    $t.append('<tr><td>' + d.seed_keyword + '</td>' +
                        '<td>' + d.status_label + '</td>' +
                        '<td>' + d.idea_count + '</td>' +
                        '<td>' + d.requested_at + '</td>' +
                        '<td>' + btn + '</td></tr>');
                });

                // Click handler for loading past discovery
                $(document).on('click', '.apseo-load-discovery', function () {
                    APSEOV2.currentDiscoveryId = $(this).data('id');
                    APSEOV2.loadDiscoveryIdeas();
                });
            });
        },

        // ===================== Utility =====================
        showNotice: function (message, type) {
            var cls = type === 'success' ? 'notice-success' : 'notice-error';
            var $n = $('<div class="notice ' + cls + ' is-dismissible" style="direction:rtl;text-align:right;"><p>' + message + '</p></div>');
            $('.apseo-wrap h1').after($n);
            setTimeout(function () { $n.fadeOut(function () { $(this).remove(); }); }, 6000);
        }
    };

    $(document).ready(function () {
        APSEOV2.init();
    });

})(jQuery);
