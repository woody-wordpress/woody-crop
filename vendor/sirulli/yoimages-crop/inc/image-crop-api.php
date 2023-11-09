<?php

if (!defined('ABSPATH')) {
    die('No script kiddies please!');
}

// API Crop
add_action('rest_api_init', function () {
    register_rest_route('woody', '/crop/(?P<attachment_id>[0-9]{1,10})/(?P<ratio>\S+)', array(
        'methods' => 'GET',
        'callback' => 'yoimg_api'
    ));
});

add_action('rest_api_init', function () {
    register_rest_route('woody', '/crop_debug/(?P<attachment_id>[0-9]{1,10})', array(
        'methods' => 'GET',
        'callback' => 'yoimg_api_debug'
    ));
});

add_action('rest_api_init', function () {
    register_rest_route('woody', '/crop-url/(?P<attachment_id>[0-9]{1,10})', array(
        'methods' => 'GET',
        'callback' => 'yoimg_api_crop_url'
    ));
});


add_action('yoimg_post_crop_image', 'yoimg_flush_cropUrl_varnish');
function yoimg_flush_cropUrl_varnish($post)
{
    do_action('woody_flush_varnish', $post);
}

/* ------------------------ */
/* CROP API                 */
/* ------------------------ */

function yoimg_api(WP_REST_Request $wprestRequest)
{
    $size = [];
    /**
     * Exemple : http://www.superot.wp.rc-dev.com/wp-json/woody/crop/382/ratio_square
     */
    global $_wp_additional_image_sizes;

    $params = $wprestRequest->get_params();
    $ratio_name = $params['ratio'];
    $attachment_id = $params['attachment_id'];

    $force = false;
    if (strpos($ratio_name, '_force') !== false) {
        $ratio_name = str_replace('_force', '', $ratio_name);
        $force = true;
    }

    // Woody constants
    if (empty(WP_UPLOAD_DIR)) {
        $upload_path = get_option('upload_path');
        define('WP_UPLOAD_DIR', $upload_path);
    }

    // Added default sizes
    $_wp_additional_image_sizes['thumbnail'] = ['height' => 150, 'width' => 150, 'crop' => true];
    $_wp_additional_image_sizes['medium'] = ['height' => 300, 'width' => 300, 'crop' => true];
    $_wp_additional_image_sizes['large'] = ['height' => 1024, 'width' => 1024, 'crop' => true];

    if (!empty($_wp_additional_image_sizes[$ratio_name])) {
        // Get metadata
        $attachment_metadata = maybe_unserialize(wp_get_attachment_metadata($attachment_id));

        // Define size from crop OR from default
        if (!empty($attachment_metadata['yoimg_attachment_metadata']['crop'][$ratio_name])) {
            $size = $attachment_metadata['yoimg_attachment_metadata']['crop'][$ratio_name];
            $size['req_width'] = $size['width'];
            $size['req_height'] = $size['height'];
            $size['width'] = $_wp_additional_image_sizes[$ratio_name]['width'];
            $size['height'] = $_wp_additional_image_sizes[$ratio_name]['height'];
        } else {
            $size = $_wp_additional_image_sizes[$ratio_name];
        }

        // Default 404
        $image_url = '';

        // Crop image OR return image url
        if (!empty($attachment_metadata['file'])) {
            $img_path = WP_UPLOAD_DIR . '/' . $attachment_metadata['file'];

            // Get infos from original image
            $img_path_parts = pathinfo($img_path);

            if (file_exists($img_path) && exif_imagetype($img_path)) {
                if (
                    $force ||
                    empty($attachment_metadata['sizes'][$ratio_name]) ||
                    empty($attachment_metadata['sizes'][$ratio_name]['file']) ||
                    strpos($attachment_metadata['sizes'][$ratio_name]['file'], 'wp-json') !== false ||
                    !file_exists($img_path_parts['dirname'] . '/' . $attachment_metadata['sizes'][$ratio_name]['file'])
                ) {
                    $cropped_image_path = yoimg_api_crop_from_size($img_path, $size, $force);

                    // Get Image cropped data
                    if (file_exists($cropped_image_path)) {
                        $img_cropped_parts = pathinfo($cropped_image_path);
                        $image_crop = 'thumbs/' . $img_cropped_parts['basename'];

                        // Save History for cleaning
                        $attachment_metadata['yoimg_attachment_metadata']['history'][] = $cropped_image_path;
                        $attachment_metadata['sizes'][$ratio_name]['file'] = $image_crop;
                        wp_update_attachment_metadata($attachment_id, $attachment_metadata);
                    }
                }

                $image_url = wp_get_attachment_image_url($attachment_id, $ratio_name);
            }
        }
    }

    // Added Headers for varnish purge
    header('xkey: ' . WP_SITE_KEY, false);
    header('xkey: ' . WP_SITE_KEY . '_' . $attachment_id, false);
    header('X-VC-TTL: ' . WOODY_VARNISH_CACHING_TTL, true);

    if (!empty($image_url) && strpos($image_url, 'wp-json') == false) {
        wp_redirect($image_url, 301, 'Woody Crop');
    } else {
        // Default 404
        $image_url = 'https://api.tourism-system.com/resize/clip/' . $size['width'] . '/' . $size['height'] . '/70/aHR0cHM6Ly9hcGkudG91cmlzbS1zeXN0ZW0uY29tL3N0YXRpYy9hc3NldHMvaW1hZ2VzL3Jlc2l6ZXIvaW1nXzQwNC5qcGc=/404.jpg';
        wp_redirect($image_url, 302, 'Woody Crop');
    }

    exit;
}

function yoimg_api_debug(WP_REST_Request $wprestRequest)
{
    /**
     * Exemple : http://www.superot.wp.rc-dev.com/wp-json/woody/crop_debug/6013
     */
    global $_wp_additional_image_sizes;

    $params = $wprestRequest->get_params();
    $attachment_id = $params['attachment_id'];

    // Added Headers for varnish purge
    header('xkey: ' . WP_SITE_KEY, false);
    header('xkey: ' . WP_SITE_KEY . '_' . $attachment_id, false);
    header('X-VC-TTL: ' . WOODY_VARNISH_CACHING_TTL, true);

    // Return
    header('Content-type: text/html');
    print '<html><body style="font-family:sans-serif;">';
    foreach ($_wp_additional_image_sizes as $ratio => $size) {
        if (!empty($ratio)) {
            if (strpos($ratio, 'small') !== false) {
                continue;
            }

            if (strpos($ratio, 'medium') !== false) {
                continue;
            }

            if (strpos($ratio, 'large') !== false) {
                continue;
            }

            print '<h2>' . $ratio . '</h2>';
            print '<p><img style="max-width:50%" src="/wp-json/woody/crop/' . $attachment_id . '/' . $ratio . '_force" title="' . $ratio . '" alt="' . $ratio . '"></p>';
        }
    }

    print '</body></html>';
    exit();
}

function yoimg_api_crop_from_size($img_path, $size, $force = false)
{
    $req_x = null;
    $req_y = null;
    $req_width = null;
    $req_height = null;
    // Get infos from original image
    $img_path_parts = pathinfo($img_path);

    // Set filename
    $cropped_image_dirname = $img_path_parts['dirname'] . '/thumbs';

    // get the size of the image
    [$width_orig, $height_orig] = @getimagesize($img_path);

    if (!empty($width_orig) && !empty($height_orig)) {
        if (isset($size['x']) && isset($size['y']) && isset($size['req_width']) && isset($size['req_height'])) {
            // Crop already set
            $req_x = $size['x'];
            $req_y = $size['y'];
            $req_width = $size['req_width'];
            $req_height = $size['req_height'];

            $cropped_image_filename = $img_path_parts['filename'] . '-' . $size['width'] . 'x' . $size['height']  . '-crop-' . time() . '.' . $img_path_parts['extension'];
            $cropped_image_path = $cropped_image_dirname . '/' . $cropped_image_filename;
        } else {
            $ratio_orig = (float) $height_orig / $width_orig;

            // Ratio Free
            if ($size['height'] == 0) {
                $req_width = $width_orig;
                $req_height = $height_orig;

                $size['height'] = $ratio_orig == 1 ? $size['width'] : round($size['width'] * $ratio_orig);
            }

            // Get ratio diff
            $ratio_expect = (float) $size['height'] / $size['width'];
            $ratio_diff = $ratio_orig - $ratio_expect;

            // Calcul du crop size
            if ($ratio_diff > 0) {
                $req_width = $width_orig;
                $req_height = round($width_orig * $ratio_expect);
                $req_x = 0;
                $req_y = round(($height_orig - $req_height) / 2);
            } elseif ($ratio_diff < 0) {
                $req_width = round($height_orig / $ratio_expect);
                $req_height = $height_orig;
                $req_x = round(($width_orig - $req_width) / 2);
                $req_y = 0;
            } elseif ($ratio_diff == 0) {
                $req_width = $width_orig;
                $req_height = $height_orig;
                $req_x = 0;
                $req_y = 0;
            }

            $cropped_image_filename = $img_path_parts['filename'] . '-' . $size['width'] . 'x' . $size['height'] . '.' . $img_path_parts['extension'];
            $cropped_image_path = $cropped_image_dirname . '/' . $cropped_image_filename;
        }

        // Create thumbs dir if not exists
        if (!file_exists($cropped_image_dirname)) {
            mkdir($cropped_image_dirname, 0775);
        }

        if (file_exists($cropped_image_path) && $force) {
            // Remove image before recreate
            unlink($cropped_image_path);
        }

        // Set webp filename
        $cropped_webp_path = $cropped_image_path . '.webp';
        if (file_exists($cropped_webp_path) && $force) {
            // Remove image before recreate
            unlink($cropped_webp_path);
        }

        yoimg_api_crop($img_path, $cropped_image_path, $req_x, $req_y, $req_width, $req_height, $size['width'], $size['height']);

        return $cropped_image_path;
    }
}

function yoimg_api_crop($img_path, $cropped_image_path, $req_x, $req_y, $req_width, $req_height, $width, $height)
{
    // get the size of the image
    [$width_orig, $height_orig, $image_type] = @getimagesize($img_path);

    // Set webp filename
    if (YOIMG_WEBP_ENABLED) {
        $cropped_image_path = dirname($cropped_image_path) . DIRECTORY_SEPARATOR . pathinfo(basename($cropped_image_path), PATHINFO_FILENAME) . '.webp';
    }

    // ----------------------------------------
    $img = yoimg_api_load_image($img_path);
    $cropped_img = yoimg_api_resampled_image($img, $req_x, $req_y, $req_width, $req_height, $width, $height, false);

    switch ($image_type) {
        case IMAGETYPE_JPEG:
            // Progressive
            if (function_exists('imageinterlace')) {
                imageinterlace($cropped_img, true);
            }

            if (YOIMG_WEBP_ENABLED) {
                // Export WEBP progressive with no EXIF data
                imagewebp($cropped_img, $cropped_image_path, 75);
            } else {
                // Export JPEG progressive with no EXIF data
                imagejpeg($cropped_img, $cropped_image_path, 75);
            }

            break;

        case IMAGETYPE_GIF:
            // Progressive
            if (function_exists('imageinterlace')) {
                imageinterlace($cropped_img, true);
            }

            // Export GIF progressive with no EXIF data
            imagegif($cropped_img, $cropped_image_path);
            break;

        case IMAGETYPE_PNG:
            if (YOIMG_WEBP_ENABLED) {
                // Export WEBP progressive with no EXIF data
                imagewebp($cropped_img, $cropped_image_path, 75);
            } else {
                // Export PNG progressive with no EXIF data
                imagepng($cropped_img, $cropped_image_path, 3);
            }

            break;
    }

    // Free memory
    imagedestroy($img);
    imagedestroy($cropped_img);

    return $cropped_image_path;
}

function yoimg_api_load_image($img_path)
{
    if (!is_file($img_path) && !preg_match('#^https?://#', $img_path)) {
        return new WP_Error('error_loading_image', __('File doesn&#8217;t exist?'), $img_path);
    }

    // Set artificially high because GD uses uncompressed images in memory.
    wp_raise_memory_limit('image');

    $img = @imagecreatefromstring(file_get_contents($img_path));
    if (!is_resource($img)) {
        return new WP_Error('invalid_image', __('File is not an image.'), $img_path);
    }

    $size = @getimagesize($img_path);
    if (!$size) {
        return new WP_Error('invalid_image', __('Could not read image size.'), $img_path);
    }

    if (function_exists('imagealphablending') && function_exists('imagesavealpha')) {
        imagealphablending($img, false);
        imagesavealpha($img, true);
    }

    return $img;
}

function yoimg_api_resampled_image($img, $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false)
{
    $dst = wp_imagecreatetruecolor($dst_w, $dst_h);

    if ($src_abs) {
        $src_w -= $src_x;
        $src_h -= $src_y;
    }

    if (function_exists('imageantialias')) {
        imageantialias($dst, true);
    }

    imagecopyresampled($dst, $img, 0, 0, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);

    if (is_resource($dst)) {
        return $dst;
    }

    return new WP_Error('image_crop_error', __('Image crop failed.'));
}

function yoimg_api_crop_url(WP_REST_Request $wprestRequest)
{
    $params = $wprestRequest->get_params();
    $attachment_id = $params['attachment_id'];
    $attachment_metadata = acf_get_attachment($attachment_id, true);

    $return = [];
    if (!empty($attachment_metadata) && !empty($attachment_metadata['sizes'])) {
        $return = array_filter($attachment_metadata['sizes'], fn ($file) => strpos($file, 'http') !== false);
    }

    // Added Headers for varnish purge
    header('xkey: ' . WP_SITE_KEY, false);
    header('xkey: ' . WP_SITE_KEY . '_' . $attachment_id, false);
    header('X-VC-TTL: ' . WOODY_VARNISH_CACHING_TTL, true);
    return $return;
}
