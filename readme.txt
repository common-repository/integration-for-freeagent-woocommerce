=== Integration for FreeAgent & WooCommerce ===
Contributors: passatgt
Tags: freeagent, invoice, estimate
Requires at least: 5.0
Tested up to: 5.2.2
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Connect FreeAgent with your WooCommerce store.

== Description ==

> **PRO version**
> The PRO version is available for $59. You can buy a license key here: [https://freeagent.visztpeter.me/](https://freeagent.visztpeter.me/)
> The license key can be activated on one website and offers 1 year email support too for customization and setup.
> With your purchase you can support the development of this extension even if you don't need the PRO version's features. Thanks!

= Functions =

* Creates and updates FreeAgent contacts for WooCommerce customers
* Create and update FreeAgent invoices for every WooCommerce order
* Mark invoices as paid by creating bank transaction on FreeAgent
* Sync contacts automatically when a new order is created _PRO_
* Create invoices automatically based on order status _PRO_
* Create estimates automatically when a new order is created _PRO_
* Attached invoices and estimates to WooCommerce emails _PRO_
* Download or print invoices in bulk _PRO_
* Close orders automatically if an invoice is marked as paid in FreeAgent _PRO_
* Unit types can be set for each product
* Works with multiply currencies: the exchange rate is sent to FreeAgent when you create an invoice
* You can change the language of the invoices
* Add custom invoice notes and create unique invoice numbers
* Disable invoicing for specific orders
* Supports sandbox mode for testing
* Include the invoices for customers on the My Account page

If you have any feature requests, please let me know in the forums.

= Important to note =
* Invoice and estimate PDF files are downloaded to your site, stored in wp-content/uploads/wc_freeagent with a unique file name
* You can set payment deadlines for each payment methods
* Works with coupons, shipping items and custom fees

= Usage =
You can find a detailed documentation [here](https://freeagent.visztpeter.me/).
After you installed the extension, go to WooCommerce / Settings / Integration. Authenticate yourself based on the instructions visible on the page.
Once authenticated, you can check out the plugin settings on the same page.
On every order details page, you can find a new FreeAgent box on the right side. You can use this box to create contacts, invoices and estimates.
If you enabled the PRO version, you can setup the automatization features: create a contact when an order is created, create an invoice when an order is complated and more.

**IMPORTANT:** This plugin is not affiliated with FreeAgent in any way, so its not an official extension. If you have any issues, contact me on [freeagent.visztpeter.me](https://freeagent.visztpeter.me/).

= For developers =

You can customize the data that is sent to the FreeAgent API using filters: `wc_freeagent_invoice_data`, `wc_freeagent_estimate_data`, `wc_freeagent_contact_data`. The first parameter is the data that is being sent, the second is the related order.

== Installation ==

1. Download and install the extension
2. Go to WooCommerce / Integrations and enter the required authentication details
3. It works

== Frequently Asked Questions ==

= Whats the difference between the PRO and the free version? =

You can access more features using the PRO version. You can read more about these features [here](https://freeagent.visztpeter.me/). The most important one is related to automatization: you can create invoices and estimates based on the order status change.

= How can i test the invoice generation? =

Turn on the Sandbox mode in the settings and create an account over at [FreeAgent Sandbox](https://signup.sandbox.freeagent.com/signup).

== Screenshots ==

1. Settings screen(WooCommerce / Integrations / FreeAgent)
2. FreeAgent box on the order details page

== Changelog ==

= 1.0 =
* First release of this extension
