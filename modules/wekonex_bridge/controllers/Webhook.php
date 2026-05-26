<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Webhooks entrants Wekonex + health check.
 */
class Webhook extends App_Controller
{
    public function ping()
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => true,
                'module' => WEKONEX_BRIDGE_MODULE,
                'enabled' => wekonex_bridge_is_enabled(),
                'time' => date('c'),
            ]));
    }

    public function receive()
    {
        if (!wekonex_bridge_is_enabled()) {
            return $this->jsonResponse(['status' => false, 'message' => 'Bridge disabled'], 503);
        }

        if (!wekonex_bridge_verify_webhook_request()) {
            wekonex_bridge_log('webhook_receive', false, ['error_message' => 'Invalid webhook secret', 'http_status' => 401]);

            return $this->jsonResponse(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $idempotencyKey = $this->input->get_request_header('X-Idempotency-Key', true)
            ?: $this->input->post('idempotency_key');

        $action = $this->input->post('action') ?: 'webhook_receive';

        if (!empty($idempotencyKey) && wekonex_bridge_idempotency_seen($idempotencyKey, $action)) {
            return $this->jsonResponse([
                'status' => true,
                'message' => 'Already processed',
                'replay' => true,
                'action' => $action,
            ]);
        }

        $payload = $this->input->post();
        $result = ['success' => true, 'message' => 'Received'];

        $syncActions = ['tenant.sync', 'member.upsert', 'payment.record'];
        if (in_array($action, $syncActions, true)) {
            $staffId = (int) get_option('wekonex_bridge_api_staff_id');
            if ($staffId > 0) {
                $this->session->set_userdata([
                    'staff_user_id' => $staffId,
                    'staff_logged_in' => true,
                ]);
            }

            $this->load->model('wekonex_bridge/wekonex_sync_model');
            $result = $this->wekonex_sync_model->handle_webhook_action($action, $payload);

            if (empty($result['success'])) {
                wekonex_bridge_log($action, false, [
                    'error_message' => $result['message'] ?? 'Sync failed',
                    'http_status' => 422,
                    'payload' => array_diff_key($payload, ['password' => 1]),
                ]);

                return $this->jsonResponse([
                    'status' => false,
                    'message' => $result['message'] ?? 'Sync failed',
                    'action' => $action,
                ], 422);
            }
        }

        $responseHash = hash('sha256', json_encode($result));

        if (!empty($idempotencyKey)) {
            wekonex_bridge_idempotency_store($idempotencyKey, $action, $responseHash);
        }

        wekonex_bridge_log($action, true, [
            'direction' => 'inbound',
            'payload' => [
                'action' => $action,
                'result' => array_diff_key($result, ['password' => 1]),
            ],
        ]);

        hooks()->do_action('wekonex_bridge_webhook_received', $payload, $action);

        return $this->jsonResponse(array_merge([
            'status' => true,
            'action' => $action,
        ], $result));
    }

    private function jsonResponse(array $data, int $code = 200)
    {
        return $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }
}
