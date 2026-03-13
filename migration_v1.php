<?php
require 'config.php';
try {
    ->exec('ALTER TABLE users ADD COLUMN name TEXT');
    ->exec('ALTER TABLE users ADD COLUMN email TEXT');
    echo 'Columns added.';
} catch (Exception ) {
    echo 'Columns might already exist.';
}
?>
