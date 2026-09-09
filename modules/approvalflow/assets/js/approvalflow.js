"use strict";
/**
 * ApprovalFlow — Frontend
 *
 * Single-file IIFE that exposes a small API on window.ApprovalFlow.
 * Modules:
 *   - Core       — CSRF token helper, JSON POST wrapper, toast helpers
 *   - PendingList— inline approve / reject + bulk approve + view modal
 *   - RuleForm   — dynamic condition builder + live preview
 *   - Rules      — toggle active + delete confirmation
 *
 * Loaded via $this->app_scripts->add() in the controller. All AJAX
 * endpoints expect the Perfex CSRF token; we get it from the global
 * window.ApprovalFlowConfig injected by each view.
 *
 * @package ApprovalFlow
 */
(function () {
    if (typeof window.jQuery === "undefined") return;
    var $ = window.jQuery;

    /* ════════════════════════════════════════════════════════════════
     * Core helpers — CSRF token, JSON POST wrapper, alert shortcuts.
     * Every $.post() goes through here so we never forget the token.
     * ════════════════════════════════════════════════════════════════ */
    var Core = {
        cfg: function () { return window.ApprovalFlowConfig || {}; },
        csrf: function (extra) {
            var c = Core.cfg();
            var data = extra || {};
            if (c.csrf_token_name && c.csrf_hash) {
                data[c.csrf_token_name] = c.csrf_hash;
            }
            return data;
        },
        post: function (url, payload) {
            return $.ajax({
                url: url,
                method: "POST",
                data: Core.csrf(payload),
                dataType: "json"
            }).done(function (resp) {
                if (resp && resp.csrf_hash) {
                    window.ApprovalFlowConfig.csrf_hash = resp.csrf_hash;
                }
            });
        },
        toastSuccess: function (msg) {
            if (typeof window.alert_float === "function") window.alert_float("success", msg);
        },
        toastError: function (msg) {
            if (typeof window.alert_float === "function") window.alert_float("danger", msg);
        },
        toastWarning: function (msg) {
            if (typeof window.alert_float === "function") window.alert_float("warning", msg);
        }
    };

    /* ════════════════════════════════════════════════════════════════
     * PendingList — inline approve / reject + bulk approve.
     * Designed so the aprobador can process 15 pending requests in
     * < 20 clicks instead of 90-120 with redirect-style actions.
     * ════════════════════════════════════════════════════════════════ */
    var PendingList = {
        $table: null,
        $modal: null,
        currentRejectId: 0,

        init: function () {
            PendingList.$table = $("#approvalflowPendingTable");
            if (!PendingList.$table.length) return;

            PendingList.$modal = $("#approvalflowRejectModal");

            // Row-action delegation: approve, reject, view
            PendingList.$table.on("click", ".approvalflow-btn-action", function (e) {
                e.preventDefault();
                var $btn = $(this);
                var action = $btn.data("action");
                var id = parseInt($btn.data("id"), 10);
                if (!id) return;

                if (action === "approve")      PendingList.approve(id, $btn);
                else if (action === "reject")  PendingList.openRejectModal(id, $btn);
                else if (action === "view")    PendingList.openViewModal(id);
            });

            // Bulk-selection mechanics
            $("#approvalflowSelectAll").on("change", function () {
                var checked = this.checked;
                $(".approvalflow-row-check").prop("checked", checked).trigger("change");
            });
            PendingList.$table.on("change", ".approvalflow-row-check", PendingList.refreshBulkBar);

            $("#approvalflowBulkClear").on("click", function () {
                $(".approvalflow-row-check, #approvalflowSelectAll").prop("checked", false);
                PendingList.refreshBulkBar();
            });
            $("#approvalflowBulkApprove").on("click", PendingList.bulkApprove);

            // Reject modal wiring (textarea counter + submit gate)
            var $textarea = $("#approvalflowRejectComment");
            var $counter  = $("#approvalflowRejectCounter");
            var $submit   = $("#approvalflowRejectSubmit");
            $textarea.on("input", function () {
                var len = this.value.length;
                $counter.text(len + " / 500");
                var require = Core.cfg().require_reject_comment;
                $submit.prop("disabled", require && len < 3);
            });
            $submit.on("click", PendingList.submitReject);

            // Reset textarea on modal close so the next reject starts clean
            PendingList.$modal.on("hidden.bs.modal", function () {
                $textarea.val("");
                $counter.text("0 / 500");
                $submit.prop("disabled", true);
                PendingList.currentRejectId = 0;
            });

            // Approval Pulse (v1.0.2): explain-the-score tooltips + risk sort.
            // Both no-op when the risk column is absent (feature off).
            if ($.fn.tooltip) {
                PendingList.$table.find('[data-toggle="tooltip"]').tooltip({ container: "body" });
            }
            PendingList.$table.on("click", ".approvalflow-risk-th", function () {
                PendingList.sortByRisk();
            });
        },

        // Reorders the visible rows by their data-risk score. Toggles
        // descending/ascending on repeated clicks. Pure DOM reorder — no
        // server round trip (the page already holds the current rows).
        riskSortDesc: true,
        sortByRisk: function () {
            var $tbody = PendingList.$table.find("tbody");
            var rows = $tbody.find("tr").get();
            var desc = PendingList.riskSortDesc;
            rows.sort(function (a, b) {
                var ra = parseInt($(a).find(".approvalflow-risk-td").data("risk"), 10) || 0;
                var rb = parseInt($(b).find(".approvalflow-risk-td").data("risk"), 10) || 0;
                return desc ? rb - ra : ra - rb;
            });
            $.each(rows, function (i, row) { $tbody.append(row); });
            PendingList.riskSortDesc = !desc;
        },

        refreshBulkBar: function () {
            var selected = $(".approvalflow-row-check:checked").length;
            var $bar = $("#approvalflowBulkBar");
            if (selected > 0) {
                $bar.prop("hidden", false);
                var tpl = Core.cfg().lang.selected || "%d selected";
                $("#approvalflowBulkCount").text(tpl.replace("%d", selected));
            } else {
                $bar.prop("hidden", true);
            }
        },

        approve: function (id, $btn) {
            if (!window.confirm(Core.cfg().lang.confirm_approve || "Approve this request?")) return;
            PendingList.setBtnLoading($btn, true);

            var url = Core.cfg().urls.approve + id;
            Core.post(url, {})
                .done(function (resp) {
                    if (resp && resp.success) {
                        Core.toastSuccess(resp.message);
                        PendingList.removeRow(id);
                    } else {
                        Core.toastError((resp && resp.message) || Core.cfg().lang.load_failed);
                        PendingList.setBtnLoading($btn, false);
                    }
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || Core.cfg().lang.load_failed;
                    Core.toastError(msg);
                    PendingList.setBtnLoading($btn, false);
                });
        },

        openRejectModal: function (id, $btn) {
            PendingList.currentRejectId = id;
            var entityLabel = $btn.data("entity-label") || "";
            var amount = $btn.data("amount") || "";
            // XSS-safe: build with .text() instead of HTML concatenation
            var $row = $("<div class='approvalflow-modal-summary__row'></div>");
            $row.append($("<span class='approvalflow-modal-summary__label'></span>").text(entityLabel));
            $row.append($("<span class='approvalflow-modal-summary__value'></span>").text(amount));
            $("#approvalflowRejectSummary").empty().append($row);
            PendingList.$modal.modal("show");
        },

        submitReject: function () {
            var id = PendingList.currentRejectId;
            if (!id) return;
            var comment = $("#approvalflowRejectComment").val();
            var $submit = $("#approvalflowRejectSubmit");
            $submit.prop("disabled", true);
            $submit.find(".approvalflow-btn-label").text(Core.cfg().lang.processing);

            var url = Core.cfg().urls.reject + id;
            Core.post(url, { comment: comment })
                .done(function (resp) {
                    if (resp && resp.success) {
                        PendingList.$modal.modal("hide");
                        Core.toastSuccess(resp.message);
                        PendingList.removeRow(id);
                    } else {
                        Core.toastError((resp && resp.message) || Core.cfg().lang.load_failed);
                        $submit.prop("disabled", false);
                    }
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || Core.cfg().lang.load_failed;
                    Core.toastError(msg);
                    $submit.prop("disabled", false);
                });
        },

        bulkApprove: function () {
            var ids = $(".approvalflow-row-check:checked").map(function () { return parseInt(this.value, 10); }).get();
            if (!ids.length) {
                Core.toastWarning(Core.cfg().lang.no_selection);
                return;
            }
            var bodyTpl = Core.cfg().lang.bulk_confirm_body || "Approving %d requests.";
            var msg = bodyTpl.replace("%d", ids.length);
            var comment = window.prompt(msg, "") || "";
            // Cancel pressed: window.prompt returns null
            if (comment === null) return;

            var $btn = $("#approvalflowBulkApprove");
            $btn.prop("disabled", true);

            Core.post(Core.cfg().urls.bulk_approve, { ids: ids, comment: comment })
                .done(function (resp) {
                    if (resp && resp.success) {
                        Core.toastSuccess(resp.message);
                        // Remove every processed row
                        ids.forEach(function (id) {
                            if (!resp.failed_ids || resp.failed_ids.indexOf(id) === -1) {
                                PendingList.removeRow(id);
                            }
                        });
                    } else {
                        Core.toastError((resp && resp.message) || Core.cfg().lang.load_failed);
                    }
                    $btn.prop("disabled", false);
                })
                .fail(function () {
                    Core.toastError(Core.cfg().lang.load_failed);
                    $btn.prop("disabled", false);
                });
        },

        openViewModal: function (id) {
            var $modal = $("#approvalflowViewRequestModal");
            var $body  = $("#approvalflowViewRequestBody");
            $body.html("<div class='approvalflow-modal-loading'><span class='approvalflow-spinner'></span> " + (Core.cfg().lang.processing || "Loading…") + "</div>");
            $modal.modal("show");
            $.ajax({
                url: Core.cfg().urls.view_request + id,
                method: "GET",
                dataType: "html"
            }).done(function (html) {
                $body.html(html);
            }).fail(function () {
                $body.html("<div class='approvalflow-modal-loading'>" + Core.cfg().lang.load_failed + "</div>");
            });
        },

        removeRow: function (id) {
            var $row = PendingList.$table.find("tr[data-id='" + id + "']");
            $row.addClass("approvalflow-row--removing");
            window.setTimeout(function () {
                $row.remove();
                PendingList.refreshBulkBar();
                // If table is empty, show a soft message
                if (!PendingList.$table.find("tbody tr").length) {
                    PendingList.$table.closest(".approvalflow-card__body").html(
                        "<div class='approvalflow-empty'><div class='approvalflow-empty__icon approvalflow-empty__icon--success'><i class='fa fa-check-circle'></i></div><h5>" +
                        "&nbsp;</h5></div>"
                    );
                }
            }, 220);
        },

        setBtnLoading: function ($btn, loading) {
            if (loading) {
                $btn.prop("disabled", true).addClass("is-disabled");
            } else {
                $btn.prop("disabled", false).removeClass("is-disabled");
            }
        }
    };

    /* ════════════════════════════════════════════════════════════════
     * RuleForm — dynamic condition builder + live preview.
     * Each row exposes a <select> with the condition types available
     * for the current entity_type. Choosing a type swaps the input
     * (number for amounts and ids, text for status). Hidden naming
     * (name="conditions[<key>]") keeps the controller contract intact.
     * ════════════════════════════════════════════════════════════════ */
    var RuleForm = {
        ENTITY_CONDITIONS: {
            invoice:  ["amount_gt", "discount_percent_gt", "client_id", "staff_id", "status"],
            estimate: ["amount_gt", "discount_percent_gt", "client_id", "staff_id", "status"],
            proposal: ["amount_gt", "discount_percent_gt", "client_id", "staff_id", "status"],
            contract: ["amount_gt", "client_id", "staff_id", "contract_type_id"],
            expense:  ["amount_gt", "client_id", "staff_id", "expense_category_id"]
        },
        CONDITION_INPUT: {
            // type "number" → raw input, type "select" → searchable dropdown
            // built from ApprovalFlowConfig.options[<key>]
            amount_gt:           { type: "number", step: "0.01", min: "0" },
            discount_percent_gt: { type: "number", step: "0.01", min: "0", max: "100" },
            client_id:           { type: "select", source: "client_id",           placeholderKey: "pick_client" },
            staff_id:            { type: "select", source: "staff_id",            placeholderKey: "pick_staff" },
            status:              { type: "text",   hintKey: "status_hint" },
            contract_type_id:    { type: "select", source: "contract_type_id",    placeholderKey: "pick_contract_type" },
            expense_category_id: { type: "select", source: "expense_category_id", placeholderKey: "pick_expense_category" }
        },
        CONDITION_LANG_KEY: {
            amount_gt:           "condition_amount_gt",
            discount_percent_gt: "condition_discount_percent_gt",
            client_id:           "condition_client",
            staff_id:            "condition_staff",
            status:              "condition_status",
            contract_type_id:    "condition_contract_type",
            expense_category_id: "condition_expense_category"
        },
        // FK-flavoured keys whose value is a numeric id resolved against
        // ApprovalFlowConfig.options for the preview text and live state.
        FK_CONDITIONS: ["client_id", "staff_id", "contract_type_id", "expense_category_id"],
        $list: null,
        $entity: null,
        state: {},

        init: function () {
            RuleForm.$list   = $("#approvalflowConditions");
            RuleForm.$entity = $("#approvalflowRuleEntity");
            if (!RuleForm.$list.length) return;

            // Restore conditions on edit
            var existing = (Core.cfg().rule && Core.cfg().rule.existing_conditions) || {};
            RuleForm.state = $.extend({}, existing);
            RuleForm.render();

            RuleForm.$entity.on("change", function () {
                RuleForm.render();
                RuleForm.updatePreview();
            });
            $("#approvalflowAddCondition").on("click", RuleForm.addCondition);
            // Value input typing: collect + preview. Do NOT re-render on the
            // typing event itself so the field keeps focus.
            RuleForm.$list.on("input", ".approvalflow-condition-row__input", function () {
                RuleForm.collectState();
                RuleForm.updatePreview();
            });
            // Dropdown picks (Bootstrap-Select fires "change") share the same
            // collect-and-refresh path — covers the 4 FK conditions.
            RuleForm.$list.on("change", "select.approvalflow-condition-row__input", function () {
                RuleForm.collectState();
                RuleForm.updatePreview();
            });
            // Type select changed: re-render row to swap input attrs and
            // refresh sibling rows so the new used-set excludes the new key.
            RuleForm.$list.on("change", ".approvalflow-condition-row__type", RuleForm.onTypeChange);
            RuleForm.$list.on("click", ".approvalflow-condition-row__remove", function () {
                var key = $(this).closest(".approvalflow-condition-row").data("key");
                if (key) { delete RuleForm.state[key]; }
                RuleForm.render();
                RuleForm.updatePreview();
            });
            $("#approvalflowRuleApprover, #approvalflowRuleName").on("change input", RuleForm.updatePreview);
            $("#approvalflowRuleForm").on("submit", RuleForm.onSubmit);

            RuleForm.updatePreview();
        },

        availableForCurrentEntity: function () {
            var t = RuleForm.$entity.val();
            return RuleForm.ENTITY_CONDITIONS[t] || [];
        },

        render: function () {
            RuleForm.$list.empty();
            var available = RuleForm.availableForCurrentEntity();
            if (!RuleForm.$entity.val()) {
                RuleForm.$list.append("<div class='approvalflow-text-muted approvalflow-text-small'>" +
                    Core.cfg().lang.select_entity_first + "</div>");
                return;
            }
            // Drop conditions that don't apply to the new entity
            Object.keys(RuleForm.state).forEach(function (k) {
                if (available.indexOf(k) === -1) delete RuleForm.state[k];
            });
            // Render rows for whatever the state has
            Object.keys(RuleForm.state).forEach(function (key) {
                RuleForm.renderRow(key, RuleForm.state[key]);
            });
        },

        addCondition: function () {
            if (!RuleForm.$entity.val()) {
                Core.toastWarning(Core.cfg().lang.select_entity_first);
                return;
            }
            var available = RuleForm.availableForCurrentEntity();
            var notUsed = available.filter(function (k) { return !(k in RuleForm.state); });
            if (!notUsed.length) return;
            // Add the first un-used condition with empty value
            var key = notUsed[0];
            RuleForm.state[key] = "";
            RuleForm.renderRow(key, "");
            RuleForm.updatePreview();
        },

        usedKeysExcept: function (exceptKey) {
            // Returns the set of condition keys present in state but not
            // equal to exceptKey. Used to filter the type <select> so the
            // user can't pick the same condition twice.
            var used = {};
            Object.keys(RuleForm.state).forEach(function (k) {
                if (k !== exceptKey) used[k] = true;
            });
            return used;
        },

        buildTypeSelect: function (currentKey) {
            // Build the inner HTML of the .approvalflow-condition-row__type
            // <select>. The currently-selected key is always present even
            // if it's already "used" (which it is, by definition).
            var available = RuleForm.availableForCurrentEntity();
            var used = RuleForm.usedKeysExcept(currentKey);
            var html = "";
            available.forEach(function (k) {
                if (used[k]) return;
                var labelKey = RuleForm.CONDITION_LANG_KEY[k] || ("condition_" + k);
                var label = Core.cfg().lang[labelKey] || k;
                var sel = (k === currentKey) ? " selected" : "";
                html += "<option value='" + k + "'" + sel + ">" + label + "</option>";
            });
            return html;
        },

        // Small HTML escaper used for any value that lands inside an
        // attribute or text node. Centralised to keep XSS surface tight.
        escape: function (s) {
            return String(s == null ? "" : s)
                .replace(/&/g, "&amp;")
                .replace(/"/g, "&quot;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;");
        },

        // Build the value-side widget for a condition row. For FK-flavoured
        // keys (client_id, staff_id, contract_type_id, expense_category_id)
        // we render a Bootstrap-Select dropdown with live search; for plain
        // numeric / text keys we keep the native <input>. Returning HTML as
        // a string keeps the row template a single $(…) builder.
        buildValueField: function (key, value) {
            var spec = RuleForm.CONDITION_INPUT[key] || { type: "text" };
            var inputName = "conditions[" + key + "]";
            var safeVal   = RuleForm.escape(value);

            if (spec.type === "select") {
                var opts = (Core.cfg().options && Core.cfg().options[spec.source]) || [];
                var placeholder = Core.cfg().lang[spec.placeholderKey] || "";
                var searchTitle = Core.cfg().lang.search_placeholder || "Search…";
                // class .selectpicker → Bootstrap-Select (loaded globally by Perfex)
                // data-live-search="true" enables type-to-filter
                var html = "<select class='approvalflow-form-control approvalflow-condition-row__input selectpicker' " +
                    "name='" + inputName + "' data-live-search='true' " +
                    "data-live-search-placeholder='" + RuleForm.escape(searchTitle) + "' " +
                    "title='" + RuleForm.escape(placeholder) + "' data-width='100%'>";
                html += "<option value=''>" + RuleForm.escape(placeholder) + "</option>";
                for (var i = 0; i < opts.length; i++) {
                    var o = opts[i];
                    var sel = (String(o.id) === String(safeVal)) ? " selected" : "";
                    html += "<option value='" + RuleForm.escape(o.id) + "'" + sel + ">" + RuleForm.escape(o.label) + "</option>";
                }
                html += "</select>";
                return html;
            }

            // Native input for amounts / discount / free-text status
            var attrs = "type='" + spec.type + "'";
            if (spec.step) attrs += " step='" + spec.step + "'";
            if (spec.min !== undefined) attrs += " min='" + spec.min + "'";
            if (spec.max !== undefined) attrs += " max='" + spec.max + "'";
            return "<input class='approvalflow-form-control approvalflow-condition-row__input' " + attrs +
                "       name='" + inputName + "' value='" + safeVal + "'>";
        },

        renderRow: function (key, value) {
            var spec = RuleForm.CONDITION_INPUT[key] || { type: "text" };
            var $row = $(
                "<div class='approvalflow-condition-row' data-key='" + key + "'>" +
                "  <select class='approvalflow-form-control approvalflow-condition-row__type' aria-label='" + (Core.cfg().lang.rule_conditions || "Condition") + "'>" +
                       RuleForm.buildTypeSelect(key) +
                "  </select>" +
                "  <div class='approvalflow-condition-row__value'>" + RuleForm.buildValueField(key, value) + "</div>" +
                "  <button type='button' class='approvalflow-condition-row__remove' aria-label='" + Core.cfg().lang.remove + "'><i class='fa fa-trash'></i></button>" +
                "</div>"
            );
            // Inline hint (currently only used by the free-text `status` field
            // to document Perfex's numeric status codes — kills the "what do
            // I type here?" objection from CodeCanyon reviewers).
            if (spec.hintKey && Core.cfg().lang[spec.hintKey]) {
                var $hint = $("<small class='approvalflow-form-hint approvalflow-condition-row__hint'></small>")
                    .text(Core.cfg().lang[spec.hintKey]);
                $row.append($hint);
            }
            RuleForm.$list.append($row);
            // Activate Bootstrap-Select on freshly-appended dropdowns; the
            // plugin is loaded globally by Perfex's admin head so we only
            // need to opt-in per element.
            if (spec.type === "select" && typeof $.fn.selectpicker === "function") {
                $row.find(".selectpicker").selectpicker();
            }
        },

        onTypeChange: function () {
            var $row = $(this).closest(".approvalflow-condition-row");
            var oldKey = $row.data("key");
            var newKey = $(this).val();
            if (!newKey || newKey === oldKey) return;
            // Carry over the current value (may not validate against the
            // new input type — the user will see it and clear if needed)
            var currentVal = $row.find(".approvalflow-condition-row__input").val();
            if (oldKey) { delete RuleForm.state[oldKey]; }
            RuleForm.state[newKey] = currentVal !== undefined ? currentVal : "";
            // Repaint everything so sibling rows refresh their option lists
            RuleForm.render();
            RuleForm.updatePreview();
        },

        collectState: function () {
            RuleForm.state = {};
            RuleForm.$list.find(".approvalflow-condition-row").each(function () {
                var $row = $(this);
                var key = $row.data("key");
                if (!key) return;
                var val = $row.find(".approvalflow-condition-row__input").val();
                if (val !== "" && val !== undefined && val !== null) {
                    RuleForm.state[key] = val;
                }
            });
        },

        // Resolve the numeric id of an FK condition to its label using the
        // server-injected ApprovalFlowConfig.options map. Falls back to "#id"
        // when the id can't be found (deleted client / staff edge case).
        resolveLabel: function (key, value) {
            if (RuleForm.FK_CONDITIONS.indexOf(key) === -1) return value;
            if (value === "" || value === null || value === undefined) return value;
            var list = (Core.cfg().options && Core.cfg().options[key]) || [];
            for (var i = 0; i < list.length; i++) {
                if (String(list[i].id) === String(value)) return list[i].label;
            }
            return "#" + value;
        },

        updatePreview: function () {
            var entity = RuleForm.$entity.val();
            var approverText = $("#approvalflowRuleApprover option:selected").text();
            var $preview = $("#approvalflowPreview");
            if (!entity || !approverText || approverText.indexOf("—") >= 0) {
                $preview.text(Core.cfg().lang.preview_pending);
                return;
            }
            var entityLabel = Core.cfg().lang["entity_" + entity] || entity;
            RuleForm.collectState();
            var keys = Object.keys(RuleForm.state);
            var tpl;
            if (!keys.length) {
                tpl = Core.cfg().lang.preview_no_conditions;
                $preview.text(tpl.replace("%s", entityLabel).replace("%s", approverText));
                return;
            }
            var parts = keys.map(function (k) {
                var langKey = RuleForm.CONDITION_LANG_KEY[k] || ("condition_" + k);
                var label = Core.cfg().lang[langKey] || k;
                // For FK keys, resolve the numeric id to its human label so
                // the live preview reads "Specific client: ACME Inc." instead
                // of "Specific client: 42".
                var rawVal = RuleForm.state[k];
                var displayVal = RuleForm.resolveLabel(k, rawVal);
                return label + ": " + displayVal;
            });
            tpl = Core.cfg().lang.preview_template;
            $preview.text(
                tpl.replace("%s", entityLabel).replace("%s", parts.join(" " + Core.cfg().lang.and + " ")).replace("%s", approverText)
            );
        },

        onSubmit: function (e) {
            RuleForm.collectState();
            var keys = Object.keys(RuleForm.state);
            if (!keys.length) {
                var entity = RuleForm.$entity.val();
                var entityLabel = Core.cfg().lang["entity_" + entity] || entity;
                var msg = (Core.cfg().lang.no_conditions_warning || "This rule has no conditions. Continue?").replace("%s", entityLabel);
                if (!window.confirm(msg)) {
                    e.preventDefault();
                    return false;
                }
            }
        }
    };

    /* ════════════════════════════════════════════════════════════════
     * Rules — toggle active + delete with confirmation.
     * ════════════════════════════════════════════════════════════════ */
    var Rules = {
        init: function () {
            $(".approvalflow-rule-toggle").on("change", Rules.onToggle);
            $(".approvalflow-rule-delete").on("click", Rules.onDelete);
        },

        onToggle: function () {
            var $cb = $(this);
            var id = parseInt($cb.data("id"), 10);
            if (!id) return;
            var url = Core.cfg().urls.rule_toggle + id;
            $cb.prop("disabled", true);
            Core.post(url, {})
                .done(function (resp) {
                    if (resp && resp.success) {
                        $cb.prop("checked", resp.active === 1);
                        Core.toastSuccess(resp.message);
                    } else {
                        $cb.prop("checked", !$cb.prop("checked"));
                        Core.toastError((resp && resp.message) || Core.cfg().lang.load_failed);
                    }
                })
                .fail(function () {
                    $cb.prop("checked", !$cb.prop("checked"));
                    Core.toastError(Core.cfg().lang.load_failed);
                })
                .always(function () { $cb.prop("disabled", false); });
        },

        onDelete: function () {
            var $btn = $(this);
            var id = parseInt($btn.data("id"), 10);
            if (!id) return;
            if (!window.confirm(Core.cfg().lang.confirm_delete)) return;
            var url = Core.cfg().urls.rule_delete + id;
            $btn.prop("disabled", true);
            Core.post(url, {})
                .done(function (resp) {
                    if (resp && resp.success) {
                        Core.toastSuccess(resp.message);
                        $btn.closest("tr").addClass("approvalflow-row--removing");
                        window.setTimeout(function () { $btn.closest("tr").remove(); }, 220);
                    } else {
                        Core.toastError((resp && resp.message) || Core.cfg().lang.load_failed);
                        $btn.prop("disabled", false);
                    }
                })
                .fail(function () {
                    Core.toastError(Core.cfg().lang.load_failed);
                    $btn.prop("disabled", false);
                });
        }
    };

    /* ════════════════════════════════════════════════════════════════
     * History — expand long comments inline.
     * ════════════════════════════════════════════════════════════════ */
    var History = {
        init: function () {
            $(".approvalflow-comment-toggle").on("click", function (e) {
                e.preventDefault();
                var $cell = $(this).closest(".approvalflow-history-comment");
                $cell.find(".approvalflow-comment-short, .approvalflow-comment-toggle").hide();
                $cell.find(".approvalflow-comment-full").prop("hidden", false).show();
            });
        }
    };

    /* ════════════════════════════════════════════════════════════════
     * Public API + boot
     * ════════════════════════════════════════════════════════════════ */
    window.ApprovalFlow = {
        Core:        Core,
        PendingList: PendingList,
        RuleForm:    RuleForm,
        Rules:       Rules,
        History:     History
    };

    $(function () {
        PendingList.init();
        RuleForm.init();
        Rules.init();
        History.init();
    });
})();
