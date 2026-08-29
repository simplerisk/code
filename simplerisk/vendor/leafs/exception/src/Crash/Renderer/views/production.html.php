<?php
// parameterized status page — included with no vars it renders the
// classic production 500, which keeps Handler::productionPage() working
$eyebrow = $eyebrow ?? 'error 500';
$title = $title ?? 'Something went wrong';
$message = $message ?? 'We hit an unexpected problem while handling your request. It has been recorded and we are on it.';
$actionUrl = $actionUrl ?? '/';
$actionLabel = $actionLabel ?? 'Back to safety';
$e = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title) ?></title>
<style>
    :root { --bg: #FAF7F2; --text: #262019; --muted: #6B5B4E; --line: #E8E0D8; --brand: #D4652E; }
    @media (prefers-color-scheme: dark) {
        :root { --bg: #0A0807; --text: #EBE1D7; --muted: #A89080; --line: #2D2720; --brand: #E8873A; }
    }
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; display: grid; place-items: center; background: var(--bg); color: var(--text);
        font: 15px/1.65 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
    }
    .card { text-align: center; padding: 40px 28px; max-width: 420px; }
    .eyebrow {
        font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 11px; letter-spacing: .14em;
        text-transform: uppercase; color: var(--muted);
    }
    .eyebrow::before { content: "// "; color: var(--brand); }
    h1 {
        font-family: "Bricolage Grotesque", Inter, ui-sans-serif, sans-serif;
        font-size: 30px; letter-spacing: -.02em; margin: 14px 0 10px;
    }
    p { color: var(--muted); margin: 0 0 26px; }
    a {
        display: inline-block; background: var(--brand); color: #FFF6EE; text-decoration: none;
        padding: 9px 18px; border-radius: 8px; font-weight: 600; font-size: 14px;
    }
</style>
</head>
<?php
$logo = @file_get_contents(__DIR__ . '/../assets/logo-circle.png');
$logo = $logo ? 'data:image/png;base64,' . base64_encode($logo) : null;
?>
<body>
    <div class="card">
        <?php if ($logo): ?><img src="<?= $logo ?>" alt="" width="42" height="42" style="border-radius: 50%; margin-bottom: 18px;"><?php endif; ?>
        <div class="eyebrow"><?= $e($eyebrow) ?></div>
        <h1><?= $e($title) ?></h1>
        <p><?= $e($message) ?></p>
        <a href="<?= $e($actionUrl) ?>"><?= $e($actionLabel) ?></a>
    </div>
</body>
</html>
