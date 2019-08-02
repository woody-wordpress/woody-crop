<?php

if (! defined('ABSPATH')) {
    die('No script kiddies please!');
}

$yoimg_retina_crop_enabled = yoimg_is_retina_crop_enabled_for_size($yoimg_image_size);
$is_immediate_cropping = isset($_GET['immediatecrop']) && $_GET['immediatecrop'] == '1';
$is_partial_rendering = isset($_GET['partial']) && $_GET['partial'] == '1';
if (! $is_partial_rendering) {
    ?>
    <script>
        var yoimg_image_id, yoimg_image_size, yoimg_cropper_min_width, yoimg_cropper_min_height, yoimg_cropper_aspect_ratio,yoimg_prev_crop_x, yoimg_prev_crop_y, yoimg_prev_crop_width, yoimg_prev_crop_height, yoimg_retina_crop_enabled;
    </script>
<?php
}

$attachment_metadata = wp_get_attachment_metadata($yoimg_image_id);
$cropped_image_sizes = yoimg_get_image_sizes($yoimg_image_size);
if (isset($attachment_metadata['yoimg_attachment_metadata']['crop'][$yoimg_image_size]['replacement'])) {
    $replacement = $attachment_metadata['yoimg_attachment_metadata']['crop'][$yoimg_image_size]['replacement'];
} else {
    $replacement = null;
}
$has_replacement = ! empty($replacement) && get_post($replacement);
if ($has_replacement) {
    $full_image_attributes = wp_get_attachment_image_src($replacement, 'full');
} else {
    $full_image_attributes = wp_get_attachment_image_src($yoimg_image_id, 'full');
}
?>
<script>
    yoimg_image_id = <?php echo $yoimg_image_id; ?>;
    yoimg_image_size = '<?php echo $yoimg_image_size; ?>';
    <?php
    if ($yoimg_retina_crop_enabled) {
        ?>
        yoimg_cropper_min_width = <?php echo $cropped_image_sizes['width'] * 2; ?>;
        yoimg_cropper_min_height = <?php echo $cropped_image_sizes['height'] * 2; ?>;
        yoimg_retina_crop_enabled = true;
    <?php
    } else {
        ?>
        yoimg_cropper_min_width = <?php echo $cropped_image_sizes['width']; ?>;
        yoimg_cropper_min_height = <?php echo $cropped_image_sizes['height']; ?>;
        yoimg_retina_crop_enabled = false;
    <?php
    }
    ?>
    yoimg_cropper_aspect_ratio = <?php echo $cropped_image_sizes['width']; ?> / <?php echo $cropped_image_sizes['height']; ?>;
    <?php
    if (isset($attachment_metadata['yoimg_attachment_metadata']['crop'][$yoimg_image_size]['x'])) {
        $crop_x = $attachment_metadata['yoimg_attachment_metadata']['crop'][$yoimg_image_size]['x'];
    } else {
        $crop_x = null;
    }
    if (is_numeric($crop_x) && $crop_x >= 0 && (! $is_immediate_cropping)) {
        ?>
        yoimg_prev_crop_x = <?php echo $crop_x; ?>;
        yoimg_prev_crop_y = <?php echo $attachment_metadata['yoimg_attachment_metadata']['crop'][$yoimg_image_size]['y']; ?>;
        yoimg_prev_crop_width = <?php echo $attachment_metadata['yoimg_attachment_metadata']['crop'][$yoimg_image_size]['width']; ?>;
        yoimg_prev_crop_height = <?php echo $attachment_metadata['yoimg_attachment_metadata']['crop'][$yoimg_image_size]['height']; ?>;
    <?php
    } else {
        ?>
        yoimg_prev_crop_x = undefined;
        yoimg_prev_crop_y = undefined;
        yoimg_prev_crop_width = undefined;
        yoimg_prev_crop_height = undefined;
    <?php
    }
    ?>
</script>
<?php if (! $is_partial_rendering) {
        ?>
<div id="yoimg-cropper-wrapper">
    <div class="media-modal wp-core-ui">
        <button type="button" class="media-modal-close" onclick="javascript:yoimgCancelCropImage();">
            <span class="media-modal-icon">
                <span class="screen-reader-text"><?php _e('Close crop panel', YOIMG_DOMAIN); ?></span>
            </span>
        </button>
        <div class="media-modal-content">
<?php
    } ?>
            <div class="media-frame wp-core-ui">
                <div class="media-frame-title"><h1><?php _e('Edit crop formats from full image', YOIMG_DOMAIN); ?> (<?php echo $full_image_attributes[1]; ?>x<?php echo $full_image_attributes[2]; ?>)</h1></div>
                <div class="media-frame-router">
                    <button type="button" class="button-link arrows arrow-l">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                    <div class="media-router">
                        <?php
                        $sizes = yoimg_get_image_sizes();
                        $sizes = yoimg_get_sameratio_sizes($sizes);
                        foreach ($sizes as $size_key => $size_value) {
                            if ($size_value['crop'] == 1 && $size_value['active']) {
                                $is_current_size = $size_key === $yoimg_image_size;
                                if ($is_current_size) {
                                    $curr_size_width = $size_value['width'];
                                    $curr_size_height = $size_value['height'];
                                }
                                $anchor_class = $is_current_size ? 'active' : '';
                                $anchor_href = yoimg_get_edit_image_url($yoimg_image_id, $size_key) . '&partial=1'; ?>
                                <a href="<?php echo $anchor_href; ?>" class="media-menu-item yoimg-thickbox yoimg-thickbox-partial <?php echo $anchor_class; ?>"><?php echo $size_value['name']; ?></a>
                        <?php
                            }
                        }
                        ?>
                    </div>
                    <button type="button" class="button-link arrows arrow-r">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </div>
                <div class="media-frame-content">
                    <div class="attachments-browser">
                        <div class="attachments">
                            <div id="yoimg-cropper-container" style="max-width: <?php echo $full_image_attributes[1]; ?>px;max-height: <?php echo $full_image_attributes[2]; ?>px;">
                                <img id="yoimg-cropper" src="<?php echo $full_image_attributes[0] . '?' . mt_rand(1000, 9999); ?>" style="max-width: 100%;" />
                                <div id="yoimg-replace-restore-wrapper">
                                    <div id="yoimg-replace-img-btn" style="display:none;" title="<?php _e('Replace image source for', YOIMG_DOMAIN); ?> <?php echo $yoimg_image_size; ?>" class="button button-primary button-large"><?php _e('Replace', YOIMG_DOMAIN); ?></div>
                                    <?php if ($has_replacement) {
                            ?>
                                        <div id="yoimg-restore-img-btn" title="<?php _e('Restore original image source for', YOIMG_DOMAIN); ?> <?php echo $yoimg_image_size; ?>" class="button button-large"><?php _e('Restore', YOIMG_DOMAIN); ?></div>
                                    <?php
                        } ?>
                                </div>
                            </div>
                        </div>
                        <div class="media-sidebar">
                            <div class="attachment-details">
                                <?php
                                $is_crop_smaller = false;
                                $is_crop_retina_smaller = false;
                                $this_crop_exists = ! empty($attachment_metadata['sizes'][$yoimg_image_size]['file']);

                                if ($this_crop_exists) {
                                    ?>
                                    <h3><?php _e('Current', YOIMG_DOMAIN); ?> <?php echo $cropped_image_sizes['name']; ?> (<?php echo $attachment_metadata['sizes'][$yoimg_image_size]['width']; ?>x<?php echo $attachment_metadata['sizes'][$yoimg_image_size]['height']; ?>)</h3>
                                <?php
                                } else {
                                    ?>
                                    <h3><?php _e('Current', YOIMG_DOMAIN); ?> <?php echo $cropped_image_sizes['name']; ?> (<?php echo $curr_size_width; ?>x<?php echo $curr_size_height; ?>)</h3>
                                <?php
                                }
                                $image_attributes = wp_get_attachment_image_src($yoimg_image_id, $yoimg_image_size);
                                if ($this_crop_exists) {
                                    ?>
                                    <img src="<?php echo $image_attributes[0] . '?' . mt_rand(1000, 9999); ?>" style="max-width: 100%;" />
                                    <?php
                                    $is_crop_smaller = $full_image_attributes[1] < $curr_size_width || $full_image_attributes[2] < $curr_size_height;
                                    $is_crop_retina_smaller = $yoimg_retina_crop_enabled && ($full_image_attributes[1] < ($curr_size_width * 2) || $full_image_attributes[2] < ($curr_size_height * 2));
                                } else {
                                    $img_url_parts = parse_url($image_attributes[0]);
                                    $img_path_parts = pathinfo($img_url_parts['path']);
                                    $expected_crop_width = $cropped_image_sizes['width'];
                                    $expected_crop_height = $cropped_image_sizes['height'];
                                    $expected_url = $img_path_parts['dirname'] . '/' . yoimg_get_cropped_image_filename($img_path_parts['filename'], $expected_crop_width, $expected_crop_height, $img_path_parts['extension']); ?>
                                    <div class="yoimg-not-existing-crop">
                                        <img src="<?php echo $expected_url; ?>" style="max-width: 100%;" />
                                        <div class="message error">
                                                <p><?php _e('Crop not generated yet, use the crop button here below to generate it', YOIMG_DOMAIN); ?></p>
                                        </div>
                                    </div>
                                <?php
                                } ?>

                                <div class="message error yoimg-crop-smaller" style="display:<?php echo $is_crop_smaller ? 'block' : 'none'; ?>;">
                                    <?php //TODO?>
                                    <p><?php _e('This crop is smaller than expected, you may replace the original image for this crop format using the replace button on the left and then cropping it', YOIMG_DOMAIN); ?></p>
                                </div>

                                <div class="message error yoimg-crop-retina-smaller" style="display:<?php echo $is_crop_retina_smaller ? 'block' : 'none'; ?>;">
                                    <p><?php _e('This crop is too small to create the retina version of the image, you may replace the original image for this crop format using the replace button on the left and then crop it again.', YOIMG_DOMAIN); ?></p>
                                </div>

                                <h3 id="yoimg-cropper-preview-title"><?php _e('Crop preview', YOIMG_DOMAIN); ?></h3>
                                <div id="yoimg-cropper-preview"></div>
                                <div class="yoimg-cropper-quality-wrapper">
                                    <label for="yoimg-cropper-quality"><?php _e('Crop quality', YOIMG_DOMAIN); ?>:</label>
                                    <select name="quality" id="yoimg-cropper-quality">

                                        <?php
                                        $yoimg_crop_settings = get_option('yoimg_crop_settings');
                                        $crop_qualities = $yoimg_crop_settings && isset($yoimg_crop_settings['crop_qualities']) ? $yoimg_crop_settings['crop_qualities'] : unserialize(YOIMG_DEFAULT_CROP_QUALITIES);
                                        foreach ($crop_qualities as $index => $value) {
                                            ?>
                                            <option value="<?php echo $value; ?>"><?php echo $value; ?>%</option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="yoimg-crop-now-wrapper">
                                    <a href="javascript:yoimgCropImage();"
                                            class="button media-button button-primary button-large media-button-select">
                                        <?php _e('Crop', YOIMG_DOMAIN); ?> <?php echo $cropped_image_sizes['name']; ?>
                                    </a>
                                    <span class="spinner"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
<?php if (! $is_partial_rendering) {
                                            ?>
        </div>
    </div>
    <div id="yoimg-cropper-bckgr" class="media-modal-backdrop"></div>
</div>
<?php
                                        }
if ($is_immediate_cropping) {
    ?>
    <script>yoimgInitCropImage(true);</script>
<?php
} else {
        ?>
    <script>yoimgInitCropImage();</script>
<?php
    }
exit();
