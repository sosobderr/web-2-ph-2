<?php

require_once 'auth_check.php';

checkLogin('user');   // already matches the security requirement



// ---------- Database connection ----------

$host    = 'localhost';

$db      = 'nutrigood';

$user    = 'root';

$pass    = 'root';

$charset = 'utf8mb4';



$dsn = "mysql:host=$host;dbname=$db;charset=$charset";



try {

    $pdo = new PDO($dsn, $user, $pass, [

        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    ]);

} catch (PDOException $e) {

    die('Database connection failed: ' . $e->getMessage());

}



// ---------- Current logged-in user ----------

$userID = $_SESSION['user_id'];



// ---------- Get this user's recipes + category + likes count ----------

$sql = "

    SELECT 

        recipe.id,

        recipe.name,

        recipe.description,

        recipe.photoFileName,

        recipe.videoFilePath,

        recipecategory.categoryName,

        COUNT(likes.recipeID) AS likeCount

    FROM recipe

    LEFT JOIN recipecategory

        ON recipe.categoryID = recipecategory.id

    LEFT JOIN likes

        ON recipe.id = likes.recipeID

    WHERE recipe.userID = ?

    GROUP BY 

        recipe.id,

        recipe.name,

        recipe.description,

        recipe.photoFileName,

        recipe.videoFilePath,

        recipecategory.categoryName

    ORDER BY recipe.id ASC

";



$stmt = $pdo->prepare($sql);

$stmt->execute([$userID]);

$recipes = $stmt->fetchAll();

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NutriGood - My Recipes</title>
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


        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 50px;
            margin-bottom: 14px;
        }

            .page-header h1 {
                font-size: 34px;
                margin: 0;
                color: var(--green);
                font-family: var(--serif);
            }

        .add-recipe-link {
            color: var(--green);
            text-decoration: underline;
            font-weight: 600;
            font-size: 17px;
            cursor: pointer;
            font-family: var(--serif);
        }

            .add-recipe-link:hover {
                color: var(--lime);
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
            font-size: 14px;
            line-height: 1.5;
            font-family: var(--serif);
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

       

            

        .recipe-cell {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

            .recipe-cell a {
                font-weight: 600;
                font-family: var(--serif);
            }

        .empty-row td {
            height: 74px;
            background: transparent;
        }

        .edit-link, .delete-link {
            color: var(--green);
            text-decoration: underline;
            font-weight: 600;
            font-family: var(--serif);
            font-size: 15px;
        }

            .edit-link:hover, .delete-link:hover {
                color: var(--lime);
            }

        .footer {
            background: linear-gradient(180deg, #F5F0E8 0%, #E8DCC8 100%);
            padding: 60px 0 40px;
            text-align: center;
        }

        .footer-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .footer-divider {
            width: 100%;
            max-width: 600px;
            height: 1px;
            background-color: rgba(34, 56, 31, 0.15);
            margin-bottom: 10px;
        }

        .footer-copyright {
            color: var(--text-dark);
            font-size: 0.95rem;
            font-family: 'Cooper BT', Georgia, serif;
            margin-bottom: 5px;
        }

        .footer-contact {
            color: var(--primary-green);
            font-size: 1.1rem;
            font-family: 'Cooper BT', Georgia, serif;
            text-decoration: underline;
            transition: all 0.3s ease;
            padding: 8px 0;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }

            .footer-contact:hover {
                color: #1a2a18;
                text-decoration: underline;
            }

        .email-icon {
            width: 20px;
            height: 20px;
            margin-right: 8px;
        }

        .footer-social {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 10px;
        }

        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            color: var(--primary-green);
            transition: all 0.3s ease;
            border-radius: 50%;
            background-color: rgba(34, 56, 31, 0.1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

            .social-link:hover {
                transform: translateY(-3px);
                background-color: var(--primary-green);
                color: var(--white);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }

            .social-link svg {
                width: 22px;
                height: 22px;
            }

        .footer-brand-text {
            font-family: 'Cooper BT', Georgia, serif;
            font-size: 1.1rem;
            color: var(--text-dark);
            font-weight: 600;
            margin-left: 10px;
        }
        .banana-recipe {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px; 
        }
            .banana-recipe:hover {
                border-color: var(--green);
                transform: scale(1.05);
            }
        td ol {
            text-align: left;
            padding-left: 22px;
            margin: 0;
        }
        td ul {
            text-align: left;
            padding-left: 22px;
            margin: 0;
        }
       .video-box {
width: 130px;
height: 70px;
overflow: hidden;
border-radius: 8px;
display: flex;
align-items: center;
justify-content: center;
}

.video-box iframe,
.video-box video {
width: 100%;
height: 100%;
object-fit: cover;
border: none;
}

.watch-video-link {
color: var(--green);
font-family: var(--serif);
font-size: 15px;
font-weight: 700;
text-decoration: underline;
}

.watch-video-link:hover {
color: var(--lime);
text-decoration: underline;
}


    </style>
</head>
<body>

    <!-- HEADER -->
    <header class="topbar-admin">
        <div class="containers">
            <div class="topbar-inner">
                <div class="logo-section">
                    <img src="images/nutrigood-logo.png" alt="NutriGood Logo" class="logo">

                </div>
            </div>

            </div>
    </header>


    <!-- MAIN -->
    <main>
        <div class="containers">

            <div class="page-header">
                <h1>My Recipes</h1>
                <a href="add_recipe.php" class="add-recipe-link">Add new recipe</a>
            </div>

            <div class="table-wrap">
                
                    <table>
                        <thead>
                            <tr>
                                <th>Recipe</th>
                                <th>Ingredients</th>
                                <th>Instructions</th>
                                <th>Video</th>
                                <th>Number of likes</th>
                                <th>Edit</th>
                                <th>Delete</th>
                            </tr>
                        </thead>

                       <tbody>

<?php if(count($recipes) == 0): ?>

<tr>
<td colspan="7">You have not added any recipes yet.</td>
</tr>

<?php else: ?>

<?php foreach($recipes as $recipe): ?>

<tr>

<!-- Recipe name + photo -->
<td>
<div class="recipe-cell">

<a href="view-recipe.php?id=<?=$recipe['id']?>">

<?=$recipe['name']?>

</a>

<a href="view-recipe.php?id=<?=$recipe['id']?>">

<img src="uploads/photos/<?=$recipe['photoFileName']?>" class="banana-recipe">

</a>

</div>
</td>

<!-- Ingredients / description -->
<td>

<?php
$ingredientSql = "SELECT ingredientName, ingredientQuantity
FROM ingredients
WHERE recipeID = ?";
$ingredientStmt = $pdo->prepare($ingredientSql);
$ingredientStmt->execute([$recipe['id']]);
$recipeIngredients = $ingredientStmt->fetchAll();
?>

<?php if (count($recipeIngredients) > 0): ?>
<ol>
<?php foreach ($recipeIngredients as $ingredient): ?>
<li>
<?= htmlspecialchars($ingredient['ingredientName']) ?>
<?php if (!empty($ingredient['ingredientQuantity'])): ?>
- <?= htmlspecialchars($ingredient['ingredientQuantity']) ?>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ol>
<?php else: ?>
No ingredients
<?php endif; ?>


</td>

<!-- Instructions placeholder -->
<td>
<?php
$instructionSql = "SELECT step FROM instructions WHERE recipeID = ? ORDER BY stepOrder ASC";
$instructionStmt = $pdo->prepare($instructionSql);
$instructionStmt->execute([$recipe['id']]);
$recipeInstructions = $instructionStmt->fetchAll();
?>

<?php if (count($recipeInstructions) > 0): ?>
<ol>
<?php foreach ($recipeInstructions as $instruction): ?>
<li><?= htmlspecialchars($instruction['step']) ?></li>
<?php endforeach; ?>
</ol>
<?php else: ?>
No instructions
<?php endif; ?>
</td>


<!-- Video -->
<td>
<div class="video-box">
<?php
$video = trim($recipe['videoFilePath'] ?? '');

if (!empty($video)) {

// Convert short YouTube link to embed
if (strpos($video, 'youtu.be/') !== false) {
$videoID = substr($video, strrpos($video, '/') + 1);
$video = "https://www.youtube.com/embed/" . $videoID;
}

// Convert normal YouTube watch link to embed
elseif (strpos($video, 'watch?v=') !== false) {
parse_str(parse_url($video, PHP_URL_QUERY), $query);
if (!empty($query['v'])) {
$video = "https://www.youtube.com/embed/" . $query['v'];
}
}

// 1) YouTube embed links
if (strpos($video, 'youtube.com/embed/') !== false) {
?>
<a class="watch-video-link" href="watch_video.php?id=<?= htmlspecialchars($recipe['id']) ?>">Watch video</a>
<?php
}

// 2) Uploaded local video files
elseif (
str_starts_with($video, 'uploads/videos/')
|| str_ends_with(strtolower($video), '.mp4')
|| str_ends_with(strtolower($video), '.mov')
|| str_ends_with(strtolower($video), '.webm')
|| str_ends_with(strtolower($video), '.ogg')
) {
?>
<a class="watch-video-link" href="watch_video.php?id=<?= htmlspecialchars($recipe['id']) ?>">Watch video</a>
<?php
}

// 3) Any other URL
else {
?>
<a class="watch-video-link" href="watch_video.php?id=<?= htmlspecialchars($recipe['id']) ?>">Watch video</a>
<?php
}

} else {
echo 'No video';
}
?>
</div>
</td>

<!-- Likes -->
<td>

<?=$recipe['likeCount']?>

</td>

<!-- Edit -->
<td>

<a href="edit_recipe.php?id=<?=$recipe['id']?>" class="edit-link">

Edit

</a>

</td>

<!-- Delete -->
<td>

<a href="delete_recipe.php?id=<?=$recipe['id']?>" class="delete-link">

Delete

</a>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

                    </table>
                </div> 

            </div>  
       
       

		    </main>

		    <section class="brand-banner">
	        <div class="banner-content">
	        </div>
	    </section>
    <!-- FOOTER -->
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
                    <span class="footer-brand-text">NutriGood </span>
                </div>
	            </div>
	        </div>
	    </footer>
		</body>
	</html>
