qwadmin

A QWizard sub-module for admin utilities.

Currently included:
- Views pre-render de-duplication for the "content_no_grouping" view on displays:
  - page_8
  - data_export_1

Install:
1) Place this folder at: modules/custom/qwizard/qwadmin
2) Enable: drush en qwadmin -y
3) Clear cache: drush cr

Adjustments:
- To target a different view or display, edit qwadmin.module.
- To ignore additional fields when computing duplicates, add them to:
  $ignore_field_ids
