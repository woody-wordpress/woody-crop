<?php
if (! defined('ABSPATH')) {
    die('No script kiddies please!');
}
function yoimg_crop_extend_settings($settings)
{
    $crop_settings = array(
            'option' => array(
                    'page' => 'yoimages-crop',
                    'title' => __('Crop settings', YOIMG_DOMAIN),
                    'option_group' => 'yoimages-crop-group',
                    'option_name' => 'yoimg_crop_settings',
                    'sanitize_callback' => 'yoimg_crop_settings_sanitize',
                    'sections' => array(
                            array(
                                    'id' => 'yoimg_crop_options_section',
                                    'title' => __('Crop settings', YOIMG_DOMAIN),
                                    'callback' => 'yoimg_crop_settings_section_info',
                                    'fields' => array(
                                            array(
                                                    'id' => 'cropping_is_active',
                                                    'title' => __('Enable', YOIMG_DOMAIN),
                                                    'callback' => 'yoimg_crop_settings_cropping_is_active_callback'
                                            ),
                                            array(
                                                    'id' => 'crop_qualities',
                                                    'title' => __('Crop qualities', YOIMG_DOMAIN),
                                                    'callback' => 'yoimg_crop_settings_crop_qualities_callback'
                                            ),
                                            array(
                                                    'id' => 'retina_cropping_is_active',
                                                    'title' => __('Retina friendly', YOIMG_DOMAIN),
                                                    'callback' => 'yoimg_crop_settings_retina_cropping_is_active_callback'
                                            ),
                                            array(
                                                'id' => 'sameratio_cropping_is_active',
                                                'title' => __('Crop the same ratio at once', YOIMG_DOMAIN),
                                                'callback' => 'yoimg_crop_settings_sameratio_cropping_is_active_callback'
                                            ),
                                            array(
                                                    'id' => 'cropping_sizes',
                                                    'title' => __('Cropping sizes', YOIMG_DOMAIN),
                                                    'callback' => 'yoimg_crop_settings_cropping_sizes_callback'
                                            ),
                                            array(
                                                    'id' => 'cachebusting_is_active',
                                                    'title' => __('Cachebust new crops', YOIMG_DOMAIN),
                                                    'callback' => 'yoimg_crop_settings_cachebust_new_crops_callback'
                                            )
                                    )
                            )
                    )
            )
    );
    array_push($settings, $crop_settings);
    return $settings;
}
add_filter('yoimg_settings', 'yoimg_crop_extend_settings', 10, 1);
function yoimg_crop_settings_cropping_is_active_callback()
{
    $crop_options = get_option('yoimg_crop_settings');
    printf('<input type="checkbox" id="cropping_is_active" name="yoimg_crop_settings[cropping_is_active]" value="TRUE" %s />
                <p class="description">' . __('If checked cropping is active', YOIMG_DOMAIN) . '</p>', $crop_options ['cropping_is_active'] ? 'checked="checked"' : (YOIMG_DEFAULT_CROP_ENABLED && ! isset($crop_options ['cropping_is_active']) ? 'checked="checked"' : ''));
}
function yoimg_crop_settings_crop_qualities_callback()
{
    $crop_options = get_option('yoimg_crop_settings');
    printf('<input type="text" id="crop_qualities" name="yoimg_crop_settings[crop_qualities]" value="%s" class="cropping_is_active-dep" />
                <p class="description">' . __('Comma separated list of crop quality values (100 best to 50 medium)', YOIMG_DOMAIN) . '</p>', ! empty($crop_options ['crop_qualities']) ? esc_attr(implode(',', $crop_options ['crop_qualities'])) : implode(',', unserialize(YOIMG_DEFAULT_CROP_QUALITIES)));
}
function yoimg_crop_settings_retina_cropping_is_active_callback()
{
    $crop_options = get_option('yoimg_crop_settings');
    printf('<input type="checkbox" id="retina_cropping_is_active" class="cropping_is_active-dep" name="yoimg_crop_settings[retina_cropping_is_active]" value="TRUE" %s />
                <p class="description">' . __('Flag to enable (enable this option if you are using a retina plugin that uses @2x as file naming convention when creating retina images from source - e.g. <a href="https://wordpress.org/plugins/wp-retina-2x/" target="_blank">WP Retina 2x</a>)', YOIMG_DOMAIN) . '</p>', $crop_options ['retina_cropping_is_active'] ? 'checked="checked"' : (YOIMG_DEFAULT_CROP_RETINA_ENABLED && ! isset($crop_options ['retina_cropping_is_active']) ? 'checked="checked"' : ''));
}
function yoimg_crop_settings_sameratio_cropping_is_active_callback()
{
    $crop_options = get_option('yoimg_crop_settings');
    printf('<input type="checkbox" id="sameratio_cropping_is_active" class="cropping_is_active-dep" name="yoimg_crop_settings[sameratio_cropping_is_active]" value="TRUE" %s />
                <p class="description">' . __('Flag to enable (enable this option allows you to crop all images at once with the same ratio)', YOIMG_DOMAIN) . '</p>', $crop_options ['sameratio_cropping_is_active'] ? 'checked="checked"' : (YOIMG_DEFAULT_CROP_SAMERATIO_ENABLED && ! isset($crop_options ['sameratio_cropping_is_active']) ? 'checked="checked"' : ''));
}
function yoimg_crop_settings_cropping_sizes_callback()
{
    $crop_sizes_options = yoimg_get_image_sizes();
    print('<table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td style="width:30%;"><span>' . __('Size id', YOIMG_DOMAIN) . '</span></td>
                        <td style="width:10%;"><span>' . __('Width', YOIMG_DOMAIN) . '</span></td>
                        <td style="width:10%;"><span>' . __('Height', YOIMG_DOMAIN) . '</span></td>
                        <td style="width:40%;"><span>' . __('User-friendly name', YOIMG_DOMAIN) . '</span></td>
                        <td style="width:10%;"><span>' . __('Active', YOIMG_DOMAIN) . '</span></td>
                    </tr>
                </thead>');
    foreach ($crop_sizes_options as $crop_size_id => $crop_size_option) {
        if (isset($crop_size_option['active']) && $crop_size_option['crop'] == 1) {
            printf('<tr><td>%s</td>', $crop_size_id);
            printf('<td>%s</td>', $crop_size_option['width']);
            printf('<td>%s</td>', $crop_size_option['height']);
            printf('<td><input class="cropping_is_active-dep" type="text" name="yoimg_crop_settings[crop_sizes][%s][name]" value="%s" style="width: 100%%;" /></td>', $crop_size_id, $crop_size_option['name']);
            printf('<td><input class="cropping_is_active-dep" type="checkbox" name="yoimg_crop_settings[crop_sizes][%s][active]" value="TRUE" %s /></td></tr>', $crop_size_id, isset($crop_size_option['active']) && $crop_size_option['active'] ? 'checked="checked"' : '');
        }
    }
    print('</table>');
}
// Create the cachebuster setting checkbox
function yoimg_crop_settings_cachebust_new_crops_callback()
{
    $crop_options = get_option('yoimg_crop_settings');
    $check_value = '';
    if ((isset($crop_options['cachebusting_is_active']) && $crop_options['cachebusting_is_active']) || (YOIMG_DEFAULT_CACHEBUSTER_ENABLED && !isset($crop_options['cachebusting_is_active']))) {
        $check_value = 'checked="checked"';
    }

    printf(
        '<input type="checkbox" id="cachebusting_is_active" class="cropping_is_active-dep" name="yoimg_crop_settings[cachebusting_is_active]" value="TRUE" %s />
                <p class="description">' . __('Generate a new filename after cropping images so that they are updated by external caches and CDNs.', YOIMG_DOMAIN) . '</p>',
        $check_value
    );
}
function yoimg_crop_settings_section_info()
{
    print __('Enter your cropping settings here below', YOIMG_DOMAIN);
}

function yoimg_crop_settings_sanitize($input)
{
    $new_input = array();
    if (isset($input ['cropping_is_active']) && ($input ['cropping_is_active'] === 'TRUE' || $input ['cropping_is_active'] === true)) {
        $new_input['cropping_is_active'] = true;
    } else {
        $new_input['cropping_is_active'] = false;
    }
    if (isset($input ['crop_qualities'])) {
        if (is_array($input ['crop_qualities'])) {
            $crop_qualities = $input ['crop_qualities'];
        } else {
            $crop_qualities = explode(',', $input ['crop_qualities']);
        }
        $crop_qualities_count = 0;
        foreach ($crop_qualities as $index => $value) {
            $crop_quality_value = ( int ) $value;
            if ($crop_quality_value > 0 && $crop_quality_value <= 100) {
                $crop_qualities_arr [$crop_qualities_count] = $crop_quality_value;
                $crop_qualities_count ++;
            }
        }
        if (empty($crop_qualities_arr)) {
            add_settings_error('yoimg_crop_options_group', 'crop_qualities', __('Crop qualities value is not valid, using default:', YOIMG_DOMAIN) . ' ' . implode(',', unserialize(YOIMG_DEFAULT_CROP_QUALITIES)), 'error');
            $new_input['crop_qualities'] = unserialize(YOIMG_DEFAULT_CROP_QUALITIES);
        } else {
            $crop_qualities_arr = array_unique($crop_qualities_arr);
            rsort($crop_qualities_arr);
            $new_input['crop_qualities'] = $crop_qualities_arr;
        }
    } else {
        $new_input['crop_qualities'] = unserialize(YOIMG_DEFAULT_CROP_QUALITIES);
    }
    if (isset($input ['retina_cropping_is_active']) && ($input ['retina_cropping_is_active'] === 'TRUE' || $input ['retina_cropping_is_active'] === true)) {
        $new_input['retina_cropping_is_active'] = true;
    } else {
        $new_input['retina_cropping_is_active'] = false;
    }
    if (isset($input ['sameratio_cropping_is_active']) && ($input ['sameratio_cropping_is_active'] === 'TRUE' || $input ['sameratio_cropping_is_active'] === true)) {
        $new_input['sameratio_cropping_is_active'] = true;
    } else {
        $new_input['sameratio_cropping_is_active'] = false;
    }
    if (isset($input ['cachebusting_is_active']) && ($input ['cachebusting_is_active'] === 'TRUE' || $input ['cachebusting_is_active'] === true)) {
        $new_input['cachebusting_is_active'] = true;
    } else {
        $new_input['cachebusting_is_active'] = false;
    }

    if (isset($input ['crop_sizes'])) {
        $there_is_one_manual_crop_active = false;
        foreach ($input['crop_sizes'] as $crop_size_id => $crop_size_option) {
            if (isset($crop_size_option ['active']) && ($crop_size_option ['active'] === 'TRUE' || $crop_size_option ['active'] === true)) {
                $new_input['crop_sizes'][$crop_size_id]['active'] = true;
                $there_is_one_manual_crop_active = true;
            } else {
                $new_input['crop_sizes'][$crop_size_id]['active'] = false;
            }
            if (isset($crop_size_option ['name']) && ! empty($crop_size_option ['name'])) {
                $new_input['crop_sizes'][$crop_size_id]['name'] = $crop_size_option ['name'];
            } else {
                $new_input['crop_sizes'][$crop_size_id]['name'] = $crop_size_id;
            }
        }
        if (! $there_is_one_manual_crop_active) {
            add_settings_error('yoimg_crop_options_group', 'crop_sizes', __('There should be at least one manual crop activated. If you don\'t want any manual crop to be active, please disable the whole cropping using the first checkbox here below.', YOIMG_DOMAIN), 'error');
            foreach ($input['crop_sizes'] as $crop_size_id => $crop_size_option) {
                $new_input['crop_sizes'][$crop_size_id]['active'] = true;
            }
        }
    }
    return $new_input;
}
