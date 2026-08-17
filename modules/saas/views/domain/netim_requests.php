<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="panel_s">
    <div class="panel-heading">
        <h4><i class="fa fa-globe"></i> Netim Domain Purchase Requests</h4>
        <div class="pull-right">
            <a href="<?php echo saas_url('netim_domains/domain_list'); ?>" class="btn btn-default btn-sm">
                <i class="fa fa-list"></i> Registered Domains
            </a>
            <a href="<?php echo saas_url('netim_domains/settings'); ?>" class="btn btn-default btn-sm">
                <i class="fa fa-cog"></i> Settings
            </a>
        </div>
        <div class="clearfix"></div>
    </div>
    <div class="panel-body">

        <?php if (empty($requests)): ?>
            <div class="text-center" style="padding:40px 0">
                <i class="fa fa-globe" style="font-size:48px; color:#ccc;"></i>
                <p class="text-muted" style="margin-top:15px">No domain purchase requests yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Domain</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($requests as $req): ?>
                        <tr>
                            <td>
                                <a href="<?php echo saas_url('companies/details/' . $req['company_id']); ?>">
                                    <?php echo htmlspecialchars($req['company_name']); ?>
                                </a>
                            </td>
                            <td><strong><?php echo htmlspecialchars($req['domain_name']); ?></strong></td>
                            <td>
                                <?php if (!empty($req['price'])): ?>
                                    <?php echo number_format($req['price'], 2); ?> <?php echo htmlspecialchars($req['currency']); ?>/yr
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <span class="label label-warning">Pending</span>
                                <?php elseif ($req['status'] === 'registered'): ?>
                                    <span class="label label-success">Registered</span>
                                <?php elseif ($req['status'] === 'rejected'): ?>
                                    <span class="label label-danger">Rejected</span>
                                <?php else: ?>
                                    <span class="label label-default"><?php echo htmlspecialchars($req['status']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo _dt($req['created_at']); ?></td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <a href="<?php echo saas_url('netim_domains/register/' . $req['request_id']); ?>"
                                       class="btn btn-success btn-xs"
                                       onclick="return confirm('Register domain <?php echo htmlspecialchars($req['domain_name']); ?> via Netim API? This will charge your Netim prepaid balance.')">
                                        <i class="fa fa-check"></i> Register Domain
                                    </a>
                                    <a href="<?php echo saas_url('netim_domains/reject/' . $req['request_id']); ?>"
                                       class="btn btn-danger btn-xs _delete">
                                        <i class="fa fa-times"></i> Reject
                                    </a>
                                <?php elseif ($req['status'] === 'registered'): ?>
                                    <span class="text-success"><i class="fa fa-check"></i> Done</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>
