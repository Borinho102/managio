<div>
    <h4 class="tw-font-semibold tw-mt-0 tw-text-neutral-800">
        <?= _l('company_tenant') . ' ' . _l('registration') ?>
        <span class="type type__post">post</span>
    </h4>
    <p>The command that you will need to execute at your terminal, is the following:</p>
    <pre class="prettyprint prettyprinted" style=""><span class="pln">curl </span><span
                class="pun">-</span><span
                class="pln">H </span><span
                class="str">"saas-authtoken: <?= $authtoken ?>"</span><span
                class="com"> //</span><span class="com"><?= site_url('saas-data/company_singup') ?>
                </span></pre>
    <div class="custom_api_table">
        <table>
            <thead>
            <tr>
                <th style="width: 30%">Field</th>
                <th style="width: 10%">Type</th>
                <th style="width: 60%">Description</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td class="code">domain</td>
                <td>
                    String
                </td>
                <td>
                    <p>Mandatory for company/tenant domain</p>
                </td>
            </tr>
            <tr>
                <td class="code">package_id</td>
                <td>
                    Integer
                </td>
                <td>
                    <p>Mandatory Select Package Id with select option</p>
                </td>
            </tr>
            <tr>
                <td class="code">billing_cycle</td>
                <td>
                    String
                </td>
                <td>
                    <p>
                        Mandatory Select [monthly_price,yearly_price,lifetime_price] with select option
                    </p>
                </td>
            </tr>
            <tr>
                <td class="code">name</td>
                <td>
                    String
                </td>
                <td>
                    <p>Mandatory Company/tenant Name</p>
                </td>
            </tr>
            <tr>
                <td class="code">email</td>
                <td>
                    String
                </td>
                <td>
                    <p>Mandatory Company/tenant email</p>
                </td>
            </tr>
            <tr>
                <td class="code">mobile<span class="label label-optional">optional</span></td>
                <td>
                    String
                </td>
                <td>
                    <p>Optional Company/tenant mobile.</p>


                </td>
            </tr>
            <tr>
                <td class="code">address<span class="label label-optional">optional</span></td>
                <td>
                    String
                </td>
                <td>
                    <p>Optional Company/tenant address</p>


                </td>
            </tr>
            <tr>
                <td class="code">country<span class="label label-optional">optional</span></td>
                <td>
                    Integer
                </td>
                <td>
                    <p>Optional Select county id.</p>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    <p>The response that you are supposed to receive, under a 200 OK status code, will be similar to the
        following:</p>
    <pre class="prettyprint prettyprinted" style=""><span class="pun">{</span><span class="pln"></span>
      <span class="str">"type"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span
                class="kwd">"success"</span><span class="pun">,</span>
      <span class="str">"message"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span
                class="str">"Registration successfully complete check mail"</span><span class="pun">,</span>
<span class="pun">}</span>
<span class="pun">{</span><span class="pln"></span>
      <span class="str">"type"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span
                class="kwd">"error"</span><span class="pun">,</span>
      <span class="str">"message"</span><span class="pln"> </span><span class="pun">:</span><span
                class="pln"> </span><span
                class="str">"domain is already exist"</span><span class="pun">,</span>
<span class="pun">}</span></pre>
</div>