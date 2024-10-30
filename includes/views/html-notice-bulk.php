<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="notice notice-info wc-freeagent-notice wc-freeagent-bulk-actions wc-freeagent-print">
  <?php if($type == 'print'): ?>
		<p>
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#FF6630" d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/><path d="M0 0h24v24H0z" fill="none"/></svg>
      <span><?php echo sprintf( esc_html__( '%s invoices marked to print', 'wc-freeagent' ), $print_count); ?>.</span>
      <a href="<?php echo $pdf_file_url; ?>" id="wc-freeagent-bulk-print" data-pdf="<?php echo $pdf_file_url; ?>">Print now</a>
    </p>
  <?php else: ?>
    <p>
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path fill="#FF6630" d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM17 13l-5 5-5-5h3V9h4v4h3z"/></svg>
      <span><?php echo sprintf( esc_html__( '%s invoices marked to download', 'wc-freeagent' ), $print_count); ?>.</span>
      <a href="<?php echo $pdf_file_url; ?>" id="wc-freeagent-bulk-download" download data-pdf="<?php echo $pdf_file_url; ?>">Download now</a>
    </p>
  <?php endif; ?>
</div>
