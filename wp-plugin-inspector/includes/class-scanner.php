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
        $scores = $this->score_health($duplicates, $conflicts, $security, $performance);
        $score_factors = $this->score_factors($duplicates, $conflicts, $security, $performance);
        $recommendations = $this->build_recommendations($plugins, $duplicates, $conflicts, $security, $performance);

        $results = [
            'generated_at' => current_time('mysql'),
            'environment' => $this->environment(),
            'scores' => $scores,
            'score_factors' => $score_factors,
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
                'category' => $this->category_for_plugin($slug),
                'metadata' => [],
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

            $metadata = $this->wordpress_org_metadata($slug);

            $plugins[$slug] = [
                'file' => $plugin_file,
                'slug' => $slug,
                'name' => $data['Name'] ?? $slug,
                'version' => $data['Version'] ?? '',
                'author' => wp_strip_all_tags($data['Author'] ?? ''),
                'active' => isset($active[$plugin_file]) || is_plugin_active_for_network($plugin_file),
                'update_available' => isset($updates->response[$plugin_file]),
                'new_version' => $updates->response[$plugin_file]->new_version ?? null,
                'category' => $this->category_for_plugin($slug, $data['Name'] ?? ''),
                'metadata' => $metadata,
            ];
        }

        uasort($plugins, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $plugins;
    }

    private function category_for_plugin(string $slug, string $name = ''): ?string
    {
        $normalized_name = sanitize_title($name);

        foreach (WPI_Rules::categories() as $category => $definition) {
            if (in_array($slug, $definition['plugins'], true)) {
                return $category;
            }

            foreach ($definition['keywords'] ?? [] as $keyword) {
                if (str_contains($slug, $keyword) || ('' !== $normalized_name && str_contains($normalized_name, $keyword))) {
                    return $category;
                }
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
            if (!empty($rule['categories'])) {
                $matched = $this->active_slugs_for_categories($plugins, $rule['categories']);
                $is_match = count($matched) >= count($rule['categories']);
            } else {
                $matched = array_values(array_intersect($rule['plugins'], $active_slugs));
                $match_type = $rule['match'] ?? 'all';
                $is_match = 'any_two' === $match_type ? count($matched) >= 2 : count($matched) === count($rule['plugins']);
            }

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

    private function active_slugs_for_categories(array $plugins, array $categories): array
    {
        $matched = [];

        foreach ($categories as $category) {
            foreach ($plugins as $plugin) {
                if ($plugin['active'] && $plugin['category'] === $category) {
                    $matched[] = $plugin['slug'];
                    break;
                }
            }
        }

        return $matched;
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

            foreach (WPI_Rules::vulnerabilities()[$plugin['slug']] ?? [] as $vulnerability) {
                if ('' !== $plugin['version'] && version_compare($plugin['version'], $vulnerability['affected_below'], '<')) {
                    $warnings[] = [
                        'type' => 'known_vulnerability',
                        'severity' => $vulnerability['severity'],
                        'plugin' => $plugin['name'],
                        'cve' => $vulnerability['cve'],
                        'message' => $vulnerability['message'],
                    ];
                }
            }

            $abandonment = $this->abandonment_warning($plugin);
            if ($abandonment) {
                $warnings[] = $abandonment;
            }

            foreach ($this->compatibility_warnings($plugin) as $compatibility_warning) {
                $warnings[] = $compatibility_warning;
            }
        }

        return [
            'warnings' => $warnings,
            'known_vulnerabilities' => [],
            'summary' => [
                'active_plugins' => $active_count,
                'updates_available' => $update_count,
                'external_api' => __('Local checks are active. Patchstack/WPScan API integration can be connected next for live CVE data.', 'wp-plugin-inspector'),
            ],
        ];
    }

    private function abandonment_warning(array $plugin): ?array
    {
        $last_updated = $plugin['metadata']['last_updated'] ?? '';
        if ('' === $last_updated) {
            return null;
        }

        $timestamp = strtotime($last_updated);
        if (!$timestamp || $timestamp > strtotime('-2 years')) {
            return null;
        }

        return [
            'type' => 'abandoned',
            'severity' => 'high',
            'plugin' => $plugin['name'],
            'message' => sprintf(
                __('Plugin appears abandoned. WordPress.org last updated date: %s.', 'wp-plugin-inspector'),
                date_i18n(get_option('date_format'), $timestamp)
            ),
        ];
    }

    private function compatibility_warnings(array $plugin): array
    {
        $warnings = [];
        $metadata = $plugin['metadata'] ?? [];

        if (!empty($metadata['requires_php']) && version_compare(PHP_VERSION, $metadata['requires_php'], '<')) {
            $warnings[] = [
                'type' => 'php_requirement',
                'severity' => 'high',
                'plugin' => $plugin['name'],
                'message' => sprintf(
                    __('Plugin requires PHP %1$s or higher. Current PHP version is %2$s.', 'wp-plugin-inspector'),
                    $metadata['requires_php'],
                    PHP_VERSION
                ),
            ];
        }

        if (!empty($metadata['requires']) && version_compare(get_bloginfo('version'), $metadata['requires'], '<')) {
            $warnings[] = [
                'type' => 'wp_requirement',
                'severity' => 'high',
                'plugin' => $plugin['name'],
                'message' => sprintf(
                    __('Plugin requires WordPress %1$s or higher. Current WordPress version is %2$s.', 'wp-plugin-inspector'),
                    $metadata['requires'],
                    get_bloginfo('version')
                ),
            ];
        }

        if (!empty($metadata['tested']) && version_compare($metadata['tested'], $this->wordpress_major_minor(), '<')) {
            $warnings[] = [
                'type' => 'tested_up_to',
                'severity' => 'medium',
                'plugin' => $plugin['name'],
                'message' => sprintf(
                    __('Plugin is tested up to WordPress %1$s, below this site version %2$s.', 'wp-plugin-inspector'),
                    $metadata['tested'],
                    get_bloginfo('version')
                ),
            ];
        }

        return $warnings;
    }

    private function wordpress_major_minor(): string
    {
        $parts = explode('.', get_bloginfo('version'));

        return implode('.', array_slice($parts, 0, 2));
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

    private function score_factors(array $duplicates, array $conflicts, array $security, array $performance): array
    {
        $factors = [];

        foreach ($duplicates as $duplicate) {
            $factors[] = [
                'severity' => $duplicate['severity'],
                'label' => sprintf(__('Duplicate %s stack', 'wp-plugin-inspector'), strtolower($duplicate['label'])),
                'impact' => __('May create duplicated output, admin confusion, or conflicting settings.', 'wp-plugin-inspector'),
            ];
        }

        foreach ($conflicts as $conflict) {
            $factors[] = [
                'severity' => $conflict['severity'],
                'label' => $conflict['title'],
                'impact' => $conflict['message'],
            ];
        }

        foreach ($security['warnings'] as $warning) {
            $factors[] = [
                'severity' => $warning['severity'],
                'label' => $warning['plugin'],
                'impact' => $warning['message'],
            ];
        }

        if ($performance['score'] < 90) {
            $factors[] = [
                'severity' => $performance['score'] < 75 ? 'medium' : 'low',
                'label' => __('Performance pressure', 'wp-plugin-inspector'),
                'impact' => sprintf(
                    __('Performance score is %d/100 based on plugin count, active plugins, memory usage, and autoloaded options.', 'wp-plugin-inspector'),
                    $performance['score']
                ),
            ];
        }

        if (!$factors) {
            $factors[] = [
                'severity' => 'low',
                'label' => __('No major score penalties detected', 'wp-plugin-inspector'),
                'impact' => __('The scan did not find duplicate stacks, known local vulnerabilities, or matching conflict rules.', 'wp-plugin-inspector'),
            ];
        }

        return $factors;
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

    private function wordpress_org_metadata(string $slug): array
    {
        $cache_key = 'wpi_plugin_meta_' . md5($slug);
        $cached = get_transient($cache_key);

        if (is_array($cached)) {
            return $cached;
        }

        if (!function_exists('plugins_api')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        $api = plugins_api('plugin_information', [
            'slug' => $slug,
            'fields' => [
                'active_installs' => true,
                'last_updated' => true,
                'requires' => true,
                'requires_php' => true,
                'tested' => true,
                'versions' => false,
                'sections' => false,
                'banners' => false,
                'icons' => false,
            ],
        ]);

        if (is_wp_error($api) || !is_object($api)) {
            return [];
        }

        $metadata = [
            'last_updated' => $api->last_updated ?? '',
            'tested' => $api->tested ?? '',
            'requires' => $api->requires ?? '',
            'requires_php' => $api->requires_php ?? '',
            'active_installs' => $api->active_installs ?? null,
        ];

        set_transient($cache_key, $metadata, DAY_IN_SECONDS);

        return $metadata;
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
