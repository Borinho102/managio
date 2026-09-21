<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php if (function_exists('pwa_head')) { pwa_head(); } ?>
    <style>
        body { font-family: Inter, system-ui, sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 24px; }
        .card { background: #fff; border-radius: 16px; padding: 40px 32px; max-width: 460px; box-shadow: 0 12px 40px rgba(15, 23, 42, .1); }
        h1 { margin: 0 0 12px; font-size: 1.5rem; }
        p { margin: 0 0 20px; color: #475569; word-break: break-word; }
        a { color: #2563eb; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <h1><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p><?php echo htmlspecialchars((string) $detail, ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars($continue, ENT_QUOTES, 'UTF-8'); ?>">Continue</a>
    </div>
</body>
</html>
