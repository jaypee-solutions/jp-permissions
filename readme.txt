=== JP Permissions for WordPress ===
Contributors: jaypeesolutions
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

JP Permissions lets an Administrator delegate parts of the page tree to Branch Editors while keeping control of site structure.

== Description ==

An Administrator creates branch groups, assigns each group one or more branch roots, and adds members. A member's authorized pages are the branch roots and all pages below them. Membership only adds access: an Editor or Administrator remains unrestricted even when added to a group.

Branch Editors see and edit their authorized pages, including pages written by someone else. They can publish, schedule, unpublish, and preview them, and create pages with Add subpage beneath an authorized page. They can upload images and edit their own uploads; they can use shared images but cannot edit others' uploads or delete media.

Administrators retain control of structure. Branch Editors cannot move or reorder pages, change branch-root slugs or status, or trash, restore, or delete pages. Ask an Administrator to make structural changes; unpublish rather than delete.

== Installation ==

1. Upload the plugin ZIP under Plugins → Add New → Upload Plugin.
2. Activate the plugin. It registers the Branch Editor role for volunteers; the role has no page access until assigned to a group.
3. Open Users → Page Branches and choose Add group.
4. Name the branch group, choose a page as a branch root, and select its members. Save. Each save adds at most one root; edit the group again to add more roots. Roots may be shared between groups and nested roots are additive.
5. Assign volunteers the Branch Editor role under Users → All Users. Add members to the groups they need.

Only Administrators can manage branch groups. A page may belong to several groups; their access adds together. Group membership never reduces an Editor's or Administrator's access.

== Navigation ==

Page Branches does not manage site navigation. Configure the site to list branch pages automatically—for example, use the Page List block or a subpage listing on branch pages—so new pages made with Add subpage appear without an Administrator updating a menu. A hand-curated menu or Navigation block still requires an Administrator to add new pages (ADR 0014).

== Known limitations ==

The Pages list status counts show site-wide totals rather than counts limited to a Branch Editor's authorized pages. The block editor shows slug and status controls on branch roots, but changes to either are rejected. Pages below a branch root that is not a top-level page may appear flat in the Pages list.

== Changelog ==

= Unreleased =
* Page Branches: branch groups, scoped page editing and publishing, and the Branch Editor role.

= 0.1.0 =
* Initial scaffolding from wp-plugin-template.
