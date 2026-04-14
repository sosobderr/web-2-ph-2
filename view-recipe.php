<?php
require_once 'auth_check.php';
checkLogin();

$host = 'localhost'; $db = 'nutrigood'; $user = 'root'; $pass = 'root';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) { die('DB error: ' . $e->getMessage()); }

$loggedInUserID   = $_SESSION['user_id'];
$loggedInUserType = $_SESSION['user_type'];
$recipeID         = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($recipeID <= 0) { header('Location: user.php'); exit(); }

// Fetch recipe + creator
$stmt = $pdo->prepare("
    SELECT R.*, U.firstName, U.lastName, U.photoFileName AS creatorPhoto, RC.categoryName
    FROM Recipe R
    JOIN User U ON R.userID = U.id
    JOIN RecipeCategory RC ON R.categoryID = RC.id
    WHERE R.id = ?
");
$stmt->execute([$recipeID]);
$recipe = $stmt->fetch();
if (!$recipe) { header('Location: user.php'); exit(); }

$isCreator = ($recipe['userID'] == $loggedInUserID);
$isAdmin   = ($loggedInUserType === 'admin');

// Fetch ingredients
$stmt = $pdo->prepare("SELECT * FROM Ingredients WHERE recipeID = ? ORDER BY id ASC");
$stmt->execute([$recipeID]);
$ingredients = $stmt->fetchAll();

// Fetch instructions
$stmt = $pdo->prepare("SELECT * FROM Instructions WHERE recipeID = ? ORDER BY stepOrder ASC");
$stmt->execute([$recipeID]);
$instructions = $stmt->fetchAll();

// Fetch comments
$stmt = $pdo->prepare("
    SELECT C.comment, C.date, U.firstName, U.lastName, U.photoFileName
    FROM Comment C JOIN User U ON C.userID = U.id
    WHERE C.recipeID = ? ORDER BY C.date DESC
");
$stmt->execute([$recipeID]);
$comments = $stmt->fetchAll();

// Like count
$stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM Likes WHERE recipeID = ?");
$stmt->execute([$recipeID]);
$likeCount = $stmt->fetch()['cnt'];

// User status on this recipe
$hasLiked = $hasFaved = $hasReported = false;
if (!$isAdmin && !$isCreator) {
    $check = $pdo->prepare("SELECT 1 FROM Likes WHERE userID = ? AND recipeID = ?");
    $check->execute([$loggedInUserID, $recipeID]);
    $hasLiked = (bool) $check->fetch();

    $check = $pdo->prepare("SELECT 1 FROM Favourites WHERE userID = ? AND recipeID = ?");
    $check->execute([$loggedInUserID, $recipeID]);
    $hasFaved = (bool) $check->fetch();

    $check = $pdo->prepare("SELECT 1 FROM Report WHERE userID = ? AND recipeID = ?");
    $check->execute([$loggedInUserID, $recipeID]);
    $hasReported = (bool) $check->fetch();
}

// Video embed
$videoEmbed = '';
$videoRaw   = trim($recipe['videoFilePath'] ?? '');
if (!empty($videoRaw)) {
    if (strpos($videoRaw, 'youtu.be/') !== false) {
        $vid = substr($videoRaw, strrpos($videoRaw, '/') + 1);
        $videoEmbed = "https://www.youtube.com/embed/$vid";
    } elseif (strpos($videoRaw, 'watch?v=') !== false) {
        parse_str(parse_url($videoRaw, PHP_URL_QUERY), $q);
        $videoEmbed = "https://www.youtube.com/embed/" . ($q['v'] ?? '');
    } elseif (strpos($videoRaw, 'youtube.com/embed/') !== false) {
        $videoEmbed = $videoRaw;
    } else {
        $videoEmbed = $videoRaw;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NutriGood | <?php echo htmlspecialchars($recipe['name']); ?></title>
  <link rel="stylesheet" href="style.css" />
  <style>
    .action-icons { display:flex; gap:14px; align-items:center; }
    .icon-btn {
        background:none; border:2px solid rgba(34,56,31,.3);
        border-radius:50%; width:48px; height:48px; cursor:pointer;
        display:flex; align-items:center; justify-content:center;
        transition:all .2s ease;
    }
    .icon-btn svg { width:22px; height:22px; fill:none; stroke:#22381F; stroke-width:2; }
    .icon-btn.active svg { fill:#22381F; }
    .icon-btn.heart.active svg { fill:#e53e3e; stroke:#e53e3e; }
    .icon-btn.flag.active svg  { fill:#e53e3e; stroke:#e53e3e; }
    .icon-btn:hover { background:rgba(34,56,31,.08); transform:scale(1.08); }
    .icon-btn:disabled { opacity:.4; cursor:not-allowed; pointer-events:none; }
    .like-count { font-family:var(--serif); font-size:15px; color:#22381F; font-weight:700; }
  </style>
</head>
<body>

  <header class="topbar-admin">
    <div class="containers">
      <div class="topbar-inner">
        <div class="logo-section">
          <img src="images/nutrigood-logo.png" alt="NutriGood Logo" class="logo">
        </div>
        <div class="admin-btns">
          <?php if ($isAdmin): ?>
            <a class="pill" href="admin.php">Admin Page</a>
          <?php else: ?>
            <a class="pill" href="user.php">My Page</a>
          <?php endif; ?>
          <a class="pill" href="signout.php">Sign out</a>
        </div>
      </div>
    </div>
  </header>

  <main class="containers">

    <section class="hero-row">

      <div class="creator-chip">
        <div class="avatar">
          <?php
            $cp = $recipe['creatorPhoto'] ?? 'default.png';
            $cpSrc = file_exists('images/uploads/'.$cp) ? 'images/uploads/'.$cp : 'images/iconss.png';
          ?>
          <img src="<?php echo htmlspecialchars($cpSrc); ?>" alt="Creator photo" />
        </div>
        <div class="creator-meta">
          <div class="name"><?php echo htmlspecialchars($recipe['firstName'].' '.$recipe['lastName']); ?></div>
          <div class="role">Healthy food expert</div>
        </div>
      </div>

      <div class="recipe-title">
        <h1><?php echo htmlspecialchars($recipe['name']); ?></h1>
        <p><?php echo htmlspecialchars($recipe['categoryName']); ?></p>
      </div>

      <?php if (!$isCreator && !$isAdmin): ?>
      <div class="action-icons">

        <!-- LIKE → add_like.php -->
        <form method="POST" action="add_like.php" style="display:inline;">
          <input type="hidden" name="recipe_id" value="<?php echo $recipeID; ?>">
          <button type="submit" class="icon-btn like <?php echo $hasLiked ? 'active' : ''; ?>"
                  <?php echo $hasLiked ? 'disabled title="Already liked"' : 'title="Like"'; ?>>
            <svg viewBox="0 0 24 24">
              <path d="M2 21h4V9H2v12zm20-11c0-1.1-.9-2-2-2h-6.3l.95-4.6.03-.3c0-.4-.17-.8-.44-1.1L13 1 6.6 7.4C6.2 7.8 6 8.3 6 8.8V19c0 1.1.9 2 2 2h8c.8 0 1.5-.5 1.8-1.2l2.7-6.3c.1-.2.2-.5.2-.8v-2.7z"/>
            </svg>
          </button>
        </form>

        <span class="like-count"><?php echo $likeCount; ?></span>

        <!-- FAVOURITE → add_favourite.php -->
        <form method="POST" action="add_favourite.php" style="display:inline;">
          <input type="hidden" name="recipe_id" value="<?php echo $recipeID; ?>">
          <button type="submit" class="icon-btn heart <?php echo $hasFaved ? 'active' : ''; ?>"
                  <?php echo $hasFaved ? 'disabled title="Already in favourites"' : 'title="Add to favourites"'; ?>>
            <svg viewBox="0 0 24 24">
              <path d="M12 21s-7.5-4.7-10-9.5C-0.2 6.5 3.2 2 7.5 4.5 9.3 5.5 12 8 12 8s2.7-2.5 4.5-3.5C20.8 2 24.2 6.5 22 11.5 19.5 16.3 12 21 12 21z" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </form>

        <!-- REPORT → add_report.php -->
        <form method="POST" action="add_report.php" style="display:inline;">
          <input type="hidden" name="recipe_id" value="<?php echo $recipeID; ?>">
          <button type="submit" class="icon-btn flag <?php echo $hasReported ? 'active' : ''; ?>"
                  <?php echo $hasReported ? 'disabled title="Already reported"' : 'title="Report recipe"'; ?>>
            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
              <rect x="4" y="2" width="2" height="20" fill="#22381F" stroke="none"></rect>
              <path d="M6 3h14l-3.5 4L20 11H6z"></path>
            </svg>
          </button>
        </form>

      </div>
      <?php else: ?>
        <div class="action-icons">
          <span class="like-count">👍 <?php echo $likeCount; ?> likes</span>
        </div>
      <?php endif; ?>

    </section>

    <section class="recipes-image" aria-label="Recipe photo">
      <img src="images/<?php echo htmlspecialchars($recipe['photoFileName']); ?>"
           alt="<?php echo htmlspecialchars($recipe['name']); ?> photo" />
    </section>

    <section class="section">
      <h2>Details</h2>
      <div class="panel">
        <div class="kv">
          <div><b>Category:</b> <?php echo htmlspecialchars($recipe['categoryName']); ?></div>
          <div style="margin-top:6px;">
            <b>Description:</b> <?php echo htmlspecialchars($recipe['description']); ?>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <h2>Ingredients</h2>
      <div class="panel">
        <?php if ($ingredients): ?>
          <ul class="ul">
            <?php foreach ($ingredients as $ing): ?>
              <li><?php echo htmlspecialchars($ing['ingredientQuantity'].' '.$ing['ingredientName']); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p>No ingredients listed.</p>
        <?php endif; ?>
      </div>
    </section>

    <section class="section">
      <h2>Instructions</h2>
      <div class="panel">
        <?php if ($instructions): ?>
          <ul class="ul">
            <?php foreach ($instructions as $step): ?>
              <li><?php echo htmlspecialchars($step['step']); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p>No instructions listed.</p>
        <?php endif; ?>
      </div>
    </section>

    <?php if (!empty($videoEmbed)): ?>
    <section class="section" id="videoSection">
      <div class="video-title">Video</div>
      <div class="video-wrap">
        <div class="video">
          <?php if (strpos($videoEmbed, 'youtube.com/embed/') !== false): ?>
            <iframe src="<?php echo htmlspecialchars($videoEmbed); ?>"
                    title="Recipe video"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
          <?php else: ?>
            <video controls style="width:100%; border-radius:12px;">
              <source src="<?php echo htmlspecialchars($videoEmbed); ?>">
            </video>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <section class="section">
      <h2>Comments</h2>
      <div class="panel">

        <?php if (!$isAdmin): ?>
        <!-- COMMENT → add_comment.php -->
        <form class="comment-form" method="POST" action="add_comment.php">
          <input type="hidden" name="recipe_id" value="<?php echo $recipeID; ?>">
          <input type="text" name="comment" placeholder="Would like your feedback!!" aria-label="Add a comment" required />
          <button class="submit-mini" type="submit">Submit</button>
        </form>
        <?php endif; ?>

        <div class="comment-grid" aria-label="Comments list">
          <?php if ($comments): ?>
            <?php foreach ($comments as $c): ?>
              <?php
                $cp2 = $c['photoFileName'] ?? 'default.png';
                $cp2Src = file_exists('images/uploads/'.$cp2) ? 'images/uploads/'.$cp2 : 'images/iconss.png';
              ?>
              <div class="comment-card">
                <div class="small-avatar">
                  <img src="<?php echo htmlspecialchars($cp2Src); ?>" alt="photo">
                </div>
                <div>
                  <div class="who"><?php echo htmlspecialchars($c['firstName'].' '.$c['lastName']); ?></div>
                  <div class="txt"><?php echo htmlspecialchars($c['comment']); ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="text-align:center; padding:10px; color:#888;">No comments yet. Be the first!</p>
          <?php endif; ?>
        </div>

      </div>
    </section>

  </main>

  <section class="brand-banner"><div class="banner-content"></div></section>

  <footer class="footer">
    <div class="containers">
      <div class="footer-content">
        <div class="footer-divider"></div>
        <p class="footer-copyright">&copy; 2026 NutriGood</p>
        <a href="mailto:NutriGood@gmail.com" class="footer-contact">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" class="email-icon">
            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
          </svg>
          NutriGood@gmail.com
        </a>
        <div class="footer-social">
          <span class="footer-brand-text">NutriGood</span>
        </div>
      </div>
    </div>
  </footer>

</body>
</html>
