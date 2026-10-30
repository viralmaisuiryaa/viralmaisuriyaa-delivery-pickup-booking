=== Viralmaisuriyaa Delivery & Pickup Booking for WooCommerce ===
Tags: woocommerce, delivery date, pickup, time slots, checkout
Requires at least: 6.5
Tested up to: 7.1.2
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let customers choose an available delivery or pickup date and time slot at checkout.

== Description ==

Viralmaisuriyaa Delivery & Pickup Booking adds simple scheduling controls to WooCommerce checkout.

Features:

* Delivery and pickup fulfilment methods, displayed as radio buttons or a dropdown.
* Configurable minimum lead time.
* Configurable maximum advance booking period.
* Available weekday rules.
* Holiday and blackout dates highlighted in orange and disabled in the calendar.
* Custom customer-facing time slots.
* Optional free pickup shipping.
* Use WooCommerce shipping rates only, or add a fixed additional delivery fee.
* Server-side validation of every submitted date and time slot.
* Schedule information in order administration, customer order details and emails.
* WooCommerce High-Performance Order Storage (HPOS) compatibility.
* Checkout Block and classic checkout support.
* Translation-ready strings.
* Optional removal of plugin settings during uninstall.

The plugin does not contact external services and does not collect or transmit personal data.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or install its ZIP through Plugins > Add New > Upload Plugin.
2. Activate the plugin through the Plugins screen.
3. Go to WooCommerce > Delivery & Pickup.
4. Configure fulfilment methods, booking range, weekdays, holidays and time slots.
5. Place a test order before using the scheduler on a production store.

== Frequently Asked Questions ==

= How do I configure pickup shipping? =

Add WooCommerce's Local pickup shipping method to every eligible shipping zone. Selecting Pickup shows pickup rates only; selecting Delivery shows delivery rates only. The plugin does not create shipping methods. If a zone has no matching rate, checkout cannot complete for that method. The newer block-based Local Pickup location selector is not supported; use zone-based Local pickup instead.

= Does this manage capacity or pickup locations? =

No. Time slots are labels shared by both fulfilment methods. There are no capacity limits, cutoff times, location management, per-product schedules or delivery-radius rules. The additional delivery fee is non-taxable.

= What personal data is stored? =

The selected method, date and time slot are stored with the WooCommerce order. WooCommerce controls order retention and privacy handling. Uninstalling retains historical order data and removes settings only when opted in.


= Does it support HPOS? =

Yes. Order data is handled through WooCommerce CRUD APIs, and HPOS compatibility is declared.

= Does it support the Checkout Block? =

Yes, with WooCommerce 8.9 or newer. Both checkout types show a calendar with unavailable dates disabled when JavaScript is enabled. Without JavaScript, classic checkout retains a text date field and Block checkout retains its validated available-date dropdown. Include the Order information block in the Checkout page.

= Are unavailable dates validated securely? =

Yes. Browser controls improve the customer experience, while authoritative validation also runs on the server.

= Can settings be retained after uninstalling? =

Yes. Settings remain by default. Enable the data removal option before uninstalling if you want the plugin to remove them.

= How do delivery fee options work? =

WooCommerce rate only uses the shipping-zone rate without a plugin surcharge. Fixed additional fee adds the configured non-taxable amount to that rate for Delivery only. It does not replace WooCommerce shipping or apply to Pickup. Existing enabled fixed fees are retained on upgrade.

= Can customers select holidays? =

No. The enhanced calendar highlights holidays in orange and disables their date buttons. Closed weekdays and dates outside the booking window are also disabled. Server-side validation rejects unavailable dates even if a request is changed manually.

== Changelog ==

= 1.1.5 =
* Renamed the plugin and translation domain for a distinctive personal-brand identity.
* Existing settings and order scheduling data remain compatible.


= 1.1.4 =
* Added fulfilment display settings for radio buttons or a dropdown in both checkout types.
* Added WooCommerce-rate-only and fixed-additional-fee options, preserving enabled fees on upgrade.
* Added an accessible calendar with orange disabled holidays, disabled closed dates and keyboard navigation.
* Restored the requested author and plugin profile URLs.


= 1.1.3 =
* Improved responsive admin settings, labels, date controls and focus visibility.
* Fixed unsupported Block date field registration using validated available-date options.
* Added Store API method synchronization and delivery-fee recalculation.
* Restricted free pickup to pickup rates; varied shipping cache by fulfilment settings.
* Validated calendar dates and non-negative fees; removed duplicate slots.
* Retained historical order details when scheduling is disabled.
* Fixed per-site multisite uninstall consent and date display timezone.


= 1.1.2 =
* Removed the unnecessary Domain Path header to resolve the missing translation directory warning.

= 1.1.1 =
* Fixed classic checkout validation incorrectly treating completed scheduler fields as empty.
* Registered classic fields with the WooCommerce checkout fields API.
* Added a datepicker with holidays highlighted in red and unavailable dates disabled.

= 1.1.0 =
* Reworked weekday persistence using explicit checkbox keys.
* Added an option to remove local pickup shipping charges.
* Added an optional configurable delivery fee.
* Added live classic-checkout total recalculation when the fulfilment method changes.

= 1.0.2 =
* Fixed selected weekdays not persisting correctly.
* Added plugin and author profile URLs.

= 1.0.1 =
* Fixed settings persistence with a dedicated secure save handler.
* Fixed scheduler fields not appearing on the classic shortcode checkout.
* Removed development-only configuration files from the release package.

= 1.0.0 =
* Initial release.
