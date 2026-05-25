<div>
    <h4 class="tw-font-semibold tw-mt-0 tw-text-neutral-800">
        <?= _l('get') . ' ' . _l('packages') ?>
        <span class="type type__post">post</span>
    </h4>
    <p>The command that you will need to execute at your terminal, is the following:</p>
    <pre class="prettyprint prettyprinted" style=""><span class="pln">curl </span><span
                class="pun">-</span><span
                class="pln">H </span><span
                class="str">"saas-authtoken: <?= $authtoken ?>"</span><span
                class="com"> //</span><span class="com"><?= site_url('saas-data/get_package') ?>
                </span></pre>

    <p>The response that you are supposed to receive, under a 200 OK status code, will be similar to the
        following:</p>
    <pre class="prettyprint prettyprinted" style=""><span class="pun">[</span><span class="pln">
   </span><span class="pun">{</span><span class="pln"></span>
      <span class="str">"id"</span><span class="pln"> </span><span class="pun">:</span><span class="pln"> </span><span
                class="kwd">1</span><span class="pun">,</span>
      <span class="str">"name"</span><span class="pln"> </span><span class="pun">:</span><span class="pln"> </span><span
                class="str">"BIZTEAM"</span><span class="pun">,</span>
      <span class="str">"monthly_price"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="str">"20"</span><span class="pun">,</span>
      <span class="str">"lifetime_price"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="str">"363"</span><span class="pun">,</span>
      <span class="str">"yearly_price"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="str">"200"</span><span class="pun">,</span>
      <span class="str">"sort"</span><span class="pln"> </span><span class="pun">:</span><span class="pln"> </span><span
                class="str">"4"</span><span class="pun">,</span>
      <span class="str">"staff_no"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="str">"14"</span><span class="pun">,</span>
      <span class="str">"additional_staff_no"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="kwd">null</span><span class="pun">,</span> <span class="com">Additional staff price</span>
      <span class="str">"client_no"</span><span class="pln"> </span><span class="pun">:</span><span class="pln"> </span><span
                class="kwd">""</span><span class="pun">,</span> <span
                class="com">0 = unlimited and empty = not included</span>
      <span class="str">"additional_client_no"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="kwd">null</span><span class="pun">,</span> <span class="com">Additional staff price</span>
      <span class="str">"project_no"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="str">"14"</span><span class="pun">,</span> <span class="com">0 = unlimited and empty = not included</span>
      <span class="str">"additional_project_no"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="kwd">null</span><span class="pun">,</span> <span class="com">Additional staff price</span>
       <span class="pun">................</span>
       <span class="str">"modules"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span
                class="str">"a:2:{i:0;s:10:\"menu_setup\";i:1;s:7:\"surveys\";}"</span><span
                class="pun">,</span> <span class="com">its json serial data</span>
   <span class="pun">}</span><span class="pln">
</span><span class="pun">]</span></pre>
</div>