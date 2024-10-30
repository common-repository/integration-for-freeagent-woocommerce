<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="notice notice-error wc-freeagent-notice wc-freeagent-welcome">
	<div class="wc-freeagent-welcome-body">
    <button type="button" class="notice-dismiss wc-freeagent-hide-notice" data-nonce="<?php echo wp_create_nonce( 'wc-freeagent-hide-notice' )?>" data-notice="error"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss', 'woocommerce' ); ?></span></button>
		<h2><?php esc_html_e('Invoice generation failed', 'wc-freeagent'); ?></h2>
		<p><?php printf( esc_html__( 'Unable to create an order automatically for order #%s. You can find the full error message in the order notes.', 'wc-freeagent' ), esc_html($order_number) ); ?></p>
		<p>
			<a class="button-secondary" href="<?php echo esc_url($order_link); ?>"><?php esc_html_e( 'Order details', 'wc-freeagent' ); ?></a>
		</p>
	</div>
</div>
