<?php
require_once __DIR__ . '/../PhpFiles/General/security.php';

http_response_code(503);
header('Retry-After: 3600');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Maintenance Mode</title>
  <link rel="icon" href="<?= htmlspecialchars(appUrl('Images/favicon_sanjose.png?v=20260211'), ENT_QUOTES, 'UTF-8') ?>">
  <style>
    :root {
      --maintenance-bg: #050505;
      --maintenance-panel: #111111;
      --maintenance-text: #f8fafc;
      --maintenance-muted: #cbd5e1;
      --maintenance-accent: #f28c18;
      --maintenance-accent-soft: rgba(242, 140, 24, 0.28);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 24px;
      background:
        radial-gradient(circle at top, rgba(242, 140, 24, 0.12), transparent 34%),
        linear-gradient(180deg, #0a0a0a 0%, var(--maintenance-bg) 100%);
      color: var(--maintenance-text);
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }

    .maintenance-shell {
      width: min(100%, 760px);
      padding: 48px 32px;
      text-align: center;
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 28px;
      background: rgba(17, 17, 17, 0.88);
      box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.03) inset,
        0 28px 60px rgba(0, 0, 0, 0.45);
      backdrop-filter: blur(8px);
    }

    .maintenance-logo-ring {
      width: clamp(130px, 22vw, 180px);
      height: clamp(130px, 22vw, 180px);
      margin: 0 auto 28px;
      padding: 12px;
      border: 4px solid var(--maintenance-accent);
      border-radius: 50%;
      display: grid;
      place-items: center;
      background: radial-gradient(circle, rgba(242, 140, 24, 0.16), rgba(242, 140, 24, 0.02));
      box-shadow:
        0 0 0 12px var(--maintenance-accent-soft),
        0 0 36px rgba(242, 140, 24, 0.28);
    }

    .maintenance-logo {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
      background: #ffffff;
      padding: 10px;
    }

    .maintenance-title {
      margin: 0 0 12px;
      color: var(--maintenance-accent);
      font-size: clamp(1.9rem, 4vw, 3rem);
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .maintenance-kicker {
      margin: 0 0 14px;
      color: rgba(242, 140, 24, 0.84);
      font-size: 0.92rem;
      font-weight: 700;
      letter-spacing: 0.22em;
      text-transform: uppercase;
    }

    .maintenance-copy {
      margin: 0 auto;
      max-width: 560px;
      color: var(--maintenance-muted);
      font-size: clamp(1rem, 2vw, 1.18rem);
      line-height: 1.7;
    }

    .maintenance-subcopy {
      margin: 16px auto 0;
      max-width: 500px;
      color: rgba(248, 250, 252, 0.68);
      font-size: 0.98rem;
      line-height: 1.6;
    }

    @media (max-width: 640px) {
      .maintenance-shell {
        padding: 40px 22px;
        border-radius: 22px;
      }
    }
  </style>
</head>
<body>
  <main class="maintenance-shell" aria-labelledby="maintenance-title">
    <div class="maintenance-logo-ring">
      <img
        class="maintenance-logo"
        src="<?= htmlspecialchars(appUrl('Images/San_Jose_LOGO.jpg'), ENT_QUOTES, 'UTF-8') ?>"
        alt="Barangay San Jose Logo">
    </div>
    <p class="maintenance-kicker">Please Stand By</p>
    <h1 class="maintenance-title" id="maintenance-title">Maintenance Mode</h1>
    <p class="maintenance-copy">Our developers are currently upgrading the system to deliver a smoother, faster, and better experience for everyone.</p>
    <p class="maintenance-subcopy">The public pages will be available again once the improvements are complete.</p>
  </main>
</body>
</html>
