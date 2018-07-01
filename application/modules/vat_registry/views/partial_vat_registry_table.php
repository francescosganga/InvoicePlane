<div class="table-responsive">
    <table class="table table-striped">

        <thead>
        <tr>
            <th><?php _trans('status'); ?></th>
            <th><?php _trans('vat_registry'); ?></th>
            <th><?php _trans('created'); ?></th>
            <th><?php _trans('day'); ?></th>
            <th><?php _trans('month'); ?></th>
            <th><?php _trans('year'); ?></th>
            <th style="text-align: right;"><?php _trans('amount'); ?></th>
            <th><?php _trans('options'); ?></th>
        </tr>
        </thead>

        <tbody>
        <?php
        $vat_registry_idx = 1;
        $vat_registry_count = count($vats_registry);
        $vat_registry_list_split = $vat_registry_count > 3 ? $vat_registry_count / 2 : 9999;
        foreach ($vats_registry as $vat_registry) {
            // Convert the dropdown menu to a dropup if vat_registry is after the vat_registry split
            $dropup = $vat_registry_idx > $vat_registry_list_split ? true : false;
            ?>
            <tr>
                <td>
                    <span class="label <?php echo $vat_registry_statuses[$vat_registry->vat_registry_status_id]['class']; ?>">
                        <?php echo $vat_registry_statuses[$vat_registry->vat_registry_status_id]['label']; ?>
                    </span>
                </td>

                <td>
                    <a href="<?php echo site_url('vats_registry/view/' . $vat_registry->vat_registry_id); ?>"
                       title="<?php _trans('edit'); ?>">
                        <?php echo $vat_registry->vat_registry_id; ?>
                    </a>
                </td>

                <td>
                    <?php echo date_from_mysql($vat_registry->vat_registry_date_created); ?>
                </td>

                <td>
                    <span>
                        <?php echo $vat_registry->vat_registry_day; ?>
                    </span>
                </td>
                
                <td>
                    <span>
                        <?php echo $vat_registry->vat_registry_month; ?>
                    </span>
                </td>
                
                <td>
                    <span>
                        <?php echo $vat_registry->vat_registry_year; ?>
                    </span>
                </td>

                <td class="amount">
                    <?php echo format_currency($vat_registry->vat_registry_amount); ?>
                </td>
                
                <td>
                    <div class="options btn-group<?php echo $dropup ? ' dropup' : ''; ?>">
                        <a class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" href="#">
                            <i class="fa fa-cog"></i> <?php _trans('options'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php if ($vat_registry->is_read_only != 1) { ?>
                                <li>
                                    <a href="<?php echo site_url('vats_registry/view/' . $vat_registry->vat_registry_id); ?>">
                                        <i class="fa fa-edit fa-margin"></i> <?php _trans('edit'); ?>
                                    </a>
                                </li>
                            <?php } ?>
                            <li>
                                <a href="<?php echo site_url('vats_registry/generate_pdf/' . $vat_registry->vat_registry_id); ?>"
                                   target="_blank">
                                    <i class="fa fa-print fa-margin"></i> <?php _trans('download_pdf'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo site_url('mailer/vat_registry/' . $vat_registry->vat_registry_id); ?>">
                                    <i class="fa fa-send fa-margin"></i> <?php _trans('send_email'); ?>
                                </a>
                            </li>
                            <?php if (
                                $vat_registry->vat_registry_status_id == 1 ||
                                ($this->config->item('enable_vat_registry_deletion') === true && $vat_registry->is_read_only != 1)
                            ) { ?>
                                <li>
                                    <form action="<?php echo site_url('vats_registry/delete/' . $vat_registry->vat_registry_id); ?>"
                                          method="POST">
                                        <?php _csrf_field(); ?>
                                        <button type="submit" class="dropdown-button"
                                                onclick="return confirm('<?php _trans('delete_vat_registry_warning'); ?>');">
                                            <i class="fa fa-trash-o fa-margin"></i> <?php _trans('delete'); ?>
                                        </button>
                                    </form>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                </td>
            </tr>
            <?php
            $vat_registry_idx++;
        } ?>
        </tbody>

    </table>
</div>
