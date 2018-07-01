<!DOCTYPE html>
<html lang="<?php echo trans('cldr'); ?>">
<head>
    <title><?php echo trans('quote_history'); ?></title>
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/<?php echo get_setting('system_theme', 'invoiceplane'); ?>/css/reports.css" type="text/css">
</head>
<body>

<h3 class="report_title">
    <?php echo trans('quote_history'); ?><br/>
    <small><?php echo $from_date . ' - ' . $to_date ?></small>
</h3>

<table>
    <tr>
        <th><?php echo trans('date'); ?></th>
        <th><?php echo trans('employee'); ?></th>
        <th><?php echo trans('client'); ?></th>
        <th class="amount"><?php echo trans('amount'); ?></th>
    </tr>
    <?php
    $sum = 0;

    foreach ($results as $result) {
        ?>
        <tr>
            <td><?php echo date_from_mysql($result->quote_date_created, true); ?></td>
            <td><?php echo $result->user_name; ?></td>
            <td><?php echo $result->client_name ?></td>
            <td class="amount"><?php echo format_currency($result->quote_total);
                $sum = $sum + $result->quote_total; ?></td>
        </tr>
        <?php
    }

    if (!empty($results)) {
        ?>
        <tr>
            <td colspan=3><?php echo trans('total'); ?></td>
            <td class="amount"><strong><?php echo format_currency($sum); ?></strong></td>
        </tr>
    <?php } ?>
</table>

</body>
</html>
