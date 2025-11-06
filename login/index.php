<?php
require_once __DIR__ . '/../config.php';

if (!empty($_SESSION['user'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '') {
        $errors[] = 'Username is required.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (!$errors) {
        $authenticatedUser = authenticate_user($username, $password);

        if ($authenticatedUser !== null) {
            $_SESSION['user'] = $authenticatedUser;
            header('Location: ../dashboard/index.php');
            exit;
        }

        $errors[] = 'Invalid username or password.';
    }
}

$usernameValue = htmlspecialchars($username, ENT_QUOTES);
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
  <title>Login</title>
</head>
<body class="auth">
  <div class="auth__container">
    <section class="auth__panel">
      <header class="auth__header">
        <h1 class="auth__title">Welcome back</h1>
        <p class="auth__subtitle">Sign in to review responses or share feedback.</p>
      </header>

      <?php if ($errors): ?>
        <div class="alert alert--error">
          <?php foreach ($errors as $error): ?>
            <p><?= htmlspecialchars($error) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form class="auth-form" method="post" novalidate>
        <label class="form-field">
          <span class="form-field__label">Username</span>
          <input
            class="form-field__input"
            type="text"
            name="username"
            value="<?= $usernameValue ?>"
            autocomplete="username"
            required
          >
        </label>

        <label class="form-field">
          <span class="form-field__label">Password</span>
          <input
            class="form-field__input"
            type="password"
            name="password"
            autocomplete="current-password"
            required
          >
        </label>

        <button class="button button--primary" type="submit">Log in</button>
      </form>

      <p class="auth__meta">Need an account? <a class="link" href="../signup/index.php">Create one</a>.</p>
    </section>
  </div>
</body>
</html>
