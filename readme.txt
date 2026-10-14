=== Advance Shipping COD for WooCommerce ===
Contributors: wpmethods
Tags: woocommerce, shipping, cod, advance payment, partial payment
Requires at least: 5.2
Requires PHP: 7.4
Stable tag: 1.4.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect an advance payment for selected shipping methods and track the balance due on delivery.

== Description ==

Configure shipping-only, fixed, percentage, shipping + fixed, shipping + percentage, shipping + percentage + fixed advance payments, or Full Payment. Enable several choices so customers can choose at checkout. Select multiple gateways, including enabled bKash, manual bKash, Nagad, Rocket, Upay or other WooCommerce payment gateways.

The checkout summary shows the full order value, payable advance and delivery balance. It appears after shipping, directly above the order total by default. It can be moved below subtotal or above payment methods from the plugin settings. Previous subtotal placements adopt the new default until the position is explicitly saved again.

Percentages use the order total after discounts, including taxes and WooCommerce fees. You can exclude shipping and shipping tax from the percentage base. Combined sources are added together, rounded to currency precision, and capped at the full order value.

== Installation and Configuration ==

1. Activate WooCommerce and this plugin.
2. Configure shipping methods under WooCommerce > Settings > Shipping.
3. Open WooCommerce > Advance Shipping COD.
4. Select the shipping methods that activate advance payment.
5. In Payment options, enable the choices customers can use and mark one enabled choice as Default. One enabled choice is applied automatically; multiple choices let customers choose at checkout.
6. Enter the fixed amount or percentage settings that appear for your enabled choices.
7. Choose the checkout summary position.
8. Select allowed payment gateways. Leave all unchecked to allow every enabled non-COD gateway. A gateway must also meet its own availability rules.
9. Save settings. Existing installations retain their previous calculation type until changed.
10. Review customized text. Use {advance_amount} for the payable amount, {shipping_amount} for actual shipping including shipping tax, and {due_amount} for the delivery balance.

== Payment Workflow ==

For matching shipping methods, COD is hidden by default when an advance is required. Enable Cash on Delivery > Allow full payment on delivery in the plugin settings to offer COD alongside advance gateways. Choosing COD shows the full checkout total and creates a normal full-value COD order with no advance. COD must also be enabled and available in WooCommerce. The chosen advance is stored as the WooCommerce order's payment total before gateway processing. Full order value and delivery balance are stored separately and shown in order details, emails and the admin panel.

Manual payment instructions that read WooCommerce's numeric cart total, including the installed Bangladeshi Payments Mobile bKash and Nagad implementations, receive the advance amount while payment fields render. This also works during checkout AJAX refreshes. The underlying cart and session keep the full order total.

Manual orders on hold remain awaiting confirmation. Confirm the transfer through the gateway's verification flow or move the order to processing after checking the payment. Payment completion or processing/completed status records a single confirmation note. Gateway totals remain the advance amount through callbacks and retries.

Zero-advance choices use COD for the full order value. If COD is unavailable, checkout cannot continue with that choice. This plugin does not verify mobile-money transactions itself.

== Checkout Design ==

Open WooCommerce > Advance Shipping COD > Checkout Design. Set primary, background, border, text, heading and description colors using color pickers. Select border style, width and corner radius. Padding and margin have separate top/right/bottom/left controls in pixels.

The live preview updates these controls as you edit. Save Design applies them to the checkout summary, including subsequent AJAX refreshes. Custom CSS is applied on checkout after the generated settings; target #wcasc-notice-wrap .wcasc-payment-card or other plugin classes to avoid affecting unrelated checkout content. The custom CSS field supports the WordPress CSS editor when enabled. Custom CSS is applied after saving and is not executed in the admin preview. Reset to defaults restores design values and clears custom CSS without changing payment settings.

== Due Collection ==

Open an advance-payment order and find the compact Payments summary below Customer in the General section. Expand Record collection to add a payment, Collection history to review or reverse entries, and Analytics & help to refresh reports. The panel shows order value, confirmed advance, collected delivery payments and outstanding due.

1. Verify the advance payment first.
2. Enter the amount actually received (full balance or an installment).
3. Select Cash, Courier settlement, bKash, Nagad, Bank transfer or Other and optionally add a receipt/reference.
4. Click Record collection. The balance, customer receipt and order-list due column update immediately.
5. Use Reverse entry to correct an incorrect record. The original entry remains in the audit history, and a negative correction restores its outstanding amount. Reversal does not send a refund.

Each entry records its time, staff member, collection method and reference. Duplicate requests cannot add the same entry twice; a database lock serializes concurrent collection writes. Only users with WooCommerce management and order-edit permission can record or reverse entries. Cancelled, failed and refunded orders cannot receive new collection entries; refund cases require manual reconciliation.

Completing an order does not collect the balance. Recording a collection does not charge a customer, complete delivery, change the gateway transaction ID, or create a new sale.

== Compatibility and Accounting ==

Requires classic WooCommerce checkout (the [woocommerce_checkout] shortcode), including themes and CartFlows templates that preserve the standard WooCommerce checkout hooks. Checkout Blocks are not supported and are declared incompatible.

HPOS order storage is supported. Gateway plugins that bypass WooCommerce order/cart APIs, reconstruct amounts from line items, or reject partial totals need a gateway-specific adapter. Verify API-based bKash/Nagad payments with the provider's sandbox before using them live.

The stored WooCommerce order payment total represents the advance, preserving gateway verification and retry amounts. Full product, shipping and tax lines are retained. WooCommerce Analytics order stats use the full order value and calculate net revenue from that value less shipping and tax, following WooCommerce sales recognition and status exclusions. Due collection entries do not generate additional sales. Sales revenue and cash received are different measures; use the collection ledger to review receipts.

For existing orders, click Refresh order analytics in the payment panel. To repair all historical analytics, use WooCommerce Analytics > Settings > Import historical data and reimport affected orders. No existing order is assumed to have its delivery balance collected simply because it is completed.

Legacy reports, exports and third-party dashboards that directly read the stored order payment total may still show only the advance; this adjustment targets native WooCommerce Analytics. This plugin does not allocate tax between installments or initiate online collection/refund transactions. Refunds need manual reconciliation against gateway payments and recorded offline receipts; do not automatically refund full product lines through a gateway that collected only an advance. Refunded orders disable new collection recording.

Gateway-specific transfer charges shown by manual gateways are controlled by those gateways and may be added to their payment instructions. They are separate from this plugin's delivery balance unless the gateway adds them as WooCommerce fees.

== Development Checks ==

Run: php tests/regression.php

The standalone checks use WooCommerce's installed legacy cart getter and test payment calculations, currency rounding, caps, validated selections, multiple gateways, payment status transitions and order reuse. When Bangladeshi Payments Mobile is installed beside this plugin, they also exercise its bKash/Nagad fee calculators without sending payments or writing database records.

== Changelog ==

= 1.4.4 =
* Aligned percentage and checkout position labels on the left, matching the other settings.

= 1.4.3 =
* Restored advance percentage and checkout position to stacked full-width fields.

= 1.4.2 =
* Refined settings fields with rounded borders, consistent spacing and focus styling.
* Placed advance percentage and checkout position side by side, stacking on small screens.

= 1.4.1 =
* Moved Customize Texts above Support & Community in the settings sidebar.
* Matched sidebar width to the Checkout Design live preview and stacked text fields for readability.

= 1.4.0 =
* Added Full Payment as an enabled, defaultable and renameable checkout option.
* Full Payment charges the discounted order total including shipping, tax and fees, with no balance due on delivery.

= 1.3.3 =
* Added editable Checkout label fields for each payment option.
* Blank labels use original names; {percentage} shows the configured percentage.

= 1.3.2 =
* Positioned Due (COD) immediately after Status in classic and HPOS order lists.
* Highlighted outstanding amounts with a badge.

= 1.3.1 =
* Positioned settings save messages below the header and above the tabs with readable success styling.

= 1.3.0 =
* Added a Checkout Design settings tab, live preview and CSS editor.
* Added color, border, radius and four-sided spacing controls.
* Applied saved designs and custom CSS to checkout summaries.
* Fixed the admin payment summary gap below the Customer field.

= 1.2.1 =
* Moved the payment summary below Customer in the order General section.
* Added a compact two-column summary with collection, history and analytics disclosures.
* Kept currency symbols and amounts on one line.
* Preserved expanded sections after saving and prevented collection fields from submitting the main order form on Enter.

= 1.2.0 =
* Added a dedicated payment summary panel for classic and HPOS order screens.
* Added installment due collections, references, staff/time audit history and correction reversals.
* Added nonce/permission checks, duplicate protection and per-order database locking.
* Updated outstanding balances in order lists and customer receipts.
* Corrected native Analytics gross and net sales to use full order value without double-counting collections.
* Added per-order Analytics refresh for historical orders.

= 1.1.1 =
* Added an optional full-payment COD choice alongside advance gateways.
* Checkout summary and total refresh when switching between COD and advance payment.
* Simplified admin payment options and moved the default summary above order total.

= 1.1.0 =
* Added percentage advance payments with configurable percentage base.
* Added multiple customer choices and combined calculation sources.
* Added configurable checkout positions, defaulting above the order total.
* Added a responsive advance payment summary and gateway selection settings.
* Fixed persisted payment totals and manual gateway cart amount calculations.
* Kept advance totals stable during verification callbacks and payment retries.
* Stopped marking on-hold manual payments as paid.
* Added full order value and delivery balance to receipts and regression checks.

= 1.0.0 =
* Initial shipping-based advance payment release.

== Support ==

Plugin website: https://wpmethods.com
