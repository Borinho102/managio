<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Sync Wekonex → Managio (clients, contacts, factures) — Phase 1.
 */
class Wekonex_sync_model extends App_Model
{
    public function mappings_table(): string
    {
        return db_prefix() . 'wekonex_entity_mappings';
    }

    public function find_mapping(string $externalKey): ?object
    {
        $table = $this->mappings_table();
        if (!$this->db->table_exists($table)) {
            return null;
        }

        return $this->db->where('external_key', $externalKey)->get($table)->row();
    }

    public function save_mapping(string $externalKey, string $entityType, int $managioId, ?string $tenantId = null): void
    {
        $table = $this->mappings_table();
        if (!$this->db->table_exists($table)) {
            return;
        }

        $existing = $this->find_mapping($externalKey);
        $row = [
            'external_key' => $externalKey,
            'entity_type' => $entityType,
            'managio_entity_id' => $managioId,
            'wekonex_tenant_id' => $tenantId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->db->where('id', $existing->id)->update($table, $row);
        } else {
            $row['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert($table, $row);
        }
    }

    /**
     * @return array{success: bool, message?: string, client_id?: int, contact_id?: int, invoice_id?: int, replay?: bool}
     */
    public function handle_webhook_action(string $action, array $payload): array
    {
        return match ($action) {
            'tenant.sync' => $this->upsert_tenant_client($payload),
            'member.upsert' => $this->upsert_member_contact($payload),
            'payment.record' => $this->record_payment_invoice($payload),
            default => ['success' => true, 'message' => 'No handler for action: ' . $action],
        };
    }

    /**
     * @return array{success: bool, message?: string, client_id?: int}
     */
    public function upsert_tenant_client(array $payload): array
    {
        $tenantId = (string) ($payload['tenant_id'] ?? '');
        if ($tenantId === '') {
            return ['success' => false, 'message' => 'Missing tenant_id'];
        }

        $externalKey = 'tenant:' . $tenantId;
        $mapping = $this->find_mapping($externalKey);

        $this->load->model('clients_model');

        $company = trim((string) ($payload['company'] ?? 'Association Wekonex'));
        $clientData = [
            'company' => $company,
            'phonenumber' => $payload['phone'] ?? '',
            'country' => (int) ($payload['country_id'] ?? 0),
            'active' => 1,
        ];

        if ($mapping) {
            $clientId = (int) $mapping->managio_entity_id;
            $this->clients_model->update($clientData, $clientId);
        } else {
            $clientData['donotsendwelcomeemail'] = true;
            $clientId = (int) $this->clients_model->add($clientData, false);
            if (!$clientId) {
                return ['success' => false, 'message' => 'Failed to create client'];
            }
            $this->save_mapping($externalKey, 'client', $clientId, $tenantId);
            $this->assign_client_groups($clientId, $tenantId);
        }

        wekonex_bridge_set_custom_field_value('customers', $clientId, 'wekonex_tenant_id', $tenantId);
        if (!empty($payload['domain'])) {
            wekonex_bridge_set_custom_field_value('customers', $clientId, 'wekonex_domain', (string) $payload['domain']);
        }

        return ['success' => true, 'client_id' => $clientId];
    }

    /**
     * @return array{success: bool, message?: string, contact_id?: int, client_id?: int}
     */
    public function upsert_member_contact(array $payload): array
    {
        $tenantId = (string) ($payload['tenant_id'] ?? '');
        $userId = (string) ($payload['user_id'] ?? '');
        if ($tenantId === '' || $userId === '') {
            return ['success' => false, 'message' => 'Missing tenant_id or user_id'];
        }

        $tenantResult = $this->upsert_tenant_client([
            'tenant_id' => $tenantId,
            'company' => $payload['tenant_name'] ?? ('Wekonex ' . $tenantId),
            'domain' => $payload['domain'] ?? '',
        ]);
        if (!$tenantResult['success']) {
            return $tenantResult;
        }

        $clientId = (int) $tenantResult['client_id'];
        $externalKey = 'user:' . $tenantId . ':' . $userId;
        $mapping = $this->find_mapping($externalKey);

        [$firstname, $lastname] = wekonex_bridge_split_name((string) ($payload['name'] ?? 'Member'));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($email === '') {
            return ['success' => false, 'message' => 'Missing email'];
        }

        $contactData = [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'phonenumber' => $payload['mobile'] ?? '',
            'title' => $payload['job_title'] ?? '',
            'active' => !empty($payload['active']) ? 1 : 0,
            'donotsendwelcomeemail' => true,
            'send_set_password_email' => false,
            'is_primary' => !empty($payload['is_primary']) ? 1 : 0,
            'permissions' => [],
        ];

        $this->load->model('clients_model');

        if ($mapping) {
            $contactId = (int) $mapping->managio_entity_id;
            $this->clients_model->update_contact($contactData, $contactId, $clientId);
        } else {
            $existing = $this->find_contact_by_email($clientId, $email);
            if ($existing) {
                $contactId = (int) $existing->id;
                $this->clients_model->update_contact($contactData, $contactId, $clientId);
            } else {
                $contactId = (int) $this->clients_model->add_contact($contactData, $clientId);
            }
            if (!$contactId) {
                return ['success' => false, 'message' => 'Failed to create contact'];
            }
            $this->save_mapping($externalKey, 'contact', $contactId, $tenantId);
        }

        $this->apply_contact_custom_fields($contactId, $payload);
        $this->assign_contact_group_tag($clientId, $payload['role'] ?? 'alumni');

        return [
            'success' => true,
            'client_id' => $clientId,
            'contact_id' => $contactId,
        ];
    }

    /**
     * @return array{success: bool, message?: string, invoice_id?: int, replay?: bool}
     */
    public function record_payment_invoice(array $payload): array
    {
        $paymentId = (string) ($payload['payment_id'] ?? '');
        if ($paymentId === '') {
            return ['success' => false, 'message' => 'Missing payment_id'];
        }

        $externalKey = 'payment:' . $paymentId;
        $mapping = $this->find_mapping($externalKey);
        if ($mapping) {
            return [
                'success' => true,
                'invoice_id' => (int) $mapping->managio_entity_id,
                'replay' => true,
            ];
        }

        $tenantId = (string) ($payload['tenant_id'] ?? '');
        $userId = (string) ($payload['user_id'] ?? '');
        $memberResult = $this->upsert_member_contact([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'tenant_name' => $payload['tenant_name'] ?? '',
            'name' => $payload['customer_name'] ?? '',
            'email' => $payload['email'] ?? '',
            'mobile' => $payload['mobile'] ?? '',
            'active' => 1,
            'role' => $payload['role'] ?? 'alumni',
        ]);
        if (!$memberResult['success']) {
            return $memberResult;
        }

        $clientId = (int) $memberResult['client_id'];
        $amount = (float) ($payload['amount'] ?? 0);
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Invalid amount'];
        }

        $currencyName = (string) ($payload['currency'] ?? get_base_currency()->name);
        $currencyId = $this->resolve_currency_id($currencyName);
        $description = (string) ($payload['description'] ?? 'Wekonex payment');
        $date = !empty($payload['payment_date'])
            ? date('Y-m-d', strtotime($payload['payment_date']))
            : date('Y-m-d');

        $this->load->model('invoices_model');
        $this->load->model('payments_model');

        $invoiceData = [
            'clientid' => $clientId,
            'date' => $date,
            'duedate' => $date,
            'currency' => $currencyId,
            'subtotal' => $amount,
            'total' => $amount,
            'discount_total' => 0,
            'adjustment' => 0,
            'billing_street' => '',
            'billing_city' => '',
            'billing_state' => '',
            'billing_zip' => '',
            'billing_country' => 0,
            'include_shipping' => 0,
            'show_quantity_as' => 1,
            'newitems' => [
                [
                    'description' => $description,
                    'long_description' => 'Wekonex payment #' . $paymentId,
                    'qty' => 1,
                    'rate' => $amount,
                    'order' => 1,
                ],
            ],
        ];

        $staffId = (int) get_option('wekonex_bridge_api_staff_id');
        if ($staffId > 0) {
            $GLOBALS['wekonex_bridge_invoice_staff_id'] = $staffId;
        }

        $invoiceId = (int) $this->invoices_model->add($invoiceData);
        if (!$invoiceId) {
            return ['success' => false, 'message' => 'Failed to create invoice'];
        }

        wekonex_bridge_set_custom_field_value('invoice', $invoiceId, 'wekonex_payment_id', $paymentId);
        if (!empty($payload['payment_uuid'])) {
            wekonex_bridge_set_custom_field_value('invoice', $invoiceId, 'wekonex_payment_uuid', (string) $payload['payment_uuid']);
        }

        $paymentModeId = $this->resolve_payment_mode_id((string) ($payload['gateway'] ?? 'Wekonex'));
        $paymentRecord = [
            'invoiceid' => $invoiceId,
            'amount' => $amount,
            'paymentmode' => $paymentModeId,
            'date' => $date,
            'transactionid' => (string) ($payload['transaction_id'] ?? ''),
            'note' => 'Wekonex payment #' . $paymentId,
        ];

        $paymentRecordId = $this->payments_model->add($paymentRecord);
        if (!$paymentRecordId) {
            return ['success' => false, 'message' => 'Invoice created but payment record failed', 'invoice_id' => $invoiceId];
        }

        $this->save_mapping($externalKey, 'invoice', $invoiceId, $tenantId);

        return [
            'success' => true,
            'invoice_id' => $invoiceId,
            'payment_record_id' => $paymentRecordId,
        ];
    }

    /**
     * Contact pour SSO (portail client).
     */
    public function find_contact_for_sso(array $payload): ?object
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $tenantId = (string) ($payload['tenant_id'] ?? '');
        $userId = (string) ($payload['sub'] ?? $payload['user_id'] ?? '');

        if ($userId !== '' && $tenantId !== '') {
            $mapping = $this->find_mapping('user:' . $tenantId . ':' . $userId);
            if ($mapping) {
                $contact = $this->db
                    ->where('id', (int) $mapping->managio_entity_id)
                    ->get(db_prefix() . 'contacts')
                    ->row();
                if ($contact && (int) $contact->active === 1) {
                    return $contact;
                }
            }
        }

        if ($email === '') {
            return null;
        }

        $this->db->where('email', $email);
        $this->db->where('active', 1);
        $this->db->limit(1);

        return $this->db->get(db_prefix() . 'contacts')->row();
    }

    public function find_staff_for_sso(string $email): ?object
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        return $this->db
            ->where('email', $email)
            ->where('active', 1)
            ->get(db_prefix() . 'staff')
            ->row();
    }

    private function find_contact_by_email(int $clientId, string $email): ?object
    {
        return $this->db
            ->where('userid', $clientId)
            ->where('email', $email)
            ->get(db_prefix() . 'contacts')
            ->row();
    }

    private function assign_client_groups(int $clientId, string $tenantId): void
    {
        $groups = array_filter([
            'WEKONEX-ASSOCIATION',
            'WEKONEX-TENANT-' . preg_replace('/[^a-zA-Z0-9_-]/', '', substr($tenantId, 0, 40)),
        ]);
        $this->load->model('client_groups_model');
        foreach ($groups as $name) {
            $groupId = $this->ensure_customer_group($name);
            if (!$groupId) {
                continue;
            }
            $exists = $this->db
                ->where('customer_id', $clientId)
                ->where('groupid', $groupId)
                ->count_all_results(db_prefix() . 'customer_groups');
            if ($exists === 0) {
                $this->db->insert(db_prefix() . 'customer_groups', [
                    'groupid' => $groupId,
                    'customer_id' => $clientId,
                ]);
            }
        }
    }

    private function assign_contact_group_tag(int $clientId, string $role): void
    {
        $map = [
            'super_admin' => 'WEKONEX-PLATFORM',
            'admin' => 'WEKONEX-ADMIN',
            'board_member' => 'WEKONEX-BOARD',
            'alumni' => 'WEKONEX-MEMBER',
        ];
        $name = $map[$role] ?? 'WEKONEX-MEMBER';
        $groupId = $this->ensure_customer_group($name);
        if ($groupId) {
            $exists = $this->db
                ->where('customer_id', $clientId)
                ->where('groupid', $groupId)
                ->count_all_results(db_prefix() . 'customer_groups');
            if ($exists === 0) {
                $this->db->insert(db_prefix() . 'customer_groups', [
                    'groupid' => $groupId,
                    'customer_id' => $clientId,
                ]);
            }
        }
    }

    private function ensure_customer_group(string $name): ?int
    {
        $row = $this->db->where('name', $name)->get(db_prefix() . 'customers_groups')->row();
        if ($row) {
            return (int) $row->id;
        }

        $this->db->insert(db_prefix() . 'customers_groups', ['name' => $name]);
        $id = (int) $this->db->insert_id();

        return $id > 0 ? $id : null;
    }

    private function apply_contact_custom_fields(int $contactId, array $payload): void
    {
        $map = [
            'wekonex_user_id' => $payload['user_id'] ?? '',
            'wekonex_tenant_id' => $payload['tenant_id'] ?? '',
            'wekonex_user_uuid' => $payload['user_uuid'] ?? '',
            'wekonex_role' => $payload['role'] ?? '',
            'wekonex_is_alumni' => !empty($payload['is_alumni']) ? '1' : '0',
            'wekonex_company' => $payload['company'] ?? '',
            'wekonex_job_title' => $payload['job_title'] ?? '',
        ];
        foreach ($map as $slug => $value) {
            if ($value !== '' && $value !== null) {
                wekonex_bridge_set_custom_field_value('contacts', $contactId, $slug, (string) $value);
            }
        }
    }

    private function resolve_currency_id(string $code): int
    {
        $row = $this->db->where('name', $code)->get(db_prefix() . 'currencies')->row();
        if ($row) {
            return (int) $row->id;
        }

        $base = get_base_currency();

        return $base ? (int) $base->id : 1;
    }

    private function resolve_payment_mode_id(string $name): int
    {
        $row = $this->db
            ->like('name', $name)
            ->limit(1)
            ->get(db_prefix() . 'payment_modes')
            ->row();
        if ($row) {
            return (int) $row->id;
        }

        $fallback = $this->db->limit(1)->get(db_prefix() . 'payment_modes')->row();

        return $fallback ? (int) $fallback->id : 1;
    }
}
