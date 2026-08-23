<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo html_escape(_l('payin_opening_wallet')); ?></title>
</head>
<body>
    <p><?php echo html_escape(_l('payin_opening_wallet_wait')); ?></p>
    <form id="payin-sso-form" method="POST" action="<?php echo html_escape($consumeUrl); ?>">
        <input type="hidden" name="token" value="<?php echo html_escape($token); ?>">
    </form>
    <script>
        document.getElementById('payin-sso-form').submit();
    </script>
</body>
</html>
