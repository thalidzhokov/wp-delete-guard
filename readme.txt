=== Delete Guard ===
Contributors: thalidzhokov
Tags: delete, trash, security, audit log, permissions
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Per post type deletion policies: logging, trash blocking, and permanent delete protection.

== Description ==

A must-have for administrators and site owners with a large team of editors and authors.

Delete Guard lets you control how posts are trashed and permanently deleted, per post type.

**Modes**

* Off — default for new post types; no logging, no restrictions
* Log only — allow trash/delete/restore and write an audit log
* Block permanent delete — allow trash; block permanent delete (optional EMPTY_TRASH_DAYS cron)
* Block trash — block trash and permanent delete (admins still allowed; always logged)

**Menus**

* Navigation Menus (`wp_navigation`) — block-theme / Site Editor menus
* Navigation Menu items (`nav_menu_item`) — classic Appearance → Menus; if that row is Off, it follows the Navigation Menus mode
* Log titles for classic items look like `Menu name › Item title`

**Also**

* Audit log for allowed and denied attempts (trash, permanent delete, restore)
* Administrators and network super admins bypass blocks; actions are still logged
* Multisite: settings and log table are per site
* English UI by default; Russian translation included (`ru_RU`)

Settings: **Settings → Delete Guard**.

== Installation ==

1. Upload the `delete-guard` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** screen
3. Open **Settings → Delete Guard** and choose a mode per post type

== Frequently Asked Questions ==

= Who can bypass deletion blocks? =

Users with the `administrator` role, and network super admins on Multisite. Their actions are still written to the log.

= Does this stop WP-Cron from emptying the trash? =

In **Block permanent delete** mode you can allow or deny cleanup based on `EMPTY_TRASH_DAYS`. If denied, the plugin refreshes trash meta so cron does not retry the same post every day.

= Where is the log stored? =

In the custom table `{prefix}delete_guard_log`. Logs are kept indefinitely; you can purge entries older than 30 days from the Log tab.

= Why did deleting a classic menu not appear under Navigation Menus? =

Classic menus delete `nav_menu_item` posts. Configure that row, or leave it Off so it inherits the Navigation Menus (`wp_navigation`) mode.

== Changelog ==

= 1.0.2 =
* Log source stores user roles (e.g. editor) instead of generic admin for wp-admin requests
* Fix duplicate Settings saved notice on the settings screen

= 1.0.1 =
* Classic menus: show `nav_menu_item` in settings; inherit `wp_navigation` mode when unset
* Clearer log titles for menu items (`Menu › Item`)
* Log allowed trash/delete before the post is removed (keeps menu term/meta for the snapshot)
* Dropdown order: Block permanent delete before Block trash
* Blocked trash/delete redirects back to the list with an admin notice instead of wp_die

= 1.0.0 =
* Initial release: per post type modes, audit log, admin UI, RU/EN translations, functional tests.

== Upgrade Notice ==

= 1.0.2 =
Log source shows roles; settings notice no longer duplicates.

= 1.0.1 =
Fixes classic menu logging and titles; rebuild recommended if you use Appearance → Menus.

= 1.0.0 =
Initial release.
