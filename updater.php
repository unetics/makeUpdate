<?php
if (!class_exists('makeUpdate')) {
    class makeUpdate{
        private $api_endpoint;
        private $product_id;
        private $plugin_key;
        private $plugin_file;

        public function __construct($product_id, $plugin_key, $plugin_file = ''){
            $this->product_id = $product_id;
            $this->plugin_key = $plugin_key;
            $this->api_endpoint = 'https://plugins.makelabs.com.au/wp-json/pu/v1';
            $this->plugin_file = $plugin_file;
            $this->licence_key = $plugin_key;
            if (is_admin()) {
                add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
                add_filter('plugins_api', [$this, 'plugins_api_handler'], 10, 3);
                add_filter( 'plugins_api_result', [$this, 'plugins_api_result'], 10, 3);
            }
        }

        public function check_for_update($transient){
            if (empty($transient->checked)) {
                return $transient;
            }
            $info = $this->is_update_available();
            if ($info !== FALSE) {
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

        public function is_update_available(){
            $license_info = $this->get_license_info();
            if (!$this->is_api_error($license_info)) {
                if (version_compare($license_info->version, $this->get_local_version(), '>')) {
                    return $license_info;
                }
            }
            return FALSE;
        }

        public function get_license_info(){
            $endpoint =  $this->api_endpoint .'/get-info';
            $body = [
                'license_key' => $this->plugin_key,
                'id' => $this->product_id,
            ];
            $options = [
                'body' => wp_json_encode($body),
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

        public function plugins_api_result($res, $action, $args){
            if ($action == 'plugin_information') {
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
                            'description' => $info->description_url,
                            'changelog' => $info->description
                        ),
                        'banners' => array(
                            'low' => isset($info->banner_low) ? $info->banner_low : '',
                            'high' => isset($info->banner_high) ? $info->banner_high : ''
                        ),
                        'external' => TRUE
                    );
                    if (isset($info->changelog)) {
                        $res['sections']['changelog'] = $info->changelog;
                    }
                    return $res;
                }
            }
            return $res;
        }

        public function plugins_api_handler($res, $action, $args){
            if ($action == 'plugin_information') {
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
                            'description' => $info->description_url,
                            'changelog' => $info->description
                        ),
                        'banners' => array(
                            'low' => isset($info->banner_low) ? $info->banner_low : '',
                            'high' => isset($info->banner_high) ? $info->banner_high : ''
                        ),
                        'external' => TRUE
                    );
                    if (isset($info->changelog)) {
                        $res['sections']['changelog'] = $info->changelog;
                    }
                    return $res;
                }
            }
            return FALSE;
        }

        private function get_local_version(){
            $plugin_data = get_plugin_data($this->plugin_file, FALSE);
            return $plugin_data['Version'];
        }

        private function is_api_error($response){
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