<?php
require_once __DIR__ . '/config.php';

$isLoggedIn = !empty($_SESSION['user']) && is_array($_SESSION['user']);
$startHref = $isLoggedIn ? 'dashboard/index.php' : 'login/index.php';
$exitHref = $isLoggedIn ? 'logout.php' : 'signup/index.php';
$startLabel = $isLoggedIn ? 'Dashboard' : 'Start';
$exitLabel = $isLoggedIn ? 'Logout' : 'Sign Up';
$infoMessage = isset($_GET['logout']) ? 'You have been logged out successfully.' : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="./vars.css">
  <link rel="stylesheet" href="./style.css">
  <style>
    a,
    button,
    input,
    select,
    textarea,
    h1,
    h2,
    h3,
    h4,
    h5,
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      border: none;
      text-decoration: none;
      background: none;
      -webkit-font-smoothing: antialiased;
    }

    menu,
    ol,
    ul {
      list-style-type: none;
      margin: 0;
      padding: 0;
    }
  </style>
  <title>SuggestionBox</title>
</head>
<body class="landing">
  <div class="landing__container">
    <header class="landing__header">
      <div class="landing__brand">
        <img src="images/logo.png" alt="SuggestionBox Logo" width="150px">
      </div>
      <nav class="landing__nav" aria-label="Main navigation">
        <a class="link" href="<?= htmlspecialchars($startHref, ENT_QUOTES) ?>"><?= htmlspecialchars($startLabel) ?></a>
        <a class="link" href="<?= htmlspecialchars($exitHref, ENT_QUOTES) ?>"><?= htmlspecialchars($exitLabel) ?></a>
      </nav>
    </header>

    <?php if ($infoMessage): ?>
      <div class="alert alert--success landing__alert">
        <p><?= htmlspecialchars($infoMessage) ?></p>
      </div>
    <?php endif; ?>

    <main class="landing__main">
      <section class="hero">
        <h1 class="hero__title">Collect ideas, act with confidence.</h1>
        <p class="hero__subtitle">
          SuggestionBox brings students and administrators together with a transparent feedback loop. Share ideas openly or anonymously and keep every conversation moving forward.
        </p>
        <div class="hero__actions">
          <a class="button button--primary" href="<?= htmlspecialchars($startHref, ENT_QUOTES) ?>"><?= htmlspecialchars($startLabel) ?></a>
          <a class="button button--ghost" href="<?= htmlspecialchars($exitHref, ENT_QUOTES) ?>"><?= htmlspecialchars($exitLabel) ?></a>
        </div>
        <?php if ($isLoggedIn): ?>
          <p class="hero__note">Signed in as <?= htmlspecialchars($_SESSION['user']['username'], ENT_QUOTES) ?>.</p>
        <?php endif; ?>
      </section>

      <section class="features" aria-label="Platform highlights">
        <article class="feature-card">
          <h2 class="feature-card__title">Anonymous or named</h2>
          <p class="feature-card__text">Students choose when to reveal their identity, letting ideas speak for themselves.</p>
        </article>
        <article class="feature-card">
          <h2 class="feature-card__title">Real-time responses</h2>
          <p class="feature-card__text">Administrators reply directly to submissions to close the loop quickly.</p>
        </article>
        <article class="feature-card">
          <h2 class="feature-card__title">Organized feedback</h2>
          <p class="feature-card__text">Track every suggestion and response in a clean, distraction-free dashboard.</p>
        </article>
      </section>
    </main>

    <footer class="landing__footer">
      <p class="landing__footer-text">Built for campus communities that listen.</p>
    </footer>
  </div>
</body>
</html>
