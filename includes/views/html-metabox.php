<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<?php if(!$this->auth->is_user_authenticated()): ?>

  <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=integration&section=wc_freeagent' ); ?>" class="wc-freeagent-metabox-settings">
    <span><?php esc_html_e('To generate an invoice, make sure that you authenticated yourself properly. You can do this on the settings page.','wc-freeagent'); ?></span>
    <span class="dashicons dashicons-arrow-right-alt2"></span>
  </a>

<?php else: ?>

  <div class="wc-freeagent-metabox-content" data-order="<?php echo absint($post->ID); ?>" data-nonce="<?php echo wp_create_nonce( "wc_freeagent_generate_invoice" ); ?>">
    <div class="wc-freeagent-metabox-messages wc-freeagent-metabox-messages-success" style="display:none;">
      <div class="wc-freeagent-metabox-messages-content">
        <ul></ul>
        <a href="#"><span class="dashicons dashicons-no-alt"></span></a>
      </div>
    </div>

    <div class="wc-freeagent-metabox-disabled <?php if($order->get_meta('_wc_freeagent_own')): ?>show<?php endif; ?>">
      <?php $note = $order->get_meta('_wc_freeagent_own'); ?>
      <p>
        <?php esc_html_e('Invoicing was turned off for this order, because:','wc-freeagent'); ?> <span><?php echo esc_html($note); ?></span>
      </p>
      <p>
        <a class="wc-freeagent-invoice-toggle on" href="#" data-nonce="<?php echo wp_create_nonce( "wc_freeagent_toggle_invoice" ); ?>" data-order="<?php echo absint($post->ID); ?>">
          <?php esc_html_e('Turn on invoicing','wc-freeagent'); ?>
        </a>
      </p>
    </div>

    <?php
    $has_invoice = $order->get_meta( '_wc_freeagent_invoice' );
		$contact_info = $this->get_local_contact($order);
    $document_types = array(
      'invoice' => esc_html__('Invoice','wc-freeagent'),
			'estimate' => esc_html__('Estimate','wc-freeagent'),
      'void' => esc_html__('Cancelled invoice','wc-freeagent'),
    );
    ?>

    <ul class="wc-freeagent-metabox-rows">

			<?php if($contact_info): ?>
				<li class="wc-freeagent-metabox-rows-invoice wc-freeagent-metabox-rows-invoice-contact show">
					<a target="_blank" href="<?php echo esc_url($contact_info['contact_link']); ?>">
            <span><?php esc_html_e('FreeAgent Contact'); ?></span>
            <strong><?php echo esc_html($contact_info['contact_id']); ?></strong>
          </a>
	      </li>
			<?php endif; ?>

      <?php foreach ($document_types as $document_type => $document_label): ?>
        <li class="wc-freeagent-metabox-rows-invoice wc-freeagent-metabox-invoices-<?php echo $document_type; ?> <?php if($order->get_meta('_wc_freeagent_'.$document_type)): ?>show<?php endif; ?>">
          <a target="_blank" href="<?php echo $this->generate_download_link($order, $document_type); ?>">
            <span><?php echo $document_label; ?></span>
            <strong><?php echo esc_html($order->get_meta('_wc_freeagent_'.$document_type)); ?></strong>
          </a>
        </li>
      <?php endforeach; ?>

      <li class="wc-freeagent-metabox-rows-data wc-freeagent-metabox-rows-data-complete <?php if($has_invoice): ?>show<?php endif; ?>">
        <div class="wc-freeagent-metabox-rows-data-inside">
          <span><?php esc_html_e('Paid on','wc-freeagent'); ?></span>
          <a href="#" data-trigger-value="<?php esc_attr_e('Mark as paid','wc-freeagent'); ?>" <?php if($order->get_meta('_wc_freeagent_completed')): ?>class="completed"<?php endif; ?>>
            <?php if(!$order->get_meta('_wc_freeagent_completed')): ?>
              <?php esc_html_e('Mark as paid','wc-freeagent'); ?>
            <?php else: ?>
							<?php echo date('Y-m-d',$order->get_meta('_wc_freeagent_completed')); ?>
            <?php endif; ?>
          </a>
        </div>
      </li>
      <li class="wc-freeagent-metabox-rows-data wc-freeagent-metabox-rows-data-void plugins <?php if($has_invoice): ?>show<?php endif; ?>" style="display:none!important">
        <div class="wc-freeagent-metabox-rows-data-inside">
          <a href="#" data-trigger-value="<?php esc_attr_e('Cancel invoice','wc-freeagent'); ?>" data-question="<?php esc_attr_e('Are you sure?','wc-freeagent'); ?>" class="delete"><?php esc_html_e('Cancel invoice','wc-freeagent'); ?></a>
        </div>
      </li>
    </ul>

    <?php if($this->get_option('auto_generate') == 'yes'): ?>
    <div class="wc-freeagent-metabox-auto-msg <?php if(!$order->get_meta('_wc_freeagent_own') && !$has_invoice): ?>show<?php endif; ?>">
      <div class="wc-freeagent-metabox-auto-msg-text">
        <p><?php esc_html_e( 'An invoice will be generated automatically if the order status changes to this:', 'wc-freeagent' ); ?> <strong><?php echo wc_get_order_status_name($this->get_option('auto_invoice_status', 'wc-completed')); ?></strong></p>
        <span class="dashicons dashicons-yes-alt"></span>
      </div>
    </div>
    <?php endif; ?>

    <div class="wc-freeagent-metabox-generate <?php if(!$order->get_meta('_wc_freeagent_own') && !$has_invoice): ?>show<?php endif; ?>">
      <ul class="wc-freeagent-metabox-generate-options" style="display:none">
        <li>
          <label><?php esc_html_e('Note', 'wc-freeagent'); ?></label>
          <textarea id="wc_freeagent_invoice_note"><?php echo esc_textarea($this->get_option('note')); ?></textarea>
        </li>
        <li>
          <label><?php esc_html_e('Payment deadline(days)', 'wc-freeagent'); ?></label>
          <input type="number" id="wc_freeagent_invoice_deadline" value="<?php echo absint($this->get_payment_method_deadline($order->get_payment_method())); ?>" />
        </li>
        <li>
          <label><?php esc_html_e('Completed date', 'wc-freeagent'); ?></label>
          <input type="text" class="date-picker" id="wc_freeagent_invoice_completed" maxlength="10" value="<?php echo date('Y-m-d'); ?>" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])">
        </li>
				<li class="wc-freeagent-metabox-generate-options-type">
          <label><?php esc_html_e('Document type', 'wc-freeagent'); ?></label>
          <label for="wc_freeagent_invoice_normal">
            <input type="radio" name="wc_freeagent_invoice_extra_type" id="wc_freeagent_invoice_normal" value="1" checked="checked" />
            <span><?php esc_html_e('Invoice', 'wc-freeagent'); ?></span>
          </label>
          <label for="wc_freeagent_invoice_estimate">
            <input type="radio" name="wc_freeagent_invoice_extra_type" id="wc_freeagent_invoice_estimate" value="1" />
            <span><?php esc_html_e('Estimate', 'wc-freeagent'); ?></span>
          </label>
        </li>
        <li>
          <a class="wc-freeagent-invoice-toggle off" href="#"><?php esc_html_e('Turn off invoicing','wc-freeagent'); ?></a>
        </li>
      </ul>

			<div class="wc-freeagent-metabox-generate-buttons">
				<a href="#" id="wc_freeagent_invoice_options"><?php esc_html_e('Options','wc-freeagent'); ?></a>
				<a href="#" id="wc_freeagent_invoice_generate" class="button button-primary" target="_blank" data-question="<?php esc_attr_e('Are you sure you want to create an invoice?','wc-freeagent'); ?>">
					<?php esc_html_e('Create invoice','wc-freeagent'); ?>
				</a>
			</div>
    </div>
  </div>

<?php endif; ?>
