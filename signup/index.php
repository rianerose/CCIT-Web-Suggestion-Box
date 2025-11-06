<?php
require_once __DIR__ . '/../config.php';

if (!empty($_SESSION['user'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

$errors = [];
$username = '';
$fullName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($username === '') {
        $errors[] = 'Username is required.';
    }

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (strlen($password) > 0 && strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $result = register_user($username, $password, $fullName);

        if ($result['success']) {
            if (isset($result['user']) && is_array($result['user'])) {
                $_SESSION['user'] = $result['user'];
            } else {
                $authenticatedUser = authenticate_user($username, $password);
                if ($authenticatedUser !== null) {
                    $_SESSION['user'] = $authenticatedUser;
                }
            }

            $_SESSION['flash_success'] = 'Account created successfully!';
            header('Location: ../dashboard/index.php');
            exit;
        }

        if (!empty($result['error'])) {
            $errors[] = $result['error'];
        }
    }
}

  $usernameValue = htmlspecialchars($username, ENT_QUOTES);
  $fullNameValue = htmlspecialchars($fullName, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../login/vars.css">
  <link rel="stylesheet" href="../login/style.css">
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
  <title>Sign Up</title>
</head>
<body class="auth">
  <div class="auth__container">
    <section class="auth__panel">
      <header class="auth__header">
        <h1 class="auth__title">Create your account</h1>
        <p class="auth__subtitle">Join as a student to start sharing suggestions.</p>
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
          <span class="form-field__label">Full name</span>
          <input
            class="form-field__input"
            type="text"
            name="full_name"
            value="<?= $fullNameValue ?>"
            autocomplete="name"
            required
          >
        </label>

        <label class="form-field">
          <span class="form-field__label">Password</span>
          <input
            class="form-field__input"
            type="password"
            name="password"
            autocomplete="new-password"
            required
          >
        </label>

        <label class="form-field">
          <span class="form-field__label">Confirm password</span>
          <input
            class="form-field__input"
            type="password"
            name="confirm_password"
            autocomplete="new-password"
            required
          >
        </label>

        <button class="button button--primary" type="submit">Create account</button>
      </form>

      <p class="auth__meta">Already registered? <a class="link" href="../login/index.php">Log in</a>.</p>
    </section>
  </div>
</body>
</html>
