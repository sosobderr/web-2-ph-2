-- =============================================
-- NutriGood Database - IT329 Phase 2
-- =============================================

CREATE TABLE User (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userType ENUM('user', 'admin') NOT NULL,
    firstName VARCHAR(50) NOT NULL,
    lastName VARCHAR(50) NOT NULL,
    emailAddress VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    photoFileName VARCHAR(255) DEFAULT 'default.png'
);

CREATE TABLE RecipeCategory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoryName VARCHAR(100) NOT NULL
);

CREATE TABLE Recipe (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userID INT NOT NULL,
    categoryID INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    photoFileName VARCHAR(255),
    videoFilePath VARCHAR(255),
    FOREIGN KEY (userID) REFERENCES User(id),
    FOREIGN KEY (categoryID) REFERENCES RecipeCategory(id)
);

CREATE TABLE Ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipeID INT NOT NULL,
    ingredientName VARCHAR(150) NOT NULL,
    ingredientQuantity VARCHAR(100),
    FOREIGN KEY (recipeID) REFERENCES Recipe(id)
);

CREATE TABLE Instructions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipeID INT NOT NULL,
    step TEXT NOT NULL,
    stepOrder INT NOT NULL,
    FOREIGN KEY (recipeID) REFERENCES Recipe(id)
);

CREATE TABLE Likes (
    userID INT NOT NULL,
    recipeID INT NOT NULL,
    PRIMARY KEY (userID, recipeID),
    FOREIGN KEY (userID) REFERENCES User(id),
    FOREIGN KEY (recipeID) REFERENCES Recipe(id)
);

CREATE TABLE Favourites (
    userID INT NOT NULL,
    recipeID INT NOT NULL,
    PRIMARY KEY (userID, recipeID),
    FOREIGN KEY (userID) REFERENCES User(id),
    FOREIGN KEY (recipeID) REFERENCES Recipe(id)
);

CREATE TABLE Comment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipeID INT NOT NULL,
    userID INT NOT NULL,
    comment TEXT NOT NULL,
    date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipeID) REFERENCES Recipe(id),
    FOREIGN KEY (userID) REFERENCES User(id)
);

CREATE TABLE Report (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userID INT NOT NULL,
    recipeID INT NOT NULL,
    FOREIGN KEY (userID) REFERENCES User(id),
    FOREIGN KEY (recipeID) REFERENCES Recipe(id)
);

CREATE TABLE BlockedUser (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firstName VARCHAR(50) NOT NULL,
    lastName VARCHAR(50) NOT NULL,
    emailAddress VARCHAR(100) NOT NULL
);

-- =============================================
-- INITIAL DATA
-- =============================================

INSERT INTO User (userType, firstName, lastName, emailAddress, password, photoFileName) VALUES
('admin', 'Hala',   'Abdulrahman', 'Hala.abdulrahman01@gmail.com', '$2y$10$DbbjMOddTRrbiOgCe2hawO4ZwtuMsZ/xGmvuE78cLVxqLeZ2hhpZS', 'default.png'),
('user',  'Ghada',  'Alhmdan',     'ghada.alhmdan@gmail.com',      '$2y$10$w//yO.T4Nf4oZDnbAfOzPudBuafuBPSvM7USRPf5fkisrqTyZF9Dq', 'default.png'),
('user',  'Sara',   'Bderr',       'sosobderr@gmail.com',          '$2y$10$ftDm.mxok.Pdd0sJiQmfDerpg0E.glwHBV3ZvHnujOs0MxnKaFcpq', 'default.png'),
('user',  'Jana',   'Alsh',        'janaalsh35@gmail.com',         '$2y$10$RoyLAm6XGCaSL89URjaINOFpfcBpVTzd0aDeD15G2iNZckZ8C3UtS', 'default.png'),
('user',  'Shatha', 'Marzuq',      'shathamarzuq@gmail.com',       '$2y$10$irtutJOFjPkVoJtNKnnbq.MjStYtnTXUtZkuoAyoq/bt2Osc1cy0S', 'default.png');

INSERT INTO RecipeCategory (categoryName) VALUES
('Appetizer'),
('Main Course'),
('Dessert');

INSERT INTO Recipe (userID, categoryID, name, description, photoFileName, videoFilePath) VALUES
(2, 1, 'Kale Salad',      'A fresh and nutritious kale salad with lemon dressing.',         'recipe_1_kaleSalad.jpeg',   'recipe_1_video.mp4'),
(3, 2, 'Chicken Bowl',    'A wholesome mix of lean protein and fresh vegetables.',          'recipe_2_chickenBowl.jpeg', NULL),
(4, 3, 'Acai Power Bowl', 'A nutrient-rich bowl with acai berries and fresh fruits.',       'recipe_3_acaiBowl.jpeg',    NULL);

INSERT INTO Ingredients (recipeID, ingredientName, ingredientQuantity) VALUES
(1, 'Kale',           '1 bunch'),
(1, 'Olive oil',      '1 tablespoon'),
(1, 'Lemon juice',    '1 tablespoon'),
(2, 'Chicken breast', '200g'),
(2, 'Brown rice',     '1 cup'),
(3, 'Acai berries',   '100g');

INSERT INTO Instructions (recipeID, step, stepOrder) VALUES
(1, 'Wash and chop the kale leaves.',        1),
(1, 'Massage kale with olive oil and salt.', 2),
(1, 'Add lemon juice and toss to coat.',     3),
(2, 'Grill chicken breast until cooked.',   1),
(2, 'Serve over rice with vegetables.',     2),
(3, 'Blend acai with banana until smooth.', 1);

INSERT INTO Likes (userID, recipeID) VALUES
(3, 1), (4, 1), (2, 3);

INSERT INTO Favourites (userID, recipeID) VALUES
(3, 1), (4, 2), (2, 3);

INSERT INTO Comment (recipeID, userID, comment) VALUES
(1, 3, 'Yummy!!'),
(1, 4, 'Loved it!'),
(2, 2, 'Great recipe, very filling.');

INSERT INTO Report (userID, recipeID) VALUES
(3, 2), (4, 1), (2, 3);

INSERT INTO BlockedUser (firstName, lastName, emailAddress) VALUES
('Khalid', 'Abdulaziz', 'k.almm@gmail.com'),
('Fahad',  'Salem',     'fahad.s@gmail.com'),
('Mona',   'Ali',       'mona.ali@gmail.com');