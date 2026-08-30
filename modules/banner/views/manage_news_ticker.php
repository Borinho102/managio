<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="_buttons">
                    <?php if (has_permission('news_ticker', get_staff_user_id(), 'create')): ?>
                        <a href="<?php echo admin_url('banner/manage_news_ticker'); ?>"
                            class="btn btn-primary mright5 test pull-left display-block">
                            <i class="fa-regular fa-plus tw-mr-1"></i>
                            <?php echo _l('add_news_ticker'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="clearfix"></div>
                <div class="panel_s tw-mt-2 sm:tw-mt-4">
                    <div class="panel-body">
                        <?php
                            $tableColumns = [
                                _l('#'),
                                _l('news_title'),
                                _l('section'),
                                _l('start_date'),
                                _l('end_date'),
                                _l('status') . '<i class="fa fa-question-circle tw-ml-1" data-toggle="tooltip" data-title="'. _l('news_ticker_status_tooltip') .'" data-placement="top"></i>',
                            ];

if (has_permission('news_ticker', get_staff_user_id(), 'edit') || has_permission('news_ticker', get_staff_user_id(), 'delete')) {
    $tableColumns[] = _l('actions');
}

echo render_datatable($tableColumns, 'news-ticker-table');
?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
$(function() {
  if (typeof initDataTable === 'function') {
    if ($('.table-news-ticker-table').length && !$.fn.DataTable.isDataTable('.table-news-ticker-table')) {
      initDataTable('.table-news-ticker-table', admin_url + 'banner/getTableData/news_ticker_table');
    }
  }
});
</script>
