<?php

/**
 * Plugin Name: Woody Crop
 * Plugin URI: https://github.com/woody-wordpress/woody-crop
 * Version: 1.1
 * Description: Optimized fork of the YoImages plugin to manage the image crop of the same ratio
 * Author: Raccourci Agency
 * Author URI: https://www.raccourci.fr
 * License: GPL2
 *
 * This program is GLP but; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of.
 */

if (!defined('ABSPATH')) {
    die('No script kiddies please!');
}

define('YOIMG_PATH', dirname(__FILE__));
require_once(YOIMG_PATH . '/vendor/sirulli/yoimages-commons/inc/init.php');
yoimg_register_module('yoimages-crop', YOIMG_PATH, true);
