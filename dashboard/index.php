<?php
require_once __DIR__ . '/../config.php';

ensure_logged_in();

if (!function_exists('format_suggestion_preview')) {
    /**
     * @param string $content
     */
    function format_suggestion_preview(string $content): string
    {
        $normalized = preg_replace('/\s+/', ' ', $content);

        if ($normalized === null) {
            $normalized = $content;
        }

        $singleLine = trim($normalized);

        if ($singleLine === '') {
            return 'Suggestion';
        }

        $limit = 80;

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($singleLine) <= $limit) {
                return $singleLine;
            }

            return rtrim(mb_substr($singleLine, 0, $limit - 3)) . '...';
        }

        if (strlen($singleLine) <= $limit) {
            return $singleLine;
        }

        return rtrim(substr($singleLine, 0, $limit - 3)) . '...';
    }
}

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
$suggestionContent = '';
$suggestionRevealIdentity = true;

$replyErrors = [];
$replyDrafts = [];
$editSuggestionErrors = [];
$editSuggestionDrafts = [];
$editingSuggestionId = null;

if (!$isAdmin && isset($_GET['edit'])) {
    $editingSuggestionId = (int) $_GET['edit'];
    if ($editingSuggestionId <= 0) {
        $editingSuggestionId = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($isAdmin) {
        if ($action === '') {
            $action = 'add_reply';
        }

        if ($action === 'delete_suggestion') {
            $suggestionId = isset($_POST['suggestion_id']) ? (int) $_POST['suggestion_id'] : 0;

            if ($suggestionId <= 0) {
                $_SESSION['flash_error'] = 'Invalid suggestion reference.';
            } else {
                $result = delete_suggestion_for_admin($suggestionId);

                if ($result['success']) {
                    $_SESSION['flash_success'] = 'Suggestion deleted successfully.';
                } else {
                    $_SESSION['flash_error'] = $result['error'] ?? 'Unable to delete suggestion. Please try again.';
                }
            }

            header('Location: index.php');
            exit;
        }

        if ($action === 'delete_reply') {
            $replyId = isset($_POST['reply_id']) ? (int) $_POST['reply_id'] : 0;

            if ($replyId <= 0) {
                $_SESSION['flash_error'] = 'Invalid reply reference.';
            } else {
                $result = delete_suggestion_reply($replyId, $currentUser['id'], true);

                if ($result['success']) {
                    $_SESSION['flash_success'] = 'Reply deleted successfully.';
                } else {
                    $_SESSION['flash_error'] = $result['error'] ?? 'Unable to delete reply. Please try again.';
                }
            }

            header('Location: index.php');
            exit;
        }

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
        if ($action === '') {
            $action = 'create_suggestion';
        }

        if ($action === 'delete_suggestion') {
            $suggestionId = isset($_POST['suggestion_id']) ? (int) $_POST['suggestion_id'] : 0;

            if ($suggestionId <= 0) {
                $_SESSION['flash_error'] = 'Invalid suggestion reference.';
            } else {
                $result = delete_suggestion_for_student($suggestionId, $currentUser['id']);

                if ($result['success']) {
                    $_SESSION['flash_success'] = 'Suggestion deleted successfully.';
                } else {
                    $_SESSION['flash_error'] = $result['error'] ?? 'Unable to delete suggestion. Please try again.';
                }
            }

            header('Location: index.php');
            exit;
        }

        $suggestionContent = trim((string) ($_POST['content'] ?? ''));
        $suggestionRevealIdentity = isset($_POST['display_identity']);

        if ($action === 'update_suggestion') {
            $suggestionId = isset($_POST['suggestion_id']) ? (int) $_POST['suggestion_id'] : 0;
            $editingSuggestionId = $suggestionId > 0 ? $suggestionId : null;

            if ($suggestionId <= 0) {
                $_SESSION['flash_error'] = 'Invalid suggestion reference.';
                header('Location: index.php');
                exit;
            }

            $result = update_suggestion($suggestionId, $currentUser['id'], $suggestionContent, $suggestionRevealIdentity);

            if ($result['success']) {
                $_SESSION['flash_success'] = 'Suggestion updated successfully.';
                header('Location: index.php');
                exit;
            }

            $editSuggestionErrors[$suggestionId] = [
                !empty($result['error']) ? $result['error'] : 'Unable to update suggestion. Please try again.',
            ];

            if ($suggestionId > 0) {
                $editSuggestionDrafts[$suggestionId] = [
                    'content' => $suggestionContent,
                    'display_identity' => $suggestionRevealIdentity,
                ];
            }
        } else {
            $result = create_suggestion($currentUser['id'], $suggestionContent, $suggestionRevealIdentity);

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
}

if ($isAdmin) {
    $suggestions = get_all_suggestions_for_admin();
} else {
    $suggestions = get_student_suggestions($currentUser['id']);
}

if (!$isAdmin && $editingSuggestionId !== null) {
    $matchedSuggestion = null;

    foreach ($suggestions as $suggestion) {
        if ((int) $suggestion['id'] === $editingSuggestionId) {
            $matchedSuggestion = $suggestion;
            break;
        }
    }

    if ($matchedSuggestion === null) {
        $_SESSION['flash_error'] = 'Suggestion not found or access denied.';
        header('Location: index.php');
        exit;
    }

    if (!isset($editSuggestionDrafts[$editingSuggestionId])) {
        $editSuggestionDrafts[$editingSuggestionId] = [
            'content' => $matchedSuggestion['content'],
            'display_identity' => !$matchedSuggestion['is_anonymous'],
        ];
    }
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
                    <?php
                        $suggestionId = (int) $suggestion['id'];
                        $suggestionPreview = format_suggestion_preview($suggestion['content']);
                    ?>
                <article class="suggestion-card">
                  <header class="suggestion-card__header">
                      <div class="suggestion-card__title-row">
                          <h2 class="suggestion-card__title"><?= htmlspecialchars($suggestionPreview, ENT_QUOTES) ?></h2>
                        <form class="inline-form" method="post" onsubmit="return confirm('Delete this suggestion and all associated replies?');">
                          <input type="hidden" name="action" value="delete_suggestion">
                          <input type="hidden" name="suggestion_id" value="<?= $suggestionId ?>">
                          <button class="button button--danger button--sm" type="submit">Delete suggestion</button>
                        </form>
                      </div>
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
                          <?php $replyId = (int) $reply['id']; ?>
                        <div class="reply">
                            <div class="reply__meta">
                            <span class="reply__author">Reply from <?= htmlspecialchars($reply['admin_name'], ENT_QUOTES) ?></span>
                            <span class="reply__date"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($reply['created_at'])), ENT_QUOTES) ?></span>
                              <form class="inline-form reply__delete" method="post" onsubmit="return confirm('Delete this reply?');">
                                <input type="hidden" name="action" value="delete_reply">
                                <input type="hidden" name="reply_id" value="<?= $replyId ?>">
                                <button class="button button--danger button--sm" type="submit">Delete reply</button>
                              </form>
                          </div>
                          <p class="reply__message"><?= nl2br(htmlspecialchars($reply['message'], ENT_QUOTES)) ?></p>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <form class="reply-form" method="post" novalidate>
                      <input type="hidden" name="action" value="add_reply">
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
                <input type="hidden" name="action" value="create_suggestion">
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
                      <?php
                        $studentSuggestionId = (int) $suggestion['id'];
                        $isEditing = $editingSuggestionId === $studentSuggestionId;
                        $editDraft = $editSuggestionDrafts[$studentSuggestionId] ?? null;
                        if ($isEditing && $editDraft === null) {
                            $editDraft = [
                                'content' => $suggestion['content'],
                                'display_identity' => !$suggestion['is_anonymous'],
                            ];
                        }
                        $editErrors = $editSuggestionErrors[$studentSuggestionId] ?? [];
                        $suggestionPreview = format_suggestion_preview($suggestion['content']);
                      ?>
                  <article class="suggestion-card">
                    <header class="suggestion-card__header">
                        <h3 class="suggestion-card__title"><?= htmlspecialchars($suggestionPreview, ENT_QUOTES) ?></h3>
                      <span class="suggestion-card__meta">
                        <?= $suggestion['is_anonymous'] ? 'Sent anonymously' : 'Name shared with admins' ?>
                        &nbsp;&middot;&nbsp;<?= htmlspecialchars(date('M j, Y g:i A', strtotime($suggestion['created_at'])), ENT_QUOTES) ?>
                      </span>
                    </header>
                      <div class="suggestion-card__actions suggestion-card__actions--student">
                        <a class="button button--ghost button--sm" href="<?= htmlspecialchars($isEditing ? 'index.php' : 'index.php?edit=' . $studentSuggestionId, ENT_QUOTES) ?>">
                          <?= $isEditing ? 'Close edit' : 'Edit' ?>
                        </a>
                        <form class="inline-form" method="post" onsubmit="return confirm('Delete this suggestion? This action cannot be undone.');">
                          <input type="hidden" name="action" value="delete_suggestion">
                          <input type="hidden" name="suggestion_id" value="<?= $studentSuggestionId ?>">
                          <button class="button button--danger button--sm" type="submit">Delete</button>
                        </form>
                      </div>
                      <?php if ($isEditing): ?>
                        <?php if (!empty($editErrors)): ?>
                          <div class="alert alert--error">
                            <?php foreach ($editErrors as $error): ?>
                              <p><?= htmlspecialchars($error) ?></p>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                        <form class="suggestion-form suggestion-form--inline" method="post" novalidate>
                          <input type="hidden" name="action" value="update_suggestion">
                          <input type="hidden" name="suggestion_id" value="<?= $studentSuggestionId ?>">
                          <label class="form-field">
                            <span class="form-field__label">Suggestion details</span>
                            <textarea
                              class="form-field__input form-field__input--textarea"
                              name="content"
                              rows="6"
                              required><?= htmlspecialchars($editDraft['content'] ?? $suggestion['content'], ENT_QUOTES) ?></textarea>
                          </label>

                          <label class="checkbox">
                            <input
                              class="checkbox__input"
                              type="checkbox"
                              name="display_identity"
                              <?= !empty($editDraft['display_identity']) ? 'checked' : '' ?>
                            >
                            <span class="checkbox__label">Display my name to administrators</span>
                          </label>

                          <button class="button button--primary" type="submit">Update suggestion</button>
                        </form>
                      <?php endif; ?>
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
