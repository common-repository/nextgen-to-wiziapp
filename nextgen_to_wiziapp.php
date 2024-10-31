<?php

/**
* Plugin Name: NextGEN to WiziApp - Integrate image galleries & albums to your WiziApp powered iPhone App
* Description: Integrate image galleries & albums to your wiziapp powered iphone app.
* Author: mayerz
* Version: 1.0.4
*/

class Nextgen_To_Wiziapp {

	// Possible Situations with Nextget plugin and Wiziapp plugin installed or uninstalled
	// at the time of Nextgen_To_Wiziapp plugin installation
	const nextgen_OFF_and_wiziapp_OFF = 0;
	const nextgen_OFF_and_wiziapp_ON  = 1;
	const nextgen_ON_and_wiziapp_OFF  = 2;
	const nextgen_ON_and_wiziapp_ON   = 3;

	private $_option_name = 'nextgen_to_wiziapp_setting';
	private $_option_value;
	private $_is_deactivation = FALSE;
	private $_maximum_scaned = array('post' => 50, 'page' => 20, 'step' => 3,);

	public function __construct() {
		$this->_option_value = get_option( $this->_option_name );

		register_activation_hook( __FILE__, array( &$this, 'activate') );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate') );

		add_filter('wiziapp_before_the_content', array(&$this, 'wiziapp_nextgenImagebrowserFilter'), 1);

		if (is_admin() && ! $this->_is_deactivation) {
			if ( strpos($_SERVER['REQUEST_URI'], 'wp-admin/plugins.php') !== FALSE ) {
				add_action( 'admin_head', array(&$this, 'styles_javascripts'), 1000 );
				add_action( 'admin_notices', array(&$this, 'activation_notice') );
			}

			add_action( 'wp_ajax_nextgen_to_wiziapp_scanning', array(&$this, 'nextgen_to_wiziapp_scanning') );
			add_action( 'wp_ajax_nextgen_to_wiziapp_message', array(&$this, 'remove_activation_message') );
		}
	}

	public function activate() {
		try {
			$this->_option_value = array(
				'is_activated' => 0,
				'post' 		   => 0,
				'page' 		   => 0,
			);

			delete_option( $this->_option_name );
			if ( ! add_option( $this->_option_name, $this->_option_value, '', 'no') ) {
				throw new Exception('add the nextgen_to_wiziapp option problem');
			}

			if ( $this->_clarify_situation() !== self::nextgen_OFF_and_wiziapp_ON || $this->_clarify_situation() !== self::nextgen_ON_and_wiziapp_ON) {
				return;
			}
			global $wpdb;
			if ( $wpdb->query("DELETE FROM " . $wpdb->postmeta . " WHERE `meta_key` = 'wiziapp_processed'") === FALSE ) {
				throw new Exception($wpdb->last_error);
			}
			if ( ! $wpdb->query( "TRUNCATE TABLE " . $wpdb->prefix . WiziappDB::getInstance()->get_media_table() ) ) {
				if ( $wpdb->query( "DELETE FROM " . $wpdb->prefix . WiziappDB::getInstance()->get_media_table() === FALSE ) ) {
					throw new Exception($wpdb->last_error);
				}
			}
		} catch (Exception $e) {
			$message = 'Activation failed, ' . $e->getMessage() . ', try again.';
			echo
			'<script type="text/javascript">alert("' . $message . '")</script>' . PHP_EOL .
			$message;
			exit;
		}
	}

	public function deactivate() {
		$this->_is_deactivation = TRUE;

		if ( get_option( $this->_option_name ) ) {
			if ( ! delete_option( $this->_option_name ) ) {
				$message = 'Deactivation failed, can not to delete the Option, try again.';
				echo
				'<script type="text/javascript">alert("' . $message . '")</script>' . PHP_EOL .
				$message;
				exit;
			}
		}
	}

	public function remove_activation_message() {
		$this->_option_value['is_activated'] = 1;
		update_option( $this->_option_name, $this->_option_value );
	}

	public function nextgen_to_wiziapp_scanning() {
		$response = array(
			'success' => 1,
			'error_message' => '',
			'percent' => 0,
		);

		try {
			if ( $this->_clarify_situation() !== self::nextgen_ON_and_wiziapp_ON ) {
				throw new Exception('Server side script error.');
			}

			global $wpdb;
			$rows_affected = 0;
			foreach (array('post', 'page',) as $item) {
				if ($this->_option_value[$item] > $this->_maximum_scaned[$item]) {
					continue;
				}

				$rows_affected = $wpdb->query(
					"SELECT `ID`, `post_type` FROM " . $wpdb->posts . ' ' .
					"WHERE `ID` NOT IN (SELECT `post_id` FROM " . $wpdb->postmeta . " WHERE `meta_key` = 'wiziapp_processed' AND `meta_value` = '1') " .
					"AND `post_status` = 'publish' " .
					"AND `post_type` = '" . $item . "' " .
					"ORDER BY `post_date` DESC " .
					"LIMIT 0, " . $this->_maximum_scaned['step']
				);
				if ( $rows_affected === FALSE ) {
					throw new Exception($wpdb->last_error);
				} elseif ( $rows_affected === 0 ) {
					continue;
				}
				break;
			}

			if ($rows_affected > 0) {
				$wiziapp_content_events = new WiziappContentEvents();
				foreach($wpdb->last_result as $item_object) {
					$wiziapp_content_events->savePost($item_object);
				}
				$this->_option_value[$item] += $rows_affected;

				$response['percent'] = round( ( ($this->_option_value['post'] + $this->_option_value['page']) * 100 ) / ($this->_maximum_scaned['post'] + $this->_maximum_scaned['page']) );
				if ($response['percent'] > 100) {
					$response['percent'] = 100;
				}
			} else {
				$response['percent'] = 100;
			}

			if ($response['percent'] === 100) {
				$this->_option_value['is_activated'] = 1;
			}

			if ( get_option( $this->_option_name ) != $this->_option_value ) {
				if ( ! update_option( $this->_option_name, $this->_option_value ) ) {
					throw new Exception('update_option error.');
				}
			}
		} catch (Exception $e) {
			$response['success'] = 0;
			$response['error_message'] = htmlspecialchars($e->getMessage());
		}

		echo 'nextgen_to_wiziapp_start' . json_encode($response) . 'nextgen_to_wiziapp_end';
		exit;
	}

	public function styles_javascripts() {
		if ( ( $this->_clarify_situation() !== self::nextgen_ON_and_wiziapp_ON ) || $this->_option_value['is_activated'] ) {
			return;
		}

		$plugindir = plugins_url( dirname( plugin_basename(__FILE__) ) );
		require NEXTGEN_TO_WIZIAPP_PATH . 'views' . DIRECTORY_SEPARATOR . 'styles_javascripts.php';
	}

	public function activation_notice() {
		if ( $this->_option_value['is_activated']) {
			return;
		}

		$current_situation = $this->_clarify_situation();

		require NEXTGEN_TO_WIZIAPP_PATH . 'views' . DIRECTORY_SEPARATOR . 'activation_notice.php';
	}

	public function wiziapp_nextgenImagebrowserFilter($content) {
		if ( $this->_clarify_situation() !== self::nextgen_ON_and_wiziapp_ON ) {
			return $content;
		}

		global $nggdb;

		$matches = array();
		// Find all nextgen singlepic
		preg_match_all('/\[\s*singlepic\s*id=([a-zA-Z0-9]*)(.*)\]/', $content, $matches);
		// Extract image
		if ( ! empty($matches[1])) {
			foreach($matches[1] as $match_key => $image_id) {
				$out = '';
				$image = $nggdb->find_image($image_id);
				$params = trim($matches[2][$match_key]);
				if ( ! empty($params)) {
					$width = array();
					$height = array();
					$float = array();
					preg_match('/w=(\d*)/', $params, $width);
					preg_match('/h=(\d*)/', $params, $height);
					preg_match('/float=(\D*)/', $params, $float);
					$width = (empty($width) ? 'auto' : $width[1]);
					$height = (empty($height) ? 'auto' : $height[1]);
					$float = (empty($float) ? 'none' : $float[1]);
				}
				if ($float == 'center') {
					$out = "<a href=\"{$image->imageURL}\"><img src=\"{$image->imageURL}\" width=\"$width\" height=\"$height\" alt=\"NextGen Image\" class=\"aligncenter\" /></a>";
				} else {
					$out = "<a href=\"{$image->imageURL}\"><img src=\"{$image->imageURL}\" width=\"$width\" height=\"$height\" alt=\"NextGen Image\" style=\"float: {$float};\" /></a>";
				}
				$content = str_replace($matches[0][$match_key], $out, $content);
			}
		}

		// Find all nextgen imagebrowsers
		$patterns = array(
			'/\[\s*imagebrowser\s*id=([a-zA-Z0-9]*)\]/'
			,'/\[\s*slideshow\s*id=([a-zA-Z0-9]*)\]/'
			,'/\[\s*nggallery\s*id=([a-zA-Z0-9]*)\]/'
		);
		foreach ($patterns as $pattern) {
			$matches = array();
			preg_match_all($pattern, $content, $matches);
			// Extract images
			if ( ! empty($matches[1])) {
				foreach($matches[1] as $match_key => $gallery_id) {
					$out = '';
					$images = $this->_getImagesFromNextgenGallery($gallery_id);
					foreach ($images as $image){
						// We dont resize images anymore!!! Only thumbnails, on demand.
						//$image = new WiziappImageHandler($image->imageURL);
						//$url_to_resized_image = $image->getResizedImageUrl($image->imageURL, wiziapp_getMultiImageWidthLimit(), 0, 'resize');
						//$width = $image->getNewWidth();
						//$height = $image->getNewHeight();
						//$size = wiziapp_getImageSize('multi_image');
						//$urlToDeviceSizedImage = $image->getResizedImageUrl($image->imageURL, $size['width'], $size['height'], 'resize');
						$out .= "<a href=\"{$image->imageURL}\" class=\"wiziapp_gallery wiziapp_nextgen_plugin\"><img src=\"{$image->imageURL}\" data-wiziapp-nextgen-gallery-id=\"{$gallery_id}\" alt=\"NextGen Image\" /></a>";
					}
					$content = str_replace($matches[0][$match_key], $out, $content);
				}
			}
		}

		$matches = array();
		preg_match_all('/\[\s*album\s*id=([a-zA-Z0-9]*)\s*.*\]/', $content, $matches);
		// Extract images
		if ( ! empty($matches[1])) {
			foreach($matches[1] as $match_key => $album_id) {
				$out = '';
				$galleries_id = $nggdb->find_album($album_id)->gallery_ids;
				if ( ! empty($galleries_id)) {
					foreach ($galleries_id as $gallery_id) {
						$images = $this->_getImagesFromNextgenGallery($gallery_id);
						if ( ! empty($images)) {
							foreach ($images as $image) {
								// We dont resize images anymore!!! Only thumbnails, on demand.
								//$image = new WiziappImageHandler($image->imageURL);

								//$size = wiziapp_getImageSize('full_image');
								//$url_to_full_sized_image = $image->getResizedImageUrl($image->imageURL, $size['width'], $size['height'], 'resize');

								//$size = wiziapp_getImageSize('multi_image');
								//$urlToMultiSizedImage = $image->getResizedImageUrl($image->imageURL, $size['width'], $size['height'], 'resize');

								//$width = $image->getNewWidth();
								//$height = $image->getNewHeight();

								$out .= "<a href=\"{$image->imageURL}\" class=\"wiziapp_gallery wiziapp_nextgen_plugin\">" .
								"<img src=\"{$image->imageURL}\" data-wiziapp-nextgen-album-id=\"$album_id\" alt=\"NextGen Image\" /></a>";
							}
						}
					}
					$content = str_replace($matches[0][$match_key], $out, $content);
				}
			}
		}

		return $content;
	}

	private function _getImagesFromNextgenGallery($gallery_id, $album = FALSE){
		WiziappLog::getInstance()->write('DEBUG', "The NextGet gallery is {$gallery_id}", 'Nextgen_To_Wiziapp._getImagesFromNextgenGallery');
		global $nggdb;
		$images = array();
		$ngImages = $nggdb->get_gallery($gallery_id);

		foreach($ngImages as $image){
			/** We dont resize images anymore!!! Only thumbnails, on demand.
			$realImage = new WiziappImageHandler(htmlspecialchars_decode($image->imageURL));
			$url_to_resized_image = $realImage->getResizedImageUrl($image->imageURL, wiziapp_getMultiImageWidthLimit(), 0, 'resize'); */

			// Get the post id
			if (!$album){
				$metadata = WiziappDB::getInstance()->get_media_metadata_equal('image', 'nextgen-gallery-id', $gallery_id);
			} else {
				$metadata = WiziappDB::getInstance()->get_media_metadata_equal('image', 'nextgen-album-id', $album->id);
			}
			if ( $metadata != FALSE ) {
				// $postId = $metadata[key($metadata)]['content_id'];
				$index = array_pop(array_keys($metadata));
				$postId = $metadata[$index]['content_id'];
			}

			if ($image->post_id == 0){
				// Get the page
				if ($image->pageid != 0){
					$image->relatedPost = $image->pageid;
				} else {
					if ($album != FALSE && $album->pageid != 0){
						$image->relatedPost = $album->pageid;
					}
				}
			} else {
				$image->relatedPost = $image->post_id;
			}

			if (!isset($image->relatedPost) && isset($postId)) {
				$image->relatedPost = $postId;
			}
			$images[] = $image;
		}
		return $images;
	}

	private function _clarify_situation() {
		$situation_to_binary = sprintf( '%s%s', (int) class_exists('nggLoader'), (int) ( defined('WP_WIZIAPP_BASE') && class_exists('WiziappLoader') ) );
		$situation_to_integer = bindec($situation_to_binary);
		return $situation_to_integer;
	}

}

// Define NextGEN to Wiziapp plugin root directory path
if ( ! defined( 'NEXTGEN_TO_WIZIAPP_PATH' ) ) {
	define( 'NEXTGEN_TO_WIZIAPP_PATH', plugin_dir_path( __FILE__ ) );
}

// Start of the Plugin work
$nextgen_to_wiziapp = new Nextgen_To_Wiziapp;