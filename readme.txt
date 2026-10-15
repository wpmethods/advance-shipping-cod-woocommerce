=== AdvShipping WPM ===
Contributors: wpmethods
Tags: woocommerce, shipping, cod, advance payment, partial payment
Requires at least: 5.2
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect shipping, fixed or percentage advance payments and record the remaining balance collected on delivery.

== Description ==

AdvShipping WPM adds flexible payment plans to classic WooCommerce checkout for selected shipping methods.

* Collect shipping cost only, a fixed amount, a percentage, or combinations of these sources.
* Offer Full Payment with no delivery balance.
* Enable several choices and set a default. Rename checkout option labels, including translated labels.
* Calculate percentages from the discounted full order total or exclude shipping and shipping tax.
* Limit advance payments to selected enabled gateways, including available mobile-money and manual gateways.
* Optionally offer full-payment Cash on Delivery alongside advance payment methods.
* Show order value, payable amount and delivery balance in a checkout summary.
* Position the summary above order total, below subtotal, above payment methods, below the payment title or below payment methods.
* Customize colors, border, radius, padding, margin and checkout CSS, with an admin live preview.
* Edit checkout, order and email messages using amount placeholders.
* Record full or installment delivery collections with collection method, reference, staff member and timestamp.
* Correct collection entries with an append-only reversal history.
* Show outstanding balances as badges immediately after order status in classic and HPOS order lists.
* Use full order value in native WooCommerce Analytics without treating collections as additional sales.

The plugin does not process or verify mobile-money transactions itself. It uses your existing WooCommerce gateways. Manual payments remain awaiting verification while on hold. Verify the advance through the gateway or move the order to processing after checking the transfer.

= Checkout compatibility =

Requires classic checkout using the [woocommerce_checkout] shortcode. Checkout Blocks are not supported and are declared incompatible. Compatible CartFlows and theme checkout templates must retain standard WooCommerce hooks. HPOS order storage is supported.

Gateways that read WooCommerce numeric cart totals in payment fields, including legacy cart->total access, receive the selected advance while fields render. Order payment totals remain the agreed payment amount through verification callbacks and retries. Gateways that rebuild totals from product lines or reject partial amounts may require a specific adapter. Verify your gateway in its sandbox before accepting live payments.

= Payment calculations =

Shipping includes shipping tax. Percentages use the total after discounts, including tax and WooCommerce fees, with an option to exclude shipping and shipping tax. Combined sources are added and capped at the full order total. Currency precision follows WooCommerce settings. A zero-advance option requires an available COD gateway. Full Payment includes shipping, tax and fees and leaves no delivery balance.

= Due collection and accounting =

The compact Payments panel appears below Customer in the order General section. Expand Record collection to enter money already received, a collection method and an optional reference. Partial collection reduces the remaining balance. Reverse entry records a correction and restores that amount to the balance; it does not initiate a refund.

Advance verification is required before delivery collections can be recorded. New collections are disabled for cancelled, failed or refunded orders. Duplicate request IDs and a per-order database advisory lock protect collection writes. Users need WooCommerce management and order-edit permission.

Completing an order does not collect its balance. Recording a collection does not charge a customer, complete delivery, change a gateway transaction ID or create another sale.

The stored order payment total represents the agreed gateway payment. Full product, shipping and tax lines are retained, and the full sale value is stored separately. Native WooCommerce Analytics use the full sale value less shipping and tax for net revenue, following WooCommerce's normal status exclusions. Cash receipts and sales revenue are different measures; review receipts in the collection ledger.

Legacy reports, exports and third-party dashboards that read the stored payment total directly may still show only the advance. This plugin does not allocate tax between installments or initiate collection/refund transactions. Refunds need manual reconciliation against gateway payments and offline receipts. Do not automatically refund full product lines through a gateway that collected only an advance.

= Privacy and external services =

The plugin does not send data to an external service, add tracking or load remote assets. Support links open only when clicked. Payment gateways are separate plugins with their own services and privacy practices.

Collection references, amounts, methods, recording times and staff IDs are stored in WooCommerce order metadata for bookkeeping. Access is controlled by WooCommerce order permissions. Removing the plugin deletes its settings but preserves order payment and collection records. Review order retention under your store's privacy policy.

= Gateway-based payment options =

Enable Payment gateway plans > Show payment options and payable cost with allowed gateways to activate plans by the selected payment method instead of shipping methods. Shipping selection is not required. The summary follows your configured checkout position and appears when an allowed online gateway is selected. Gateway names display the payable amount. Other online gateways remain available and collect the full order total. Leave the allowed gateway selection empty to support all online gateways. Turn the option off to restore shipping-based activation.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the advshipping-wpm folder to wp-content/plugins, or upload its ZIP through Plugins > Add New.
3. Activate AdvShipping WPM.
4. Configure shipping zones and payment gateways in WooCommerce.
5. Open WooCommerce > Advance Shipping COD > Payment Settings.
6. Select shipping methods, enable payment choices, choose a default, and enter fixed or percentage values.
7. Optionally rename choices using Checkout label. Leave blank for the original label; {percentage}% inserts the configured percentage.
8. Select advance gateways, checkout position and the optional full-payment COD setting. Save Settings.
9. Customize messages in the sidebar. Use {advance_amount}, {shipping_amount} and {due_amount} where indicated.
10. Use Checkout Design for appearance settings and custom CSS. Scope CSS to #wcasc-notice-wrap to target the plugin summary.

== Frequently Asked Questions ==

= Does this plugin include bKash or Nagad gateways? =

No. Install and configure a compatible WooCommerce gateway separately. Advance calculations can be used with enabled online or manual gateways; provider verification remains the gateway's responsibility.

= Can customers choose Full Payment or COD? =

Enable Full Payment in Payment options. Enable Allow full payment on delivery to offer COD alongside advance gateways. COD must also be enabled and available in WooCommerce.

= Does an on-hold order mean its advance was received? =

No. The advance remains awaiting confirmation. Verify the payment before marking the order processing or completed.

= How do I record money received on delivery? =

Open the order, expand Record collection under Payments, and enter the amount, method and optional receipt/reference. The history records the entry, and the due balance updates. Use Reverse entry for a correction.

= How do I repair historical Analytics totals? =

Use Refresh order analytics in the order's Analytics & help section. For multiple historical orders, reimport affected orders through WooCommerce Analytics > Settings > Import historical data.

= Does uninstall remove financial records? =

No. Uninstall removes plugin settings but retains order payment and collection metadata. Deactivation does not delete settings.

== Changelog ==

= 1.0.0 =
* Initial WordPress.org release.
* Added optional gateway-based payment plans and payable amounts beside gateway names.
* Added configurable advance payment plans and Full Payment.
* Added optional COD, renamed checkout labels and checkout appearance controls.
* Added installment collection records, correction history and due badges.
* Added full-value native WooCommerce Analytics and historical order refresh.

== Upgrade Notice ==

= 1.0.0 =
Initial WordPress.org release. Existing local settings and order records are preserved through their original option and metadata keys.
