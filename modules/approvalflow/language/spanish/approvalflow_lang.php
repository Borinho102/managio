<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ApprovalFlow — Spanish language file
 *
 * Replica exacta del set de keys en inglés. Cualquier addición debe
 * hacerse en ambos archivos al mismo tiempo (codecanyon-qa lo valida).
 *
 * @package ApprovalFlow
 */

// ── Módulo / general
$lang['approvalflow']                              = 'ApprovalFlow';
$lang['approvalflow_open_dashboard']               = 'Abrir Panel';
$lang['approvalflow_dashboard']                    = 'Panel';
$lang['approvalflow_pending']                      = 'Aprobaciones Pendientes';
$lang['approvalflow_rules']                        = 'Reglas';
$lang['approvalflow_history']                      = 'Historial';
$lang['approvalflow_settings']                     = 'Configuración';
$lang['approvalflow_new_rule']                     = 'Nueva Regla';
$lang['approvalflow_edit_rule']                    = 'Editar Regla';
$lang['approvalflow_save']                         = 'Guardar';
$lang['approvalflow_save_rule']                    = 'Guardar Regla';
$lang['approvalflow_cancel']                       = 'Cancelar';
$lang['approvalflow_back']                         = 'Volver';
$lang['approvalflow_yes']                          = 'Sí';
$lang['approvalflow_no']                           = 'No';
$lang['approvalflow_active']                       = 'Activa';
$lang['approvalflow_inactive']                     = 'Inactiva';
$lang['approvalflow_actions']                      = 'Acciones';
$lang['approvalflow_search']                       = 'Buscar';
$lang['approvalflow_filter']                       = 'Filtrar';
$lang['approvalflow_empty_state']                  = 'Sin datos por ahora.';
$lang['approvalflow_and']                          = 'y';
$lang['approvalflow_more']                         = 'más';
$lang['approvalflow_loading']                      = 'Cargando…';
$lang['approvalflow_close']                        = 'Cerrar';
$lang['approvalflow_view']                         = 'Ver';
$lang['approvalflow_view_document']                = 'Ver documento';
$lang['approvalflow_view_request']                 = 'Ver solicitud';
$lang['approvalflow_review_pending']               = 'Revisar Pendientes';
$lang['approvalflow_dashboard_subtitle']           = 'Gobierna quién puede aprobar facturas, presupuestos, propuestas, contratos y gastos en tu equipo.';
$lang['approvalflow_open_request']                 = 'Abrir solicitud';
$lang['approvalflow_processing']                   = 'Procesando…';
$lang['approvalflow_hero_meta_pending']            = '%d solicitud(es) esperando tu revisión';
$lang['approvalflow_hero_meta_all_clear']          = 'Todo al día — no hay aprobaciones pendientes';
$lang['approvalflow_hero_meta_this_month']         = 'Este mes: %d aprobadas · %d rechazadas';
$lang['approvalflow_cta_pending_sub']              = 'Ordenadas de más antiguas a recientes';
$lang['approvalflow_pagination_showing']           = 'Mostrando %d–%d de %d';
$lang['approvalflow_pagination_prev']              = 'Anterior';
$lang['approvalflow_pagination_next']              = 'Siguiente';
$lang['approvalflow_pagination_page_of']           = 'Página %d de %d';
$lang['approvalflow_pagination_first']             = 'Primera';
$lang['approvalflow_pagination_last']              = 'Última';
$lang['approvalflow_pagination_per_page']          = 'por página';

// ── Entidades
$lang['approvalflow_entity_invoice']               = 'Factura';
$lang['approvalflow_entity_estimate']              = 'Presupuesto';
$lang['approvalflow_entity_proposal']              = 'Propuesta';
$lang['approvalflow_entity_contract']              = 'Contrato';
$lang['approvalflow_entity_expense']               = 'Gasto';
$lang['approvalflow_entity_invoices']              = 'Facturas';
$lang['approvalflow_entity_estimates']             = 'Presupuestos';
$lang['approvalflow_entity_proposals']             = 'Propuestas';
$lang['approvalflow_entity_contracts']             = 'Contratos';
$lang['approvalflow_entity_expenses']              = 'Gastos';

// ── Reglas
$lang['approvalflow_rule_name']                    = 'Nombre de la regla';
$lang['approvalflow_rule_entity']                  = 'Tipo de entidad';
$lang['approvalflow_rule_approver']                = 'Aprobador';
$lang['approvalflow_rule_priority']                = 'Prioridad';
$lang['approvalflow_rule_priority_help']           = 'Los números menores se evalúan primero. Úsalo solo si varias reglas pueden coincidir con el mismo documento.';
$lang['approvalflow_rule_active']                  = 'Activa';
$lang['approvalflow_rule_conditions']              = 'Condiciones';
$lang['approvalflow_rule_conditions_help']         = 'Las condiciones se combinan con Y lógico. Solo se crea una solicitud cuando se cumplen todas las condiciones definidas.';
$lang['approvalflow_rule_add_condition']           = 'Añadir condición';
$lang['approvalflow_rule_remove_condition']        = 'Quitar';
$lang['approvalflow_rule_select_entity_first']     = 'Selecciona primero el tipo de entidad.';
$lang['approvalflow_rule_no_conditions_warning']   = 'Esta regla no tiene condiciones. Coincidirá con TODOS los documentos de tipo %s. ¿Continuar?';
$lang['approvalflow_rule_in_use_warning']          = 'Esta regla tiene solicitudes pendientes. Desactívala en lugar de eliminarla para conservar la auditoría.';
$lang['approvalflow_rule_advanced_options']        = 'Opciones avanzadas';
$lang['approvalflow_rule_preview_heading']         = 'Qué hará esta regla';
$lang['approvalflow_rule_preview_template']        = 'Cuando se cree un(a) %s con %s, requerirá aprobación de %s.';
$lang['approvalflow_rule_preview_no_conditions']   = 'Cuando se cree cualquier %s, requerirá aprobación de %s.';
$lang['approvalflow_rule_preview_pending']         = 'configura la regla para ver una vista previa en tiempo real.';
$lang['approvalflow_rule_created_successfully']    = 'Regla creada.';
$lang['approvalflow_rule_updated_successfully']    = 'Regla actualizada.';
$lang['approvalflow_rule_deleted_successfully']    = 'Regla eliminada.';
$lang['approvalflow_rule_activated']               = 'Regla activada.';
$lang['approvalflow_rule_deactivated']             = 'Regla desactivada.';
$lang['approvalflow_rule_confirm_delete']          = '¿Eliminar esta regla? No se puede deshacer.';
$lang['approvalflow_rule_no_approver']             = '(aprobador no asignado)';
$lang['approvalflow_rule_approver_deleted']        = 'Aprobador eliminado';
$lang['approvalflow_rule_active_requests']         = '%d solicitud(es) activa(s)';

// ── Aprobaciones multi-nivel (v1.0.1)
$lang['approvalflow_rule_approval_levels']         = 'Niveles de aprobación';
$lang['approvalflow_rule_level_1']                 = 'Nivel 1 (primario)';
$lang['approvalflow_rule_level_1_hint']            = 'definido arriba';
$lang['approvalflow_rule_level_n']                 = 'Nivel %d';
$lang['approvalflow_rule_add_level']               = 'Agregar nivel de aprobación';
$lang['approvalflow_rule_remove_level']            = 'Eliminar nivel';
$lang['approvalflow_rule_levels_help']             = 'Opcional: agrega aprobadores secuenciales. El Nivel 2 se notifica solo tras la aprobación del Nivel 1, el Nivel 3 tras el 2, etc. Si se rechaza cualquier nivel, los siguientes se cancelan automáticamente.';
$lang['approvalflow_level_badge']                  = 'Nivel %d de %d';
$lang['approvalflow_banner_ml_progress']           = 'Progreso de aprobación: %d de %d niveles completados';
$lang['approvalflow_banner_ml_current']            = 'Nivel %d — esperando aprobación de %s';
$lang['approvalflow_banner_ml_completed']          = 'Todos los %d niveles de aprobación completados';
$lang['approvalflow_history_action_level_activated'] = 'Nivel activado';

// ── Condiciones
$lang['approvalflow_condition_amount_gt']                = 'Monto mayor que';
$lang['approvalflow_condition_discount_percent_gt']      = 'Descuento %% mayor que';
$lang['approvalflow_condition_client']                   = 'Cliente específico';
$lang['approvalflow_condition_staff']                    = 'Staff creador específico';
$lang['approvalflow_condition_status']                   = 'Estado del documento';
$lang['approvalflow_condition_contract_type']            = 'Tipo de contrato';
$lang['approvalflow_condition_expense_category']         = 'Categoría de gasto';
$lang['approvalflow_condition_amount_short']             = 'Monto';
$lang['approvalflow_condition_discount_short']           = 'Descuento';
$lang['approvalflow_condition_client_short']             = 'Cliente';
$lang['approvalflow_condition_staff_short']              = 'Staff';
$lang['approvalflow_condition_status_short']             = 'Estado';
$lang['approvalflow_condition_contract_type_short']      = 'Tipo';
$lang['approvalflow_condition_category_short']           = 'Categoría';
$lang['approvalflow_condition_value']                    = 'Valor';
$lang['approvalflow_condition_none']                     = 'Sin condiciones (coincide con todo)';
$lang['approvalflow_condition_select_placeholder']       = '— elige una condición —';
$lang['approvalflow_rule_select_entity_placeholder']     = '— elige tipo de documento —';
$lang['approvalflow_rule_select_approver_placeholder']   = '— elige un aprobador —';
// Placeholders para los dropdowns buscables (las 4 condiciones tipo FK)
$lang['approvalflow_condition_pick_client']              = '— elige un cliente —';
$lang['approvalflow_condition_pick_staff']               = '— elige un staff —';
$lang['approvalflow_condition_pick_contract_type']       = '— elige un tipo de contrato —';
$lang['approvalflow_condition_pick_expense_category']    = '— elige una categoría de gasto —';
$lang['approvalflow_condition_search_placeholder']       = 'Buscar…';
$lang['approvalflow_condition_status_hint']              = 'Estados de invoice: 1=no pagada, 2=pagada, 3=parcial, 4=vencida, 5=cancelada. Estimate/proposal usan su status numérico.';

// ── Solicitudes
$lang['approvalflow_request_id']                   = 'Solicitud #';
$lang['approvalflow_request_document']             = 'Documento';
$lang['approvalflow_request_type']                 = 'Tipo';
$lang['approvalflow_request_client']               = 'Cliente';
$lang['approvalflow_request_amount']               = 'Monto';
$lang['approvalflow_request_requester']            = 'Solicitante';
$lang['approvalflow_request_approver']             = 'Aprobador';
$lang['approvalflow_request_status']               = 'Estado';
$lang['approvalflow_request_created_at']           = 'Creada';
$lang['approvalflow_request_resolved_at']          = 'Resuelta';
$lang['approvalflow_request_resolution_time']      = 'Tiempo de resolución';
$lang['approvalflow_request_waiting_time']         = 'Esperando';
$lang['approvalflow_request_rule_used']            = 'Regla';
$lang['approvalflow_request_rule_deleted']         = 'Regla eliminada';
$lang['approvalflow_request_rule_inactive']        = 'Regla inactiva';
$lang['approvalflow_request_view_document']        = 'Ver documento';
$lang['approvalflow_request_comment']              = 'Comentario';
$lang['approvalflow_status_pending']               = 'Pendiente';
$lang['approvalflow_status_approved']              = 'Aprobada';
$lang['approvalflow_status_rejected']              = 'Rechazada';
$lang['approvalflow_status_cancelled']             = 'Cancelada';
$lang['approvalflow_status_waiting']               = 'En espera';
$lang['approvalflow_action_approve']               = 'Aprobar';
$lang['approvalflow_action_reject']                = 'Rechazar';
$lang['approvalflow_action_approve_confirm']       = '¿Aprobar esta solicitud?';
$lang['approvalflow_action_reject_confirm']        = '¿Rechazar esta solicitud?';
$lang['approvalflow_approved_successfully']        = 'Solicitud aprobada.';
$lang['approvalflow_rejected_successfully']        = 'Solicitud rechazada.';
$lang['approvalflow_reject_modal_title']           = 'Rechazar solicitud de aprobación';
$lang['approvalflow_reject_modal_intro']           = 'Estás a punto de rechazar esta solicitud:';
$lang['approvalflow_reject_reason']                = 'Comentario de rechazo';
$lang['approvalflow_reject_reason_placeholder']    = 'Indica el motivo (visible para el solicitante)…';
$lang['approvalflow_reject_reason_hint']           = 'Obligatorio. Mínimo 3 caracteres.';
$lang['approvalflow_reject_submit']                = 'Rechazar solicitud';
$lang['approvalflow_bulk_select_all']              = 'Seleccionar todo en esta página';
$lang['approvalflow_bulk_selected_n']              = '%d seleccionadas';
$lang['approvalflow_bulk_clear']                   = 'Limpiar selección';
$lang['approvalflow_bulk_approve']                 = 'Aprobar Seleccionadas';
$lang['approvalflow_bulk_approve_confirm_title']   = 'Aprobar solicitudes seleccionadas';
$lang['approvalflow_bulk_approve_confirm_body']    = 'Aprobando %d solicitud(es). Comentario opcional:';
$lang['approvalflow_bulk_done']                    = 'Aprobación masiva finalizada — %d aprobadas, %d omitidas.';
$lang['approvalflow_no_selection']                 = 'Selecciona al menos una solicitud.';

// ── KPIs / panel
$lang['approvalflow_kpi_pending']                  = 'Aprobaciones pendientes';
$lang['approvalflow_kpi_approved_month']           = 'Aprobadas este mes';
$lang['approvalflow_kpi_rejected_month']           = 'Rechazadas este mes';
$lang['approvalflow_kpi_avg_time']                 = 'Tiempo medio de aprobación';
$lang['approvalflow_kpi_high_value']               = 'Mayor monto aprobado';
$lang['approvalflow_kpi_high_value_help']          = 'Monto más alto aprobado en el mes actual.';
$lang['approvalflow_kpi_top_requester']            = 'Solicitante principal';
$lang['approvalflow_kpi_top_requester_help']       = 'Miembro del staff que creó más solicitudes este mes.';
$lang['approvalflow_kpi_no_data']                  = 'Sin datos';
$lang['approvalflow_kpi_hours']                    = 'h';
$lang['approvalflow_kpi_minutes']                  = 'm';
$lang['approvalflow_kpi_days']                     = 'd';
$lang['approvalflow_kpi_n_requests']               = '%d solicitudes';
$lang['approvalflow_dashboard_recent_pending']     = 'Pendientes recientes';
$lang['approvalflow_dashboard_recent_activity']    = 'Actividad reciente';
$lang['approvalflow_dashboard_view_all_pending']   = 'Ver todas las pendientes';

// ── Empty states
$lang['approvalflow_empty_pending_title']          = 'Todo al día';
$lang['approvalflow_empty_pending_desc']           = 'No hay solicitudes de aprobación pendientes.';
$lang['approvalflow_empty_history_title']          = 'Aún no hay actividad';
$lang['approvalflow_empty_history_desc']           = 'Las solicitudes de aprobación y sus resoluciones aparecerán aquí.';
$lang['approvalflow_empty_rules_title']            = 'No hay reglas de aprobación';
$lang['approvalflow_empty_rules_desc']             = 'Crea tu primera regla para empezar a controlar facturas, presupuestos, propuestas, contratos y gastos.';
$lang['approvalflow_empty_rules_cta']              = 'Crear tu primera regla';
$lang['approvalflow_empty_dashboard_title']        = 'Listo cuando lo estés';
$lang['approvalflow_empty_dashboard_desc']         = 'Crea una regla de aprobación y ApprovalFlow empezará a monitorear documentos.';
$lang['approvalflow_empty_dashboard_cta']          = 'Crear tu primera regla';

// ── Configuración
$lang['approvalflow_setting_enabled_entities']       = 'Tipos de documento';
$lang['approvalflow_setting_enabled_entities_help']  = 'Solo los tipos marcados se evaluarán contra las reglas cuando se cree un documento.';
$lang['approvalflow_setting_enable_invoices']        = 'Monitorear facturas';
$lang['approvalflow_setting_enable_estimates']       = 'Monitorear presupuestos';
$lang['approvalflow_setting_enable_proposals']       = 'Monitorear propuestas';
$lang['approvalflow_setting_enable_contracts']       = 'Monitorear contratos';
$lang['approvalflow_setting_enable_expenses']        = 'Monitorear gastos';
$lang['approvalflow_setting_notifications']          = 'Notificaciones';
$lang['approvalflow_setting_notifications_internal'] = 'Notificaciones internas (campana)';
$lang['approvalflow_setting_notifications_email']    = 'Notificaciones por correo (requiere SMTP configurado en Perfex)';
$lang['approvalflow_setting_behavior']               = 'Comportamiento';
$lang['approvalflow_setting_require_reject_comment'] = 'Exigir comentario al rechazar';
$lang['approvalflow_setting_require_reject_comment_hint'] = 'Recomendado. Conserva la explicación auditable de cada rechazo.';
$lang['approvalflow_setting_admin_auto_approve']     = 'Aprobación automática para documentos creados por admins';
$lang['approvalflow_setting_admin_auto_approve_hint']= 'Los admins evitan la aprobación — úsalo con precaución.';
$lang['approvalflow_setting_reminder_days']          = 'Recordatorio tras X días';
$lang['approvalflow_setting_reminder_help']          = 'Re-notificar al aprobador si una solicitud sigue pendiente después de estos días. 0 = desactivado.';
$lang['approvalflow_setting_advanced']               = 'Avanzado';
$lang['approvalflow_setting_debug']                  = 'Habilitar logs de depuración';
$lang['approvalflow_setting_debug_hint']             = 'Registra cada evaluación de regla en la tabla de logs. Desactivado por defecto.';
$lang['approvalflow_setting_logs_retention']         = 'Retención de logs (días)';
$lang['approvalflow_setting_logs_retention_hint']    = 'Los logs más viejos se purgan automáticamente en el tick del cron.';
$lang['approvalflow_settings_saved']                 = 'Configuración guardada.';
$lang['approvalflow_save_settings']                  = 'Guardar configuración';
$lang['approvalflow_settings_all_off_warning']       = 'Todos los tipos de documento están desactivados. No se disparará ninguna aprobación.';
// Settings page chrome (hero + dividers de sección + hints intro + accesos)
$lang['approvalflow_settings_eyebrow']               = 'ApprovalFlow';
$lang['approvalflow_settings_subtitle']              = 'Elige qué tipos de documento requieren aprobación, cómo se notifica a los aprobadores y ajusta el resto del flujo.';
$lang['approvalflow_setting_enabled_entities_hint']  = 'Desactiva un tipo para omitirlo por completo. Las solicitudes pendientes existentes de ese tipo siguen visibles en Historial.';
$lang['approvalflow_setting_notifications_hint']     = 'Los aprobadores siempre ven las nuevas solicitudes en su bandeja. Los interruptores de abajo agregan canales complementarios.';
$lang['approvalflow_setting_behavior_hint']          = 'Ajusta qué sucede cuando una solicitud es rechazada o lleva demasiado tiempo pendiente.';
$lang['approvalflow_setting_advanced_hint']          = 'Para instalaciones con foco en auditoría y para diagnóstico. Los valores por defecto son seguros para la mayoría de equipos.';
$lang['approvalflow_section_documents']              = 'Documentos';
$lang['approvalflow_section_channels']               = 'Canales';
$lang['approvalflow_section_rules_audit']            = 'Auditoría';
$lang['approvalflow_section_reminders']              = 'Recordatorios';
$lang['approvalflow_section_diagnostics']            = 'Diagnóstico';
$lang['approvalflow_section_retention']              = 'Retención';
$lang['approvalflow_section_shortcuts']              = 'Accesos directos';
$lang['approvalflow_open_dashboard']                 = 'Abrir panel';
$lang['approvalflow_view_pending']                   = 'Ver pendientes';
$lang['approvalflow_manage_rules']                   = 'Gestionar reglas';

// ── Permisos
$lang['approvalflow_perm_view']                    = 'Ver pendientes y reglas';
$lang['approvalflow_perm_view_all']                = 'Ver todas las solicitudes (no solo las propias)';
$lang['approvalflow_perm_create_rules']            = 'Crear reglas';
$lang['approvalflow_perm_edit_rules']              = 'Editar reglas';
$lang['approvalflow_perm_delete_rules']            = 'Eliminar reglas';
$lang['approvalflow_perm_approve']                 = 'Aprobar solicitudes asignadas';
$lang['approvalflow_perm_reject']                  = 'Rechazar solicitudes asignadas';
$lang['approvalflow_perm_settings']                = 'Modificar configuración del módulo';

// ── Notificaciones / emails
$lang['approvalflow_notif_new_request']            = 'Nueva solicitud de aprobación: %s';
$lang['approvalflow_notif_approved']               = 'Tu solicitud fue aprobada: %s';
$lang['approvalflow_notif_rejected']               = 'Tu solicitud fue rechazada: %s';
$lang['approvalflow_notif_reminder']               = 'Tienes aprobaciones pendientes';
$lang['approvalflow_email_subject_new']            = '[ApprovalFlow] Nueva solicitud de aprobación — %s';
$lang['approvalflow_email_subject_approved']       = '[ApprovalFlow] Aprobada — %s';
$lang['approvalflow_email_subject_rejected']       = '[ApprovalFlow] Rechazada — %s';
$lang['approvalflow_email_body_new']               = 'Una nueva solicitud de aprobación requiere tu revisión: %s. Abre ApprovalFlow para aprobar o rechazar.';
$lang['approvalflow_email_body_approved']          = 'Tu solicitud fue aprobada por %s: %s.';
$lang['approvalflow_email_body_rejected']          = 'Tu solicitud fue rechazada por %s: %s.';

// ── Advertencias / alerts
$lang['approvalflow_warning_pending_approval']     = '%s #%s requiere aprobación de %s antes de enviarse. El aprobador ya fue notificado.';
$lang['approvalflow_warning_already_resolved']     = 'Esta solicitud ya fue resuelta.';
$lang['approvalflow_warning_not_your_request']     = 'No eres el aprobador asignado a esta solicitud.';
$lang['approvalflow_warning_no_active_rules']      = 'No hay reglas activas para este tipo de entidad.';
$lang['approvalflow_banner_pending_title']         = 'Aprobación pendiente';
$lang['approvalflow_banner_pending_desc']          = 'Este(a) %s espera revisión de %s.';
$lang['approvalflow_banner_pending_meta']          = 'Enviado %s · Regla: %s';
$lang['approvalflow_banner_approved_title']        = 'Aprobación concedida';
$lang['approvalflow_banner_approved_desc']         = 'Aprobada por %s el %s.';
$lang['approvalflow_banner_rejected_title']        = 'Aprobación rechazada';
$lang['approvalflow_banner_rejected_desc']         = 'Rechazada por %s el %s.';

// ── Errores
$lang['approvalflow_error_invalid_rule']           = 'Regla inválida.';
$lang['approvalflow_error_invalid_request']        = 'Solicitud inválida.';
$lang['approvalflow_error_not_authorized']         = 'No estás autorizado para esta acción.';
$lang['approvalflow_error_comment_required']       = 'El comentario de rechazo es obligatorio (mínimo 3 caracteres).';
$lang['approvalflow_error_approver_required']      = 'El aprobador es obligatorio.';
$lang['approvalflow_error_entity_required']        = 'El tipo de entidad es obligatorio.';
$lang['approvalflow_error_name_required']          = 'El nombre de la regla es obligatorio.';
$lang['approvalflow_error_save_failed']            = 'No se pudo guardar. Inténtalo de nuevo.';
$lang['approvalflow_error_delete_failed']          = 'No se pudo eliminar esta regla.';
$lang['approvalflow_error_already_processed']      = 'Esta solicitud ya fue procesada por otro usuario.';
$lang['approvalflow_error_entity_not_found']       = 'No se encontró el documento original.';
$lang['approvalflow_error_load_failed']            = 'No se pudieron cargar los datos. Refresca la página.';
$lang['approvalflow_error_csrf']                   = 'Sesión expirada. Refresca la página.';

// ── Historial
$lang['approvalflow_history_subtitle']             = 'Auditoría completa de cada solicitud de aprobación y sus cambios de estado.';
$lang['approvalflow_history_actor']                = 'Realizado por';
$lang['approvalflow_history_action']               = 'Acción';
$lang['approvalflow_history_previous']             = 'Estado anterior';
$lang['approvalflow_history_new']                  = 'Estado nuevo';
$lang['approvalflow_history_date']                 = 'Fecha';
$lang['approvalflow_history_system']               = 'Sistema';
$lang['approvalflow_history_action_created']       = 'Solicitud creada';
$lang['approvalflow_history_action_approved']      = 'Aprobada';
$lang['approvalflow_history_action_rejected']      = 'Rechazada';
$lang['approvalflow_history_action_cancelled']     = 'Cancelada';
$lang['approvalflow_history_action_reminder_sent'] = 'Recordatorio enviado';
$lang['approvalflow_history_action_viewed_warning']= 'Advertencia vista';
$lang['approvalflow_history_action_reassigned']    = 'Reasignada';
$lang['approvalflow_history_filter_from']          = 'Desde';
$lang['approvalflow_history_filter_to']            = 'Hasta';
$lang['approvalflow_history_clear_filters']        = 'Limpiar filtros';

// ── Tabs alternativas
$lang['approvalflow_tab_pending_approval']         = 'Aprobación Pendiente';

// ── Eyebrow del módulo + ARIA labels (cumplimiento i18n)
$lang['approvalflow_module_eyebrow']               = 'APPROVALFLOW';
$lang['approvalflow_aria_view_request']            = 'Ver solicitud #%s';
$lang['approvalflow_aria_approve_request']         = 'Aprobar solicitud #%s';
$lang['approvalflow_aria_reject_request']          = 'Rechazar solicitud #%s';
$lang['approvalflow_aria_select_request']          = 'Seleccionar solicitud #%s';

// ── Approval Pulse (v1.0.2): score de riesgo, SLA/escalado, digest por email
// Lista de pendientes — columna de riesgo + badge vencido
$lang['approvalflow_request_risk']                 = 'Riesgo';
$lang['approvalflow_risk_why']                     = 'Por qué este score';
$lang['approvalflow_risk_factor_amount']           = 'Monto';
$lang['approvalflow_risk_factor_aging']            = 'Tiempo de espera';
$lang['approvalflow_risk_factor_discount']         = 'Descuento';
$lang['approvalflow_risk_factor_depth']            = 'Nivel de aprobación';
$lang['approvalflow_request_overdue']              = 'Vencido';
$lang['approvalflow_request_overdue_hint']         = 'Esta solicitud lleva pendiente más que el SLA configurado.';
// Notificaciones de escalado
$lang['approvalflow_notif_escalated']              = 'Aprobación vencida — escalada a vos: %s';
$lang['approvalflow_email_subject_escalated']      = '[ApprovalFlow] Aprobación vencida escalada — %s';
$lang['approvalflow_email_body_escalated']         = 'Una solicitud de aprobación superó su SLA y fue escalada a vos: %s. Abrí ApprovalFlow para revisarla.';
// Digest por email
$lang['approvalflow_digest_email_subject']         = '[ApprovalFlow] Resumen de tus aprobaciones pendientes';
$lang['approvalflow_digest_email_body']            = "Hola %1\$s,\n\nTenés %2\$d aprobación(es) pendiente(s), %3\$d vencida(s), por un total de %4\$s, y la más antigua espera hace %5\$d día(s).";
$lang['approvalflow_email_cta_open']               = 'Abrir ApprovalFlow';
// Settings — card Pulse
$lang['approvalflow_setting_pulse']                = 'Approval Pulse';
$lang['approvalflow_setting_pulse_hint']           = 'Score de riesgo, escalado por SLA y resúmenes por email, todo opcional. Todo viene desactivado por defecto.';
$lang['approvalflow_section_risk']                 = 'Score de riesgo';
$lang['approvalflow_setting_risk_enabled']         = 'Mostrar score de riesgo';
$lang['approvalflow_setting_risk_enabled_hint']    = 'Agrega una columna de riesgo 0–100 a la lista de pendientes.';
$lang['approvalflow_setting_risk_ceiling']         = 'Tope de monto';
$lang['approvalflow_setting_risk_ceiling_hint']    = 'Monto que otorga el peso máximo de riesgo.';
$lang['approvalflow_section_sla']                  = 'SLA y escalado';
$lang['approvalflow_setting_sla_hours']            = 'SLA (horas)';
$lang['approvalflow_setting_sla_hours_hint']       = '0 desactiva los avisos de vencido y el escalado.';
$lang['approvalflow_setting_escalate_to']          = 'Escalar a';
$lang['approvalflow_setting_escalate_none']        = '— sin escalado —';
$lang['approvalflow_setting_escalate_to_hint']     = 'Responsable notificado cuando una solicitud supera el SLA.';
$lang['approvalflow_section_digest']               = 'Resumen por email';
$lang['approvalflow_setting_digest_enabled']       = 'Enviar resumen';
$lang['approvalflow_setting_digest_enabled_hint']  = 'Envía a cada aprobador un resumen de sus pendientes.';
$lang['approvalflow_setting_digest_freq']          = 'Frecuencia';
$lang['approvalflow_digest_freq_daily']            = 'Diario';
$lang['approvalflow_digest_freq_weekly']           = 'Semanal';
$lang['approvalflow_setting_digest_day']           = 'Día de la semana';
$lang['approvalflow_setting_digest_day_hint']      = 'Solo se usa para el resumen semanal.';
$lang['approvalflow_day_monday']                   = 'Lunes';
$lang['approvalflow_day_tuesday']                  = 'Martes';
$lang['approvalflow_day_wednesday']                = 'Miércoles';
$lang['approvalflow_day_thursday']                 = 'Jueves';
$lang['approvalflow_day_friday']                   = 'Viernes';
$lang['approvalflow_day_saturday']                 = 'Sábado';
$lang['approvalflow_day_sunday']                   = 'Domingo';
$lang['approvalflow_history_action_escalated']     = 'Escalada';
