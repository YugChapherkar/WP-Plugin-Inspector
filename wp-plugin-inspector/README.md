# WP Plugin Inspector

WP Plugin Inspector is a WordPress admin plugin that scans the current site for plugin inventory, duplicate functionality, likely plugin conflicts, security update signals, and performance pressure.

## MVP Scope

- Tools -> Plugin Inspector admin page.
- Installed plugin inventory via `get_plugins()`.
- Active/inactive status, version, author, update availability, and category detection.
- Duplicate functionality rules for SEO, cache, security, backup, forms, SMTP, image optimization, page builders, redirection, analytics, and WooCommerce/commerce.
- Local conflict knowledge base with family overlap rules, plugin-pair rules, and version-based compatibility checks.
- WordPress.org metadata enrichment for last updated, tested up to, required WP/PHP, and active installs where the plugin exists in the public directory.
- Local security intelligence for available updates, abandonment risk, and starter vulnerability rules.
- Explainable health score with visible factors behind each penalty.
- Performance score using plugin count, active plugin count, autoloaded option size, current request query count, memory usage, and database table count.
- Scan persistence in `wp_plugin_inspector_scans`.
- Recommendations generated from duplicates, conflicts, security warnings, and performance score.
- Pasted plugin-list analysis for quick pre-sales or pre-install checks.
- Printable, branded client report via **Generate Client Report** using agency and client fields.

## Installation

1. Copy the `wp-plugin-inspector` folder into `wp-content/plugins/`.
2. Activate **WP Plugin Inspector** from the WordPress Plugins screen.
3. Open **Tools -> Plugin Inspector**.
4. Click **Run Scan**.

## Future API Integrations

The PRD calls for WPScan and Patchstack vulnerability lookups. This MVP keeps the scanner local-first, but `WPI_Scanner::scan_security()` is the intended integration point for API-backed vulnerability data, CVEs, CVSS scores, and abandonment metadata.

## Roadmap

- WPScan/Patchstack API settings screen for live vulnerability intelligence.
- Uploaded plugin-list analysis.
- Branded PDF export with agency logo, client name, and prioritized action plan.
- AI Plugin Advisor using an OpenAI-backed recommendations layer.
