<?php defined('BASEPATH') or exit('No direct script access allowed');

// This can be removed if you use __autoload() in config.php OR use Modular Extensions
/** @noinspection PhpIncludeInspection */
require __DIR__ . '/../REST_Controller.php';

class Prchat extends REST_Controller
{

    /**
     * Stores the pusher options.
     *
     * @var array
     */
    protected $pusher_options = [];

    /**
     * Hold Pusher instance.
     *
     * @var object
     */
    protected $pusher;

    /**
     * Class constructor / Pusher logic
     */
    public function __construct()
    {
        parent::__construct();

        load_admin_language();

        if (! get_option('pusher_chat_enabled') == '1') {
            redirect('admin');
        }

        if (! defined('PR_CHAT_MODULE_NAME')) {
            show_404();
        }

        if (! staff_can('view', PR_CHAT_MODULE_NAME)) {
            access_denied(_l('chat_access_label'));
        }

        $this->load->model('prchat/prchat_model', 'chat_model');

        $this->pusher_options['app_key'] = get_option('pusher_app_key');
        $this->pusher_options['app_secret'] = get_option('pusher_app_secret');
        $this->pusher_options['app_id'] = get_option('pusher_app_id');

        if (
            get_option('pusher_app_key') == '' ||
            get_option('pusher_app_secret') == '' ||
            get_option('pusher_app_id') == '' ||
            get_option('pusher_cluster') == ''
        ) {
            echo '<h1>Seems that your Pusher account it is not setup correctly.</h1>';
            echo '<h4>Setup Pusher now: <a href="' . site_url('admin/settings?group=pusher') . '">Perfex CRM Settings->Pusher.com</a></h4>';
            echo '<h4>Tutorial: <a target="blank" href="https://help.perfexcrm.com/setup-realtime-notifications-with-pusher-com/">See example how to setup Pusher from Perfex CRM documentation</a>';
            die;
        }

        if (get_option('pusher_cluster') != '') {
            $this->pusher_options['cluster'] = get_option('pusher_cluster');
        }
        $this->pusher = new Pusher\Pusher(
            $this->pusher_options['app_key'],
            $this->pusher_options['app_secret'],
            $this->pusher_options['app_id'],
            ['cluster' => $this->pusher_options['cluster']]
        );
    }

    public function users_get()
    {
        $users = $this->chat_model->getUsers();

        if ($users) {
            foreach ($users as &$user) {
                $user['profile_images'] = [
                    'small' => staff_profile_image_url($user['staffid']),
                    'thumb' => staff_profile_image_url($user['staffid'], 'thumb'),
                ];
            }
            $data = [
                'users' => $users,
                'getUnread' => $this->chat_model->getUnread()
            ];

            $this->response($data, REST_Controller::HTTP_OK);
        } else {
            $this->response([
                'status' => FALSE,
                'message' => _l('chat_error_table')
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function update_unread_post()
    {
        if ($this->input->post('id')) {
            $id = $this->input->post('id');
            $result = $this->chat_model->updateUnread($this->pusher, $id);

            if ($result) {
                $this->response(['status' => true, 'message' => ''], REST_Controller::HTTP_OK);
            }
        }

        $this->response([
            'status' => FALSE,
            'message' => _l('something went wrong')
        ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
    }

    public function my_groups_post()
    {
        return $this->chat_model->getMyGroups();
    }

    public function messages_get()
    {
        $limit = $this->input->get('limit');
        $from = $this->input->get('from');
        $to = $this->input->get('to');

        ($limit) ? $limit : $limit = 10;

        $offset = 0;
        if ($this->input->get('offset')) {
            $offset = $this->input->get('offset');
        }

        $response = $this->getMessages($from, $to, $limit, $offset);

        if ($response) {
            $this->response($response, REST_Controller::HTTP_OK);
        } else {
            $this->response([
                'status' => FALSE,
                'message' => _l('chat_no_more_messages_in_database')
            ], REST_Controller::HTTP_OK);
        }
    }

    public function group_messages_get()
    {
        $limit = $this->input->get('limit');
        $group_id = $this->input->get('group_id');
        $message = '';

        ($limit) ? $limit : $limit = 10;

        $offset = 0;

        if ($this->input->get('offset')) {
            $offset = $this->input->get('offset');
        }

        $response = $this->getGroupMessages($group_id, $limit, $offset);

        if ($response) {
            $this->response($response, REST_Controller::HTTP_OK);
        } else {
            $this->response([
                'status' => FALSE,
                'message' => _l('chat_no_more_messages_in_database')
            ], REST_Controller::HTTP_OK);
        }
    }

    public function shared_files_post()
    {
        $own_id = $this->input->post('own_id');
        $contact_id = $this->input->post('contact_id');

        $data = $this->get_shared_files_and_create_template($own_id, $contact_id);

        $this->response($data, REST_Controller::HTTP_OK);
    }

    public function group_shared_files_post()
    {
        $group_id = $this->input->post('group_id');
        $data = $this->get_group_shared_files_and_create_template($group_id);

        $this->response($data, REST_Controller::HTTP_OK);
    }

    public function search_messages_post()
    {
        $id = $this->input->post('id');
        $table = $this->input->post('table');

        $name = (strpos($id, 'client') !== false)
            ? get_contact_full_name(str_replace('client_', '', $id))
            : get_staff_full_name($id);

        $data = [
            'id'             => $id,
            'user_full_name' => $name,
            'messages'       => $this->chat_model->getMessagesHistoryBetween($id, $table),
        ];

        $this->response($data, REST_Controller::HTTP_OK);
    }

    public function export_csv_get()
    {
        if (is_admin()) {
            $to = $this->input->get('user');

            $this->chat_model->initiateExportToCSV($to);
        } else {
            $this->response([
                'status' => FALSE,
                'message' => _l('access_denied')
            ]);
        }
    }

    public function delete_chat_conversation_post()
    {
        if (! chatStaffCanDelete()) access_denied();

        if ($this->input->post('id')) {
            $id = $this->input->post('id');
            $table = $this->input->post('table');
            header('Content-Type: application/json');
            echo json_encode($this->chat_model->deleteMutualConversation($id, $table));
        }
    }

    /**
     * Pusher authentication.
     *
     * @return mixed
     * @throws \Pusher\PusherException
     */
    public function pusher_auth_post()
    {
        if ($this->input->post()) {
            $name = get_staff_full_name();
            $user_id = get_staff_user_id();
            $channel_name = $this->input->post('channel_name');
            $socket_id = $this->input->post('socket_id');

            if (! $channel_name) {
                exit('channel_name must be supplied');
            }

            if (! $socket_id) {
                exit('socket_id must be supplied');
            }

            if (
                ! empty($this->pusher_options['app_key'])
                && ! empty($this->pusher_options['app_secret'])
                && ! empty($this->pusher_options['app_id'])
            ) {
                $justLoggedIn = false;

                if ($this->session->has_userdata('prchat_user_before_login')) {
                    $this->session->unset_userdata('prchat_user_before_login');

                    $justLoggedIn = true;
                }

                $presence_data = [
                    'name'         => $name,
                    'justLoggedIn' => $justLoggedIn,
                    'status'       => '' . $this->chat_model->_get_chat_status() . ''
                ];

                $auth = $this->pusher->presence_auth($channel_name, $socket_id, $user_id, $presence_data);
                echo $auth;
            } else {
                exit('Appkey, secret or appid is missing');
            }
        }
    }

    /**
     * Messaging events
     *
     * @return void
     */
    public function initiate_chat_post()
    {
        if ($this->input->post('typing') == 'false') {
            $imageData['sender_image'] = $this->chat_model->getUserImage(get_staff_user_id());
            $imageData['receiver_image'] = $this->chat_model->getUserImage(str_replace('#', '', $this->input->post('to')));

            $from = $this->input->post('from');
            $receiver = str_replace('#', '', $this->input->post('to'));

            if (trim($this->input->post('msg')) !== '') {
                $message_data = [
                    'sender_id'   => $this->input->post('from'),
                    'reciever_id' => str_replace('#', '', $this->input->post('to')),
                    'message'     => htmlentities($this->input->post('msg')),
                    'viewed'      => 0,
                    'time_sent'   => date("Y-m-d H:i:s"),
                ];

                $last_id = $this->chat_model->createMessage($message_data, db_prefix() . 'chatmessages');

                $this->pusher->trigger('presence-mychanel', 'send-event', [
                    'message'        => pr_chat_convertLinkImageToString($this->input->post('msg')),
                    'from'           => $from,
                    'to'             => $receiver,
                    'from_name'      => get_staff_full_name($from),
                    'last_insert_id' => $last_id,
                    'sender_image'   => $imageData['sender_image'],
                    'receiver_image' => $imageData['receiver_image'],
                ]);

                $this->pusher->trigger(
                    'presence-mychanel',
                    'notify-event',
                    [
                        'from'         => $this->input->post('from'),
                        'to'           => str_replace('#', '', $this->input->post('to')),
                        'from_name'    => get_staff_full_name($from),
                        'sender_image' => $imageData['sender_image'],
                        'message'      => pr_chat_convertLinkImageToString($this->input->post('msg')),
                    ]
                );

                hooks()->do_action('pr_chat_message_notification', [
                    'type'         => 'send-event',
                    'from'         => $this->input->post('from'),
                    'to'           => str_replace('#', '', $this->input->post('to')),
                    'from_name'    => get_staff_full_name($from),
                    'sender_image' => $imageData['sender_image'],
                    'message'      => pr_chat_convertLinkImageToString($this->input->post('msg'))
                ]);
            }
        } else if ($this->input->post('typing') == 'true') {
            $this->pusher->trigger(
                'presence-mychanel',
                'typing-event',
                [
                    'message' => $this->input->post('typing'),
                    'from'    => $this->input->post('from'),
                    'to'      => str_replace('#', '', $this->input->post('to')),
                ]
            );
        } else {
            $this->pusher->trigger(
                'presence-mychanel',
                'typing-event',
                [
                    'message' => 'null',
                    'from'    => $this->input->post('from'),
                    'to'      => str_replace('#', '', $this->input->post('to')),
                ]
            );
        }
    }

    public function initiate_group_chat_post()
    {
        $from = $this->input->post('from');
        $group_id = $this->input->post('group_id');
        $group_name = $this->db->get_where(db_prefix() . 'chatgroups', ['id' => $group_id])->row('group_name');

        if ($this->input->post('typing') == 'false') {
            $imageData['sender_image'] = $this->chat_model->getUserImage(get_staff_user_id());

            $message_data = [
                'sender_id' => $this->input->post('from'),
                'group_id'  => $this->input->post('group_id'),
                'message'   => htmlspecialchars($this->input->post('g_message')),
                'time_sent' => date("Y-m-d H:i:s")
            ];

            $last_id = $this->chat_model->createGroupMessage($message_data);

            $this->pusher->trigger($group_name, 'group-send-event', [
                'message'        => pr_chat_convertLinkImageToString($this->input->post('g_message')),
                'from'           => $from,
                'to_group'       => $group_id,
                'from_name'      => get_staff_full_name($this->input->post('from')),
                'group_name'     => $group_name,
                'last_insert_id' => $last_id,
                'sender_image'   => $imageData['sender_image'],
            ]);

            $this->pusher->trigger($group_name, 'group-notify-event', [
                'from'         => $this->input->post('from'),
                'from_name'    => get_staff_full_name($this->input->post('from')),
                'to_group'     => $group_id,
                'group_name'   => $group_name,
                'sender_image' => $imageData['sender_image'],
                'message'      => pr_chat_convertLinkImageToString($this->input->post('g_message')),
            ]);

            hooks()->do_action('pr_chat_message_notification', [
                'type'         => 'group-send-event',
                'message'        => pr_chat_convertLinkImageToString($this->input->post('g_message')),
                'from'           => $from,
                'to_group'       => $group_id,
                'from_name'      => get_staff_full_name($this->input->post('from')),
                'group_name'     => $group_name,
                'last_insert_id' => $last_id,
                'sender_image'   => $imageData['sender_image'],
            ]);
        } else if ($this->input->post('typing') == 'true') {
            $this->pusher->trigger(
                $group_name,
                'group-typing-event',
                [
                    'message'    => $this->input->post('typing'),
                    'from'       => $this->input->post('from'),
                    'to_group'   => $group_id,
                    'group_name' => $group_name,
                ]
            );
        } else {
            $this->pusher->trigger(
                $group_name,
                'group-typing-event',
                [
                    'message'    => 'test',
                    'from'       => $this->input->post('from'),
                    'to_group'   => $group_id,
                    'group_name' => $group_name,
                ]
            );
        }
    }

    public function upload_method_post()
    {
        $allowedFiles = get_option('allowed_files');
        $allowedFiles = str_replace(',', '|', $allowedFiles);
        $allowedFiles = str_replace('.', '', $allowedFiles);

        $config = [
            'upload_path'   => PR_CHAT_MODULE_UPLOAD_FOLDER,
            'allowed_types' => $allowedFiles,
            'max_size'      => '9048000',
        ];

        $this->load->library('upload', $config);

        if ($this->upload->do_upload()) {
            $from = $this->input->post()['send_from'];
            $to = str_replace('id_', '', $this->input->post()['send_to']);

            if (is_numeric($from) && is_numeric($to)) {
                $this->db->insert(
                    'tblchatsharedfiles',
                    [
                        'sender_id'   => $from,
                        'reciever_id' => $to,
                        'file_name'   => $this->upload->data('file_name'),
                    ]
                );
            }

            $response = $this->upload->data();
            $response['file_url'] = base_url('modules/prchat/uploads/' . $response['file_name']);
            $this->response($response, REST_Controller::HTTP_OK);
        } else {
            $this->response([
                'status' => FALSE,
                'message' => $this->upload->display_errors()
            ], REST_Controller::HTTP_OK);
        }
    }

    public function group_upload_method_post()
    {
        $allowedFiles = get_option('allowed_files');
        $allowedFiles = str_replace(',', '|', $allowedFiles);
        $allowedFiles = str_replace('.', '', $allowedFiles);

        $config = [
            'upload_path'   => PR_CHAT_MODULE_UPLOAD_FOLDER,
            'allowed_types' => $allowedFiles,
            'max_size'      => '9048000',
        ];

        $this->load->library('upload', $config);

        if ($this->upload->do_upload()) {
            $from = $this->input->post()['send_from'];
            $to_group = $this->input->post()['to_group'];

            $this->db->insert(
                'tblchatgroupsharedfiles',
                [
                    'sender_id' => $from,
                    'group_id'  => $to_group,
                    'file_name' => $this->upload->data('file_name'),
                ]
            );

            $response = $this->upload->data();
            $response['file_url'] = base_url('modules/prchat/uploads/' . $response['file_name']);
            $this->response($response, REST_Controller::HTTP_OK);
        } else {
            $this->response([
                'status' => FALSE,
                'message' => $this->upload->display_errors()
            ], REST_Controller::HTTP_OK);
        }
    }

    public function add_chat_group_post()
    {
        if ($this->input->post('group_name')) {
            $data = [];

            $data['group_name'] = 'presence-' . slugifyGroupName($this->input->post('group_name'));

            $data['members'] = $this->input->post('members');

            $own_id = $this->session->userdata('staff_user_id');

            if (empty($data['members'])) {
                return false;
            }

            if (! in_array($own_id, $data['members'])) {
                array_push($data['members'], $own_id);
            }

            $insertData = [
                'created_by_id' => $own_id,
                'group_name'    => $data['group_name'],
            ];

            return $this->chat_model->addChatGroup($insertData, $data, $this->pusher);
        }
    }

    public function rename_chat_group_post()
    {
        $groupId = $this->input->post('groupId');
        $newName = $this->input->post('groupName');

        try {
            if ($groupId) {
                $this->db->where('id', $groupId)->update(db_prefix() . 'chatgroups', ['group_name' => 'presence-' . slugifyGroupName($newName)]);
                $this->db->where('group_id', $groupId)->update(db_prefix() . 'chatgroupmembers', ['group_name' => 'presence-' . slugifyGroupName($newName)]);
            }

            $this->pusher->trigger(
                'group-chat',
                'group-renamed',
                [
                    'group_id' => $groupId,
                    'newName'  => $newName,
                ]
            );
            $this->response(['status' => true, 'message' => _l('chat_group_rename_success')], REST_Controller::HTTP_OK);
        } catch (Exception $e) {
            $this->response(['status' => false, 'message' => $e->getMessage()], REST_Controller::HTTP_OK);
        }
    }

    public function add_chat_group_members_post()
    {
        if (! empty($this->input->post('group_name'))) {
            $group_name = $this->input->post('group_name');
            $members = $this->input->post('members');
            $group_id = $this->input->post('group_id');

            return $this->chat_model->addChatGroupMembers($group_name, $group_id, $members, $this->pusher);
        }
    }

    public function remove_chat_group_user_post()
    {
        $own_id = get_staff_user_id();

        if ($this->input->post('id')) {
            $group_name = $this->input->post('group_name');
            $user_id = $this->input->post('id');
            $group_id = $this->input->post('group_id');

            return $this->chat_model->removeChatGroupUser($group_name, $group_id, $user_id, $own_id, $this->pusher);
        } else {
            return false;
        }
    }

    public function chat_member_leave_group_post()
    {
        if ($this->input->post('group_id')) {
            $group_id = $this->input->post('group_id');
            $member_id = $this->input->post('member_id');

            return $this->chat_model->chatMemberLeaveGroup($group_id, $member_id, $this->pusher);
        }
    }

    public function delete_group_post()
    {
        if ($this->input->post('group_id')) {
            $group_id = $this->input->post('group_id');
            $group_name = $this->input->post('group_name');

            return $this->chat_model->deleteGroup($group_id, $group_name, $this->pusher);
        }
    }

    public function delete_message_post()
    {
        if (chatStaffCanDelete()) {
            $id = $this->input->post('id');
            $contact_id = $this->input->post('contact_id');

            if ($this->input->post('group_id')) {
                $group_id = $this->input->post('group_id');

                $output = $this->chat_model->deleteMessage($id, 'group_id' . $group_id);
            } else {
                $output = $this->chat_model->deleteMessage($id, $contact_id);
            }

            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Message Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Message Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        } else {
            $message = array(
                'status' => FALSE,
                'message' => 'Message Delete Fail.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function forward_users_get()
    {
        $data['staff'] = $this->chat_model->getStaffForForward();

        foreach ($data['staff'] as &$user) {
            $user['profile_images'] = [
                'small' => staff_profile_image_url($user['staffid']),
                'thumb' => staff_profile_image_url($user['staffid'], 'thumb'),
            ];
        }

        $data['groups'] = $this->chat_model->getChatGroups();

        $this->response($data, REST_Controller::HTTP_OK);
    }

    public function staff_announcement_get()
    {
        $staff = $this->chat_model->getUsers();

        foreach ($staff as &$user) {
            $user['profile_images'] = [
                'small' => staff_profile_image_url($user['staffid']),
                'thumb' => staff_profile_image_url($user['staffid'], 'thumb'),
            ];
        }

        $this->response($staff, REST_Controller::HTTP_OK);
    }

    public function staff_announcement_post()
    {
        if ($this->input->post()) {
            $members = $this->input->post('members');
            $message = $this->input->post('message');

            $this->response($this->chat_model->globalMessage($members, $message, $this->pusher), REST_Controller::HTTP_OK);
        }
    }

    public function handle_audio_post()
    {
        if ($this->input->post('audio')) {
            $audioB64Data = str_replace('data:audio/ogg;base64,', '', str_replace('[removed]', 'data:audio/ogg;base64, ', $this->input->post('audio')));
            $decodedAudio = base64_decode($audioB64Data);
            $hashFilename = app_generate_hash() . '-' . app_generate_hash();

            $fileSaveLocation = PR_CHAT_MODULE_UPLOAD_FOLDER . "/audio/{$hashFilename}.ogg";

            try {
                if (file_put_contents($fileSaveLocation, $decodedAudio)) {
                    $response = ['filename' => $hashFilename . '.ogg'];
                    $response['audio_url'] = base_url('modules/prchat/uploads/audio/' . $response['filename']);
                    $response['status'] = true;
                    $response['message'] = _l('chat_audio_upload_success');
                    $response['audio_html'] = '<audio controls src="' . site_url('modules/prchat/uploads/audio/' . $response['filename']) . '" type="audio/ogg"></audio>';

                    $this->response($response, REST_Controller::HTTP_OK);
                }
            } catch (\Exception $e) {
                $this->response([
                    'status' => FALSE,
                    'message' => 'Whoops! Something went wrong... and the message is: ' . $e->getMessage()
                ], REST_Controller::HTTP_OK);
            }
        }
    }

    private function getMessages($from, $to, $limit, $offset)
    {
        $sql = 'SELECT * FROM ' . db_prefix() . "chatmessages WHERE (sender_id = {$to} AND reciever_id = {$from}) OR (sender_id = {$from} AND reciever_id = {$to}) ORDER BY id DESC LIMIT {$offset}, {$limit}";
        $query = $this->db->query($sql)->result();

        $fileTypes = [
            'isImage' => '~https?://[^\s]+?\.(jpe?g|png|gif|tiff?|svg)~i',
            'isVideo' => '~https?://[^\s]+?\.(mp4|webm|ogg|mov|flv|wmv|avi)~i',
            'isAudio' => '~https?://[^\s]+?\.(mp3|wav|ogg)~i',
            'isFile'  => '~https?://[^\s]+?\.(docx?|xlsx?|pptx?|pdf|zip|rar|txt|doc|xls|php|html|css)~i',
        ];

        foreach ($query as &$chat) {
            // Reset attachment flags
            $chat->type = 'text';

            foreach ($fileTypes as $flag => $regex) {
                if (preg_match($regex, $chat->message)) {
                    $chat->type = 'attachment';
                    $chat->$flag = true;
                }
            }

            // Decode HTML for media tags
            if (preg_match('~\b(src|audio|controls|ogg)\b~i', $chat->message)) {
                $chat->message = html_entity_decode($chat->message);
            }

            // Basic user data
            $chat->user_image = $this->chat_model->getUserImage($chat->sender_id);
            $chat->sender_fullname = get_staff_full_name($chat->sender_id);
            $chat->time_sent_formatted = _dt($chat->time_sent);

            // Handle announcement messages
            if (strpos($chat->message, _l('chat_message_announce')) !== false) {
                $chat->message = str_replace(_l('chat_message_announce'), '', $chat->message);
                $chat->isAnnouncement = true;
            }
        }

        return $query ?: false;
    }

    private function getGroupMessages($group_id, $limit, $offset)
    {
        if ($group_id !== null) {
            $sql = 'SELECT * FROM ' . db_prefix() . "chatgroupmessages WHERE group_id = {$group_id} ORDER BY id DESC LIMIT {$offset}, {$limit}";

            $query = $this->db->query($sql)->result();
            $created_by = $this->db->get_where(db_prefix() . 'chatgroups', ['id' => $group_id])->row('created_by_id');

            $fileTypes = [
                'isImage' => '~https?://[^\s]+?\.(jpe?g|png|gif|tiff?|svg)~i',
                'isVideo' => '~https?://[^\s]+?\.(mp4|webm|ogg|mov|flv|wmv|avi)~i',
                'isAudio' => '~https?://[^\s]+?\.(mp3|wav|ogg)~i',
                'isFile'  => '~https?://[^\s]+?\.(docx?|xlsx?|pptx?|pdf|zip|rar|txt|doc|xls|php|html|css)~i',
            ];

            foreach ($query as &$chat) {
                $chat->type = 'text';
                foreach ($fileTypes as $flag => $regex) {
                    if (preg_match($regex, $chat->message)) {
                        $chat->type = 'attachment';
                        $chat->$flag = true;
                    }
                }

                /**
                 *  If message not contains user_mentioned class and emoji class convert image link data-lity
                 *  This gives an incorrect image path when you have @firstname lastname mention and :D  (emoji )inside a message
                 */
                // if (strpos($chat->message, 'user_mentioned') != true && strpos($chat->message, '"emoji"') != true) {
                //     $chat->message = pr_chat_convertLinkImageToString($chat->message);
                // }

                // $chat->message = check_for_links_lity($chat->message);

                if (preg_match('~\b(src|audio|controls|ogg)\b~i', $chat->message)) {
                    $chat->message = html_entity_decode($chat->message);
                }

                if (strpos($chat->message, _l('chat_message_announce')) !== false) {
                    $chat->message = str_replace(_l('chat_message_announce'), '', $chat->message);
                    $chat->isAnnouncement = true;
                }

                $chat->user_image = $this->chat_model->getUserImage($chat->sender_id);
                $chat->sender_fullname = get_staff_full_name($chat->sender_id);
                $chat->time_sent_formatted = _dt($chat->time_sent);
                $chat->created_by_id = $created_by;
            }

            $newQuery['messages'] = $query;

            $this->db->select('member_id,  group_id, lastname, firstname, created_by_id');
            $this->db->from(TABLE_CHATGROUPMEMBERS);
            $this->db->where('group_id', $group_id);
            $this->db->join(TABLE_STAFF, '' . TABLE_STAFF . '.staffid=' . TABLE_CHATGROUPMEMBERS . '.member_id');
            $this->db->join(TABLE_CHATGROUPS, '' . TABLE_CHATGROUPS . '.id=' . TABLE_CHATGROUPMEMBERS . '.group_id');
            $result = $this->db->get();
            $newQuery['users'] = $result->result_array();

            foreach ($newQuery['users'] as &$user) {
                $user['profile_image'] = staff_profile_image_url($user['member_id']);
            }

            $group_name = $this->db->get_where(TABLE_CHATGROUPS, ['id' => $group_id])->row('group_name');

            $newQuery['separete_group_id'] = $group_id;
            $newQuery['separete_group_name'] = $group_name;

            if ($newQuery) {
                return $newQuery;
            }
        } else {
            return false;
        }

        return false;
    }

    private function get_shared_files_and_create_template($own_id, $contact_id)
    {
        $files = [];
        $data_lity = ' ';
        $allFiles = 'unknown|rar|zip|mp3|mp4|mov|flv|wmv|avi|doc|docx|pdf|xls|xlsx|zip|rar|txt|php|html|css|jpeg|jpg|png|swf|PNG|JPG|JPEG';
        $photoExtensions = 'unknown|jpeg|jpg|png|gif|swf|PNG|JPG|JPEG|';
        $docFiles = 'unknown|rar|zip|mp3|mp4|mov|flv|wmv|avi|doc|docx|pdf|xls|xlsx|zip|rar|txt|php|html|css';

        $dir = list_files(PR_CHAT_MODULE_UPLOAD_FOLDER);

        $from_messages_table = $this->db->query('SELECT file_name FROM ' . db_prefix() . 'chatsharedfiles' . " WHERE file_name REGEXP '^.*\.(" . $allFiles . ")$' AND sender_id  = '" . $own_id . "' AND reciever_id = '" . $contact_id . "' OR sender_id = '" . $contact_id . "' AND reciever_id = '" . $own_id . "'");
        if ($from_messages_table) {
            $from_messages_table = $from_messages_table->result_array();
        } else {
            return false;
        }

        foreach ($dir as $file_name) {
            foreach ($from_messages_table as $value) {
                if (strpos($file_name, $value['file_name']) !== false) {
                    if (!in_array($file_name, $files)) {
                        array_push($files, $file_name);
                    }
                }
            }
        }

        $response = [
            'photos' => [],
            'pdfs' => [],
            'docs' => []
        ];
        foreach ($files as $file) {
            if (preg_match("/^[^\?]+\.('" . $photoExtensions . "')$/", $file)) {
                $response['photos'][] = [
                    'file_url' => base_url('modules/prchat/uploads/' . $file),
                    'file_name' => $file
                ];
            }
        }

        foreach ($files as $file) {
            if (strpos($file, '.pdf')) {
                $response['pdfs'][] = [
                    'file_url' => base_url('modules/prchat/uploads/' . $file),
                    'file_name' => $file
                ];
            }
            if (preg_match("/^[^\?]+\.('" . $docFiles . "')$/", $file)) {
                $response['docs'][] = [
                    'file_url' => base_url('modules/prchat/uploads/' . $file),
                    'file_name' => $file
                ];
            }
        }

        return $response;
    }

    private function get_group_shared_files_and_create_template($group_id)
    {
        $files = [];
        $data_lity = ' ';
        $allFiles = 'unknown|rar|zip|mp3|mp4|mov|flv|wmv|avi|doc|docx|pdf|xls|xlsx|zip|rar|txt|php|html|css|jpeg|jpg|png|swf|PNG|JPG|JPEG';
        $photoExtensions = 'unknown|jpeg|jpg|png|gif|swf|PNG|JPG|JPEG|';
        $docFiles = 'unknown|rar|zip|mp3|mp4|mov|flv|wmv|avi|doc|docx|pdf|xls|xlsx|zip|rar|txt|php|html|css';

        $dir = list_files(PR_CHAT_MODULE_GROUPS_UPLOAD_FOLDER);

        $from_messages_table = $this->db->query('SELECT file_name FROM ' . db_prefix() . 'chatgroupsharedfiles' . " WHERE file_name REGEXP '^.*\.(" . $allFiles . ")$' AND group_id = '" . $group_id . "'");

        if ($from_messages_table) {
            $from_messages_table = $from_messages_table->result_array();
        } else {
            return false;
        }
        foreach ($dir as $file_name) {
            foreach ($from_messages_table as $value) {
                if (strpos($file_name, $value['file_name']) !== false) {
                    if (!in_array($file_name, $files)) {
                        array_push($files, $file_name);
                    }
                }
            }
        }

        $response = [
            'photos' => [],
            'pdfs' => [],
            'docs' => []
        ];
        foreach ($files as $file) {
            if (preg_match("/^[^\?]+\.('" . $photoExtensions . "')$/", $file)) {
                $response['photos'][] = [
                    'file_url' => base_url('modules/prchat/uploads/' . $file),
                    'file_name' => $file
                ];
            }
        }

        foreach ($files as $file) {
            if (strpos($file, '.pdf')) {
                $response['pdfs'][] = [
                    'file_url' => base_url('modules/prchat/uploads/' . $file),
                    'file_name' => $file
                ];
            }
            if (preg_match("/^[^\?]+\.('" . $docFiles . "')$/", $file)) {
                $response['docs'][] = [
                    'file_url' => base_url('modules/prchat/uploads/' . $file),
                    'file_name' => $file
                ];
            }
        }

        return $response;
    }
}
