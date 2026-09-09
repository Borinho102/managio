<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ApprovalFlow — Directory listing guard
 *
 * Prevents directory indexing if Apache directory listing is enabled.
 * All real entry points live one level deeper (approvalflow.php manifest,
 * controllers/, models/, views/). Keep this file two lines + comment.
 *
 * @package ApprovalFlow
 */
