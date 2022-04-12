{*-------------------------------------------------------+
| SYSTOPIA Event Invitation                              |
| Copyright (C) 2020 SYSTOPIA                            |
| Author: J. Schuppe (schuppe@systopia.de)               |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*}

{crmScope extensionKey='de.systopia.resourceevent'}
    <div class="crm-section">
        <div class="label">{$form.event.label}</div>
        <div class="content">{$form.event.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        {capture assign=label_help}{ts}Template Help{/ts}{/capture}
        <div class="label">{$form.template.label}{help id="id-template-tokens" title=$label_help}</div>
        <div class="content">{$form.template.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.pdfs_instead_of_emails.label}</div>
        <div class="content">{$form.pdfs_instead_of_emails.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.email_sender.label}</div>
        <div class="content">{$form.email_sender.html}</div>
        <div class="clear"></div>
    </div>

    {* FOOTER *}
    <br>
    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
{/crmScope}

<script>
{literal}
(function ($) {
  $(document).ready(function () {
    // Hide "email sender" field when generating PDF files.
    let $switch = $('input[name=pdfs_instead_of_emails]');
    $switch
        .change(function () {
          $("#email_sender").closest('.crm-section').toggle(!$switch.prop('checked'));
        })
        // Trigger the event for initialisation.
        .change();
  });
})(CRM.$)
{/literal}
</script>
