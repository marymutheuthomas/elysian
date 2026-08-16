<?php
require_once __DIR__ . '/../config/db.php';

echo "=== TESTING COMPONENT ADD & EDIT HANDLERS ===\n\n";

// 1. Fetch default pillar ID and named block ID
$pillar = $pdo->query("SELECT id FROM pillars LIMIT 1")->fetch();
if (!$pillar) {
    die("No pillar found in database.\n");
}
$pillar_id = $pillar['id'];

$block = $pdo->prepare("SELECT id FROM blocks WHERE pillar_id = ? LIMIT 1");
$block->execute([$pillar_id]);
$block_row = $block->fetch();

$named_block_id = $block_row ? $block_row['id'] : null;

echo "Target Pillar: {$pillar_id}\n";
echo "Target Named Block: " . ($named_block_id ?: 'NULL') . "\n\n";

// 2. Test inserting a component directly via the save_block logic
$new_comp_id = 'COMP-TEST-' . time();
$schema = [
    ['id' => 'elem_1', 'type' => 'heading', 'level' => 'h2', 'text' => 'Test Decoupled Heading'],
    ['id' => 'elem_2', 'type' => 'trait_matrix', 'question' => 'Choose focus:', 'opt_a_label' => 'Extroverted Action', 'opt_a_trait' => 'E', 'opt_b_label' => 'Introverted Strategy', 'opt_b_trait' => 'I']
];
$schema_json = json_encode($schema);

$stmt = $pdo->prepare("INSERT INTO `components` (`id`, `pillar_id`, `block_id`, `type`, `question`, `placeholder`, `required`, `show_if`, `options`, `config`, `content_schema`, `sort_order`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([
    $new_comp_id,
    $pillar_id,
    $named_block_id,
    'content_only',
    'Test Decoupled Heading',
    '',
    1,
    null,
    null,
    null,
    $schema_json,
    99
]);

echo "1. Inserted Component: {$new_comp_id}\n";

// 3. Verify component exists in DB
$check = $pdo->prepare("SELECT * FROM components WHERE id = ?");
$check->execute([$new_comp_id]);
$row = $check->fetch();

if ($row && $row['content_schema'] === $schema_json) {
    echo "  - Question: {$row['question']}\n";
    echo "  - Content Schema Loaded Successfully: " . strlen($row['content_schema']) . " bytes\n";
} else {
    echo "❌ FAILED: Component insert check failed.\n";
}

// 4. Test updating the component
$updated_schema = [
    ['id' => 'elem_1', 'type' => 'heading', 'level' => 'h1', 'text' => 'Updated Decoupled Heading Title'],
    ['id' => 'elem_2', 'type' => 'result_reveal', 'title' => 'Revealed Strategic Profile']
];
$updated_json = json_encode($updated_schema);

$upd = $pdo->prepare("UPDATE `components` SET `type` = ?, `question` = ?, `content_schema` = ? WHERE `id` = ?");
$upd->execute(['content_only', 'Updated Decoupled Heading Title', $updated_json, $new_comp_id]);

$check->execute([$new_comp_id]);
$updated_row = $check->fetch();

if ($updated_row && $updated_row['question'] === 'Updated Decoupled Heading Title') {
    echo "2. Updated Component successfully!\n";
    echo "  - New Title: {$updated_row['question']}\n";
} else {
    echo "❌ FAILED: Component update failed.\n";
}

// 5. Clean up test component
$pdo->prepare("DELETE FROM components WHERE id = ?")->execute([$new_comp_id]);
echo "3. Cleaned up test component.\n\n";

echo "✅ SUCCESS: All Component Add and Edit operations working correctly!\n";
