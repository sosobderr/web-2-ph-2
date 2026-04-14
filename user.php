<?php
require_once 'auth_check.php';
checkLogin('user');

// database connection
$host = 'localhost';
$db = 'nutrigood';
$user = 'root';
$pass = 'root';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
   $pdo = new PDO($dsn, $user, $pass);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// logged in user id
$userId = $_SESSION['user_id'];

// get current user info
$stmt = $pdo->prepare("
    SELECT firstName, lastName, emailAddress, photoFileName
    FROM User
    WHERE id = ?
");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    die("User not found.");
}

// total recipes for this user
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS totalRecipes
    FROM Recipe
    WHERE userID = ?
");
$stmt->execute([$userId]);
$row = $stmt->fetch();
$totalRecipes = $row['totalRecipes'];

// total likes for all this user's recipes
$stmt = $pdo->prepare("
    SELECT COUNT(L.recipeID) AS totalLikes
    FROM Recipe R
    LEFT JOIN Likes L ON R.id = L.recipeID
    WHERE R.userID = ?
");
$stmt->execute([$userId]);
$row = $stmt->fetch();
$totalLikes = $row['totalLikes'];

// get all categories
$stmtCategories = $pdo->query("
    SELECT id, categoryName
    FROM RecipeCategory
    ORDER BY categoryName
");


// recipes
$selectedCategory = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['category_id'])) {
        $selectedCategory = (int) $_POST['category_id'];
    }
}
if ($selectedCategory > 0) {
    $stmtRecipes = $pdo->prepare("
        SELECT 
            R.id,
            R.name,
            R.photoFileName,
            RC.categoryName,
            U.firstName,
            U.lastName,
            U.photoFileName AS creatorPhoto,
            COUNT(L.userID) AS likeCount
        FROM Recipe R
        JOIN User U ON R.userID = U.id
        JOIN RecipeCategory RC ON R.categoryID = RC.id
        LEFT JOIN Likes L ON R.id = L.recipeID
        WHERE R.categoryID = ?
        GROUP BY R.id, R.name, R.photoFileName, RC.categoryName, U.firstName, U.lastName, U.photoFileName
        ORDER BY R.id DESC
    ");
    $stmtRecipes->execute([$selectedCategory]);
} else {
    $stmtRecipes = $pdo->query("
        SELECT 
            R.id,
            R.name,
            R.photoFileName,
            RC.categoryName,
            U.firstName,
            U.lastName,
            U.photoFileName AS creatorPhoto,
            COUNT(L.userID) AS likeCount
        FROM Recipe R
        JOIN User U ON R.userID = U.id
        JOIN RecipeCategory RC ON R.categoryID = RC.id
        LEFT JOIN Likes L ON R.id = L.recipeID
        GROUP BY R.id, R.name, R.photoFileName, RC.categoryName, U.firstName, U.lastName, U.photoFileName
        ORDER BY R.id DESC
    ");
}

// get favourite recipes
$stmtFav = $pdo->prepare("
    SELECT 
        R.id,
        R.name,
        R.photoFileName
    FROM Favourites F
    JOIN Recipe R ON F.recipeID = R.id
    WHERE F.userID = ?
    ORDER BY R.id DESC
");
$stmtFav->execute([$userId]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriGood - User Page</title>
    <link rel="stylesheet" href="style.css">
    <style>

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 100px;
            height: 100px;
            object-fit: contain;
        }

        .brand-name {
            font-family: 'Cooper BT', Georgia, serif;
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--primary-green);
        }

        .nav-buttons {
            display: flex;
            gap: 15px;
        }

        .brand-banner {
            width: 100%;
            height: 300px;
            background-image: url('images/brand-banner.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            margin-bottom: 60px;
        }

            .brand-banner::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(255, 255, 255, 0);
            }

        .banner-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .banner-logo {
            font-family: 'Cooper BT', Georgia, serif;
            font-size: 4rem;
            color: var(--primary-green);
            line-height: 1;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .banner-tagline {
            font-family: 'Cooper BT', Georgia, serif;
            font-size: 1.5rem;
            color: var(--text-dark);
            font-weight: 600;
        }

        .user-welcome {
            margin-top: 18px;
            font-size: 40px;
        }

            .username {
                font-weight: 700;
            }

        .info-section {
            display: flex;
            flex-direction: column;
            gap: 24px;
            align-items: flex-start; 
        }



        .info-card {
            width: min(520px, 100%);
            border-radius: var(--radius);
            border: 2px solid rgba(34,56,31,.45);
            background: var(--cream);
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: var(--serif);
        }

            .info-card h3 {
                margin: 0 0 10px;
                font-size: 22px;
                font-family: var(--serif);
            }

        .info-row {
            display: flex;
            gap: 6px;
            margin: 6px 0;
            font-size: 15px;
            font-family: var(--serif);
        }

            .info-row b {
                min-width: 60px;
            }

        .my-recipes-card {
            border-radius: var(--radius);
            border: 2px solid rgba(34,56,31,.45);
            background: var(--cream);
            padding: 20px 24px;
            flex: 1;
            min-width: 240px;
            font-family: var(--serif);
            width: min(500px, 100%);
        }

            .my-recipes-card h3 {
                margin: 0 0 10px;
                font-size: 22px;
                font-family: var(--serif);
            }

            .my-recipes-card p {
                margin: 5px 0;
                font-size: 15px;
                font-family: var(--serif);
            }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 34px;
            margin-bottom: 10px;
        }

            .section-header h2 {
                font-size: 28px;
                margin: 0;
                font-family: var(--serif);
            }

        .filter-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            background: none;
            border: none;
            font-family: var(--serif);
            font-size: 17px;
            color: var(--green);
            cursor: pointer;
            font-weight: 600;
        }

            .filter-btn svg {
                width: 22px;
                height: 22px;
                fill: var(--butter);
                stroke: var(--green);
                stroke-width: 1;
            }

            .filter-btn:hover {
                opacity: 0.75;
            }

        .table-wrap {
            margin-top: 10px;
            border-radius: 18px;
            border: 2px solid rgba(34,56,31,.45);
            overflow: hidden;
            background: var(--cream);
            margin-bottom: 40px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead th {
            background: transparent;
            font-weight: 700;
            text-align: center;
            padding: 14px 10px;
            border-bottom: 2px solid rgba(34,56,31,.45);
            font-size: 15px;
            font-family: var(--serif);
        }

        tbody tr {
            border-bottom: 2px solid rgba(34,56,31,.35);
        }

            tbody tr:last-child {
                border-bottom: none;
            }

        tbody td {
            padding: 18px 14px;
            vertical-align: middle;
            border-right: 2px solid rgba(34,56,31,.35);
            text-align: center;
            font-size: 15px;
            font-family: var(--serif);
           

        
            text-align: center;
        

        }

            tbody td:last-child {
                border-right: none;
            }

            tbody td a {
                color: var(--green);
                
                font-family: var(--serif);
            }

                tbody td a:hover {
                    color: var(--lime);
                    text-decoration: underline;
                }




        .creator-cell img {
            width: 55px;
            height: 55px;
            border-radius: 10px;
            object-fit: cover;
        }


        .creator-cell {
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
            font-family: var(--serif);
        }


       



        .fav-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 34px;
            margin-bottom: 10px;
            font-size: 28px;
            font-family: var(--serif);
        }

            .fav-title .heart-icon {
                color: var(--butter);
                font-size: 26px;
            }

        .empty-row td {
            height: 74px;
            background: transparent;
        }

        .my-recipes-link {
            color: var(--green);
            text-decoration: underline;
            font-weight: 600;
            cursor: pointer;
            font-size: 15px;
            font-family: var(--serif);
        }

            .my-recipes-link:hover {
                color: var(--lime);
            }

        .remove-link {
            color: var(--green);
            text-decoration: underline;
            font-weight: 600;
            font-family: var(--serif);
            font-size: 15px;
        }

            .remove-link:hover {
                color: var(--lime);
            }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 100%;
        }
        .creator-cell {
            display: flex;
            align-items: center;
            justify-content: center; /* يخليهم بالنص */
            gap: 10px;
            font-family: var(--serif);
        }

        /* صورة البروفايل الكبيرة */
        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
        }

       
        .table-pic {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
        }


   
        .info-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .creator-cell .username {
            margin-left: 0;
        }
       
        .filter-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .category-select {
            padding: 6px 10px;
            border-radius: 8px;
            border: 1.5px solid rgba(34,56,31,.4);
            font-family: var(--serif);
            font-size: 14px;
            background: white;
        }
        .fav-recipe {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px; 
        }

        .heart-icon-img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .filter-icon-img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

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
                    <a class="pill" href="signout.php">Sign out</a>
                </div>
            </div>
        </div>
    </header>

   
    <main>
        <div class="containers">

            <div class="user-welcome">
                Welcome  <?php echo $currentUser['firstName']; ?>,
            </div>

            <div class="info-section">
                <!-- My Information -->
                <div class="info-card">
                    <div>
                        <h3>My Information</h3>
                        <div class="info-row"><b>Name:</b>  <?php echo $currentUser['firstName'] . ' ' . $currentUser['lastName']; ?></div>
                        <div class="info-row"><b>Email:</b> <?php echo $currentUser['emailAddress']; ?></div>
                    </div>
                    <div class="profile-avatar">
                        <img src="uploads/photos/<?php echo $currentUser['photoFileName']; ?>" class="profile-pic" alt="Profile">

                    </div>
                </div>

                <!-- My Recipes -->
                <div class="my-recipes-card">
                    <h3><a href="Myrecipes.php" class="my-recipes-link">My recipes</a></h3>
                    <p>Total recipes: <?php echo $totalRecipes; ?></p>
                    <p>Total likes: <?php echo $totalLikes; ?></p>
                   
                </div>
            </div>

            <!-- Available Recipes -->
            <div class="section-header">

                <h2>Available Recipes</h2>

              <form method="POST" action="user.php" class="filter-controls">
     <select name="category_id" class="category-select">
                        <option value="0">All NutriGood Picks</option>
                        <?php while ($category = $stmtCategories->fetch()) { ?>
                            <option value="<?php echo $category['id']; ?>" <?php if ($selectedCategory == $category['id']) { echo "selected"; } ?>>
                                <?php echo $category['categoryName']; ?>
                            </option>
                        <?php } ?>
                    </select>
                  
<button type="submit" class="filter-btn">
    Filter
    <img src="images/filter.png" class="filter-icon-img" alt="filter">
</button>
   
              </form>

            </div>

           
            <div class="table-wrap">
                <?php
                $firstRecipe = $stmtRecipes->fetch();
                if ($firstRecipe) {
                ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Recipe Name</th>
                                <th>Recipe photo</th>
                                <th>Recipe creator</th>
                                <th>Number of likes</th>
                                <th>Category</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <a href="view-recipe.php?id=<?php echo $firstRecipe['id']; ?>">
                                        <?php echo $firstRecipe['name']; ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="img-placeholder">
                                        <img src="uploads/photos/<?php echo $firstRecipe['photoFileName']; ?>" alt="recipe" class="table-pic">
                                    </div>
                                </td>
                                <td>
                                    <div class="creator-cell">
                                        <div class="creator-small-avatar">
                                            <img src="uploads/photos/<?php echo $firstRecipe['creatorPhoto']; ?>" alt="person" class="table-pic">
                                        </div>
                                        <span class="username"><?php echo $firstRecipe['firstName'] . ' ' . $firstRecipe['lastName']; ?></span>
                                    </div>
                                </td>
                                <td><?php echo $firstRecipe['likeCount']; ?></td>
                                <td><?php echo $firstRecipe['categoryName']; ?></td>
                            </tr>

                            <?php while ($recipe = $stmtRecipes->fetch()) { ?>
                                <tr>
                                    <td>
                                        <a href="view-recipe.php?id=<?php echo $recipe['id']; ?>">
                                            <?php echo $recipe['name']; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="img-placeholder">
                                            <img src="uploads/photos/<?php echo $recipe['photoFileName']; ?>" alt="recipe" class="table-pic">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="creator-cell">
                                            <div class="creator-small-avatar">
                                                <img src="uploads/photos/<?php echo $recipe['creatorPhoto']; ?>" alt="person" class="table-pic">
                                            </div>
                                            <span class="username"><?php echo $recipe['firstName'] . ' ' . $recipe['lastName']; ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo $recipe['likeCount']; ?></td>
                                    <td><?php echo $recipe['categoryName']; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } else { ?>
                    <p style="text-align:center; padding:20px;">No recipes found.</p>
                <?php } ?>
            </div>
            
            
            <!-- My Favorite Recipes -->
            
           <div class="fav-title">
                My favorite recipes <img src="images/heart.png" class="heart-icon-img" alt="favorite">
            </div>

            <div class="table-wrap" style="max-width:520px;">
                <?php
                $firstFav = $stmtFav->fetch();
                if ($firstFav) {
                ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Recipe Name</th>
                                <th>Recipe photo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <a href="view-recipe.php?id=<?php echo $firstFav['id']; ?>">
                                        <?php echo $firstFav['name']; ?>
                                    </a>
                                </td>
                                <td>
                                    <img src="uploads/photos/<?php echo $firstFav['photoFileName']; ?>" alt="Favourite Recipe" class="fav-recipe">
                                </td>
                                <td>
                                    <a href="remove_favourite.php?recipe_id=<?php echo $firstFav['id']; ?>" class="remove-link">
                                        Remove
                                    </a>
                                </td>
                            </tr>

                            <?php while ($fav = $stmtFav->fetch()) { ?>
                                <tr>
                                    <td>
                                        <a href="view-recipe.php?id=<?php echo $fav['id']; ?>">
                                            <?php echo $fav['name']; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <img src="uploads/photos/<?php echo $fav['photoFileName']; ?>" alt="Favourite Recipe" class="fav-recipe">
                                    </td>
                                    <td>
                                        <a href="remove_favourite.php?recipe_id=<?php echo $fav['id']; ?>" class="remove-link">
                                            Remove
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } else { ?>
                    <p style="text-align:center; padding:20px;">You do not have any favourite recipes.</p>
                <?php } ?>
            </div>

      
</main>

    <section class="brand-banner">
        <div class="banner-content">
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-divider"></div>
                <p class="footer-copyright">&copy; 2026 NutriGood</p>
                <a href="mailto:NutriGood@gmail.com" class="footer-contact">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" class="email-icon">
                        <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" />
                    </svg>
                    NutriGood@gmail.com
                </a>
                <div class="footer-social">
                    <a href="#" class="social-link" aria-label="LinkedIn">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" />
                        </svg>
                    </a>
                    <a href="#" class="social-link" aria-label="Twitter">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z" />
                        </svg>
                    </a>
                    <a href="#" class="social-link" aria-label="TikTok">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z" />
                        </svg>
                    </a>
                    <span class="footer-brand-text">NutriGood</span>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
