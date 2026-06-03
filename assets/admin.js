(function ($) {
    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function severityClass(severity) {
        return 'wpi-severity wpi-severity-' + esc(severity || 'low');
    }

    function renderCards(scores) {
        var cards = [
            ['Health Score', scores.health + '/100', scores.health_label],
            ['Performance', scores.performance_label, scores.performance + '/100'],
            ['Security', scores.security_label, scores.security + '/100'],
            ['Conflicts', scores.conflicts, 'Potential issues'],
            ['Duplicates', scores.duplicates, 'Overlaps found']
        ];

        return '<div class="wpi-cards">' + cards.map(function (card) {
            return '<div class="wpi-card"><span>' + esc(card[0]) + '</span><strong>' + esc(card[1]) + '</strong><small>' + esc(card[2]) + '</small></div>';
        }).join('') + '</div>';
    }

    function renderPluginTable(plugins) {
        return '<table class="widefat striped wpi-table"><thead><tr><th>Plugin</th><th>Version</th><th>Status</th><th>Category</th><th>WordPress.org Metadata</th><th>Author</th></tr></thead><tbody>' +
            plugins.map(function (plugin) {
                var meta = plugin.metadata || {};
                var metadata = meta.last_updated ?
                    'Updated: ' + esc(meta.last_updated) +
                    '<br>Tested: ' + esc(meta.tested || '-') +
                    '<br>Requires WP: ' + esc(meta.requires || '-') +
                    '<br>Requires PHP: ' + esc(meta.requires_php || '-') +
                    '<br>Installs: ' + esc(meta.active_installs || '-') :
                    '-';

                return '<tr>' +
                    '<td><strong>' + esc(plugin.name) + '</strong><br><code>' + esc(plugin.slug) + '</code></td>' +
                    '<td>' + esc(plugin.version) + (plugin.update_available ? '<br><span class="wpi-update">Update: ' + esc(plugin.new_version) + '</span>' : '') + '</td>' +
                    '<td>' + (plugin.active ? '<span class="wpi-pill is-active">Active</span>' : '<span class="wpi-pill">Inactive</span>') + '</td>' +
                    '<td>' + esc(plugin.category || '-') + '</td>' +
                    '<td>' + metadata + '</td>' +
                    '<td>' + esc(plugin.author || '-') + '</td>' +
                '</tr>';
            }).join('') +
            '</tbody></table>';
    }

    function renderIssues(title, issues, emptyText) {
        if (!issues.length) {
            return '<section class="wpi-panel"><h2>' + esc(title) + '</h2><p class="wpi-muted">' + esc(emptyText) + '</p></section>';
        }

        return '<section class="wpi-panel"><h2>' + esc(title) + '</h2><div class="wpi-issue-list">' +
            issues.map(function (issue) {
                var names = issue.plugins || [];
                if (names.length && typeof names[0] === 'object') {
                    names = names.map(function (plugin) { return plugin.name; });
                }

                return '<article class="wpi-issue">' +
                    '<div><span class="' + severityClass(issue.severity) + '">' + esc(issue.severity || 'info') + '</span><h3>' + esc(issue.title || issue.label || issue.category) + '</h3></div>' +
                    '<p>' + esc(issue.message || issue.reason || '') + '</p>' +
                    (names.length ? '<code>' + esc(names.join(' + ')) + '</code>' : '') +
                '</article>';
            }).join('') +
            '</div></section>';
    }

    function renderRecommendations(recommendations) {
        if (!recommendations.length) {
            return '<section class="wpi-panel"><h2>Recommendations</h2><p class="wpi-muted">No action items were generated.</p></section>';
        }

        return '<section class="wpi-panel"><h2>Recommendations</h2><div class="wpi-recommendations">' +
            recommendations.map(function (item) {
                return '<article class="wpi-recommendation">' +
                    '<span class="' + severityClass(item.severity) + '">' + esc(item.severity) + '</span>' +
                    '<strong>' + esc(item.action) + ': ' + esc(item.title) + '</strong>' +
                    '<p>' + esc(item.reason) + '</p>' +
                '</article>';
            }).join('') +
            '</div></section>';
    }

    function renderPerformance(performance, environment) {
        var metrics = performance.metrics || {};
        var rows = Object.keys(metrics).map(function (key) {
            return '<tr><td>' + esc(key.replace(/_/g, ' ')) + '</td><td>' + esc(metrics[key]) + '</td></tr>';
        }).join('');
        var envRows = Object.keys(environment || {}).map(function (key) {
            return '<tr><td>' + esc(key.replace(/_/g, ' ')) + '</td><td>' + esc(environment[key]) + '</td></tr>';
        }).join('');

        return '<section class="wpi-panel"><h2>Performance</h2><p><strong>' + esc(performance.score) + '/100</strong> ' + esc(performance.label) + '</p>' +
            '<div class="wpi-two-col"><table class="widefat striped"><tbody>' + rows + '</tbody></table>' +
            '<table class="widefat striped"><tbody>' + envRows + '</tbody></table></div></section>';
    }

    function renderScoreFactors(factors) {
        if (!factors || !factors.length) {
            return '';
        }

        return '<section class="wpi-panel"><h2>Score Breakdown</h2><div class="wpi-issue-list">' +
            factors.map(function (factor) {
                return '<article class="wpi-issue">' +
                    '<div><span class="' + severityClass(factor.severity) + '">' + esc(factor.severity) + '</span><h3>' + esc(factor.label) + '</h3></div>' +
                    '<p>' + esc(factor.impact) + '</p>' +
                '</article>';
            }).join('') +
            '</div></section>';
    }

    function renderResults(data) {
        var agency = $('#wpi-agency-name').val() || 'Agency Audit';
        var client = $('#wpi-client-name').val() || 'WordPress Site';
        var security = data.security || { warnings: [] };
        var scores = data.scores || {
            health: '-',
            health_label: 'List Analysis',
            performance: '-',
            performance_label: 'Not measured',
            security: '-',
            security_label: 'Not measured',
            conflicts: data.conflicts.length,
            duplicates: data.duplicates.length
        };
        var securityIssues = (security.warnings || []).map(function (warning) {
            return {
                title: warning.plugin,
                severity: warning.severity,
                message: warning.message,
                plugins: [warning.plugin]
            };
        });

        return '<div class="wpi-report-title"><h2>WordPress Plugin Health Audit Report</h2><p><strong>' + esc(client) + '</strong> &middot; Prepared by ' + esc(agency) + ' &middot; Generated ' + esc(data.generated_at) + '</p></div>' +
            '<div class="wpi-actions"><button type="button" id="wpi-print-report" class="button">Generate Client Report</button></div>' +
            renderCards(scores) +
            '<nav class="wpi-tabs" aria-label="Report sections"><a href="#wpi-overview">Overview</a><a href="#wpi-conflicts">Conflicts</a><a href="#wpi-security">Security</a><a href="#wpi-performance">Performance</a><a href="#wpi-recommendations">Recommendations</a></nav>' +
            '<section id="wpi-overview" class="wpi-panel"><h2>Plugin Inventory</h2>' + renderPluginTable(data.plugins) + '</section>' +
            renderScoreFactors(data.score_factors) +
            '<div id="wpi-conflicts">' + renderIssues('Conflicts', data.conflicts, 'No known conflict rules matched this scan.') + renderIssues('Duplicate Functionality', data.duplicates, 'No duplicate functionality groups were detected.') + '</div>' +
            '<div id="wpi-security">' + (data.security ? renderIssues('Security', securityIssues, 'No update or vulnerability warnings were found by the local MVP scanner.') : '') + '</div>' +
            '<div id="wpi-performance">' + (data.performance ? renderPerformance(data.performance, data.environment) : '') + '</div>' +
            '<div id="wpi-recommendations">' + renderRecommendations(data.recommendations) + '</div>';
    }

    $(function () {
        $('#wpi-run-scan').on('click', function () {
            var $button = $(this);
            var $status = $('#wpi-status');
            var $results = $('#wpi-results');

            $button.prop('disabled', true);
            $status.text(WPI.strings.running);

            $.post(WPI.ajaxUrl, {
                action: 'wpi_run_scan',
                nonce: WPI.nonce
            }).done(function (response) {
                if (!response || !response.success) {
                    $status.text((response && response.data && response.data.message) || WPI.strings.failed);
                    return;
                }

                $status.text('Scan complete: ' + response.data.generated_at);
                $results.removeClass('is-empty').html(renderResults(response.data));
            }).fail(function () {
                $status.text(WPI.strings.failed);
            }).always(function () {
                $button.prop('disabled', false);
            });
        });

        $('#wpi-analyze-list').on('click', function () {
            var $button = $(this);
            var $status = $('#wpi-status');
            var $results = $('#wpi-results');

            $button.prop('disabled', true);
            $status.text('Analyzing pasted plugin list...');

            $.post(WPI.ajaxUrl, {
                action: 'wpi_analyze_list',
                nonce: WPI.nonce,
                plugins: $('#wpi-plugin-list').val()
            }).done(function (response) {
                if (!response || !response.success) {
                    $status.text((response && response.data && response.data.message) || WPI.strings.failed);
                    return;
                }

                $status.text('List analysis complete: ' + response.data.generated_at);
                $results.removeClass('is-empty').html(renderResults(response.data));
            }).fail(function () {
                $status.text(WPI.strings.failed);
            }).always(function () {
                $button.prop('disabled', false);
            });
        });

        $(document).on('click', '#wpi-print-report', function () {
            window.print();
        });
    });
})(jQuery);
