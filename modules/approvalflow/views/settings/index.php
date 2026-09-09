<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * ApprovalFlow — Settings view (Integrations tab)
 *
 * Rendered inside Perfex's settings page via add_settings_section_child().
 * The view is injected INSIDE the core `<form id="settings-form">` that
 * Settings::index() opens above us, so we MUST NOT open a second form
 * (HTML5 forbids nested forms — the parser silently drops the inner one,
 * which is exactly the regression introduced in the previous fix attempt).
 *
 * Persistence contract (canonical DupliGuard / Perfex bundled pattern):
 *   - All inputs carry name="settings[approvalflow_<key>]".
 *   - Settings_model::update() iterates over $_POST['settings'] and calls
 *     update_option($name, $val) for each entry, so the option names land
 *     unchanged in tbloptions.
 *   - Every checkbox is preceded by a hidden mirror with value="0" so
 *     unchecked boxes still submit a value (browsers omit unchecked
 *     checkboxes; the hidden guarantees "off" reaches the model).
 *
 * Layout (mirrors DupliGuard's approved-on-CodeCanyon settings, in our
 * slate + teal palette):
 *   1. Hero strip (icon + eyebrow + title + subtitle)
 *   2. Empty-state warning when all 5 entity toggles are off
 *   3. Row top — three cards in a col-md-4 grid:
 *      a) "Document types" — entity toggles
 *      b) "Notifications"  — channel toggles
 *      c) "Behavior"       — audit toggles + reminder days
 *   4. Divider with "Advanced" label
 *   5. Full-width "Advanced" card — debug + retention + shortcuts
 *
 * Note: Perfex does NOT push the module CSS on the settings screen
 * automatically, so the stylesheet is injected manually with the same
 * version cache-buster pattern used by DupliGuard.
 *
 * @package ApprovalFlow
 * @since   1.0.0
 */

// ── Snapshot current option values upfront so the markup body stays free
//    of repeated get_option() round trips.
$opts = [
    'enable_invoices'        => (int) get_option('approvalflow_enable_invoices'),
    'enable_estimates'       => (int) get_option('approvalflow_enable_estimates'),
    'enable_proposals'       => (int) get_option('approvalflow_enable_proposals'),
    'enable_contracts'       => (int) get_option('approvalflow_enable_contracts'),
    'enable_expenses'        => (int) get_option('approvalflow_enable_expenses'),
    'notifications_internal' => (int) get_option('approvalflow_notifications_internal'),
    'notifications_email'    => (int) get_option('approvalflow_notifications_email'),
    'require_reject_comment' => (int) get_option('approvalflow_require_reject_comment'),
    'admin_auto_approve'     => (int) get_option('approvalflow_admin_auto_approve'),
    'reminder_days'          => (int) get_option('approvalflow_reminder_days'),
    'debug_enabled'          => (int) get_option('approvalflow_debug_enabled'),
    'logs_retention_days'    => (int) get_option('approvalflow_logs_retention_days'),
    // Approval Pulse (v1.0.2)
    'risk_enabled'           => (int) get_option('approvalflow_risk_enabled'),
    'risk_amount_ceiling'    => (int) get_option('approvalflow_risk_amount_ceiling'),
    'sla_hours'              => (int) get_option('approvalflow_sla_hours'),
    'escalate_to'            => (int) get_option('approvalflow_escalate_to'),
    'digest_enabled'         => (int) get_option('approvalflow_digest_enabled'),
    'digest_freq'            => (string) get_option('approvalflow_digest_freq'),
    'digest_day'             => (int) get_option('approvalflow_digest_day'),
];

// Active staff for the escalation target dropdown (Approval Pulse).
$af_staff = $this->db->select('staffid, firstname, lastname')
    ->where('active', 1)
    ->order_by('firstname', 'ASC')
    ->get(db_prefix() . 'staff')->result_array();
$all_entities_off = !$opts['enable_invoices'] && !$opts['enable_estimates']
    && !$opts['enable_proposals'] && !$opts['enable_contracts'] && !$opts['enable_expenses'];

// Perfex renders this view inside Setup → Settings → Integrations and does
// NOT include the module's CSS on that screen — emit it explicitly here
// (cache-busted by version). Same pattern as DupliGuard's settings tab.
$_approvalflow_css_url = approvalflow_asset_url('assets/css/approvalflow.css');

// Entity rows for the first card (label key + icon)
$entity_rows = [
    'invoices'  => 'fa-file-invoice-dollar',
    'estimates' => 'fa-file-signature',
    'proposals' => 'fa-file-contract',
    'contracts' => 'fa-handshake',
    'expenses'  => 'fa-receipt',
];
?>
<link rel="stylesheet" href="<?php echo $_approvalflow_css_url; ?>">

<?php
// NO form_open() here on purpose. Perfex's Settings::index() has already
// opened <form id="settings-form" action="admin/settings/save_settings">
// above this partial. The core's bottom "Save Settings" button submits
// every input named settings[*] through Settings_model::update(), which
// calls update_option() for each pair. Adding a nested <form> here is
// invalid HTML5 — the parser silently drops it, leaving our inputs
// attached to the core form anyway (regression seen in retest-20260515).
?>

<div class="approvalflow-app approvalflow-settings-shell">

    <!-- Hero strip -->
    <div class="approvalflow-hero">
        <div class="approvalflow-hero__icon">
            <i class="fa fa-circle-check" aria-hidden="true"></i>
        </div>
        <div class="approvalflow-hero__body">
            <div class="approvalflow-hero__eyebrow"><?php echo html_escape(_l('approvalflow_settings_eyebrow')); ?></div>
            <h4><?php echo html_escape(_l('approvalflow')); ?> &mdash; <?php echo html_escape(_l('settings')); ?></h4>
            <p><?php echo html_escape(_l('approvalflow_settings_subtitle')); ?></p>
        </div>
    </div>

    <?php if ($all_entities_off): ?>
        <div class="approvalflow-alert approvalflow-alert--warning">
            <i class="fa fa-exclamation-triangle approvalflow-alert__icon" aria-hidden="true"></i>
            <div class="approvalflow-alert__body">
                <?php echo html_escape(_l('approvalflow_settings_all_off_warning')); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row approvalflow-settings-row approvalflow-settings-row--top">

        <!-- Card 1: Document types -->
        <div class="col-md-4 col-sm-12">
            <div class="approvalflow-card approvalflow-settings-card">
                <div class="approvalflow-card__header">
                    <h6>
                        <i class="fa fa-folder-open" aria-hidden="true"></i>
                        <?php echo html_escape(_l('approvalflow_setting_enabled_entities')); ?>
                    </h6>
                </div>
                <div class="approvalflow-card__body">
                    <p class="approvalflow-settings-hint">
                        <?php echo html_escape(_l('approvalflow_setting_enabled_entities_hint')); ?>
                    </p>
                    <div class="approvalflow-section-divider"><?php echo html_escape(_l('approvalflow_section_documents')); ?></div>
                    <div class="approvalflow-toggle-group">
                        <?php foreach ($entity_rows as $k => $icon): ?>
                            <div class="approvalflow-toggle-row">
                                <div class="approvalflow-toggle-row__label">
                                    <i class="fa <?php echo $icon; ?>" aria-hidden="true"></i>
                                    <?php echo html_escape(_l('approvalflow_setting_enable_' . $k)); ?>
                                </div>
                                <label class="approvalflow-switch" aria-label="<?php echo html_escape(_l('approvalflow_setting_enable_' . $k)); ?>">
                                    <input type="hidden" name="settings[approvalflow_enable_<?php echo $k; ?>]" value="0">
                                    <input type="checkbox" name="settings[approvalflow_enable_<?php echo $k; ?>]" value="1" <?php echo $opts['enable_' . $k] ? 'checked' : ''; ?>>
                                    <span class="approvalflow-switch__slider"></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Notifications -->
        <div class="col-md-4 col-sm-12">
            <div class="approvalflow-card approvalflow-settings-card">
                <div class="approvalflow-card__header">
                    <h6>
                        <i class="fa fa-bell" aria-hidden="true"></i>
                        <?php echo html_escape(_l('approvalflow_setting_notifications')); ?>
                    </h6>
                </div>
                <div class="approvalflow-card__body">
                    <p class="approvalflow-settings-hint">
                        <?php echo html_escape(_l('approvalflow_setting_notifications_hint')); ?>
                    </p>
                    <div class="approvalflow-section-divider"><?php echo html_escape(_l('approvalflow_section_channels')); ?></div>
                    <div class="approvalflow-toggle-group">
                        <div class="approvalflow-toggle-row">
                            <div class="approvalflow-toggle-row__label">
                                <i class="fa fa-bell-o" aria-hidden="true"></i>
                                <strong><?php echo html_escape(_l('approvalflow_setting_notifications_internal')); ?></strong>
                            </div>
                            <label class="approvalflow-switch" aria-label="<?php echo html_escape(_l('approvalflow_setting_notifications_internal')); ?>">
                                <input type="hidden" name="settings[approvalflow_notifications_internal]" value="0">
                                <input type="checkbox" name="settings[approvalflow_notifications_internal]" value="1" <?php echo $opts['notifications_internal'] ? 'checked' : ''; ?>>
                                <span class="approvalflow-switch__slider"></span>
                            </label>
                        </div>
                        <div class="approvalflow-toggle-row">
                            <div class="approvalflow-toggle-row__label">
                                <i class="fa fa-envelope-o" aria-hidden="true"></i>
                                <strong><?php echo html_escape(_l('approvalflow_setting_notifications_email')); ?></strong>
                            </div>
                            <label class="approvalflow-switch" aria-label="<?php echo html_escape(_l('approvalflow_setting_notifications_email')); ?>">
                                <input type="hidden" name="settings[approvalflow_notifications_email]" value="0">
                                <input type="checkbox" name="settings[approvalflow_notifications_email]" value="1" <?php echo $opts['notifications_email'] ? 'checked' : ''; ?>>
                                <span class="approvalflow-switch__slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: Behavior -->
        <div class="col-md-4 col-sm-12">
            <div class="approvalflow-card approvalflow-settings-card">
                <div class="approvalflow-card__header">
                    <h6>
                        <i class="fa fa-cogs" aria-hidden="true"></i>
                        <?php echo html_escape(_l('approvalflow_setting_behavior')); ?>
                    </h6>
                </div>
                <div class="approvalflow-card__body">
                    <p class="approvalflow-settings-hint">
                        <?php echo html_escape(_l('approvalflow_setting_behavior_hint')); ?>
                    </p>
                    <div class="approvalflow-section-divider"><?php echo html_escape(_l('approvalflow_section_rules_audit')); ?></div>
                    <div class="approvalflow-toggle-group">
                        <div class="approvalflow-toggle-row">
                            <div class="approvalflow-toggle-row__label">
                                <i class="fa fa-comment-o" aria-hidden="true"></i>
                                <strong><?php echo html_escape(_l('approvalflow_setting_require_reject_comment')); ?></strong>
                                <span class="approvalflow-toggle-row__sub">
                                    <?php echo html_escape(_l('approvalflow_setting_require_reject_comment_hint')); ?>
                                </span>
                            </div>
                            <label class="approvalflow-switch" aria-label="<?php echo html_escape(_l('approvalflow_setting_require_reject_comment')); ?>">
                                <input type="hidden" name="settings[approvalflow_require_reject_comment]" value="0">
                                <input type="checkbox" name="settings[approvalflow_require_reject_comment]" value="1" <?php echo $opts['require_reject_comment'] ? 'checked' : ''; ?>>
                                <span class="approvalflow-switch__slider"></span>
                            </label>
                        </div>
                        <div class="approvalflow-toggle-row">
                            <div class="approvalflow-toggle-row__label">
                                <i class="fa fa-exclamation-triangle approvalflow-text-warning" aria-hidden="true"></i>
                                <strong><?php echo html_escape(_l('approvalflow_setting_admin_auto_approve')); ?></strong>
                                <span class="approvalflow-toggle-row__sub">
                                    <?php echo html_escape(_l('approvalflow_setting_admin_auto_approve_hint')); ?>
                                </span>
                            </div>
                            <label class="approvalflow-switch" aria-label="<?php echo html_escape(_l('approvalflow_setting_admin_auto_approve')); ?>">
                                <input type="hidden" name="settings[approvalflow_admin_auto_approve]" value="0">
                                <input type="checkbox" name="settings[approvalflow_admin_auto_approve]" value="1" <?php echo $opts['admin_auto_approve'] ? 'checked' : ''; ?>>
                                <span class="approvalflow-switch__slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="approvalflow-section-divider"><?php echo html_escape(_l('approvalflow_section_reminders')); ?></div>
                    <div class="approvalflow-settings-field">
                        <label class="approvalflow-form-label" for="approvalflow_reminder_days">
                            <i class="fa fa-clock-o" aria-hidden="true"></i>
                            <?php echo html_escape(_l('approvalflow_setting_reminder_days')); ?>
                        </label>
                        <input type="number"
                               id="approvalflow_reminder_days"
                               name="settings[approvalflow_reminder_days]"
                               class="approvalflow-form-control approvalflow-form-control--narrow"
                               min="0" max="30"
                               value="<?php echo (int) $opts['reminder_days']; ?>">
                        <small class="approvalflow-form-hint">
                            <?php echo html_escape(_l('approvalflow_setting_reminder_help')); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.row top -->

    <!-- ═══════════════════════════════════════════════════════════════
         Approval Pulse (v1.0.2) — risk score, SLA/escalation, digest.
         All controls ship OFF; enabling them is fully opt-in.
         ══════════════════════════════════════════════════════════════ -->
    <div class="row approvalflow-settings-row approvalflow-settings-row--pulse">
        <div class="col-md-12">
            <div class="approvalflow-card approvalflow-settings-card approvalflow-settings-card--pulse">
                <div class="approvalflow-card__header">
                    <h6>
                        <i class="fa fa-heartbeat" aria-hidden="true"></i>
                        <?php echo html_escape(_l('approvalflow_setting_pulse')); ?>
                    </h6>
                </div>
                <div class="approvalflow-card__body">
                    <p class="approvalflow-settings-hint">
                        <?php echo html_escape(_l('approvalflow_setting_pulse_hint')); ?>
                    </p>

                    <div class="approvalflow-advanced-grid">

                        <!-- Col A: Risk score -->
                        <div class="approvalflow-advanced-col">
                            <div class="approvalflow-section-divider"><?php echo html_escape(_l('approvalflow_section_risk')); ?></div>
                            <div class="approvalflow-toggle-group">
                                <div class="approvalflow-toggle-row">
                                    <div class="approvalflow-toggle-row__label">
                                        <i class="fa fa-shield" aria-hidden="true"></i>
                                        <strong><?php echo html_escape(_l('approvalflow_setting_risk_enabled')); ?></strong>
                                        <span class="approvalflow-toggle-row__sub">
                                            <?php echo html_escape(_l('approvalflow_setting_risk_enabled_hint')); ?>
                                        </span>
                                    </div>
                                    <label class="approvalflow-switch" aria-label="<?php echo html_escape(_l('approvalflow_setting_risk_enabled')); ?>">
                                        <input type="hidden" name="settings[approvalflow_risk_enabled]" value="0">
                                        <input type="checkbox" name="settings[approvalflow_risk_enabled]" value="1" <?php echo $opts['risk_enabled'] ? 'checked' : ''; ?>>
                                        <span class="approvalflow-switch__slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="approvalflow-settings-field">
                                <label class="approvalflow-form-label" for="approvalflow_risk_amount_ceiling">
                                    <i class="fa fa-money" aria-hidden="true"></i>
                                    <?php echo html_escape(_l('approvalflow_setting_risk_ceiling')); ?>
                                </label>
                                <input type="number"
                                       id="approvalflow_risk_amount_ceiling"
                                       name="settings[approvalflow_risk_amount_ceiling]"
                                       class="approvalflow-form-control approvalflow-form-control--narrow"
                                       min="1" step="1"
                                       value="<?php echo (int) $opts['risk_amount_ceiling']; ?>">
                                <small class="approvalflow-form-hint">
                                    <?php echo html_escape(_l('approvalflow_setting_risk_ceiling_hint')); ?>
                                </small>
                            </div>
                        </div>

                        <!-- Col B: SLA + escalation -->
                        <div class="approvalflow-advanced-col">
                            <div class="approvalflow-section-divider"><?php echo html_escape(_l('approvalflow_section_sla')); ?></div>
                            <div class="approvalflow-settings-field">
                                <label class="approvalflow-form-label" for="approvalflow_sla_hours">
                                    <i class="fa fa-hourglass-end" aria-hidden="true"></i>
                                    <?php echo html_escape(_l('approvalflow_setting_sla_hours')); ?>
                                </label>
                                <input type="number"
                                       id="approvalflow_sla_hours"
                                       name="settings[approvalflow_sla_hours]"
                                       class="approvalflow-form-control approvalflow-form-control--narrow"
                                       min="0" max="8760" step="1"
                                       value="<?php echo (int) $opts['sla_hours']; ?>">
                                <small class="approvalflow-form-hint">
                                    <?php echo html_escape(_l('approvalflow_setting_sla_hours_hint')); ?>
                                </small>
                            </div>
                            <div class="approvalflow-settings-field">
                                <label class="approvalflow-form-label" for="approvalflow_escalate_to">
                                    <i class="fa fa-level-up" aria-hidden="true"></i>
                                    <?php echo html_escape(_l('approvalflow_setting_escalate_to')); ?>
                                </label>
                                <select id="approvalflow_escalate_to"
                                        name="settings[approvalflow_escalate_to]"
                                        class="approvalflow-form-control">
                                    <option value=""><?php echo html_escape(_l('approvalflow_setting_escalate_none')); ?></option>
                                    <?php foreach ($af_staff as $st): ?>
                                        <option value="<?php echo (int) $st['staffid']; ?>" <?php echo $opts['escalate_to'] === (int) $st['staffid'] ? 'selected' : ''; ?>>
                                            <?php echo html_escape(trim($st['firstname'] . ' ' . $st['lastname'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="approvalflow-form-hint">
                                    <?php echo html_escape(_l('approvalflow_setting_escalate_to_hint')); ?>
                                </small>
                            </div>
                        </div>

                        <!-- Col C: Email digest -->
                        <div class="approvalflow-advanced-col">
                            <div class="approvalflow-section-divider"><?php echo html_escape(_l('approvalflow_section_digest')); ?></div>
                            <div class="approvalflow-toggle-group">
                                <div class="approvalflow-toggle-row">
                                    <div class="approvalflow-toggle-row__label">
                                        <i class="fa fa-envelope-open-o" aria-hidden="true"></i>
                                        <strong><?php echo html_escape(_l('approvalflow_setting_digest_enabled')); ?></strong>
                                        <span class="approvalflow-toggle-row__sub">
                                            <?php echo html_escape(_l('approvalflow_setting_digest_enabled_hint')); ?>
                                        </span>
                                    </div>
                                    <label class="approvalflow-switch" aria-label="<?php echo html_escape(_l('approvalflow_setting_digest_enabled')); ?>">
                                        <input type="hidden" name="settings[approvalflow_digest_enabled]" value="0">
                                        <input type="checkbox" name="settings[approvalflow_digest_enabled]" value="1" <?php echo $opts['digest_enabled'] ? 'checked' : ''; ?>>
                                        <span class="approvalflow-switch__slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="approvalflow-settings-field">
                                <label class="approvalflow-form-label" for="approvalflow_digest_freq">
                                    <i class="fa fa-repeat" aria-hidden="true"></i>
                                    <?php echo html_escape(_l('approvalflow_setting_digest_freq')); ?>
                                </label>
                                <select id="approvalflow_digest_freq"
                                        name="settings[approvalflow_digest_freq]"
                                        class="approvalflow-form-control">
                                    <option value="daily"  <?php echo $opts['digest_freq'] === 'daily' ? 'selected' : ''; ?>><?php echo html_escape(_l('approvalflow_digest_freq_daily')); ?></option>
                                    <option value="weekly" <?php echo $opts['digest_freq'] !== 'daily' ? 'selected' : ''; ?>><?php echo html_escape(_l('approvalflow_digest_freq_weekly')); ?></option>
                                </select>
                            </div>
                            <div class="approvalflow-settings-field">
                                <label class="approvalflow-form-label" for="approvalflow_digest_day">
                                    <i class="fa fa-calendar" aria-hidden="true"></i>
                                    <?php echo html_escape(_l('approvalflow_setting_digest_day')); ?>
                                </label>
                                <select id="approvalflow_digest_day"
                                        name="settings[approvalflow_digest_day]"
                                        class="approvalflow-form-control">
                                    <?php
                                        $af_days = [1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday', 7 => 'sunday'];
                                        foreach ($af_days as $dnum => $dkey):
                                    ?>
                                        <option value="<?php echo $dnum; ?>" <?php echo $opts['digest_day'] === $dnum ? 'selected' : ''; ?>><?php echo html_escape(_l('approvalflow_day_' . $dkey)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="approvalflow-form-hint">
                                    <?php echo html_escape(_l('approvalflow_setting_digest_day_hint')); ?>
                                </small>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div><!-- /.row pulse -->

    <!-- Divider between basic config and advanced -->
    <div class="approvalflow-settings-divider" aria-hidden="true">
        <span class="approvalflow-settings-divider__label">
            <i class="fa fa-sliders" aria-hidden="true"></i>
            <?php echo html_escape(_l('approvalflow_setting_advanced')); ?>
        </span>
    </div>

    <div class="row approvalflow-settings-row approvalflow-settings-row--advanced">
        <!-- Card 4: Advanced (full-width) -->
        <div class="col-md-12">
            <div class="approvalflow-card approvalflow-settings-card approvalflow-settings-card--advanced">
                <div class="approvalflow-card__header">
                    <h6>
                        <i class="fa fa-database" aria-hidden="true"></i>
                        <?php echo html_escape(_l('approvalflow_setting_advanced')); ?>
                    </h6>
                </div>
                <div class="approvalflow-card__body approvalflow-settings-card--advanced__body">
                    <p class="approvalflow-settings-hint">
                        <?php echo html_escape(_l('approvalflow_setting_advanced_hint')); ?>
                    </p>

                    <div class="approvalflow-advanced-grid">
                        <!-- Col A: Diagnostics -->
                        <div class="approvalflow-advanced-col">
                            <div class="approvalflow-section-divider"><?php echo html_escape(_l('approvalflow_section_diagnostics')); ?></div>
                            <div class="approvalflow-toggle-group">
                                <div class="approvalflow-toggle-row">
                                    <div class="approvalflow-toggle-row__label">
                                        <i class="fa fa-bug" aria-hidden="true"></i>
                                        <strong><?php echo html_escape(_l('approvalflow_setting_debug')); ?></strong>
                                        <span class="approvalflow-toggle-row__sub">
                                            <?php echo html_escape(_l('approvalflow_setting_debug_hint')); ?>
                                        </span>
                                    </div>
                                    <label class="approvalflow-switch" aria-label="<?php echo html_escape(_l('approvalflow_setting_debug')); ?>">
                                        <input type="hidden" name="settings[approvalflow_debug_enabled]" value="0">
                                        <input type="checkbox" name="settings[approvalflow_debug_enabled]" value="1" <?php echo $opts['debug_enabled'] ? 'checked' : ''; ?>>
                                        <span class="approvalflow-switch__slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Col B: Retention -->
                        <div class="approvalflow-advanced-col">
                            <div class="approvalflow-section-divider"><?php echo html_escape(_l('approvalflow_section_retention')); ?></div>
                            <div class="approvalflow-settings-field">
                                <label class="approvalflow-form-label" for="approvalflow_logs_retention_days">
                                    <i class="fa fa-archive" aria-hidden="true"></i>
                                    <?php echo html_escape(_l('approvalflow_setting_logs_retention')); ?>
                                </label>
                                <input type="number"
                                       id="approvalflow_logs_retention_days"
                                       name="settings[approvalflow_logs_retention_days]"
                                       class="approvalflow-form-control approvalflow-form-control--narrow"
                                       min="0" max="365"
                                       value="<?php echo (int) $opts['logs_retention_days']; ?>">
                                <small class="approvalflow-form-hint">
                                    <?php echo html_escape(_l('approvalflow_setting_logs_retention_hint')); ?>
                                </small>
                            </div>
                        </div>

                        <!-- Col C: Shortcuts -->
                        <div class="approvalflow-advanced-col">
                            <div class="approvalflow-section-divider"><?php echo html_escape(_l('approvalflow_section_shortcuts')); ?></div>
                            <div class="approvalflow-quick-links">
                                <a href="<?php echo admin_url('approvalflow'); ?>" class="btn btn-default btn-sm">
                                    <i class="fa fa-tachometer" aria-hidden="true"></i>
                                    <?php echo html_escape(_l('approvalflow_open_dashboard')); ?>
                                </a>
                                <a href="<?php echo admin_url('approvalflow/pending'); ?>" class="btn btn-default btn-sm">
                                    <i class="fa fa-inbox" aria-hidden="true"></i>
                                    <?php echo html_escape(_l('approvalflow_view_pending')); ?>
                                </a>
                                <a href="<?php echo admin_url('approvalflow/rules'); ?>" class="btn btn-default btn-sm">
                                    <i class="fa fa-list" aria-hidden="true"></i>
                                    <?php echo html_escape(_l('approvalflow_manage_rules')); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- No save button here. Persistence is wired through the parent
         Perfex settings form (`<form id="settings-form">`) whose "Save
         Settings" button at the bottom of the screen POSTs every
         settings[*] input to Settings_model::update(). -->
</div>
