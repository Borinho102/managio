<div>
    <h4 class="tw-font-semibold tw-mt-0 tw-text-neutral-800">
        <?= _l('get') . ' ' . _l('coupons') ?>
        <span class="type type__post">post</span>
    </h4>
    <p>The command that you will need to execute at your terminal, is the following:</p>
    <pre class="prettyprint prettyprinted" style=""><span class="pln">curl </span><span
                class="pun">-</span><span
                class="pln">H </span><span
                class="str">"saas-authtoken: <?= $authtoken ?>"</span><span
                class="com"> //</span><span class="com"><?= site_url('saas-data/get_coupons') ?>
                </span></pre>

    <p>The response that you are supposed to receive, under a 200 OK status code, will be similar to the
        following:</p>
    <pre class="prettyprint prettyprinted" style=""><span class="pun">[</span><span class="pln">
   </span><span class="pun">{</span><span class="pln"></span>
      <span class="str">"id"</span><span class="pln"> </span><span class="pun">:</span><span class="pln"> </span><span
                class="kwd">1</span><span class="pun">,</span>
      <span class="str">"coupon_name"</span><span class="pln"> </span><span class="pun">:</span><span class="pln"> </span><span
                class="str">"New Year"</span><span class="pun">,</span>
      <span class="str">"code"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="str">"newyear30"</span><span class="pun">,</span>
      <span class="str">"amount"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="str">"30"</span><span class="pun">,</span>
      <span class="str">"end_date"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="str">"2023-12-22"</span><span class="pun">,</span>
      <span class="str">"type"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="kwd">1</span><span class="pun">,</span> <span class="com">0 = fixed and 1 = percentage</span>
      <span class="str">"package_type"</span><span class="pln"> </span><span class="pun">:</span><span class="pln"> </span><span
                class="str">"monthly"</span><span class="pun">,</span> <span
                class="com">Frequency for monthly,yearly,lifetime</span>
      <span class="str">"package_name"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span
                class="str">"FREE PLAN"</span><span
                class="pun">,</span> <span class="com">if package_name is null then its for all package</span>
   <span class="pun">}</span><span class="pln">
</span><span class="pun">]</span></pre>
</div>