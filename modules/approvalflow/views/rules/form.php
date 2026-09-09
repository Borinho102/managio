<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * ApprovalFlow — Rule create / edit form
 *
 * Houses the dynamic condition builder used by both "New rule" and
 * "Edit rule" URLs. UX features:
 *   - Entity selector filters the available condition types so the
 *     UI never offers e.g. expense_category for an invoice rule.
 *   - Right-hand "Preview" card translates the in-progress rule into
 *     plain language, updated live by JS.
 *   - FK conditions (client/staff/contract_type/expense_category) use
 *     searchable dropdowns built from pre-loaded option lists — no
 *     extra AJAX trip when the form opens.
 *
 * Inputs (set by Approvalflow::rule_form()):
 *   $title              page title (create / edit)
 *   $rule               existing rule row when $is_edit, null otherwise
 *   $is_edit            boolean — controls form action + button labels
 *   $staff_options      active staff for the approver dropdown
 *   $condition_options  pre-loaded FK option lists
 *
 * @package ApprovalFlow
 * @since   1.0.0
 */
// Normalise inputs — keeps the markup body free of null checks
$is_edit          = !empty($is_edit);
$rule             = $rule ?? null;
$rule_name        = $rule ? $rule['name'] : '';
$rule_entity_type = $rule ? $rule['entity_type'] : '';
$rule_approver    = $rule ? (int) $rule['approver_staff_id'] : 0;
$rule_priority    = $rule ? (int) $rule['priority'] : 10;
$rule_active      = $rule ? (int) $rule['active'] : 1;
$rule_conditions  = $rule ? ($rule['conditions_array'] ?? []) : [];
$rule_extra_levels = $rule ? ($rule['extra_levels'] ?? []) : [];
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content-page approvalflow-page">
        <div class="container-fluid">
            <div class="approvalflow-app">

                <div class="approvalflow-hero approvalflow-hero--compact">
                    <div class="approvalflow-hero__icon">
                        <i class="fa fa-cog" aria-hidden="true"></i>
                    </div>
                    <div class="approvalflow-hero__body">
                        <div class="approvalflow-hero__eyebrow"><?php echo html_escape(_l('approvalflow_module_eyebrow')); ?></div>
                        <h4><?php echo html_escape($title); ?></h4>
                    </div>
                    <div class="approvalflow-hero__actions">
                        <a href="<?php echo admin_url('approvalflow/rules'); ?>" class="btn btn-default">
                            <?php echo html_escape(_l('approvalflow_back')); ?>
                        </a>
                    </div>
                </div>

                <?php $this->load->view('admin/includes/alerts'); ?>

                <!-- ═════════════════════════════════════════════════════
                     Rule form — POSTs back to the same controller action.
                     CSRF token is injected via form_hidden() so the
                     middleware accepts the submission.
                     ════════════════════════════════════════════════════ -->
                <form method="post" action="<?php echo admin_url('approvalflow/rule_form' . ($is_edit ? '/' . (int) $rule['id'] : '')); ?>" id="approvalflowRuleForm" class="approvalflow-rule-form">
                    <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="approvalflow-card">
                                <div class="approvalflow-card__body">

                                    <!-- Active toggle -->
                                    <div class="approvalflow-form-row approvalflow-form-row--inline">
                                        <label class="approvalflow-switch">
                                            <input type="checkbox" name="active" value="1" <?php echo $rule_active ? 'checked' : ''; ?>>
                                            <span class="approvalflow-switch__slider"></span>
                                        </label>
                                        <span class="approvalflow-form-label-inline"><?php echo html_escape(_l('approvalflow_rule_active')); ?></span>
                                    </div>

                                    <!-- Name -->
                                    <div class="approvalflow-form-row">
                                        <label class="approvalflow-form-label" for="approvalflowRuleName">
                                            <?php echo html_escape(_l('approvalflow_rule_name')); ?> *
                                        </label>
                                        <input type="text"
                                               id="approvalflowRuleName"
                                               name="name"
                                               class="approvalflow-form-control"
                                               required
                                               maxlength="150"
                                               value="<?php echo html_escape($rule_name); ?>">
                                    </div>

                                    <!-- Entity type -->
                                    <div class="approvalflow-form-row">
                                        <label class="approvalflow-form-label" for="approvalflowRuleEntity">
                                            <?php echo html_escape(_l('approvalflow_rule_entity')); ?> *
                                        </label>
                                        <select id="approvalflowRuleEntity"
                                                name="entity_type"
                                                class="approvalflow-form-control"
                                                required>
                                            <option value=""><?php echo html_escape(_l('approvalflow_rule_select_entity_placeholder')); ?></option>
                                            <?php foreach (['invoice','estimate','proposal','contract','expense'] as $et): ?>
                                                <option value="<?php echo $et; ?>" <?php echo $rule_entity_type === $et ? 'selected' : ''; ?>>
                                                    <?php echo html_escape(_l('approvalflow_entity_' . $et)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Conditions (dynamic) -->
                                    <div class="approvalflow-form-row">
                                        <label class="approvalflow-form-label">
                                            <?php echo html_escape(_l('approvalflow_rule_conditions')); ?>
                                        </label>
                                        <div id="approvalflowConditions" class="approvalflow-conditions"></div>
                                        <button type="button" id="approvalflowAddCondition" class="btn btn-default approvalflow-btn-add-condition">
                                            <i class="fa fa-plus" aria-hidden="true"></i>
                                            <?php echo html_escape(_l('approvalflow_rule_add_condition')); ?>
                                        </button>
                                        <small class="approvalflow-form-hint">
                                            <?php echo html_escape(_l('approvalflow_rule_conditions_help')); ?>
                                        </small>
                                    </div>

                                    <!-- Approver -->
                                    <div class="approvalflow-form-row">
                                        <label class="approvalflow-form-label" for="approvalflowRuleApprover">
                                            <?php echo html_escape(_l('approvalflow_rule_approver')); ?> *
                                        </label>
                                        <select id="approvalflowRuleApprover"
                                                name="approver_staff_id"
                                                class="approvalflow-form-control"
                                                required>
                                            <option value=""><?php echo html_escape(_l('approvalflow_rule_select_approver_placeholder')); ?></option>
                                            <?php foreach ($staff_options as $s): ?>
                                                <?php $full = trim($s['firstname'] . ' ' . $s['lastname']); ?>
                                                <option value="<?php echo (int) $s['staffid']; ?>" <?php echo $rule_approver === (int) $s['staffid'] ? 'selected' : ''; ?>>
                                                    <?php echo html_escape($full); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- ─────────────────────────────────────────
                                         Multi-level approvals (level 2, 3, …)
                                         Each entry added here becomes a sequential
                                         approver who is notified only after the
                                         previous level approves.
                                         ──────────────────────────────────────── -->
                                    <div class="approvalflow-form-row" id="approvalflowLevelsSection">
                                        <label class="approvalflow-form-label">
                                            <?php echo html_escape(_l('approvalflow_rule_approval_levels')); ?>
                                        </label>

                                        <!-- Level 1 (read-only indicator) -->
                                        <div class="approvalflow-level-item approvalflow-level-item--fixed">
                                            <span class="approvalflow-level-badge"><?php echo html_escape(_l('approvalflow_rule_level_1')); ?></span>
                                            <span class="approvalflow-level-approver-label">
                                                <?php echo html_escape(_l('approvalflow_rule_approver')); ?>
                                                <em class="approvalflow-level-hint">(<?php echo html_escape(_l('approvalflow_rule_level_1_hint')); ?>)</em>
                                            </span>
                                        </div>

                                        <!-- Dynamic extra levels -->
                                        <div id="approvalflowExtraLevels">
                                            <?php foreach ($rule_extra_levels as $lvl): ?>
                                                <div class="approvalflow-level-item approvalflow-level-extra">
                                                    <span class="approvalflow-level-badge">
                                                        <?php echo sprintf(html_escape(_l('approvalflow_rule_level_n')), (int) $lvl['level']); ?>
                                                    </span>
                                                    <select name="extra_levels[]"
                                                            class="approvalflow-form-control approvalflow-level-select"
                                                            required>
                                                        <option value=""><?php echo html_escape(_l('approvalflow_rule_select_approver_placeholder')); ?></option>
                                                        <?php foreach ($staff_options as $s): ?>
                                                            <?php $full = trim($s['firstname'] . ' ' . $s['lastname']); ?>
                                                            <option value="<?php echo (int) $s['staffid']; ?>"
                                                                <?php echo (int) $lvl['approver_staff_id'] === (int) $s['staffid'] ? 'selected' : ''; ?>>
                                                                <?php echo html_escape($full); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="button" class="btn btn-xs btn-danger approvalflow-level-remove"
                                                            title="<?php echo html_escape(_l('approvalflow_rule_remove_level')); ?>">
                                                        <i class="fa fa-trash" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <button type="button" id="approvalflowAddLevel" class="btn btn-default approvalflow-btn-add-condition" style="margin-top:6px;">
                                            <i class="fa fa-plus" aria-hidden="true"></i>
                                            <?php echo html_escape(_l('approvalflow_rule_add_level')); ?>
                                        </button>
                                        <small class="approvalflow-form-hint">
                                            <?php echo html_escape(_l('approvalflow_rule_levels_help')); ?>
                                        </small>
                                    </div>

                                    <!-- Advanced (priority) -->
                                    <details class="approvalflow-advanced">
                                        <summary><?php echo html_escape(_l('approvalflow_rule_advanced_options')); ?></summary>
                                        <div class="approvalflow-form-row">
                                            <label class="approvalflow-form-label" for="approvalflowRulePriority">
                                                <?php echo html_escape(_l('approvalflow_rule_priority')); ?>
                                            </label>
                                            <input type="number"
                                                   id="approvalflowRulePriority"
                                                   name="priority"
                                                   class="approvalflow-form-control approvalflow-form-control--narrow"
                                                   min="1" max="32000"
                                                   value="<?php echo (int) $rule_priority; ?>">
                                            <small class="approvalflow-form-hint">
                                                <?php echo html_escape(_l('approvalflow_rule_priority_help')); ?>
                                            </small>
                                        </div>
                                    </details>

                                </div>
                            </div>
                        </div>

                        <!-- ═════════════════════════════════════════════
                             Sticky live preview (right column)
                             ════════════════════════════════════════════ -->
                        <div class="col-md-4">
                            <div class="approvalflow-card approvalflow-preview-card">
                                <div class="approvalflow-card__header">
                                    <h6><i class="fa fa-eye" aria-hidden="true"></i>
                                        <?php echo html_escape(_l('approvalflow_rule_preview_heading')); ?>
                                    </h6>
                                </div>
                                <div class="approvalflow-card__body">
                                    <p id="approvalflowPreview" class="approvalflow-preview-text">
                                        <?php echo html_escape(_l('approvalflow_rule_preview_pending')); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="approvalflow-form-actions">
                        <a href="<?php echo admin_url('approvalflow/rules'); ?>" class="btn btn-default">
                            <?php echo html_escape(_l('approvalflow_cancel')); ?>
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-check" aria-hidden="true"></i>
                            <?php echo html_escape(_l('approvalflow_save_rule')); ?>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php
// Build the i18n payload server-side; the inline <script> stays ≤30 lines.
$approvalflow_rule_lang = [
    'select_entity_first'           => _l('approvalflow_rule_select_entity_first'),
    'no_conditions_warning'         => _l('approvalflow_rule_no_conditions_warning'),
    'preview_template'              => _l('approvalflow_rule_preview_template'),
    'preview_no_conditions'         => _l('approvalflow_rule_preview_no_conditions'),
    'preview_pending'               => _l('approvalflow_rule_preview_pending'),
    'remove'                        => _l('approvalflow_rule_remove_condition'),
    'rule_conditions'               => _l('approvalflow_rule_conditions'),
    'and'                           => _l('approvalflow_and'),
    'condition_amount_gt'           => _l('approvalflow_condition_amount_gt'),
    'condition_discount_percent_gt' => _l('approvalflow_condition_discount_percent_gt'),
    'condition_client'              => _l('approvalflow_condition_client'),
    'condition_staff'               => _l('approvalflow_condition_staff'),
    'condition_status'              => _l('approvalflow_condition_status'),
    'condition_contract_type'       => _l('approvalflow_condition_contract_type'),
    'condition_expense_category'    => _l('approvalflow_condition_expense_category'),
    'entity_invoice'                => _l('approvalflow_entity_invoice'),
    'entity_estimate'               => _l('approvalflow_entity_estimate'),
    'entity_proposal'               => _l('approvalflow_entity_proposal'),
    'entity_contract'               => _l('approvalflow_entity_contract'),
    'entity_expense'                => _l('approvalflow_entity_expense'),
];
?>
<?php init_tail(); ?>
<script>
window.ApprovalFlowConfig = {
    csrf_token_name: '<?php echo $this->security->get_csrf_token_name(); ?>',
    csrf_hash: '<?php echo $this->security->get_csrf_hash(); ?>',
    rule: { existing_conditions: <?php echo json_encode($rule_conditions); ?> },
    options: <?php echo json_encode($condition_options ?? new stdClass(), JSON_UNESCAPED_UNICODE); ?>,
    lang: <?php echo json_encode($approvalflow_rule_lang, JSON_UNESCAPED_UNICODE); ?>,
    staff_options: <?php echo json_encode(array_map(function($s) {
        return ['id' => (int) $s['staffid'], 'label' => trim($s['firstname'] . ' ' . $s['lastname'])];
    }, $staff_options), JSON_UNESCAPED_UNICODE); ?>,
    lang_level_n: <?php echo json_encode(_l('approvalflow_rule_level_n'), JSON_UNESCAPED_UNICODE); ?>,
    lang_level_placeholder: <?php echo json_encode(_l('approvalflow_rule_select_approver_placeholder'), JSON_UNESCAPED_UNICODE); ?>,
    lang_remove_level: <?php echo json_encode(_l('approvalflow_rule_remove_level'), JSON_UNESCAPED_UNICODE); ?>
};

// ── Multi-level approval builder
(function () {
    var container     = document.getElementById('approvalflowExtraLevels');
    var addBtn        = document.getElementById('approvalflowAddLevel');
    var level1Select  = document.getElementById('approvalflowRuleApprover');
    var cfg           = window.ApprovalFlowConfig;
    var MAX_LEVELS    = 10;

    function countExtra() { return container.querySelectorAll('.approvalflow-level-extra').length; }

    function levelNumber() { return countExtra() + 2; }

    function buildSelect(selectedId) {
        var sel = document.createElement('select');
        sel.name      = 'extra_levels[]';
        sel.className = 'approvalflow-form-control approvalflow-level-select';
        sel.required  = true;
        var blank = document.createElement('option');
        blank.value = '';
        blank.textContent = cfg.lang_level_placeholder;
        sel.appendChild(blank);
        cfg.staff_options.forEach(function (s) {
            var opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.label;
            if (s.id === selectedId) { opt.selected = true; }
            sel.appendChild(opt);
        });
        return sel;
    }

    function addLevelRow(selectedId) {
        if (countExtra() >= MAX_LEVELS - 1) { return; }
        var num  = levelNumber();
        var item = document.createElement('div');
        item.className = 'approvalflow-level-item approvalflow-level-extra';

        var badge = document.createElement('span');
        badge.className = 'approvalflow-level-badge';
        badge.textContent = cfg.lang_level_n.replace('%d', num);

        var removeBtn = document.createElement('button');
        removeBtn.type      = 'button';
        removeBtn.className = 'btn btn-xs btn-danger approvalflow-level-remove';
        removeBtn.title     = cfg.lang_remove_level;
        removeBtn.innerHTML = '<i class="fa fa-trash" aria-hidden="true"></i>';
        removeBtn.addEventListener('click', function () {
            item.parentNode.removeChild(item);
            renumberLevels();
        });

        item.appendChild(badge);
        item.appendChild(buildSelect(selectedId || 0));
        item.appendChild(removeBtn);
        container.appendChild(item);
    }

    function renumberLevels() {
        var items = container.querySelectorAll('.approvalflow-level-extra');
        items.forEach(function (item, idx) {
            var badge = item.querySelector('.approvalflow-level-badge');
            if (badge) { badge.textContent = cfg.lang_level_n.replace('%d', idx + 2); }
        });
    }

    if (addBtn) {
        addBtn.addEventListener('click', function () { addLevelRow(0); });
    }

    // Remove buttons already in DOM (edit mode with existing levels)
    document.querySelectorAll('.approvalflow-level-remove').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var item = btn.closest('.approvalflow-level-extra');
            if (item) { item.parentNode.removeChild(item); renumberLevels(); }
        });
    });
}());
</script>
</body>
</html>
