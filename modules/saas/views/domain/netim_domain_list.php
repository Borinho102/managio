<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="panel_s">
    <div class="panel-heading">
        <h4><i class="fa fa-globe"></i> Netim Registered Domains</h4>
        <div class="pull-right">
            <a href="<?php echo saas_url('netim_domains/requests'); ?>" class="btn btn-default btn-sm">
                <i class="fa fa-list"></i> Purchase Requests
            </a>
            <a href="<?php echo saas_url('netim_domains/settings'); ?>" class="btn btn-default btn-sm">
                <i class="fa fa-cog"></i> Settings
            </a>
        </div>
        <div class="clearfix"></div>
    </div>
    <div class="panel-body">

        <?php if (empty($domains)): ?>
            <div class="text-center" style="padding:40px 0">
                <i class="fa fa-globe" style="font-size:48px; color:#ccc;"></i>
                <p class="text-muted" style="margin-top:15px">No domains registered via Netim yet.</p>
                <a href="<?php echo saas_url('netim_domains/requests'); ?>" class="btn btn-primary">
                    View Purchase Requests
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped DataTables" width="100%">
                    <thead>
                        <tr>
                            <th>Domain</th>
                            <th>Company</th>
                            <th>Status</th>
                            <th>Expiry</th>
                            <th>DNS</th>
                            <th>Price</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($domains as $domain): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($domain['domain_name']); ?></strong>
                            </td>
                            <td>
                                <a href="<?php echo saas_url('companies/details/' . $domain['company_id']); ?>">
                                    <?php echo htmlspecialchars($domain['company_name']); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($domain['status'] === 'active'): ?>
                                    <span class="label label-success">Active</span>
                                <?php elseif ($domain['status'] === 'pending'): ?>
                                    <span class="label label-warning">Pending</span>
                                <?php elseif ($domain['status'] === 'expired'): ?>
                                    <span class="label label-danger">Expired</span>
                                <?php else: ?>
                                    <span class="label label-default"><?php echo htmlspecialchars($domain['status']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($domain['expiry_date'])): ?>
                                    <?php
                                    $days = (int) ((strtotime($domain['expiry_date']) - time()) / 86400);
                                    $cls  = $days < 30 ? 'text-danger' : ($days < 90 ? 'text-warning' : '');
                                    ?>
                                    <span class="<?php echo $cls; ?>">
                                        <?php echo date('d M Y', strtotime($domain['expiry_date'])); ?>
                                        <?php if ($days < 90): ?>
                                            <br><small>(<?php echo $days; ?> days)</small>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($domain['dns_configured']): ?>
                                    <span class="label label-success"><i class="fa fa-check"></i> Done</span>
                                <?php else: ?>
                                    <span class="label label-warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($domain['purchase_price'])): ?>
                                    <?php echo number_format($domain['purchase_price'], 2); ?> <?php echo htmlspecialchars($domain['currency']); ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo !empty($domain['registered_at']) ? _dt($domain['registered_at']) : '—'; ?>
                            </td>
                            <td>
                                <?php if (!$domain['dns_configured']): ?>
                                    <a href="<?php echo saas_url('netim_domains/configure_dns/' . $domain['domain_id']); ?>"
                                       class="btn btn-info btn-xs"
                                       onclick="return confirm('Configure DNS for <?php echo htmlspecialchars($domain['domain_name']); ?>? This will add A records pointing to your server IP.')">
                                        <i class="fa fa-sitemap"></i> Configure DNS
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
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
