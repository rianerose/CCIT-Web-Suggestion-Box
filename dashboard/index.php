<?php
require_once __DIR__ . '/../config.php';

ensure_logged_in();

/** @var array{id: int, username: string, full_name: string, role: string} $currentUser */
$currentUser = $_SESSION['user'];
$isAdmin = $currentUser['role'] === 'admin';

$flashSuccess = null;
$flashError = null;

if (!empty($_SESSION['flash_success'])) {
    $flashSuccess = (string) $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

if (!empty($_SESSION['flash_error'])) {
    $flashError = (string) $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$suggestionFormErrors = [];
$suggestionTitle = '';
$suggestionContent = '';
$suggestionRevealIdentity = true;

$replyErrors = [];
$replyDrafts = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isAdmin) {
        $suggestionId = isset($_POST['suggestion_id']) ? (int) $_POST['suggestion_id'] : 0;
        $replyMessage = trim((string) ($_POST['reply_message'] ?? ''));

        if ($suggestionId <= 0) {
            $replyErrors[0] = 'Invalid suggestion reference.';
        } else {
            $result = add_suggestion_reply($suggestionId, $currentUser['id'], $replyMessage);

            if ($result['success']) {
                $_SESSION['flash_success'] = 'Reply posted successfully.';
                header('Location: index.php');
                exit;
            }

            if (!empty($result['error'])) {
                $replyErrors[$suggestionId] = $result['error'];
                $replyDrafts[$suggestionId] = $replyMessage;
            } else {
                $replyErrors[$suggestionId] = 'Unable to post reply. Please try again.';
            }
        }
    } else {
        $suggestionTitle = trim((string) ($_POST['title'] ?? ''));
        $suggestionContent = trim((string) ($_POST['content'] ?? ''));
        $suggestionRevealIdentity = isset($_POST['display_identity']);

        $result = create_suggestion($currentUser['id'], $suggestionTitle, $suggestionContent, $suggestionRevealIdentity);

        if ($result['success']) {
            $_SESSION['flash_success'] = 'Suggestion submitted. Thank you for sharing your ideas!';
            header('Location: index.php');
            exit;
        }

        if (!empty($result['error'])) {
            $suggestionFormErrors[] = $result['error'];
        } else {
            $suggestionFormErrors[] = 'Unable to submit suggestion. Please try again.';
        }
    }
}

if ($isAdmin) {
    $suggestions = get_all_suggestions_for_admin();
} else {
    $suggestions = get_student_suggestions($currentUser['id']);
}

$username = htmlspecialchars($currentUser['full_name'], ENT_QUOTES);
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
    label,
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
  <title>Dashboard</title>
</head>
<body>
  <div class="dashboard">
    <header class="dashboard__header">
      <div class="dashboard__brand">
        <img src="../images/logo.png" alt="SuggestionBox Logo" width="120px">
        <span class="dashboard__role-tag"><?= htmlspecialchars(strtoupper($currentUser['role']), ENT_QUOTES) ?></span>
      </div>
      <div class="dashboard__user">
        <div>
          <span class="dashboard__welcome">Welcome, <?= $username ?>.</span>
          <span class="dashboard__meta">Signed in as <?= htmlspecialchars($currentUser['username'], ENT_QUOTES) ?></span>
        </div>
        <a class="dashboard__logout" href="../logout.php">Logout</a>
      </div>
    </header>

    <?php if ($flashSuccess): ?>
      <div class="alert alert--success">
        <p><?= htmlspecialchars($flashSuccess) ?></p>
      </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
      <div class="alert alert--error">
        <p><?= htmlspecialchars($flashError) ?></p>
      </div>
    <?php endif; ?>

    <main class="dashboard__main">
      <?php if ($isAdmin): ?>
        <section class="panel panel--admin">
          <header class="panel__header">
            <h1 class="panel__title">Suggestion Inbox</h1>
            <p class="panel__subtitle">Review student feedback and respond directly.</p>
          </header>

          <?php if ($replyErrors): ?>
            <div class="alert alert--error">
              <?php foreach ($replyErrors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if (empty($suggestions)): ?>
            <p class="panel__empty">No suggestions yet. Check back soon.</p>
          <?php else: ?>
            <div class="suggestion-grid">
              <?php foreach ($suggestions as $suggestion): ?>
                <article class="suggestion-card">
                  <header class="suggestion-card__header">
                    <h2 class="suggestion-card__title"><?= htmlspecialchars($suggestion['title'], ENT_QUOTES) ?></h2>
                    <span class="suggestion-card__meta">
                      <?php if ($suggestion['is_anonymous']): ?>
                        Submitted anonymously
                      <?php else: ?>
                        <?= htmlspecialchars($suggestion['student_name'], ENT_QUOTES) ?>
                        <span class="suggestion-card__username">(@<?= htmlspecialchars($suggestion['student_username'], ENT_QUOTES) ?>)</span>
                      <?php endif; ?>
                      &nbsp;&middot;&nbsp;<?= htmlspecialchars(date('M j, Y g:i A', strtotime($suggestion['created_at'])), ENT_QUOTES) ?>
                    </span>
                  </header>
                  <p class="suggestion-card__body"><?= nl2br(htmlspecialchars($suggestion['content'], ENT_QUOTES)) ?></p>

                  <?php if (!empty($suggestion['replies'])): ?>
                    <div class="reply-thread">
                      <?php foreach ($suggestion['replies'] as $reply): ?>
                        <div class="reply">
                          <div class="reply__meta">
                            <span class="reply__author">Reply from <?= htmlspecialchars($reply['admin_name'], ENT_QUOTES) ?></span>
                            <span class="reply__date"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($reply['created_at'])), ENT_QUOTES) ?></span>
                          </div>
                          <p class="reply__message"><?= nl2br(htmlspecialchars($reply['message'], ENT_QUOTES)) ?></p>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <form class="reply-form" method="post" novalidate>
                    <input type="hidden" name="suggestion_id" value="<?= (int) $suggestion['id'] ?>">
                    <label class="form-field">
                      <span class="form-field__label">Add a reply</span>
                      <textarea
                        class="form-field__input form-field__input--textarea"
                        name="reply_message"
                        rows="3"
                        required><?php echo htmlspecialchars($replyDrafts[$suggestion['id']] ?? '', ENT_QUOTES); ?></textarea>
                    </label>
                    <button class="button button--primary" type="submit">Send reply</button>
                  </form>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php else: ?>
        <section class="panel panel--student">
          <div class="panel__column">
            <header class="panel__header">
              <h1 class="panel__title">Share a suggestion</h1>
              <p class="panel__subtitle">Help us improve by sharing ideas, concerns, or appreciation.</p>
            </header>

            <?php if ($suggestionFormErrors): ?>
              <div class="alert alert--error">
                <?php foreach ($suggestionFormErrors as $error): ?>
                  <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <form class="suggestion-form" method="post" novalidate>
              <label class="form-field">
                <span class="form-field__label">Title</span>
                <input
                  class="form-field__input"
                  type="text"
                  name="title"
                  value="<?= htmlspecialchars($suggestionTitle, ENT_QUOTES) ?>"
                  maxlength="190"
                  required
                >
              </label>

              <label class="form-field">
                <span class="form-field__label">Suggestion details</span>
                <textarea
                  class="form-field__input form-field__input--textarea"
                  name="content"
                  rows="6"
                  required><?php echo htmlspecialchars($suggestionContent, ENT_QUOTES); ?></textarea>
              </label>

              <label class="checkbox">
                <input
                  class="checkbox__input"
                  type="checkbox"
                  name="display_identity"
                  <?= $suggestionRevealIdentity ? 'checked' : '' ?>
                >
                <span class="checkbox__label">Display my name to administrators</span>
              </label>

              <button class="button button--primary" type="submit">Submit suggestion</button>
            </form>
          </div>

          <div class="panel__column">
            <header class="panel__header panel__header--compact">
              <h2 class="panel__title">Your submissions</h2>
              <p class="panel__subtitle">Track responses from administrators in real time.</p>
            </header>

            <?php if (empty($suggestions)): ?>
              <p class="panel__empty">You have not shared any suggestions yet.</p>
            <?php else: ?>
              <div class="suggestion-list">
                <?php foreach ($suggestions as $suggestion): ?>
                  <article class="suggestion-card">
                    <header class="suggestion-card__header">
                      <h3 class="suggestion-card__title"><?= htmlspecialchars($suggestion['title'], ENT_QUOTES) ?></h3>
                      <span class="suggestion-card__meta">
                        <?= $suggestion['is_anonymous'] ? 'Sent anonymously' : 'Name shared with admins' ?>
                        &nbsp;&middot;&nbsp;<?= htmlspecialchars(date('M j, Y g:i A', strtotime($suggestion['created_at'])), ENT_QUOTES) ?>
                      </span>
                    </header>
                    <p class="suggestion-card__body"><?= nl2br(htmlspecialchars($suggestion['content'], ENT_QUOTES)) ?></p>

                    <?php if (!empty($suggestion['replies'])): ?>
                      <div class="reply-thread">
                        <?php foreach ($suggestion['replies'] as $reply): ?>
                          <div class="reply reply--student">
                            <div class="reply__meta">
                              <span class="reply__author">Response from <?= htmlspecialchars($reply['admin_name'], ENT_QUOTES) ?></span>
                              <span class="reply__date"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($reply['created_at'])), ENT_QUOTES) ?></span>
                            </div>
                            <p class="reply__message"><?= nl2br(htmlspecialchars($reply['message'], ENT_QUOTES)) ?></p>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <p class="suggestion-card__status">Awaiting administrator reply.</p>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
