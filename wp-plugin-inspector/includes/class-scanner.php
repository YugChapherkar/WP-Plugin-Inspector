<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WPI_Scanner
{
    public function run(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = $this->collect_plugins();
        $duplicates = $this->detect_duplicates($plugins);
        $conflicts = $this->detect_conflicts($plugins);
        $security = $this->scan_security($plugins);
        $performance = $this->score_performance($plugins);
        $recommendations = $this->build_recommendations($plugins, $duplicates, $conflicts, $security, $performance);
        $scores = $this->score_health($duplicates, $conflicts, $security, $performance);

        $results = [
            'generated_at' => current_time('mysql'),
            'environment' => $this->environment(),
            'scores' => $scores,
            'plugins' => array_values($plugins),
            'duplicates' => $duplicates,
            'conflicts' => $conflicts,
            'security' => $security,
            'performance' => $performance,
            'recommendations' => $recommendations,
        ];

        $this->store_scan($results);

        return $results;
    }

    public function analyze_plugin_list(string $raw_list): array
    {
        $slugs = $this->parse_plugin_list($raw_list);
        $plugins = [];

        foreach ($slugs as $slug) {
            $plugins[$slug] = [
                'file' => $slug . '/' . $slug . '.php',
                'slug' => $slug,
                'name' => ucwords(str_replace(['-', '_'], ' ', $slug)),
                'version' => '',
                'author' => '',
                'active' => true,
                'update_available' => false,
                'new_version' => null,
                'category' => $this->category_for_slug($slug),
            ];
        }

        $duplicates = $this->detect_duplicates($plugins);
        $conflicts = $this->detect_conflicts($plugins);
        $recommendations = $this->build_recommendations(
            $plugins,
            $duplicates,
            $conflicts,
            ['warnings' => []],
            ['score' => 100]
        );

        return [
            'generated_at' => current_time('mysql'),
            'plugins' => array_values($plugins),
            'duplicates' => $duplicates,
            'conflicts' => $conflicts,
            'recommendations' => $recommendations,
        ];
    }

    private function parse_plugin_list(string $raw_list): array
    {
        $items = preg_split('/[\r\n,]+/', $raw_list);
        $slugs = [];

        foreach ((array) $items as $item) {
            $slug = sanitize_title(trim((string) $item));
            if ('' !== $slug) {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    private function collect_plugins(): array
    {
        $installed = get_plugins();
        $active = array_flip((array) get_option('active_plugins', []));
        $updates = get_site_transient('update_plugins');
        $plugins = [];

        foreach ($installed as $plugin_file => $data) {
            $slug = dirname($plugin_file);
            if ('.' === $slug) {
                $slug = basename($plugin_file, '.php');
            }

            $plugins[$slug] = [
                'file' => $plugin_file,
                'slug' => $slug,
                'name' => $data['Name'] ?? $slug,
                'version' => $data['Version'] ?? '',
                'author' => wp_strip_all_tags($data['Author'] ?? ''),
                'active' => isset($active[$plugin_file]) || is_plugin_active_for_network($plugin_file),
                'update_available' => isset($updates->response[$plugin_file]),
                'new_version' => $updates->response[$plugin_file]->new_version ?? null,
                'category' => $this->category_for_slug($slug),
            ];
        }

        uasort($plugins, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $plugins;
    }

    private function category_for_slug(string $slug): ?string
    {
        foreach (WPI_Rules::categories() as $category => $definition) {
            if (in_array($slug, $definition['plugins'], true)) {
                return $category;
            }
        }

        return null;
    }

    private function detect_duplicates(array $plugins): array
    {
        $duplicates = [];

        foreach (WPI_Rules::categories() as $category => $definition) {
            $matches = array_values(array_filter($plugins, static function (array $plugin) use ($definition): bool {
                return $plugin['active'] && in_array($plugin['slug'], $definition['plugins'], true);
            }));

            if (count($matches) > 1) {
                $duplicates[] = [
                    'category' => $category,
                    'label' => $definition['label'],
                    'severity' => in_array($category, ['caching', 'security', 'seo'], true) ? 'high' : 'medium',
                    'message' => sprintf(
                        __('Multiple active %s plugins detected. Consolidation may prevent conflicts and duplicated work.', 'wp-plugin-inspector'),
                        strtolower($definition['label'])
                    ),
                    'plugins' => array_map(static fn (array $plugin): array => [
                        'name' => $plugin['name'],
                        'slug' => $plugin['slug'],
                        'version' => $plugin['version'],
                    ], $matches),
                ];
            }
        }

        return $duplicates;
    }

    private function detect_conflicts(array $plugins): array
    {
        $active_slugs = array_keys(array_filter($plugins, static fn (array $plugin): bool => $plugin['active']));
        $conflicts = [];

        foreach (WPI_Rules::conflicts() as $rule) {
            $matched = array_values(array_intersect($rule['plugins'], $active_slugs));
            $match_type = $rule['match'] ?? 'all';
            $is_match = 'any_two' === $match_type ? count($matched) >= 2 : count($matched) === count($rule['plugins']);

            if ($is_match) {
                $conflicts[] = [
                    'title' => $rule['title'],
                    'severity' => $rule['severity'],
                    'message' => $rule['message'],
                    'plugins' => $matched,
                ];
            }
        }

        foreach (WPI_Rules::version_rules() as $rule) {
            if (empty($plugins[$rule['plugin']]) || version_compare($plugins[$rule['plugin']]['version'], $rule['version_at_least'], '<')) {
                continue;
            }

            foreach ($rule['conflicts_with'] as $slug => $minimum_version) {
                if (!empty($plugins[$slug]) && version_compare($plugins[$slug]['version'], $minimum_version, '<')) {
                    $conflicts[] = [
                        'title' => $rule['title'],
                        'severity' => $rule['severity'],
                        'message' => $rule['message'],
                        'plugins' => [$rule['plugin'], $slug],
                    ];
                }
            }
        }

        return $conflicts;
    }

    private function scan_security(array $plugins): array
    {
        $warnings = [];
        $active_count = 0;
        $update_count = 0;

        foreach ($plugins as $plugin) {
            if ($plugin['active']) {
                $active_count++;
            }

            if ($plugin['update_available']) {
                $update_count++;
                $warnings[] = [
                    'type' => 'update',
                    'severity' => 'medium',
                    'plugin' => $plugin['name'],
                    'message' => sprintf(
                        __('Update available: %1$s to %2$s.', 'wp-plugin-inspector'),
                        $plugin['version'],
                        $plugin['new_version']
                    ),
                ];
            }
        }

        return [
            'warnings' => $warnings,
            'known_vulnerabilities' => [],
            'summary' => [
                'active_plugins' => $active_count,
                'updates_available' => $update_count,
                'external_api' => __('WPScan/Patchstack integration is not configured in this MVP build.', 'wp-plugin-inspector'),
            ],
        ];
    }

    private function score_performance(array $plugins): array
    {
        global $wpdb;

        $plugin_count = count($plugins);
        $active_count = count(array_filter($plugins, static fn (array $plugin): bool => $plugin['active']));
        $autoload_bytes = $this->autoload_bytes();
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memory_usage = memory_get_usage(true);

        $score = 100;
        $score -= max(0, $active_count - 15) * 2;
        $score -= max(0, $plugin_count - 30);
        $score -= $autoload_bytes > 1000000 ? 15 : ($autoload_bytes > 500000 ? 8 : 0);
        $score -= $memory_limit > 0 && ($memory_usage / $memory_limit) > 0.70 ? 10 : 0;
        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'label' => $this->score_label($score),
            'metrics' => [
                'installed_plugins' => $plugin_count,
                'active_plugins' => $active_count,
                'db_queries_current_request' => function_exists('get_num_queries') ? get_num_queries() : null,
                'autoload_options_bytes' => $autoload_bytes,
                'memory_usage_bytes' => $memory_usage,
                'memory_limit_bytes' => $memory_limit,
                'database_tables' => is_object($wpdb) ? count($wpdb->get_col('SHOW TABLES')) : null,
            ],
        ];
    }

    private function autoload_bytes(): int
    {
        global $wpdb;

        $value = $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes', 'on', 'auto-on', 'auto')"
        );

        return absint($value);
    }

    private function score_health(array $duplicates, array $conflicts, array $security, array $performance): array
    {
        $security_score = 100;
        foreach ($security['warnings'] as $warning) {
            $security_score -= 'high' === $warning['severity'] ? 18 : 8;
        }
        $security_score = max(0, min(100, $security_score));

        $health = (int) round(($performance['score'] * 0.35) + ($security_score * 0.4) + (100 * 0.25));
        $health -= count($duplicates) * 5;
        foreach ($conflicts as $conflict) {
            $health -= 'high' === $conflict['severity'] ? 12 : 7;
        }
        $health = max(0, min(100, $health));

        return [
            'health' => $health,
            'health_label' => $this->score_label($health),
            'performance' => $performance['score'],
            'performance_label' => $performance['label'],
            'security' => $security_score,
            'security_label' => $this->score_label($security_score),
            'conflicts' => count($conflicts),
            'duplicates' => count($duplicates),
        ];
    }

    private function score_label(int $score): string
    {
        if ($score >= 90) {
            return __('Excellent', 'wp-plugin-inspector');
        }

        if ($score >= 75) {
            return __('Good', 'wp-plugin-inspector');
        }

        if ($score >= 50) {
            return __('Average', 'wp-plugin-inspector');
        }

        return __('Poor', 'wp-plugin-inspector');
    }

    private function build_recommendations(array $plugins, array $duplicates, array $conflicts, array $security, array $performance): array
    {
        $recommendations = [];

        foreach ($duplicates as $duplicate) {
            $recommendations[] = [
                'action' => __('Consolidate', 'wp-plugin-inspector'),
                'title' => sprintf(__('Review duplicate %s plugins', 'wp-plugin-inspector'), strtolower($duplicate['label'])),
                'reason' => $duplicate['message'],
                'severity' => $duplicate['severity'],
            ];
        }

        foreach ($security['warnings'] as $warning) {
            $recommendations[] = [
                'action' => __('Update', 'wp-plugin-inspector'),
                'title' => $warning['plugin'],
                'reason' => $warning['message'],
                'severity' => $warning['severity'],
            ];
        }

        foreach ($conflicts as $conflict) {
            $recommendations[] = [
                'action' => __('Investigate', 'wp-plugin-inspector'),
                'title' => $conflict['title'],
                'reason' => $conflict['message'],
                'severity' => $conflict['severity'],
            ];
        }

        if ($performance['score'] < 75) {
            $recommendations[] = [
                'action' => __('Optimize', 'wp-plugin-inspector'),
                'title' => __('Reduce plugin and autoload pressure', 'wp-plugin-inspector'),
                'reason' => __('Audit inactive plugins, large autoloaded options, and overlapping performance tools.', 'wp-plugin-inspector'),
                'severity' => 'medium',
            ];
        }

        return $recommendations;
    }

    private function environment(): array
    {
        global $wpdb;

        return [
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'theme' => wp_get_theme()->get('Name'),
            'theme_version' => wp_get_theme()->get('Version'),
            'memory_limit' => ini_get('memory_limit'),
            'database_version' => $wpdb->db_version(),
        ];
    }

    private function store_scan(array $results): void
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . WPI_SCAN_TABLE,
            [
                'scan_date' => current_time('mysql'),
                'health_score' => $results['scores']['health'],
                'performance_score' => $results['scores']['performance'],
                'security_score' => $results['scores']['security'],
                'results_json' => wp_json_encode($results),
            ],
            ['%s', '%d', '%d', '%d', '%s']
        );
    }
}
