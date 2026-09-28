<section class="bg-invoice d-table w-100 bg-primary">
    <div class="container">
        <div class="row mt-5 justify-content-center">
            <div class="col-12 text-center">
                <div class="pages-heading title-heading">
                    <h2 class="text-white title-dark"><?= html_escape(function_exists('saas_brand_name') ? saas_brand_name() : 'Managio'); ?></h2>
                    <p class="text-white-50 para-desc mb-0 mx-auto">
                        <?= html_escape(function_exists('saas_brand_name') ? saas_brand_name() : 'Managio'); ?>
                        — gestion, facturation et relation client pour votre entreprise.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
<section class="section pt-3">
    <div class="container ">
        <?php
        $alertclass = "";
        if ($this->session->flashdata('message-success')) {
            $alertclass = "success";
        } else if ($this->session->flashdata('message-warning')) {
            $alertclass = "warning";
        } else if ($this->session->flashdata('message-info')) {
            $alertclass = "info";
        } else if ($this->session->flashdata('message-danger')) {
            $alertclass = "danger";
        }
        if ($this->session->flashdata('message-' . $alertclass)) {
            $messages = $this->session->flashdata('message-' . $alertclass);
            if (is_array($messages)) {

                foreach ($messages as $message) { ?>
                    <div class="col-lg-12 d-flex align-items-center justify-content-center">
                        <div class="text-center alert alert-<?php echo $alertclass; ?>">
                            <?php echo $message; ?>
                        </div>
                    </div>
                    <?php
                }
            } else { ?>
                <div class="col-lg-12 d-flex align-items-center justify-content-center">
                    <div class="text-center alert alert-<?php echo $alertclass; ?>">
                        <?php echo $messages; ?>
                    </div>
                </div>
            <?php }
        }
        ?>
        <?php
        $this->load->view('frontcms/frontend/signup_company')
        ?>
    </div>
</section>
