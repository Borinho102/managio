<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ApprovalFlow — Migration v1.0.0
 *
 * Baseline migration. install.php already creates the four module tables —
 * this migration is a no-op stub registered so Perfex's App_module_migration
 * framework records v1.0.0 as the installed schema version, leaving room
 * for future ALTER statements in 101_*, 102_* etc.
 *
 * @package ApprovalFlow\Migrations
 */
class Migration_Version_100 extends App_module_migration
{
    public function up()
    {
    }
}
