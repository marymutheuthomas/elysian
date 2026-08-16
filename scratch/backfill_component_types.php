<?php
// One-time repair: re-derive every component's `type` column from its
// content_schema, correcting the drift caused by the save handler previously
// defaulting every save to 'short_answer'. Safe to re-run — it's idempotent
// and only writes rows whose inferred type differs from the stored one.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/component_type.php';

$stmt = $pdo->query("SELECT id, type, content_schema FROM components");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$before_counts = [];
$after_counts = [];
$updated = 0;

foreach ($rows as $row) {
    $before_counts[$row['type']] = ($before_counts[$row['type']] ?? 0) + 1;

    $schema = !empty($row['content_schema']) ? json_decode($row['content_schema'], true) : null;
    $inferred = inferComponentType(is_array($schema) ? $schema : null);
    $after_counts[$inferred] = ($after_counts[$inferred] ?? 0) + 1;

    if ($inferred !== $row['type']) {
        $pdo->prepare("UPDATE `components` SET `type` = ? WHERE `id` = ?")->execute([$inferred, $row['id']]);
        echo "Updated {$row['id']}: '{$row['type']}' -> '{$inferred}'\n";
        $updated++;
    }
}

echo "\n=== Type distribution before ===\n";
print_r($before_counts);
echo "=== Type distribution after ===\n";
print_r($after_counts);
echo "\nTotal components: " . count($rows) . "\n";
echo "Total updated: $updated\n";
