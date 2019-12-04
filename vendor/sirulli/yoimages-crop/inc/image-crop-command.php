<?php

use Symfony\Component\Finder\Finder;

if (!defined('ABSPATH')) {
    die('No script kiddies please!');
}

\WP_CLI::add_command('woody:remove_crops', 'woodyRemoveCrops');

function woodyRemoveCrops()
{
    $posts = woodyRemoveCrops_getPosts();
    $medias_filesystem = woodyRemoveCrops_getMediasFileSystem();

    global $_wp_additional_image_sizes;

    // Added default sizes
    $_wp_additional_image_sizes['thumbnail'] = ['height' => 150, 'width' => 150, 'crop' => true];
    $_wp_additional_image_sizes['medium'] = ['height' => 300, 'width' => 300, 'crop' => true];
    $_wp_additional_image_sizes['large'] = ['height' => 1024, 'width' => 1024, 'crop' => true];

    print '---------------------------' . "\n";
    print 'REECRITURE DES METADONNEES' . "\n";
    print '---------------------------' . "\n";

    $total = count($posts);
    $i = 1;
    foreach ($posts as $post) {
        // Get Mime-Type
        $attachment_metadata = $post['metadata'];
        
        if (file_exists(WP_UPLOAD_DIR . '/' . $post['metadata']['file'])) {
            $mime_type = mime_content_type(WP_UPLOAD_DIR . '/' . $post['metadata']['file']);

            foreach ($_wp_additional_image_sizes as $ratio_name => $data) {
                if (!empty($attachment_metadata['sizes'][$ratio_name]) && strpos($attachment_metadata['sizes'][$ratio_name]['file'], 'wp-json') !== false) {
                    continue;
                }

                // Remplacer ou remplir la ligne par la crop API dans les metadatas
                $attachment_metadata['sizes'][$ratio_name] = [
                'file' => '../../../../../wp-json/woody/crop/' . $post['id'] . '/' . $ratio_name,
                'height' => $data['height'],
                'width' => $data['width'],
                'mime-type' => $mime_type,
            ];
            }

            $attachment_metadata['yoimg_attachment_metadata']['history'] = [];

            wp_update_attachment_metadata($post['id'], $attachment_metadata);
            do_action('save_attachment', $post['id']);

            if (array_key_exists($post['file'], $medias_filesystem)) {
                unset($medias_filesystem[$post['file']]);
            }
        }

        print sprintf('%s/%s : %s (%s)', $i, $total, $post['title'], $post['file']) . "\n";
        $i++;
    }

    $total = count($medias_filesystem);
    if (!empty($total)) {
        print '-------------------------------' . "\n";
        print 'SUPPRESSION DES IMAGES CROPEES' . "\n";
        print '-------------------------------' . "\n";

        $i = 1;
        $total_filesize = 0;
        foreach ($medias_filesystem as $file => $path) {
            $filesize = filesize($path);
            print sprintf('%s/%s : %s (%s)', $i, $total, $file, human_filesize($filesize)) . "\n";
            unlink($path);

            $i++;
            $total_filesize += $filesize;
        }

        print '****************' . "\n";
        print 'Free Space : ' . human_filesize($total_filesize);
    }
}

function woodyRemoveCrops_getPosts()
{
    $args = [
        'lang' => PLL_DEFAULT_LANG, // request all languages
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
                        //'file' => pathinfo($attachment_metadata['file'])
                        'file' => $attachment_metadata['file'],
                        'metadata' => $attachment_metadata
                    ];
                }
            }
        }
    }

    return $posts;
}

function woodyRemoveCrops_getMediasFileSystem()
{
    $finder = new Finder();
    $finder->in(WP_UPLOAD_DIR)->files()->name('*.jpg')->name('*.jpeg')->name('*.png')->name('*.gif')->name('*.webp')->followLinks();

    // check if there are any search results
    $medias_filesystem = [];
    if ($finder->hasResults()) {
        foreach ($finder as $file) {
            $medias_filesystem[$file->getRelativePathname()] = $file->getRealPath();
        }
    }

    return $medias_filesystem;
}

function human_filesize($bytes, $decimals = 2)
{
    $factor = floor((strlen($bytes) - 1) / 3);
    if ($factor > 0) {
        $sz = 'KMGT';
    }
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor - 1] . 'B';
}
