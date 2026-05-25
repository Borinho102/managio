<div>
    <h4 class="tw-font-semibold tw-mt-0 tw-text-neutral-800">
        <?= _l('get') . ' ' . _l('modules') ?>
        <span class="type type__post">post</span>
    </h4>
    <p>The command that you will need to execute at your terminal, is the following:</p>
    <pre class="prettyprint prettyprinted" style=""><span class="pln">curl </span><span
                class="pun">-</span><span
                class="pln">H </span><span
                class="str">"saas-authtoken: <?= $authtoken ?>"</span><span
                class="com"> //</span><span class="com"><?= site_url('saas-data/get_modules') ?>
                </span></pre>

    <p>The response that you are supposed to receive, under a 200 OK status code, will be similar to the
        following:</p>
    <pre class="prettyprint prettyprinted" style=""><span class="pun">[</span><span class="pln">
   </span><span class="pun">{</span><span class="pln"></span>
      <span class="str">"module_id"</span><span class="pln"> </span><span class="pun">:</span><span class="pln"> </span><span
                class="kwd">1</span><span class="pun">,</span>
      <span class="str">"module_name"</span><span class="pln"> </span><span class="pun">:</span><span class="pln"> </span><span
                class="str">"Best Database Backup"</span><span class="pun">,</span>
      <span class="str">"price"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="str">"300"</span><span class="pun">,</span>
      <span class="str">"preview_screenshot"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="str">"a:1:{i:0;a:2:{s:9:\"file_name\";s:16:\"default_logo.png\";s:8:\"filetype\";s:9:\"image/png\";}}"</span><span class="pun">,</span><span class="com">its json serial data</span>
      <span class="str">"preview_video_url"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="str">"https://youtu.be/lw8TCjIYeVk?si=UJODa3Dd2t8XsT8t"</span><span class="pun">,</span>
      <span class="str">"descriptions"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span class="str">This is descriptions</span><span class="pun">,</span>
      <span class="str">"module_order"</span><span class="pln"> </span><span class="pun">:</span><span class="pln"> </span><span
                class="str">"1"</span><span class="pun">,</span>
   <span class="pun">}</span><span class="pln">
</span><span class="pun">]</span></pre>
</div>