<?php

if (! defined('ABSPATH')) {
    die('No script kiddies please!');
}

function yoimg_generate_attachment_metadata($metadata, $id)
{
    if ($id) {
        $yoimg_meta_data = wp_get_attachment_metadata($id, true);
        if (isset($yoimg_meta_data['yoimg_attachment_metadata']) && ! isset($metadata['yoimg_attachment_metadata'])) {
            $metadata['yoimg_attachment_metadata'] = $yoimg_meta_data['yoimg_attachment_metadata'];
        }
    }
    return $metadata;
}

add_filter('wp_generate_attachment_metadata', 'yoimg_generate_attachment_metadata', 2, 2);
