<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="_buttons tw-mb-4">
    <?php $c_url = !empty(subdomain()) ? 'clients/' : 'admin/'; ?>
    <a href="<?php echo site_url($c_url . 'buy-domain'); ?>" class="btn btn-primary">
        <i class="fa fa-plus tw-mr-1"></i> Buy a New Domain
    </a>
    <div class="clearfix"></div>
</div>

<!-- Pending purchase requests -->
<?php if (!empty($requests)): ?>
<div class="panel_s">
    <div class="panel-heading">
        <h4><i class="fa fa-clock-o"></i> Pending Domain Requests</h4>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Requested</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $req): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($req->domain_name); ?></strong></td>
                        <td>
                            <?php if (!empty($req->price)): ?>
                                <?php echo number_format($req->price, 2); ?> <?php echo htmlspecialchars($req->currency); ?>/yr
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($req->status === 'pending'): ?>
                                <span class="label label-warning">Pending Review</span>
                            <?php elseif ($req->status === 'registered'): ?>
                                <span class="label label-success">Registered</span>
                            <?php elseif ($req->status === 'rejected'): ?>
                                <span class="label label-danger">Rejected</span>
                            <?php else: ?>
                                <span class="label label-default"><?php echo htmlspecialchars($req->status); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo _dt($req->created_at); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Registered domains -->
<div class="panel_s">
    <div class="panel-heading">
        <h4><i class="fa fa-globe"></i> My Registered Domains</h4>
    </div>
    <div class="panel-body">
        <?php if (empty($domains)): ?>
            <div class="text-center" style="padding:40px 0">
                <i class="fa fa-globe" style="font-size:48px; color:#ccc;"></i>
                <p class="text-muted" style="margin-top:15px">No domains registered yet.</p>
                <a href="<?php echo site_url($c_url . 'buy-domain'); ?>" class="btn btn-primary">
                    <i class="fa fa-search"></i> Search for a Domain
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped DataTables" width="100%">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Status</th>
                            <th>Expires</th>
                            <th>DNS</th>
                            <th>Auto-Renew</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($domains as $domain): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($domain->domain_name); ?></strong><br>
                                <small class="text-muted">
                                    <a href="https://<?php echo htmlspecialchars($domain->domain_name); ?>" target="_blank">
                                        <?php echo htmlspecialchars($domain->domain_name); ?> <i class="fa fa-external-link"></i>
                                    </a>
                                </small>
                            </td>
                            <td>
                                <?php if ($domain->status === 'active'): ?>
                                    <span class="label label-success">Active</span>
                                <?php elseif ($domain->status === 'pending'): ?>
                                    <span class="label label-warning">Pending</span>
                                <?php elseif ($domain->status === 'expired'): ?>
                                    <span class="label label-danger">Expired</span>
                                <?php else: ?>
                                    <span class="label label-default"><?php echo htmlspecialchars($domain->status); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($domain->expiry_date)): ?>
                                    <?php
                                    $days = (int) ((strtotime($domain->expiry_date) - time()) / 86400);
                                    $cls  = $days < 30 ? 'text-danger' : ($days < 90 ? 'text-warning' : 'text-success');
                                    ?>
                                    <span class="<?php echo $cls; ?>">
                                        <?php echo date('d M Y', strtotime($domain->expiry_date)); ?>
                                        <small>(<?php echo $days; ?> days)</small>
                                    </span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($domain->dns_configured): ?>
                                    <span class="label label-success"><i class="fa fa-check"></i> Configured</span>
                                <?php else: ?>
                                    <span class="label label-warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $domain->auto_renew ? '<span class="label label-success">ON</span>' : '<span class="label label-default">OFF</span>'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
