<?php

if (! defined('ABSPATH')) {
    die('No script kiddies please!');
}

function yoimg_crop_image()
{
    $_required_args = array(
        'post',
        'size',
        'width',
        'height',
        'x',
        'y',
        'quality',
    );
    $_args          = array();
    foreach ($_required_args as $_key) {
        $_args[ $_key ] = esc_html($_POST[ $_key ]);
    }
    do_action('yoimg_pre_crop_image');
    $result = yoimg_crop_this_image($_args);
    do_action('yoimg_post_crop_image');
    wp_send_json($result);
}

function yoimg_crop_this_image($args)
{
    $req_post = esc_html($args['post']);
    if (current_user_can('edit_post', $req_post)) {
        $req_size                  = esc_html($args[ 'size' ]);
        $req_width                 = esc_html($args[ 'width' ]);
        $req_height                = esc_html($args[ 'height' ]);
        $req_x                     = esc_html($args[ 'x' ]);
        $req_y                     = esc_html($args[ 'y' ]);
        $req_quality               = esc_html($args[ 'quality' ]);
        $yoimg_retina_crop_enabled = yoimg_is_retina_crop_enabled_for_size($req_size);
        $attachment_metadata       = maybe_unserialize(wp_get_attachment_metadata($req_post));
        $pre_crop_filename         = $attachment_metadata['sizes'][$req_size]['file'];

        // Get replacement path if exist
        if (isset($attachment_metadata['yoimg_attachment_metadata']['crop'][$req_size]['replacement'])) {
            $replacement = $attachment_metadata['yoimg_attachment_metadata']['crop'][$req_size]['replacement'];
        } else {
            $replacement = null;
        }
        $has_replacement = !empty($replacement) && get_post($replacement);
        if ($has_replacement) {
            $img_path = _load_image_to_edit_path($replacement);
            $full_image_attributes = wp_get_attachment_image_src($replacement, 'full');
        } else {
            $img_path = _load_image_to_edit_path($req_post);
            $full_image_attributes = wp_get_attachment_image_src($req_post, 'full');
        }

        // Vars
        $cropped_image_sizes = yoimg_get_image_sizes($req_size);
        $crop_width = $cropped_image_sizes['width'];
        $crop_height = $cropped_image_sizes['height'];
        $is_crop_smaller = $full_image_attributes[1] < $crop_width || $full_image_attributes[2] < $crop_height;
        $is_crop_retina_smaller = $full_image_attributes[1] < $crop_width * 2 || $full_image_attributes[2] < $crop_height * 2;

        $vars = [
            'req_size' => $req_size,
            'req_x' => $req_x,
            'req_y' => $req_y,
            'req_width' => $req_width,
            'req_height' => $req_height,
            'req_quality' => $req_quality,
            'req_post' => $req_post,
            'crop_width' => $crop_width,
            'crop_height' => $crop_height,
            'attachment_metadata' => $attachment_metadata,
            'pre_crop_filename' => $pre_crop_filename,
            'replacement' => $replacement,
            'has_replacement' => $has_replacement,
            'img_path' => $img_path,
            'yoimg_retina_crop_enabled' => ($yoimg_retina_crop_enabled && !$is_crop_retina_smaller) ? true : false
        ];

        $cropped_image_filename = yoimg_save_this_image($vars);

        // Save all images with the same ratio
        if (YOIMG_CROP_SAMERATIO_ENABLED) {
            $cropped_sizes = yoimg_get_image_sizes();
            $sameratio_sizes = yoimg_get_sameratio_sizes($cropped_sizes, $req_size);
            if (!empty($sameratio_sizes['sameratio'])) {
                foreach ($sameratio_sizes['sameratio'] as $sameratio_key => $sameratio_val) {
                    $vars['req_size'] = $sameratio_key;
                    $vars['crop_width'] = $sameratio_val['width'];
                    $vars['crop_height'] = $sameratio_val['height'];
                    $vars['attachment_metadata'] = maybe_unserialize(wp_get_attachment_metadata($req_post));
                    $return['sameratio'][$sameratio_key] = yoimg_save_this_image($vars);
                }
            }
        }

        $return = [
            'previous_filename' => $pre_crop_filename,
            'filename' => $cropped_image_filename,
            'smaller'  => $is_crop_smaller,
            'attachment_metadata'  => $attachment_metadata,
        ];

        if ($yoimg_retina_crop_enabled) {
            $return['retina_smaller'] = $is_crop_retina_smaller;
        }

        return $return;
    }

    return false;
}

function yoimg_save_this_image($vars)
{
    extract($vars);
    $img_path_parts = pathinfo($img_path);

    // Retina Save
    if ($yoimg_retina_crop_enabled) {
        $cropped_retina_image_filename = yoimg_get_cropped_image_filename($img_path_parts['filename'], $crop_width, $crop_height, $img_path_parts['extension'], true);
        $cropped_retina_image_path = $img_path_parts['dirname'] . '/' . $cropped_retina_image_filename;

        $img_editor = wp_get_image_editor($img_path);
        $img_editor->crop($req_x, $req_y, $req_width, $req_height, $crop_width * 2, $crop_height * 2, false);
        $img_editor->set_quality($req_quality);
        $img_editor->save($cropped_retina_image_path);
        unset($img_editor);
    }

    // Orginal Save
    $cropped_image_filename = yoimg_get_cropped_image_filename($img_path_parts['filename'], $crop_width, $crop_height, $img_path_parts['extension']);
    $cropped_image_path = $img_path_parts['dirname'] . '/' . $cropped_image_filename;

    $img_editor = wp_get_image_editor($img_path);
    $img_editor->crop($req_x, $req_y, $req_width, $req_height, $crop_width, $crop_height, false);
    $img_editor->set_quality($req_quality);
    $img_editor->save($cropped_image_path);
    unset($img_editor);

    // Updated attachment_metadata
    $attachment_metadata['sizes'][$req_size] = array(
        'file' => $cropped_image_filename,
        'width' => $crop_width,
        'height' => $crop_height,
        'mime-type' => $attachment_metadata['sizes']['thumbnail']['mime-type']
    );

    // YoImages Metadata
    $attachment_metadata['yoimg_attachment_metadata']['crop'][$req_size] = array(
        'x' => $req_x,
        'y' => $req_y,
        'width' => $req_width,
        'height' => $req_height
    );

    if ($has_replacement) {
        $attachment_metadata['yoimg_attachment_metadata']['crop'][$req_size]['replacement'] = $replacement;
    }

    // Save History for cleaning
    $attachment_metadata['yoimg_attachment_metadata']['history'][] = $img_path_parts['dirname'] . '/' . $cropped_image_filename;
    if (!empty($pre_crop_filename)) {
        $attachment_metadata['yoimg_attachment_metadata']['history'][] = $img_path_parts['dirname'] . '/' . $pre_crop_filename;
    }

    wp_update_attachment_metadata($req_post, $attachment_metadata);

    return $cropped_image_filename;
}

function yoimg_edit_thumbnails_page()
{
    global $yoimg_image_id;
    global $yoimg_image_size;
    $yoimg_image_id = esc_html($_GET ['post']);
    $yoimg_image_size = esc_html($_GET ['size']);

    $sizes = yoimg_get_image_sizes();
    $sizes = yoimg_get_sameratio_sizes($sizes);

    $size = false;
    foreach ($sizes as $size_key => $size_value) {
        if ($size_value['crop'] == 1 && $size_value['active']) {
            if ($size_key == $yoimg_image_size) {
                $size = $size_key;
                break;
            } elseif (! $size) {
                $size = $size_key;
            }
        }
    }
    $yoimg_image_size = $size;

    if (current_user_can('edit_post', $yoimg_image_id)) {
        include(YOIMG_CROP_PATH . '/html/edit-image-size.php');
    } else {
        die();
    }
}

function yoimg_replace_image_for_size()
{
    $id = esc_html($_POST['image']);
    $size = esc_html($_POST['size']);
    if (current_user_can('edit_post', $id)) {
        $attachment_metadata = wp_get_attachment_metadata($id);
        $attachment_metadata['yoimg_attachment_metadata']['crop'][$size]['replacement'] = esc_html($_POST['replacement']);
        wp_update_attachment_metadata($id, $attachment_metadata);
    }
    die();
}

function yoimg_restore_original_image_for_size()
{
    $id = esc_html($_POST['image']);
    $size = esc_html($_POST['size']);
    if (current_user_can('edit_post', $id)) {
        $attachment_metadata = wp_get_attachment_metadata($id);
        unset($attachment_metadata['yoimg_attachment_metadata']['crop'][$size]['replacement']);
        wp_update_attachment_metadata($id, $attachment_metadata);
    }
    die();
}

add_action('wp_ajax_yoimg_edit_thumbnails_page', 'yoimg_edit_thumbnails_page');
add_action('wp_ajax_yoimg_restore_original_image_for_size', 'yoimg_restore_original_image_for_size');
add_action('wp_ajax_yoimg_crop_image', 'yoimg_crop_image');
add_action('wp_ajax_yoimg_replace_image_for_size', 'yoimg_replace_image_for_size');
