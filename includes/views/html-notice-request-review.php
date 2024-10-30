<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="notice notice-info wc-freeagent-notice wc-freeagent-request-review">
	<p>⭐️ <?php printf( __( 'Hey, I noticed you created a few invoices - that’s awesome! Could you please do me a BIG favor and give it a 5-star rating on WordPress to help us spread the word and boost our motivation?', 'wc-freeagent' ), '<strong>', '</strong>' ); ?></p>
	<p>
		<a class="button-primary" target="_blank" rel="noopener noreferrer" href="https://wordpress.org/support/plugin/integration-for-freeagent-woocommerce/reviews/?filter=5#new-post"><?php esc_html_e( 'Ok, you deserve it', 'wc-freeagent' ); ?></a>
		<a class="button-secondary wc-freeagent-hide-notice remind-later" data-nonce="<?php echo wp_create_nonce( 'wc-freeagent-hide-notice' )?>" data-notice="request_review" href="#"><?php esc_html_e( 'Remind me later', 'wc-freeagent' ); ?></a>
		<a class="button-secondary wc-freeagent-hide-notice" data-nonce="<?php echo wp_create_nonce( 'wc-freeagent-hide-notice' )?>" data-notice="request_review" href="#"><?php esc_html_e( 'No, thanks', 'wc-freeagent' ); ?></a>
	</p>
</div>
