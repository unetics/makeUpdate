<?php
if (!class_exists('MW_updaterr')) {
    class MW_updaterr
    {
        private $api_endpoint;
        private $product_id;
        private $product_name;
        private $plugin_file;
        private $max_version;
        private $max_version_notice;

        public function __construct($product_id, $product_name, $plugin_file = '', $plugin_email, $plugin_key)
        {
    // Store setup data
            $this->product_id = $product_id;
            $this->product_name = $product_name;
            $this->api_endpoint = 'https://plugins.makelabs.com.au/wp-json/pu/v1';
            $this->plugin_file = $plugin_file;
            $this->licence_email = $plugin_email;
            $this->licence_key = $plugin_key;
            $this->max_version = 9999;
            $this->max_version_notice = "";
            $plugin = $this->plugin_file;
            $path = plugin_basename($plugin);
            if (is_admin()) {
                add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
                add_filter('plugins_api', array($this, 'plugins_api_handler'), 10, 3);
                add_action("after_plugin_row_{$path}", array($this, 'update_message'), 10, 3);
            }
        }

        public function update_message($plugin_file, $plugin_data, $status)
        {
            if ($this->max_version < 9999) {
                $message = str_replace("%ver%", $this->max_version, $this->max_version_notice);
                echo '<tr class="active"><td>&nbsp;</td><td colspan="2">' . $message . '</td></tr>';
            }
        }

// Update MAX Version
        public function set_max_version($version, $update_info)
        {
            $version_check = version_compare($version, $this->max_version);

            if ($version_check < 1) {
                $this->max_version = $version;
                $this->max_version_notice = $update_info;
            }
        }

// CHECKING FOR UPDATES
        public function check_for_update($transient)
        {
            if (empty($transient->checked)) {
                return $transient;
            }

            $info = $this->is_update_available();

            if ($info !== FALSE) {

                // Plugin update
                $plugin_slug = plugin_basename($this->plugin_file);

                $icons = (array)$info->icons;

                $transient->response[$plugin_slug] = (object)array(
                    'new_version' => $info->version,
                    'package' => $info->package_url,
                    'slug' => $plugin_slug,
                    'icons' => $icons,
                );
            }

            return $transient;
        }

        public function is_update_available()
        {
            $license_info = $this->get_license_info();
            if ($this->is_api_error($license_info)) {
                return FALSE;
            }

            if (version_compare($license_info->version, $this->get_local_version(), '>')) {
                $version_check = version_compare($this->max_version, $license_info->version);
                if ($version_check > 0) {
                    return $license_info;
                }
            }
            return FALSE;
        }

        public function get_license_info()
        {
            $endpoint = 'https://plugins.makelabs.com.au/wp-json/pu/v1/get-info';
            $body = [
                'license_key' => 'Pixelbart',
                'id' => '1933',
            ];

            $body = wp_json_encode($body);

            $options = [
                'body' => $body,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 60,
                'redirection' => 5,
                'blocking' => true,
                'httpversion' => '1.0',
                'sslverify' => false,
                'data_format' => 'body',
            ];
            $info = wp_remote_retrieve_body(wp_remote_post($endpoint, $options));
            return json_decode($info);
        }

        public function plugins_api_handler($res, $action, $args)
        {
            if ($action == 'plugin_information') {

// If the request is for this plugin, respond to it
                if (isset($args->slug) && $args->slug == plugin_basename($this->plugin_file)) {
                    $info = $this->get_license_info();

                    $res = (object)array(
                        'name' => isset($info->name) ? $info->name : '',
                        'version' => $info->version,
                        'slug' => $args->slug,
                        'download_link' => $info->package_url,

                        'tested' => isset($info->tested) ? $info->tested : '',
                        'requires' => isset($info->requires) ? $info->requires : '',
                        'last_updated' => isset($info->last_updated) ? $info->last_updated : '',
                        'homepage' => isset($info->description_url) ? $info->description_url : '',

                        'sections' => array(
                            'description' => $info->description,
                        ),

                        'banners' => array(
                            'low' => isset($info->banner_low) ? $info->banner_low : '',
                            'high' => isset($info->banner_high) ? $info->banner_high : ''
                        ),
                        'external' => TRUE
                    );

// Add change log tab if the server sent it
                    if (isset($info->changelog)) {
                        $res['sections']['changelog'] = $info->changelog;
                    }
                    return $res;
                }
            }

// Not our request, let WordPress handle this.
            return FALSE;
        }

// HELPER FUNCTIONS FOR ACCESSING PROPERTIES
        private function get_local_version()
        {
            $plugin_data = get_plugin_data($this->plugin_file, FALSE);
            return $plugin_data['Version'];
        }

// API HELPER FUNCTIONS
        private function call_api($action, $params)
        {
            $url = $this->api_endpoint . '/' . $action;

// Append parameters for GET request
            $url .= '?' . http_build_query($params);

// Send the request
            $response = wp_remote_get($url);
            if (is_wp_error($response)) {
                return FALSE;
            }

            $response_body = wp_remote_retrieve_body($response);
            $result = json_decode($response_body);

            return $result;
        }

        private function is_api_error($response)
        {
            if ($response === FALSE) {
                return TRUE;
            }

            if (!is_object($response)) {
                return TRUE;
            }

            if (isset($response->error)) {
                return TRUE;
            }
            return FALSE;
        }

    }
}