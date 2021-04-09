<?php

use Symfony\Component\Finder\Finder;

if (!defined('ABSPATH')) {
    die('No script kiddies please!');
}

\WP_CLI::add_command('woody:debug_crops', 'woodyCrop_debug');
\WP_CLI::add_command('woody:reset_crops', 'woodyCrop_reset');

function woodyCrop_debug($args = [], $assoc_args = [])
{
    if (!empty($assoc_args) && !empty($assoc_args['force'])) {
        $force = true;
        output_warning(sprintf('FORCE'));
    } else {
        $force = false;
        output_warning(sprintf('SIMULATION'));
    }

    woodyCrop_debugMetas($force);
}

function woodyCrop_reset($args = [], $assoc_args = [])
{
    if (!empty($assoc_args) && !empty($assoc_args['force'])) {
        $force = true;
        output_warning(sprintf('FORCE'));
    } else {
        $force = false;
        output_warning(sprintf('SIMULATION'));
    }

    $existing_original_files = woodyCrop_resetMetas($force);
    woodyCrop_removeOrphans($existing_original_files, $force);
}

function woodyCrop_debugMetas($force = false)
{
    /* -------------------------------------------------------------------------------- */
    /* ATTENTION cette fonction supprime les images misent directement dans les Wysiwyg */
    /* -------------------------------------------------------------------------------- */

    $posts = woodyCrop_getPosts();

    global $_wp_additional_image_sizes;

    // Added default sizes
    $_wp_additional_image_sizes['thumbnail'] = ['height' => 150, 'width' => 150, 'crop' => true];
    $_wp_additional_image_sizes['medium'] = ['height' => 300, 'width' => 300, 'crop' => true];
    $_wp_additional_image_sizes['large'] = ['height' => 1024, 'width' => 1024, 'crop' => true];

    foreach ($posts as $post) {
        $update_metadata = false;
        $attachment_metadata = maybe_unserialize(wp_get_attachment_metadata($post['id']));
        if (!empty($attachment_metadata['file'])) {
            $img_path = WP_UPLOAD_DIR . '/' . $attachment_metadata['file'];
            $img_path_parts = pathinfo($img_path);
            $cropped_image_path = $img_path_parts['dirname'] . '/';

            // Get Mime-Type
            $mime_type = mime_content_type(WP_UPLOAD_DIR . '/' . $attachment_metadata['file']);

            foreach ($_wp_additional_image_sizes as $ratio_name => $data) {
                if (!empty($attachment_metadata['sizes'][$ratio_name]) && strpos($attachment_metadata['sizes'][$ratio_name]['file'], 'wp-json') !== false) {
                    continue;
                }

                // Supprimer le fichier sur le filer
                if (!empty($attachment_metadata['sizes'][$ratio_name])) {
                    $deleted_image_path = $cropped_image_path . $attachment_metadata['sizes'][$ratio_name]['file'];
                    if (!file_exists($deleted_image_path)) {
                        // Remplacer ou remplir la ligne par la crop API dans les metadatas
                        $attachment_metadata['sizes'][$ratio_name] = [
                            'file' => '../../../../../wp-json/woody/crop/' . $post['id'] . '/' . $ratio_name,
                            'height' => $data['height'],
                            'width' => $data['width'],
                            'mime-type' => $mime_type,
                        ];

                        $update_metadata = true;
                        output_log(sprintf('Image manquante (%s) : %s', $ratio_name, $deleted_image_path));
                    }
                }
            }

            // Added full size
            $filename = explode('/', $attachment_metadata['file']);
            $filename = end($filename);
            $attachment_metadata['sizes']['full'] = [
                'file' => $filename,
                'height' => $attachment_metadata['height'],
                'width' => $attachment_metadata['width'],
                'mime-type' => $mime_type,
            ];

            if ($update_metadata) {
                if ($force) {
                    wp_update_attachment_metadata($post['id'], $attachment_metadata);
                    do_action('save_attachment', $post['id']);
                }

                output_log(sprintf('Image nettoyée : %s - %s (%s)', $post['lang'], $post['title'], $post['id']));
            }
        }
    }

    // Total filesize
    output_success(sprintf('Poids de la suppression (%s)', woodyCrop_HumanFileSize($cleaning_filesize)));

    return $existing_original_files;
}

function woodyCrop_resetMetas($force = false)
{
    /* -------------------------------------------------------------------------------- */
    /* ATTENTION cette fonction supprime les images misent directement dans les Wysiwyg */
    /* -------------------------------------------------------------------------------- */

    $posts = woodyCrop_getPosts();

    global $_wp_additional_image_sizes;
    $cleaning_filesize = 0;
    $existing_original_files = [];

    // Added default sizes
    $_wp_additional_image_sizes['thumbnail'] = ['height' => 150, 'width' => 150, 'crop' => true];
    $_wp_additional_image_sizes['medium'] = ['height' => 300, 'width' => 300, 'crop' => true];
    $_wp_additional_image_sizes['large'] = ['height' => 1024, 'width' => 1024, 'crop' => true];

    foreach ($posts as $post) {
        $attachment_metadata = maybe_unserialize(wp_get_attachment_metadata($post['id']));
        if (!empty($attachment_metadata['file'])) {
            $img_path = WP_UPLOAD_DIR . '/' . $attachment_metadata['file'];
            $img_path_parts = pathinfo($img_path);
            $cropped_image_path = $img_path_parts['dirname'] . '/';
            $filesize = 0;

            // Get Mime-Type
            $mime_type = mime_content_type(WP_UPLOAD_DIR . '/' . $attachment_metadata['file']);

            foreach ($_wp_additional_image_sizes as $ratio_name => $data) {
                if (!empty($attachment_metadata['sizes'][$ratio_name]) && strpos($attachment_metadata['sizes'][$ratio_name]['file'], 'wp-json') !== false) {
                    continue;
                }

                // Supprimer le fichier sur le filer
                if (!empty($attachment_metadata['sizes'][$ratio_name])) {
                    $deleted_image_path = $cropped_image_path . $attachment_metadata['sizes'][$ratio_name]['file'];
                    if (file_exists($deleted_image_path)) {
                        $filesize += filesize($deleted_image_path);

                        if ($force) {
                            unlink($deleted_image_path);
                        }

                        output_log(sprintf('DELETE : %s', $deleted_image_path));
                    }

                    if (file_exists($deleted_image_path . '.webp')) {
                        $filesize += filesize($deleted_image_path . '.webp');

                        if ($force) {
                            unlink($deleted_image_path . '.webp');
                        }

                        output_log(sprintf('DELETE : %s', $deleted_image_path . '.webp'));
                    }
                }

                // Remplacer ou remplir la ligne par la crop API dans les metadatas
                $attachment_metadata['sizes'][$ratio_name] = [
                    'file' => '../../../../../wp-json/woody/crop/' . $post['id'] . '/' . $ratio_name,
                    'height' => $data['height'],
                    'width' => $data['width'],
                    'mime-type' => $mime_type,
                ];
            }

            // Delete history
            if (!empty($attachment_metadata['yoimg_attachment_metadata']['history'])) {
                foreach ($attachment_metadata['yoimg_attachment_metadata']['history'] as $key => $file) {
                    if (file_exists($file)) {
                        $filesize += filesize($file);

                        if ($force) {
                            unlink($file);
                        }

                        output_log(sprintf('DELETE : %s', $file));
                    }

                    if (file_exists($file . '.webp')) {
                        $filesize += filesize($file . '.webp');

                        if ($force) {
                            unlink($file . '.webp');
                        }

                        output_log(sprintf('DELETE : %s', $file . '.webp'));
                    }

                    if ($force) {
                        unset($attachment_metadata['yoimg_attachment_metadata']['history'][$key]);
                    }
                }
            }

            // Added full size
            $filename = explode('/', $attachment_metadata['file']);
            $filename = end($filename);
            $attachment_metadata['sizes']['full'] = [
                'file' => $filename,
                'height' => $attachment_metadata['height'],
                'width' => $attachment_metadata['width'],
                'mime-type' => $mime_type,
            ];

            if ($force) {
                wp_update_attachment_metadata($post['id'], $attachment_metadata);
                do_action('save_attachment', $post['id']);
            }

            $existing_original_files[] = $img_path;
            $cleaning_filesize += $filesize;
            output_log(sprintf('Image nettoyée (%s) : %s - %s (%s)', woodyCrop_HumanFileSize($filesize), $post['lang'], $post['title'], $post['id']));
        }
    }

    // Total filesize
    output_success(sprintf('Poids de la suppression (%s)', woodyCrop_HumanFileSize($cleaning_filesize)));

    return $existing_original_files;
}

function woodyCrop_removeOrphans($existing_original_files = [], $force = false)
{
    $imgs_inside_upload_dir = [];
    $keep_imgs = 0;
    $delete_imgs = 0;
    $cleaning_filesize = 0;

    // Search all images inside WP_UPLOAD_DIR
    $finder = new Finder();
    $finder->files()->in(WP_UPLOAD_DIR)->name(['*.jpeg', '*.jpg', '*.gif', '*.bmp', '*.png', '*.svg', '*.webp']);
    foreach ($finder as $file) {
        $real_path = str_replace('/shared/', '/current/', $file->getRealPath());
        if (in_array($real_path, $existing_original_files)) {
            output_log(sprintf('KEEP : %s', $real_path));
            $keep_imgs++;
        } else {
            output_log(sprintf('DELETE : %s', $real_path));
            $delete_imgs++;
            $cleaning_filesize += filesize($real_path);

            if ($force) {
                unlink($file);
            }
        }
    }

    output_success(sprintf('Images %s supprimées / %s conservées', $delete_imgs, $keep_imgs));
    output_success(sprintf('Poids de la suppression (%s)', woodyCrop_HumanFileSize($cleaning_filesize)));
}

function woodyCrop_getPosts()
{
    $args = [
        'lang' => '', // request all languages
        'posts_per_page' => -1,
        //'posts_per_page' => 5,
        'post_status' => 'inherit',
        'post_type' => 'attachment',
    ];

    $posts = [];
    $query_result = new \WP_Query($args);
    if (!empty($query_result->posts)) {
        foreach ($query_result->posts as $result) {
            if (wp_attachment_is_image($result->ID)) {
                $attachment_metadata = maybe_unserialize(wp_get_attachment_metadata($result->ID));
                if (!empty($attachment_metadata['file'])) {
                    $posts[] = [
                        'id' => $result->ID,
                        'title' => $result->post_title,
                        'lang' => pll_get_post_language($result->ID),
                        'file' => $attachment_metadata['file'],
                        'metadata' => $attachment_metadata
                    ];
                }
            }
        }
    }

    return $posts;
}

function woodyCrop_HumanFileSize($bytes, $decimals = 2)
{
    $factor = floor((strlen($bytes) - 1) / 3);
    if ($factor > 0) {
        $sz = 'KMGT';
    }
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor - 1] . 'B';
}
