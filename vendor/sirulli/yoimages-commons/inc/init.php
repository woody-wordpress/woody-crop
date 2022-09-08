<?php

if (! defined('ABSPATH')) {
    die('No script kiddies please!');
}

if (! defined('YOIMG_COMMONS_PATH')) {
    define('YOIMG_COMMONS_PATH', dirname(__FILE__));
    define('YOIMG_SUPPORTED_LOCALES', 'en_US it_IT de_DE nl_NL fr_FR pl_PL');

    require_once(YOIMG_COMMONS_PATH . '/utils.php');

    if (is_admin() || php_sapi_name() == 'cli') {
        define('YOIMG_COMMONS_URL', plugins_url(plugin_basename(YOIMG_COMMONS_PATH)));

        global $yoimg_plugins_url;
        $yoimg_plugins_url = array(
            'yoimages-crop' => 'https://github.com/raccourci/yoimages-crop',
            // 'yoimages-seo' => 'https://github.com/sirulli/yoimages-seo',
            // 'yoimages-search' => 'https://github.com/sirulli/yoimages-search'
        );

        define('YOIMG_DOMAIN', 'yoimg');
        define('YOIMG_LANG_REL_PATH', plugin_basename(YOIMG_COMMONS_PATH . '/languages/'));
        load_plugin_textdomain(YOIMG_DOMAIN, false, YOIMG_LANG_REL_PATH);

        require_once(YOIMG_COMMONS_PATH . '/settings.php');
    }
    if (! function_exists('yoimg_settings_load_styles_and_scripts')) {
        function yoimg_settings_load_styles_and_scripts($hook)
        {
            if (isset($_GET ['page']) && $_GET ['page'] === 'yoimg-settings') {
                wp_enqueue_script('yoimg-settings-js', YOIMG_COMMONS_URL . '/js/yoimg-settings.js', array(
                        'jquery'
                ), false, true);
            }
        }
        add_action('admin_enqueue_scripts', 'yoimg_settings_load_styles_and_scripts');
    }

    function filter_yoimg_meta($links, $file)
    {
        global $yoimg_modules;
        $file_base_dir = explode('/', plugin_dir_path($file));
        $file_base_dir = $file_base_dir[0];
        $plugin_slug = explode('/', plugin_basename(YOIMG_COMMONS_PATH));
        $plugin_slug = $plugin_slug[0];
        $is_yoimg_module_w_settings = isset($yoimg_modules[$file_base_dir]['has-settings']) && $yoimg_modules[$file_base_dir]['has-settings'];
        if ($file_base_dir == $plugin_slug || $is_yoimg_module_w_settings) {
            $plugin_settings_link = 'options-general.php?page=yoimg-settings';
            if ($is_yoimg_module_w_settings) {
                $plugin_settings_link .= '&tab=' . $file_base_dir;
            }
            array_push($links, '<a href="' . $plugin_settings_link . '">'. __('Settings') .'</a>');
        }
        return $links;
    }
    global $wp_version;
    if (version_compare($wp_version, '2.8alpha', '>')) {
        add_filter('plugin_row_meta', 'filter_yoimg_meta', 10, 2);
    }
    add_filter('plugin_action_links', 'filter_yoimg_meta', 10, 2);
}
