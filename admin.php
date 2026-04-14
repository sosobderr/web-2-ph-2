<?php
require_once 'auth_check.php';
checkLogin('admin');

// ── Database connection ──────────────────────────────────────────────────────
$host    = 'localhost';
$db      = 'nutrigood';
$user    = 'root';
$pass    = 'root';
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// ── Get logged-in admin info ─────────────────────────────────────────────────
$adminID = $_SESSION['user_id'];
$stmt    = $pdo->prepare("SELECT firstName, lastName, emailAddress FROM User WHERE id = ?");
$stmt->execute([$adminID]);
$adminInfo = $stmt->fetch();

// ── Handle POST action (Block or Dismiss) ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reportID  = isset($_POST['report_id'])  ? (int) $_POST['report_id']  : 0;
    $reportedUserID = isset($_POST['reported_user_id']) ? (int) $_POST['reported_user_id'] : 0;
    $recipeID  = isset($_POST['recipe_id'])  ? (int) $_POST['recipe_id']  : 0;
    $action    = $_POST['report_action']     ?? '';

    if ($action === 'block' && $reportedUserID > 0) {

        // 1. Get user info before deleting (for BlockedUser table)
        $stmt = $pdo->prepare("SELECT firstName, lastName, emailAddress FROM User WHERE id = ?");
        $stmt->execute([$reportedUserID]);
        $blockedUser = $stmt->fetch();

        if ($blockedUser) {
            // 2. Get all recipe IDs for this user (for cascading delete)
            $stmt = $pdo->prepare("SELECT id, photoFileName, videoFilePath FROM Recipe WHERE userID = ?");
            $stmt->execute([$reportedUserID]);
            $userRecipes = $stmt->fetchAll();

            foreach ($userRecipes as $r) {
                $rid = $r['id'];

                // Delete ingredients
                $pdo->prepare("DELETE FROM Ingredients WHERE recipeID = ?")->execute([$rid]);
                // Delete instructions
                $pdo->prepare("DELETE FROM Instructions WHERE recipeID = ?")->execute([$rid]);
                // Delete comments
                $pdo->prepare("DELETE FROM Comment WHERE recipeID = ?")->execute([$rid]);
                // Delete likes
                $pdo->prepare("DELETE FROM Likes WHERE recipeID = ?")->execute([$rid]);
                // Delete favourites
                $pdo->prepare("DELETE FROM Favourites WHERE recipeID = ?")->execute([$rid]);
                // Delete reports on this recipe
                $pdo->prepare("DELETE FROM Report WHERE recipeID = ?")->execute([$rid]);

                // Delete photo file
                $photo = 'images/' . $r['photoFileName'];
                if (!empty($r['photoFileName']) && file_exists($photo)) {
                    unlink($photo);
                }

                // Delete video file (local only)
                $video = $r['videoFilePath'];
                if (!empty($video) && file_exists($video) && strpos($video, 'http') !== 0) {
                    unlink($video);
                }
            }

            // 3. Delete all recipes
            $pdo->prepare("DELETE FROM Recipe WHERE userID = ?")->execute([$reportedUserID]);

            // 4. Delete any remaining likes/favourites/reports/comments by this user
            $pdo->prepare("DELETE FROM Likes WHERE userID = ?")->execute([$reportedUserID]);
            $pdo->prepare("DELETE FROM Favourites WHERE userID = ?")->execute([$reportedUserID]);
            $pdo->prepare("DELETE FROM Report WHERE userID = ?")->execute([$reportedUserID]);
            $pdo->prepare("DELETE FROM Comment WHERE userID = ?")->execute([$reportedUserID]);

            // 5. Insert into BlockedUser table
            $ins = $pdo->prepare("INSERT INTO BlockedUser (firstName, lastName, emailAddress) VALUES (?, ?, ?)");
            $ins->execute([$blockedUser['firstName'], $blockedUser['lastName'], $blockedUser['emailAddress']]);

            // 6. Delete user from User table
            $pdo->prepare("DELETE FROM User WHERE id = ?")->execute([$reportedUserID]);
        }

    } elseif ($action === 'dismiss' && $reportID > 0) {

        // Just delete the report entry
        $pdo->prepare("DELETE FROM Report WHERE id = ?")->execute([$reportID]);
    }

    header('Location: admin.php');
    exit();
}

// ── Fetch reported recipes (with distinct grouping) ───────────────────────────
$reportedRecipes = $pdo->query("
    SELECT
        Report.id         AS reportID,
        Report.recipeID,
        Report.userID     AS reporterID,
        Recipe.name       AS recipeName,
        Recipe.userID     AS creatorID,
        User.firstName    AS creatorFirstName,
        User.lastName     AS creatorLastName,
        User.photoFileName AS creatorPhoto
    FROM Report
    JOIN Recipe ON Report.recipeID = Recipe.id
    JOIN User   ON Recipe.userID   = User.id
    GROUP BY Report.id, Report.recipeID, Report.userID,
             Recipe.name, Recipe.userID, User.firstName, User.lastName, User.photoFileName
    ORDER BY Report.id DESC
")->fetchAll();

// ── Fetch blocked users ───────────────────────────────────────────────────────
$blockedUsers = $pdo->query("
    SELECT firstName, lastName, emailAddress
    FROM BlockedUser
    ORDER BY id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NutriGood | Admin Page</title>
  <link rel="stylesheet" href="style.css" />
</head>

<body>

  <header class="topbar-admin">
    <div class="containers">
      <div class="topbar-inner">
        <div class="logo-section">
          <img src="images/nutrigood-logo.png" alt="NutriGood Logo" class="logo">
        </div>
        <div class="admin-btns">
          <a class="pill" href="signout.php">Sign out</a>
        </div>
      </div>
    </div>
  </header>

  <main class="containers">

    <div class="admin-welcome">
      Welcome <span class="name"><?php echo htmlspecialchars($adminInfo['firstName']); ?></span>,
    </div>

    <!-- Admin Information -->
    <section class="info-card" aria-label="Admin information">
      <h3>My Information</h3>
      <div class="info-row">
        <b>Name:</b>
        <span><?php echo htmlspecialchars($adminInfo['firstName'] . ' ' . $adminInfo['lastName']); ?></span>
      </div>
      <div class="info-row">
        <b>Email:</b>
        <span><?php echo htmlspecialchars($adminInfo['emailAddress']); ?></span>
      </div>
    </section>

    <!-- Reported Recipes -->
    <section class="section">
      <h2>Reported Recipes</h2>

      <div class="table-wrap" role="region" aria-label="Reported recipes table">
        <table class="admin-table">
          <colgroup>
            <col style="width:34%">
            <col style="width:34%">
            <col style="width:32%">
          </colgroup>
          <thead>
            <tr>
              <th>Recipe Name</th>
              <th>Recipe Creator</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($reportedRecipes): ?>
              <?php foreach ($reportedRecipes as $report): ?>
                <?php
                  $cp = $report['creatorPhoto'] ?? 'default.png';
                  $cpSrc = file_exists('images/uploads/' . $cp) ? 'images/uploads/' . $cp : 'images/iconss.png';
                ?>
                <tr>
                  <td class="center">
                    <a href="view-recipe.php?id=<?php echo $report['recipeID']; ?>">
                      <?php echo htmlspecialchars($report['recipeName']); ?>
                    </a>
                  </td>

                  <td>
                    <div class="creator-cell">
                      <div class="small-avatar">
                        <img src="<?php echo htmlspecialchars($cpSrc); ?>"
                             alt="<?php echo htmlspecialchars($report['creatorFirstName']); ?> photo">
                      </div>
                      <div class="creator-name">
                        <?php echo htmlspecialchars($report['creatorFirstName'] . ' ' . $report['creatorLastName']); ?>
                      </div>
                    </div>
                  </td>

                  <td>
                    <form class="action-form" action="admin_action.php" method="POST">
                      <input type="hidden" name="report_id"        value="<?php echo $report['reportID']; ?>">
                      <input type="hidden" name="reported_user_id" value="<?php echo $report['creatorID']; ?>">
                      <input type="hidden" name="recipe_id"        value="<?php echo $report['recipeID']; ?>">

                      <div class="radios">
                        <label class="radio-row">
                          <input type="radio" name="report_action" value="block" required>
                          Block user
                        </label>
                        <label class="radio-row">
                          <input type="radio" name="report_action" value="dismiss">
                          Dismiss report
                        </label>
                      </div>

                      <button class="submit-mini" type="submit">Submit</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="3" style="text-align:center; padding:20px; color:#888;">
                  No reported recipes.
                </td>
              </tr>
            <?php endif; ?>

            <tr class="empty-row"><td></td><td></td><td></td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Blocked Users -->
    <section class="section">
      <h2>Blocked Users List</h2>

      <div class="table-wrap narrow" role="region" aria-label="Blocked users table">
        <table>
          <thead>
            <tr>
              <th style="width:45%;">Name</th>
              <th style="width:55%;">Email address</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($blockedUsers): ?>
              <?php foreach ($blockedUsers as $bu): ?>
                <tr>
                  <td class="center">
                    <?php echo htmlspecialchars($bu['firstName'] . ' ' . $bu['lastName']); ?>
                  </td>
                  <td class="center">
                    <?php echo htmlspecialchars($bu['emailAddress']); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="2" style="text-align:center; padding:20px; color:#888;">
                  No blocked users.
                </td>
              </tr>
            <?php endif; ?>
            <tr><td style="height:74px;"></td><td></td></tr>
          </tbody>
        </table>
      </div>
    </section>

  </main>

  <!-- Brand Banner -->
  <section class="brand-banner"><div class="banner-content"></div></section>

  <!-- Footer -->
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
          <a href="#" class="social-link" aria-label="LinkedIn">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
              <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
            </svg>
          </a>
          <span class="footer-brand-text">NutriGood</span>
        </div>
      </div>
    </div>
  </footer>

</body>
</html>
