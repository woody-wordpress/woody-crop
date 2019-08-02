var yoimgMediaUploader;

function yoimgLoadCropThickbox(href, partial) {
    jQuery.get(href, function(data) {
        if (partial) {
            jQuery('#yoimg-cropper-wrapper .media-modal-content').empty().append(data);
        } else {
            jQuery('body').append(data);
        }
    });
}

function yoimgAddEditImageAnchors() {
    setInterval(function() {
        if (jQuery('#media-items .edit-attachment').length) {
            jQuery('#media-items .edit-attachment').each(function(i, k) {
                try {
                    var currEl = jQuery(this);
                    var mRegexp = /\?post=([0-9]+)/;
                    var match = mRegexp.exec(currEl.attr('href'));
                    if (!currEl.parent().find('.yoimg').length && currEl.parent().find('.pinkynail').attr('src').match(/upload/g)) {
                        var data = {
                            'action': 'yoimg_get_edit_image_anchor',
                            'post': match[1]
                        };
                        jQuery.post(ajaxurl, data, function(response) {
                            currEl.after(response);
                        });
                    }
                } catch (e) {
                    console.log(e);
                }
            });
        }
    }, 1000);
}

function yoimgExtendMediaLightboxTemplate(anchor1, anchor2, anchor3, anchor4) {
    var attachmentDetailsTmpl = jQuery('#tmpl-attachment-details').text();
    attachmentDetailsTmpl = attachmentDetailsTmpl.replace(/(<(a|button)[^>]+class="[^"]*edit-attachment[^"]*"[^>]*>[^<]*<\/(a|button)>)/, '\n$1' + anchor1);
    jQuery('#tmpl-attachment-details').text(attachmentDetailsTmpl);
    var attachmentDetailsTmplTwoColumn = jQuery('#tmpl-attachment-details-two-column').text();
    attachmentDetailsTmplTwoColumn = attachmentDetailsTmplTwoColumn.replace(/(<a[^>]+class="[^"]*view-attachment[^"]*"[^>]*>[^<]*<\/a>)/, '\n$1 | ' + anchor2);
    attachmentDetailsTmplTwoColumn = attachmentDetailsTmplTwoColumn.replace(/(<(a|button)[^>]+class="[^"]*edit-attachment[^"]*"[^>]*>[^<]*<\/(a|button)>)/, '\n$1' + anchor3);
    jQuery('#tmpl-attachment-details-two-column').text(attachmentDetailsTmplTwoColumn);
    var imageDetailsTmpl = jQuery('#tmpl-image-details').text();
    imageDetailsTmpl = imageDetailsTmpl.replace(/(<input type="button" class="replace-attachment button")/, anchor4 + '\n$1');
    jQuery('#tmpl-image-details').text(imageDetailsTmpl);
}

function yoimgInitCropImage(doImmediateCrop) {
    if (typeof yoimg_cropper_aspect_ratio !== 'undefined') {
        function adaptCropPreviewWidth() {
            var width = Math.min(jQuery('#yoimg-cropper-preview-title').width(), yoimg_retina_crop_enabled ? (yoimg_cropper_min_width / 2) : yoimg_cropper_min_width);
            jQuery('#yoimg-cropper-preview').css({
                'height': (width / yoimg_cropper_aspect_ratio) + 'px',
                'width': width + 'px'
            });
        }
        adaptCropPreviewWidth();
        jQuery(window).resize(adaptCropPreviewWidth);
        var cropperData;
        if (typeof yoimg_prev_crop_x !== 'undefined') {
            cropperData = {
                x: yoimg_prev_crop_x,
                y: yoimg_prev_crop_y,
                width: yoimg_prev_crop_width,
                height: yoimg_prev_crop_height
            };
        } else {
            cropperData = {};
        }
        jQuery('#yoimg-cropper-container').css({
            'max-width': jQuery('#yoimg-cropper-wrapper .attachments').width() + 'px',
            'max-height': jQuery('#yoimg-cropper-wrapper .attachments').height() + 'px'
        });
        jQuery('#yoimg-cropper').on('built.cropper', function() {
            if (doImmediateCrop) {
                yoimgCropImage();
            }
            adaptCropPreviewWidth();
        }).cropper({
            aspectRatio: yoimg_cropper_aspect_ratio,
            minWidth: yoimg_cropper_min_width,
            minHeight: yoimg_cropper_min_height,
            modal: true,
            data: cropperData,
            preview: '#yoimg-cropper-preview'
        });

        function positionReplaceRestoreWrapper() {
            jQuery('#yoimg-replace-restore-wrapper').appendTo('#yoimg-cropper-wrapper .cropper-container');
        }
        jQuery(window).on('built.cropper', function() {
            setTimeout(positionReplaceRestoreWrapper, 100);
        });

        if (wp.media) {
            jQuery('#yoimg-replace-img-btn').show().click(function() {
                if (yoimgMediaUploader) {
                    // TODO find "the backbone way" solution for dynamic title
                    jQuery('#yoimg-replace-media-uploader .media-frame-title h1').text(jQuery(this).attr('title'));
                    yoimgMediaUploader.open();
                    return;
                }
                var el = jQuery(this);
                yoimgMediaUploader = wp.media({
                    id: 'yoimg-replace-media-uploader',
                    title: el.attr('title'),
                    multiple: false,
                    button: {
                        text: el.attr('data-button-text')
                    },
                    library: {
                        type: 'image'
                    }
                });
                yoimgMediaUploader.on('select', function() {
                    attachment = yoimgMediaUploader.state().get('selection').first().toJSON();
                    var data = {
                        'action': 'yoimg_replace_image_for_size',
                        'image': yoimg_image_id,
                        'size': yoimg_image_size,
                        'replacement': attachment.id
                    };
                    jQuery.post(ajaxurl, data, function(response) {
                        var currEl = jQuery('#yoimg-cropper-wrapper .yoimg-thickbox-partial.active');
                        yoimgLoadCropThickbox(currEl.attr('href') + '&immediatecrop=1', true);
                    });

                });
                yoimgMediaUploader.open();
                jQuery('#yoimg-replace-media-uploader').parents('.media-modal.wp-core-ui').css('z-index', '17000002');
            });
        }
        jQuery('#yoimg-restore-img-btn').click(function() {
            var data = {
                'action': 'yoimg_restore_original_image_for_size',
                'image': yoimg_image_id,
                'size': yoimg_image_size
            };
            jQuery.post(ajaxurl, data, function(response) {
                var currEl = jQuery('#yoimg-cropper-wrapper .yoimg-thickbox-partial.active');
                yoimgLoadCropThickbox(currEl.attr('href') + '&immediatecrop=1', true);
            });
        });

        function initScrollingMediaFrameRouter() {
            var arrows, arrowL, arrowR, arrowWidth;
            var mediaFrameRouter = jQuery('#yoimg-cropper-wrapper .media-frame-router');
            var mediaRouter = mediaFrameRouter.find('.media-router');
            var mediaFrameRouterWidth = mediaFrameRouter.width();
            var mediaRouterWidth = 3;
            var currIndex = 0;
            var activeIndex = 0;
            var scrollLeft = 0;
            var mediaRouterAnchors = mediaRouter.find('a');
            mediaRouterAnchors.each(function(index) {
                var currEl = jQuery(this);
                mediaRouterWidth += parseInt(currEl.outerWidth(true), 10);
                if (currEl.hasClass('active')) {
                    activeIndex = index;
                }
            });
            mediaRouter.css('width', mediaRouterWidth + 'px');
            var hiddenWidth = mediaRouterWidth - mediaFrameRouterWidth;
            if (hiddenWidth > 0) {
                function _mediaFrameVisible(index) {
                    var minScrollLeft = arrowWidth + mediaFrameRouterWidth;
                    for (var i = 0; i <= index; i++) {
                        minScrollLeft -= parseInt(jQuery(mediaRouterAnchors[i]).outerWidth(true), 10);
                    }
                    return scrollLeft < minScrollLeft;
                }

                function _scrollMediaFrameTo(index, forced) {
                    if ((index != currIndex || forced === true) && index > -1 && index < mediaRouterAnchors.length) {
                        scrollLeft = arrowWidth;
                        for (var i = 0; i < index; i++) {
                            scrollLeft -= parseInt(jQuery(mediaRouterAnchors[i]).outerWidth(true), 10);
                        }
                        var doScroll = (scrollLeft * -1) - parseInt(jQuery(mediaRouterAnchors[index]).outerWidth(true), 10) - arrowWidth < hiddenWidth;
                        if (doScroll) {
                            if (forced === true) {
                                mediaRouter.css('left', scrollLeft + 'px');
                            } else {
                                mediaRouter.animate({
                                    left: scrollLeft + 'px'
                                }, 300);
                            }
                            currIndex = index;
                        }
                        if (currIndex > 0) {
                            arrowL.addClass('active');
                        } else {
                            arrowL.removeClass('active');
                        }
                        if (currIndex < mediaRouterAnchors.length - 1 && !_mediaFrameVisible(mediaRouterAnchors.length - 1)) {
                            arrowR.addClass('active');
                        } else {
                            arrowR.removeClass('active');
                        }
                    }
                }

                function _scrollMediaFrame(right, forced) {
                    _scrollMediaFrameTo(currIndex + (right ? 1 : -1), forced);
                }

                function scrollMediaFrameRight(forced) {
                    _scrollMediaFrame(true, forced);
                }

                function scrollMediaFrameLeft(forced) {
                    _scrollMediaFrame(false, forced);
                }
                arrows = mediaFrameRouter.find('.arrows');
                arrowR = arrows.filter('.arrow-r').unbind().click(scrollMediaFrameRight);
                arrowL = arrows.filter('.arrow-l').unbind().click(scrollMediaFrameLeft);
                arrowWidth = parseInt(arrowL.outerWidth(true), 10);
                arrows.css('background-color', jQuery('.media-modal-content').css('background-color')).show();
                _scrollMediaFrameTo(currIndex, true);
                while (!_mediaFrameVisible(activeIndex)) {
                    scrollMediaFrameRight(true);
                }
            }
        }
        initScrollingMediaFrameRouter();
        jQuery(window).resize(initScrollingMediaFrameRouter);
    }
}

function yoimgCancelCropImage() {
    jQuery('#yoimg-cropper-wrapper').remove();
}

function yoimgCropImage() {
    jQuery('#yoimg-cropper-wrapper .spinner').addClass('is-active');
    var data = jQuery('#yoimg-cropper').cropper('getData');
    data['action'] = 'yoimg_crop_image';
    data['post'] = yoimg_image_id;
    data['size'] = yoimg_image_size;
    data['quality'] = jQuery('#yoimg-cropper-quality').val();
    jQuery.post(ajaxurl, data, function(response) {
        // Use the existing filename and replace with the new filename
        jQuery('img[src*=\'' + response.previous_filename + '\']').each(function() {
            // Define the image object and current file name and path
            var img = jQuery(this);
            var imgSrc = img.attr('src');
            // Check if cachebusting is enabled or not.
            if (response.previous_filename === response.filename) {
                // If cachebusting isn't enabled then do frontend cachebust
                imgSrc = imgSrc + (imgSrc.indexOf('?') > -1 ? '&' : '?') + '_frontend_cachebust=' + Math.floor((Math.random() * 100) + 1);
            } else {
                // With cachebusting enabled we can use the new filename as the thumbnail
                // replacing the existing filename with the new one in the file path.
                imgSrc = imgSrc.replace(response.previous_filename, response.filename);
            }
            // Update the image tag src attribute to show the new image
            img.attr('src', imgSrc);
            if (img.parents('.yoimg-not-existing-crop').length) {
                img.parents('.yoimg-not-existing-crop').removeClass('yoimg-not-existing-crop').find('.message.error').hide();
            }
        });
        if (response.smaller) {
            jQuery('.message.yoimg-crop-smaller').show();
        } else {
            jQuery('.message.yoimg-crop-smaller').hide();
        }
        if (response.retina_smaller) {
            jQuery('.message.yoimg-crop-retina-smaller').show();
        } else {
            jQuery('.message.yoimg-crop-retina-smaller').hide();
        }
        jQuery('#yoimg-cropper-wrapper .spinner').removeClass('is-active');
        jQuery(window).resize();
    });
}

function yoimgGetUrlVars() {
    var vars = [],
        hash;
    var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
    for (var i = 0; i < hashes.length; i++) {
        hash = hashes[i].split('=');
        vars.push(hash[0]);
        vars[hash[0]] = hash[1];
    }
    return vars;
}

jQuery(document).ready(function($) {

    if ($('body.post-type-attachment').length) {
        var currPostId = yoimgGetUrlVars()['post'];
        var editImageBtn = $('#imgedit-open-btn-' + currPostId);
        if (editImageBtn.length) {
            var data = {
                'action': 'yoimg_get_edit_image_anchor',
                'post': currPostId,
                'classes': 'button'
            };
            jQuery.post(ajaxurl, data, function(response) {
                editImageBtn.after(response);
            });
        }
    }

    yoimgAddEditImageAnchors();

    $(document).on('click', 'a.yoimg-thickbox', function(e) {
        e.preventDefault();
        var currEl = $(this);
        yoimgLoadCropThickbox(currEl.attr('href'), currEl.hasClass('yoimg-thickbox-partial'));
        return false;
    });
    $(document).on('click', '#yoimg-cropper-bckgr', function(e) {
        e.preventDefault();
        yoimgCancelCropImage();
        return false;
    });
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) {
            yoimgCancelCropImage();
            return false;
        }
    });

    if ($('input#large_size_h').length) {
        var data = {
            'action': 'yoimg_get_custom_sizes_table_rows'
        };
        $.post(ajaxurl, data, function(response) {
            $('input#large_size_h').parents('table.form-table').after(response);
        });
    }

});
