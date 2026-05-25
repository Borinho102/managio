<?php
defined('BASEPATH') or exit('No direct script access allowed');

$route['mpc_mobile_app_connector/(:any)/estimates/estimate_accepted/(:num)/(:num)'] = '$1/estimates/data_estimate_accepted/$2/$3';
//////ADMIN API ROUTES
$route['perfex_mobile_companion/(:any)/validate_url']         = '$1/login/validate_url';
$route['perfex_mobile_companion/(:any)/order']                = '$1/order/purchase_app';
$route['perfex_mobile_companion/(:any)/items/get_groups']     = '$1/items/data_groups';
$route['perfex_mobile_companion/(:any)/items/copy/(:num)']    = '$1/items/data_copy/$2';

$route['perfex_mobile_companion/(:any)/staffs/change_status/(:num)/(:num)'] = '$1/staffs/change_status/$2/$3';
$route['perfex_mobile_companion/(:any)/staffs/profile_image/(:num)'] = '$1/staffs/profile_image/$2';

$route['perfex_mobile_companion/(:any)/customers/change_client_status/(:num)/(:num)'] = '$1/customers/change_client_status/$2/$3';

$route['perfex_mobile_companion/(:any)/contacts/profile_image/(:num)'] = '$1/contacts/profile_image/$2';

$route['perfex_mobile_companion/(:any)/reminders/(:num)/(:any)/search/(:any)'] = '$1/reminders/data_search/$2/$3/$4';
$route['perfex_mobile_companion/(:any)/reminders/(:num)/(:any)/search'] = '$1/reminders/data_search/$2/$3';

$route['perfex_mobile_companion/(:any)/delete/(:any)/(:num)'] = '$1/$2/data/$3';

$route['perfex_mobile_companion/(:any)/proposals/comment/(:num)'] = '$1/proposals/data_comment/$2';
$route['perfex_mobile_companion/(:any)/proposals/copy/(:num)'] = '$1/proposals/copy/$2';
$route['perfex_mobile_companion/(:any)/proposals/mark_action_status/(:num)/(:num)'] = '$1/proposals/mark_action_status/$2/$3';
$route['perfex_mobile_companion/(:any)/proposals/comment'] = '$1/proposals/data_comment';
$route['perfex_mobile_companion/(:any)/proposals/pdf/(:num)'] = '$1/proposals/data_pdf/$2';
$route['perfex_mobile_companion/(:any)/proposals/delete_attachment/(:num)'] = '$1/proposals/delete_attachment/$2';
$route['perfex_mobile_companion/(:any)/proposals/convert_to_estimate/(:num)'] = '$1/proposals/convert_to_estimate/$2';
$route['perfex_mobile_companion/(:any)/proposals/convert_to_invoice/(:num)'] = '$1/proposals/convert_to_invoice/$2';
$route['perfex_mobile_companion/(:any)/proposals/send_expiry_reminder/(:num)'] = '$1/proposals/send_expiry_reminder/$2';

$route['perfex_mobile_companion/(:any)/estimates/convert_to_invoice/(:num)'] = '$1/estimates/convert_to_invoice/$2';
$route['perfex_mobile_companion/(:any)/estimates/convert_to_invoice/(:num)/(:num)'] = '$1/estimates/convert_to_invoice/$2/$3';
$route['perfex_mobile_companion/(:any)/estimates/mark_action_status/(:num)/(:num)'] = '$1/estimates/mark_action_status/$2/$3';
$route['perfex_mobile_companion/(:any)/estimates/copy/(:num)'] = '$1/estimates/copy/$2';
$route['perfex_mobile_companion/(:any)/estimates/pdf/(:num)'] = '$1/estimates/data_pdf/$2';
$route['perfex_mobile_companion/(:any)/estimates/send_to_email/(:num)'] = '$1/estimates/send_to_email/$2';

$route['perfex_mobile_companion/(:any)/projects/view_as_customer/(:num)/(:num)'] = '$1/projects/view_as_customer/$2/$3';
$route['perfex_mobile_companion/(:any)/projects/mark_action_status/(:num)/(:num)'] = '$1/projects/mark_action_status/$2/$3';
$route['perfex_mobile_companion/(:any)/projects/change_activity_visibility/(:num)/(:num)'] = '$1/projects/change_activity_visibility/$2/$3';
$route['perfex_mobile_companion/(:any)/projects/get_chart_data/(:num)/(:any)'] = '$1/projects/chart_data/$2/$3';
$route['perfex_mobile_companion/(:any)/projects/files/(:num)'] = '$1/projects/data_files/$2';
$route['perfex_mobile_companion/(:any)/projects/add_member/(:num)'] = '$1/projects/data_add_member/$2';
$route['perfex_mobile_companion/(:any)/projects/save_note/(:num)'] = '$1/projects/save_note/$2';
$route['perfex_mobile_companion/(:any)/projects/discussions/(:num)'] = '$1/projects/data_discussions/$2';
$route['perfex_mobile_companion/(:any)/projects/discussions'] = '$1/projects/data_discussions';
$route['perfex_mobile_companion/(:any)/projects/copy/(:num)'] = '$1/projects/copy/$2';
$route['perfex_mobile_companion/(:any)/proposals/sign_proposal/(:num)'] = '$1/proposals/data_sign_proposal/$2';

///////CUSTOMER API ROUTES

$route['perfex_mobile_companion/(:any)/projects/discussions_comment/(:num)'] = '$1/projects/data_discussions_comment/$2';
$route['perfex_mobile_companion/(:any)/projects/discussions_comments/(:num)'] = '$1/projects/data_discussions_comments/$2';
$route['perfex_mobile_companion/(:any)/projects/discussions_comment'] = '$1/projects/data_discussions_comment';
$route['perfex_mobile_companion/(:any)/projects/update_discussions_comment/(:num)'] = '$1/projects/update_discussions_comment/$2';
$route['perfex_mobile_companion/(:any)/projects/delete_discussion_comment/(:num)'] = '$1/projects/delete_discussion_comment/$2';

//////////END CUSTOMER API ROUTES

$route['perfex_mobile_companion/(:any)/tickets/departments/(:num)'] = '$1/tickets/data_departments/$2';
$route['perfex_mobile_companion/(:any)/tickets/departments'] = '$1/tickets/data_departments';
$route['perfex_mobile_companion/(:any)/tickets/priorities/(:num)'] = '$1/tickets/data_priorities/$2';
$route['perfex_mobile_companion/(:any)/tickets/priorities'] = '$1/tickets/data_priorities';
$route['perfex_mobile_companion/(:any)/tickets/services/(:num)'] = '$1/tickets/data_services/$2';
$route['perfex_mobile_companion/(:any)/tickets/services'] = '$1/tickets/data_services';
$route['perfex_mobile_companion/(:any)/tickets/statuses'] = '$1/tickets/data_statuses';
$route['perfex_mobile_companion/(:any)/tickets/add_reply/(:num)'] = '$1/tickets/data_add_reply/$2';
$route['perfex_mobile_companion/(:any)/tickets/mark_action_status/(:num)/(:num)'] = '$1/tickets/mark_action_status/$2/$3';

$route['perfex_mobile_companion/(:any)/tasks/comment/(:num)'] = '$1/tasks/data_comment/$2';
$route['perfex_mobile_companion/(:any)/tasks/comment'] = '$1/tasks/data_comment';
$route['perfex_mobile_companion/(:any)/tasks/timesheet/(:num)'] = '$1/tasks/data_timesheet/$2';
$route['perfex_mobile_companion/(:any)/tasks/timesheet'] = '$1/tasks/data_timesheet';
$route['perfex_mobile_companion/(:any)/tasks/assignees/(:num)/(:num)'] = '$1/tasks/data_assignees/$2/$3';
$route['perfex_mobile_companion/(:any)/tasks/assignees'] = '$1/tasks/data_assignees';
$route['perfex_mobile_companion/(:any)/tasks/followers/(:num)/(:num)'] = '$1/tasks/data_followers/$2/$3';
$route['perfex_mobile_companion/(:any)/tasks/followers'] = '$1/tasks/data_followers';
$route['perfex_mobile_companion/(:any)/tasks/checklist/(:num)/(:num)'] = '$1/tasks/data_checklist/$2/$3';
$route['perfex_mobile_companion/(:any)/tasks/checklist'] = '$1/tasks/data_checklist';
$route['perfex_mobile_companion/(:any)/tasks/checkbox_action/(:num)/(:num)'] = '$1/tasks/data_checkbox_action/$2/$3';
$route['perfex_mobile_companion/(:any)/tasks/mark_action_status/(:num)/(:num)'] = '$1/tasks/mark_as/$2/$3';
$route['perfex_mobile_companion/(:any)/tasks/mark_action_priority/(:num)/(:num)'] = '$1/tasks/change_priority/$2/$3';
$route['perfex_mobile_companion/(:any)/tasks/checklist_item_template'] = '$1/tasks/data_checklist_item_template';
$route['perfex_mobile_companion/(:any)/tasks/files/(:num)'] = '$1/tasks/files/$2';
$route['perfex_mobile_companion/(:any)/tasks/copy'] = '$1/tasks/copy';
$route['perfex_mobile_companion/(:any)/tasks/timer_tracking'] = '$1/tasks/timer_tracking';
$route['perfex_mobile_companion/(:any)/tasks/save_checklist_assigned_staff'] = '$1/tasks/save_checklist_assigned_staff';
$route['perfex_mobile_companion/(:any)/tasks/checklist_item_template'] = '$1/tasks/checklist_item_template';

$route['perfex_mobile_companion/(:any)/credit_notes/pdf/(:num)'] = '$1/credit_notes/data_pdf/$2';
$route['perfex_mobile_companion/(:any)/credit_notes/delete_attachment/(:num)'] = '$1/credit_notes/delete_attachment/$2';
$route['perfex_mobile_companion/(:any)/credit_notes/available_creditable_invoices/(:num)'] = '$1/credit_notes/available_creditable_invoices/$2';
$route['perfex_mobile_companion/(:any)/credit_notes/apply_credits_to_invoices/(:num)'] = '$1/credit_notes/apply_credits_to_invoices/$2';
$route['perfex_mobile_companion/(:any)/credit_notes/delete_credit_note_applied_credit/(:num)/(:num)/(:num)'] = '$1/credit_notes/delete_credit_note_applied_credit/$2/$3/$4';
$route['perfex_mobile_companion/(:any)/credit_notes/credit_note_from_invoice/(:num)'] = '$1/credit_notes/credit_note_from_invoice/$2';
$route['perfex_mobile_companion/(:any)/credit_notes/create_refund/(:num)'] = '$1/credit_notes/create_refund/$2';
$route['perfex_mobile_companion/(:any)/credit_notes/edit_refund/(:num)'] = '$1/credit_notes/edit_refund/$2';
$route['perfex_mobile_companion/(:any)/credit_notes/delete_refund/(:num)/(:num)'] = '$1/credit_notes/delete_refund/$2/$3';
$route['perfex_mobile_companion/(:any)/credit_notes/mark_credit_note_status/(:num)/(:num)'] = '$1/credit_notes/mark_credit_note_status/$2/$3';

$route['perfex_mobile_companion/(:any)/invoices/get_invoices_total'] = '$1/invoices/get_invoices_total';
$route['perfex_mobile_companion/(:any)/invoices/pdf/(:num)'] = '$1/invoices/data_pdf/$2';
$route['perfex_mobile_companion/(:any)/invoices/mark_as_sent/(:num)'] = '$1/invoices/mark_as_sent/$2';
$route['perfex_mobile_companion/(:any)/invoices/mark_as_cancelled/(:num)'] = '$1/invoices/mark_as_cancelled/$2';
$route['perfex_mobile_companion/(:any)/invoices/unmark_as_cancelled/(:num)'] = '$1/invoices/unmark_as_cancelled/$2';
$route['perfex_mobile_companion/(:any)/invoices/copy/(:num)'] = '$1/invoices/copy/$2';
$route['perfex_mobile_companion/(:any)/invoices/send_to_email/(:num)'] = '$1/invoices/send_to_email/$2';

$route['perfex_mobile_companion/(:any)/todo/change_todo_status/(:num)/(:num)'] = '$1/todo/change_todo_status/$2/$3';

$route['perfex_mobile_companion/(:any)/payments/pdf/(:num)'] = '$1/payments/data_pdf/$2';

$route['perfex_mobile_companion/(:any)/expenses/attachment/(:num)'] = '$1/expenses/data_attachment/$2';
$route['perfex_mobile_companion/(:any)/expenses/copy/(:num)'] = '$1/expenses/copy/$2';
$route['perfex_mobile_companion/(:any)/expenses/convert_to_invoice/(:num)'] = '$1/expenses/convert_to_invoice/$2';

$route['perfex_mobile_companion/(:any)/leads/convert_to_customer'] = '$1/leads/convert_to_customer';
$route['perfex_mobile_companion/(:any)/leads/add_note'] = '$1/leads/add_note';
$route['perfex_mobile_companion/(:any)/leads/add_activity'] = '$1/leads/add_activity';
$route['perfex_mobile_companion/(:any)/leads/attachments/(:num)'] = '$1/leads/data_attachments/$2';
$route['perfex_mobile_companion/(:any)/leads/mark_as_lost/(:num)/(:num)'] = '$1/leads/mark_as_lost/$2/$3';
$route['perfex_mobile_companion/(:any)/leads/mark_as_junk/(:num)/(:num)'] = '$1/leads/mark_as_junk/$2/$3';
$route['perfex_mobile_companion/(:any)/leads/pdf/(:num)'] = '$1/leads/data_pdf/$2';

$route['perfex_mobile_companion/(:any)/contracts/contract_type'] = '$1/contracts/contract_type';
$route['perfex_mobile_companion/(:any)/contracts/copy/(:num)'] = '$1/contracts/copy/$2';
$route['perfex_mobile_companion/(:any)/contracts/mark_as_signed/(:num)'] = '$1/contracts/mark_as_signed/$2';
$route['perfex_mobile_companion/(:any)/contracts/unmark_as_signed/(:num)'] = '$1/contracts/unmark_as_signed/$2';
$route['perfex_mobile_companion/(:any)/contracts/clear_signature/(:num)'] = '$1/contracts/clear_signature/$2';
$route['perfex_mobile_companion/(:any)/contracts/files/(:num)'] = '$1/contracts/data_files/$2';
$route['perfex_mobile_companion/(:any)/contracts/comment/(:num)'] = '$1/contracts/data_comment/$2';
$route['perfex_mobile_companion/(:any)/contracts/comment'] = '$1/contracts/data_comment';

$route['perfex_mobile_companion/(:any)/contracts/pdf/(:num)'] = '$1/contracts/data_pdf/$2';

$route['perfex_mobile_companion/(:any)/contracts/sign_contract/(:num)'] = '$1/contracts/data_sign_contract/$2';
// data_sign_contract
$route['perfex_mobile_companion/(:any)/contacts/change_contact_status/(:num)/(:num)'] = '$1/contacts/change_contact_status/$2/$3';

$route['perfex_mobile_companion/(:any)/download/file/(:any)/(:any)'] = '$1/download/file/$2/$3';

$route['perfex_mobile_companion/(:any)/subscriptions/get_plans'] = '$1/subscriptions/data_plans';
$route['perfex_mobile_companion/(:any)/subscriptions/cancel/(:num)'] = '$1/subscriptions/canceled/$2';

$route['perfex_mobile_companion/(:any)/misc/upload_sales_file'] = '$1/misc/upload_sales_file';
$route['perfex_mobile_companion/(:any)/misc/toggle_file_visibility/(:num)'] = '$1/misc/toggle_file_visibility/$2';
$route['perfex_mobile_companion/(:any)/misc/delete_sale_activity/(:num)'] = '$1/misc/delete_sale_activity/$2';

$route['perfex_mobile_companion/(:any)/notifications/mark_all_as_read'] = '$1/notifications/data_mark_all_as_read';
$route['perfex_mobile_companion/(:any)/notifications/mark_as/(:num)/(:num)'] = '$1/notifications/data_mark_as_read_unread/$2/$3';

$route['perfex_mobile_companion/(:any)/(:any)/activity/(:num)'] = '$1/$2/data_activity/$3';

$route['perfex_mobile_companion/(:any)/(:any)/search/(:any)'] = '$1/$2/data_search/$3';

$route['perfex_mobile_companion/(:any)/(:any)/search']        = '$1/$2/data_search';

$route['perfex_mobile_companion/(:any)/login/auth']                  = '$1/login/login_api';
$route['perfex_mobile_companion/(:any)/login/qr_code_otp']           = '$1/login/login_qr_code_otp_api';

$route['perfex_mobile_companion/(:any)/login/forgot_password']       = '$1/login/forgot_password';
$route['perfex_mobile_companion/(:any)/login/logout/(:num)/(:num)']  = '$1/login/logout/$2/$3';
$route['perfex_mobile_companion/(:any)/login/view']                  = '$1/login/view';
$route['perfex_mobile_companion/(:any)/login/key']                   = '$1/login/api_key';
$route['perfex_mobile_companion/(:any)/(:any)/(:any)/(:num)']        = '$1/$2/data/$3/$4';
$route['perfex_mobile_companion/(:any)/(:any)/(:num)/(:num)']        = '$1/$2/data/$3/$4';
$route['perfex_mobile_companion/(:any)/custom_fields/(:any)/(:num)'] = '$1/custom_fields/data/$2/$3';
$route['perfex_mobile_companion/(:any)/custom_fields/(:any)']        = '$1/custom_fields/data/$2';
$route['perfex_mobile_companion/(:any)/common/(:any)/(:num)']        = '$1/common/data/$2/$3';
$route['perfex_mobile_companion/(:any)/common/(:any)']               = '$1/common/data/$2';
$route['perfex_mobile_companion/(:any)/(:any)/(:num)']               = '$1/$2/data/$3';
$route['perfex_mobile_companion/(:any)/(:any)']                      = '$1/$2/data';

///////CUSTOMER API ROUTES

$route['perfex_mobile_companion/(:any)/clients/change_password/'] = '$1/clients/change_password/';
$route['perfex_mobile_companion/(:any)/clients/contact/'] = '$1/clients/contact/';
$route['perfex_mobile_companion/(:any)/clients/add_profile_image'] = '$1/clients/add_profile_image';
$route['perfex_mobile_companion/(:any)/clients/remove_profile_image'] = '$1/clients/remove_profile_image';

// customer project routes

