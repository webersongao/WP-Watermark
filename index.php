<?php
/**
 * Plugin Name: WP 轻水印
 * Plugin URI: https://www.lezaiyun.com/wpwatermark.html
 * Description: 全网首个实现WordPress固定九宫格、随机位置、满铺水印的插件之一，方便每一个站长实现不同水印效果，加强图片防盗能力
 * Author: 老蒋和他的小伙伴
 * Version: 4.2
 * Author URI: https://www.lezaiyun.com
 */
require_once 'WMFunctions.php';


define('WPWaterMark_BASEFOLDER', plugin_basename(dirname(__FILE__)));
define('WPWaterMark_INDEXFILE', WPWaterMark_BASEFOLDER.'/index.php');
define('WPWaterMark_VERSION', 3.2);
register_activation_hook( __FILE__, 'wpwatermark_set_options' );
add_filter( 'wp_handle_upload', 'wp_handle_upload_wpwatermark' );
add_action( 'admin_menu', 'wpwatermark_add_setting_page' );
add_filter( 'plugin_action_links', 'wpwatermark_plugin_action_links', 10, 2 );
add_action( 'admin_enqueue_scripts', 'wpwatermark_admin_enqueue_scripts' );
function wpwatermark_set_options() {
	$options = array(
		'version' => WPWaterMark_VERSION,
		'watermark_type' => "text_watermark",
		'watermark_mark_image' => '',
		'text_content' => 'WPWaterMark',
		'text_font' => "simhei.ttf",
		'text_angle' => '0',
		'text_size' => "18",
		'text_color' => "#790000",
		'watermark_position' => "6",
		'watermark_margin' => '80',
		'watermark_diaphaneity' => '100',
		'watermark_spacing' => '30',
		'watermark_min_width' => '250',
		'watermark_min_height' => '250',
	);
	$wpwatermark_options = get_option('wpwatermark_options');
	if(!$wpwatermark_options){
		add_option('wpwatermark_options', $options, '', 'yes');
	};

}

function wp_handle_upload_wpwatermark( $upload ) {
    $mime_types = get_allowed_mime_types();
    $mime_types = get_allowed_mime_types();
    $image_mime_types = [$mime_types['jpg|jpeg|jpe'], $mime_types['png']];

    // 检查上传文件类型是否为图片
    if ( in_array( $upload['type'], $image_mime_types ) ) {
        $wpwatermark_options = get_option('wpwatermark_options');
        list($width, $height, $type) = getimagesize($upload['file']);
        
        // 判断是否符合最小水印尺寸
        if ($width < (int) $wpwatermark_options['watermark_min_width'] || $height < (int) $wpwatermark_options['watermark_min_height']) {
            return $upload;
        }
        $webp_convert = true;
        $webp_file = $upload['file'];
        $new_im_url = generate_new_image_url($upload['file']);
        // 根据水印类型处理
        if ($wpwatermark_options['watermark_type'] === 'text_watermark') {
            $webp_file = wpWaterMarkCreateWordsWatermark(
                $upload['file'], $new_im_url,
                $wpwatermark_options['text_content'],
                $wpwatermark_options['watermark_spacing'],
                $wpwatermark_options['text_size'],
                $wpwatermark_options['text_color'],
                $wpwatermark_options['watermark_position'],
                $wpwatermark_options['text_font'],
                $wpwatermark_options['text_angle'],
                $wpwatermark_options['watermark_margin'],
				$webp_convert
            );
        } elseif ($wpwatermark_options['watermark_type'] === 'image_watermark') {
            $webp_file = wpWaterMarkCreateImageWatermark(
                $upload['file'], $wpwatermark_options['watermark_mark_image'],
                $new_im_url, $wpwatermark_options['watermark_position'],
                $wpwatermark_options['watermark_diaphaneity'],
                $wpwatermark_options['watermark_spacing'],
                $wpwatermark_options['watermark_margin'],
				$webp_convert
            );
        }

        // 如果图片路径被修改为 .webp，更新上传文件信息
        if ($webp_convert && $webp_file && $webp_file !== $upload['file']) {
            @unlink($upload['file']);
            $upload['file'] = $webp_file;
            $upload['type'] = 'image/webp';
        }
    }

    return $upload;
}

function wpwatermark_add_setting_page() {
	if (!function_exists('wpwatermark_setting_page')) {
		require_once 'setting_page.php';
	}
	add_management_page('轻水印设置', '轻水印设置', 'manage_options', __FILE__, 'wpwatermark_setting_page');
}

function wpwatermark_plugin_action_links($links, $file) {
	if ($file == plugin_basename(dirname(__FILE__) . '/index.php')) {
		$links[] = '<a href="admin.php?page=' . WPWaterMark_BASEFOLDER . '/index.php">设置</a>';
	}
	return $links;
}

function wpwatermark_admin_enqueue_scripts() {
	wp_register_script( 'jqueryColorPicker', plugins_url( 'js/jquery.colorpicker.js', __FILE__ ), array('jquery') );
	wp_enqueue_script( 'jqueryColorPicker' );
}
