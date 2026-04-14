<?php
// TEMPORARY FILE - DELETE AFTER USE

$members = [
    ['name' => 'Hala (Admin)',  'password' => 'Hala@123'],
    ['name' => 'Ghada',         'password' => 'Ghada@123'],
    ['name' => 'Soso',          'password' => 'Soso@123'],
    ['name' => 'Jana',          'password' => 'Jana@123'],
    ['name' => 'Shatham',       'password' => 'Shatham@123'],
];

foreach ($members as $m) {
    $hash = password_hash($m['password'], PASSWORD_DEFAULT);
    echo "<b>" . $m['name'] . ":</b> " . $hash . "<br><br>";
}
?>