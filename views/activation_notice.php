<?php if ( ! defined( 'NEXTGEN_TO_WIZIAPP_PATH' ) ) exit( "Can not be called directly." ); ?>

<div id="nextgen_to_wiziapp_message" class="updated">
	<?php
		if ( $current_situation === self::nextgen_ON_and_wiziapp_ON ) {
		?>
		<div>
			<p>
				Please click here To complete the NextGen to WiziApp plugin integration with the Wiziapp plugin
			</p>
			<div id="nextgen_to_wiziapp_scan">
				<input class="button" type="button" value="Complete integration">
			</div>
			<div id="nextgen_to_wiziapp_remove">
				<input class="button" type="button" value="Hide this message">
			</div>
		</div>
		<div id="nw_progress_bar_container">
			<div style="clear: both;"></div>
			<div id="nw_progress_bar"></div>
			<div id="nw_progress_bar_bg" style="background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/cms/progress_bar_bg.png) no-repeat;"></div>
		</div>
		<div id="nextgen_to_wiziapp_warning">
			<input class="button" type="button" value="Retry">
		</div>
		<div style="clear: both;"></div>
		<?php
		} else {
		?>
		<p>
			WiziApp to NextGen plugin integrates the WiziApp and the NextGen plugins and both of these plugins needs to be installed in order for its functionality to be available.
		</p>
		<?php
			$install_links_params = array(
				self::nextgen_OFF_and_wiziapp_OFF => array(
					array( 'plugin_name' => 'Nextgen', 'directory_name' => 'nextgen-gallery', ),
					array( 'plugin_name' => 'Wiziapp', 'directory_name' => 'wiziapp-create-your-own-native-iphone-app', ),
				),
				self::nextgen_OFF_and_wiziapp_ON => array(
					array( 'plugin_name' => 'Nextgen', 'directory_name' => 'nextgen-gallery', ),
				),
				self::nextgen_ON_and_wiziapp_OFF => array(
					array( 'plugin_name' => 'Wiziapp', 'directory_name' => 'wiziapp-create-your-own-native-iphone-app', ),
				),
			);

			foreach ( $install_links_params[$current_situation] as $install_link ) {
			?>
			<p>
				Please click
				<a href="<?php echo wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin='.$install_link['directory_name'] ), 'install-plugin_'.$install_link['directory_name'] ); ?>">here</a>
				to install the <?php echo $install_link['plugin_name']; ?> plugin.
			</p>
			<?php
			}
		}
	?>
</div>