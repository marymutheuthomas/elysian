<?php
require_once __DIR__ . '/../config/db.php';
$stmt = $pdo->query("SELECT permanent_id, name, status, selected_program_id FROM students");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));




