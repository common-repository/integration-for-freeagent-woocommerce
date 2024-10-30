<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="notice notice-info wc-freeagent-notice wc-freeagent-welcome">
	<div class="wc-freeagent-welcome-body">
    <button type="button" class="notice-dismiss wc-freeagent-hide-notice" data-nonce="<?php echo wp_create_nonce( 'wc-freeagent-hide-notice' )?>" data-notice="welcome"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss', 'woocommerce' ); ?></span></button>
		<h2><?php esc_html_e('WooCommerce + FreeAgent PRO', 'wc-freeagent'); ?></h2>
		<p><?php esc_html_e("Thank you for installing this extension. If you don't know it already, theres a PRO version of this plugin, which offers more functions, for example automatic contact, estimate and invoice generation and a lot more! To use this extension, please check out the settings page and configure it first.", 'wc-freeagent'); ?></p>
		<p>
			<a class="button-primary" target="_blank" rel="noopener noreferrer" href="https://freeagent.visztpeter.me/"><?php esc_html_e( 'Buy the PRO version', 'woocommerce' ); ?></a>
			<a class="button-secondary" href="<?php echo esc_url(admin_url( wp_nonce_url('admin.php?page=wc-settings&tab=integration&section=wc_freeagent&welcome=1', 'wc-freeagent-hide-notice' ) )); ?>"><?php esc_html_e( 'Settings', 'wc-freeagent' ); ?></a>
		</p>
	</div>
</div>
