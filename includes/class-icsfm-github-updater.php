<?php
defined('ABSPATH') || exit;

class ICSFM_GitHub_Updater {

    private string $repo;
    private string $slug;
    private string $basename;
    private ?object $github_data = null;

    public function __construct() {
        $this->repo = ICSFM_GITHUB_REPO;
        $this->slug = 'ics-feed-monitor';
        $this->basename = ICSFM_PLUGIN_BASENAME;

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);
    }

    private function get_github_release(): ?object {
        if ($this->github_data !== null) {
            return $this->github_data;
        }

        // Cache for 6 hours
        $cache_key = 'icsfm_github_release';
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            $this->github_data = $cached;
            return $this->github_data;
        }

        $url = "https://api.github.com/repos/{$this->repo}/releases/latest";
        $headers = [
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'ICS-Feed-Monitor/' . ICSFM_VERSION,
        ];

        // Support private repos via wp-config.php constant
        if (defined('ICSFM_GITHUB_TOKEN') && ICSFM_GITHUB_TOKEN) {
            $headers['Authorization'] = 'token ' . ICSFM_GITHUB_TOKEN;
        }

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => $headers,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->github_data = null;
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response));
        if (!$data || !isset($data->tag_name)) {
            $this->github_data = null;
            return null;
        }

        set_transient($cache_key, $data, 6 * HOUR_IN_SECONDS);
        $this->github_data = $data;

        return $this->github_data;
    }

    private function get_version_from_tag(string $tag): string {
        // Strip leading 'v' from tag: v1.0.1 -> 1.0.1
        return ltrim($tag, 'vV');
    }

    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_github_release();
        if (!$release) {
            return $transient;
        }

        $remote_version = $this->get_version_from_tag($release->tag_name);
        $local_version = ICSFM_VERSION;

        if (version_compare($remote_version, $local_version, '>')) {
            // Find the zip asset or use the zipball URL
            $download_url = $release->zipball_url;
            if (!empty($release->assets)) {
                foreach ($release->assets as $asset) {
                    if (str_ends_with($asset->name, '.zip')) {
                        $download_url = $asset->browser_download_url;
                        break;
                    }
                }
            }

            // Add auth header for private repos
            if (defined('ICSFM_GITHUB_TOKEN') && ICSFM_GITHUB_TOKEN) {
                $download_url = add_query_arg('access_token', ICSFM_GITHUB_TOKEN, $download_url);
            }

            $transient->response[$this->basename] = (object) [
                'slug'        => $this->slug,
                'plugin'      => $this->basename,
                'new_version' => $remote_version,
                'url'         => "https://github.com/{$this->repo}",
                'package'     => $download_url,
                'icons'       => [],
                'banners'     => [],
                'tested'      => '',
                'requires_php'=> '7.4',
            ];

            ICSFM_Logger::info('system', 'Update available', [
                'current_version' => $local_version,
                'new_version'     => $remote_version,
                'tag'             => $release->tag_name,
            ]);
        }

        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== $this->slug) {
            return $result;
        }

        $release = $this->get_github_release();
        if (!$release) {
            return $result;
        }

        $remote_version = $this->get_version_from_tag($release->tag_name);

        return (object) [
            'name'            => 'ICS Feed Monitor',
            'slug'            => $this->slug,
            'version'         => $remote_version,
            'author'          => '<a href="https://nonatech.co.uk">Nonatech UK</a>',
            'homepage'        => "https://github.com/{$this->repo}",
            'requires_php'    => '7.4',
            'downloaded'      => 0,
            'last_updated'    => $release->published_at ?? '',
            'sections'        => [
                'description'  => 'Proxies ICS calendar feeds and monitors polling activity. Alerts via webhook when platforms stop syncing.',
                'changelog'    => nl2br(esc_html($release->body ?? 'No changelog provided.')),
            ],
            'download_link'   => $release->zipball_url,
        ];
    }

    public function post_install($true, $hook_extra, $result) {
        // Only handle our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->basename) {
            return $result;
        }

        // GitHub zipball extracts to a directory like "owner-repo-hash"
        // We need to rename it to our expected directory
        global $wp_filesystem;

        $plugin_dir = WP_PLUGIN_DIR . '/' . $this->slug;
        $installed_dir = $result['destination'];

        if ($installed_dir !== $plugin_dir) {
            $wp_filesystem->move($installed_dir, $plugin_dir);
            $result['destination'] = $plugin_dir;
        }

        // Reactivate plugin
        if (is_plugin_active($this->basename)) {
            activate_plugin($this->basename);
        }

        ICSFM_Logger::info('system', 'Plugin updated via GitHub', [
            'version' => ICSFM_VERSION,
        ]);

        return $result;
    }
}
