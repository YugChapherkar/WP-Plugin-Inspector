# WP Plugin Inspector

WP Plugin Inspector is a WordPress admin plugin that scans the current site for plugin inventory, duplicate functionality, likely plugin conflicts, security update signals, and performance pressure.

## MVP Scope

- Tools -> Plugin Inspector admin page.
- Installed plugin inventory via `get_plugins()`.
- Active/inactive status, version, author, update availability, and category detection.
- Duplicate functionality rules for SEO, caching, security, forms, page builders, and commerce.
- Local conflict rules with a small version-based compatibility check.
- Local security signals for available updates.
- Performance score using plugin count, active plugin count, autoloaded option size, current request query count, memory usage, and database table count.
- Scan persistence in `wp_plugin_inspector_scans`.
- Recommendations generated from duplicates, conflicts, security warnings, and performance score.
- Pasted plugin-list analysis for quick pre-sales or pre-install checks.
- Printable client report via **Generate Client Report**.

## Installation

1. Copy the `wp-plugin-inspector` folder into `wp-content/plugins/`.
2. Activate **WP Plugin Inspector** from the WordPress Plugins screen.
3. Open **Tools -> Plugin Inspector**.
4. Click **Run Scan**.

## Future API Integrations

The PRD calls for WPScan and Patchstack vulnerability lookups. This MVP keeps the scanner local-first, but `WPI_Scanner::scan_security()` is the intended integration point for API-backed vulnerability data, CVEs, CVSS scores, and abandonment metadata.

## Roadmap

- WPScan/Patchstack API settings screen.
- WordPress.org plugin metadata cache for abandoned-plugin detection.
- Uploaded plugin-list analysis.
- Branded client report export.
- AI Plugin Advisor using an OpenAI-backed recommendations layer.
