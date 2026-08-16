<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/profiles.php';
require_once __DIR__ . '/../includes/evaluation_engine.php';

echo "=== TEST DECOUPLED ASSESSMENT ENGINE ===\n\n";

// 1. Check schemes
$schemes = getAllTraitSchemes($pdo);
echo "1. Registered Trait Schemes:\n";
foreach ($schemes as $s) {
    echo "  - [{$s['scheme_id']}] {$s['name']} (Type: {$s['scheme_type']})\n";
}

// 2. Check traits for MBTI
$traits = getSchemeTraits($pdo, 'mbti_16_types');
echo "\n2. Traits for mbti_16_types (" . count($traits) . " total):\n";
foreach ($traits as $t) {
    echo "  - Code: {$t['code']} | Label: {$t['label']} | ID: {$t['trait_id']}\n";
}

// 3. Test evaluateAssessment with MBTI raw scores
$test_raw = [
    ['step_id' => 'step1', 'trait_id' => 'I', 'points' => 1],
    ['step_id' => 'step2', 'trait_id' => 'N', 'points' => 1],
    ['step_id' => 'step3', 'trait_id' => 'T', 'points' => 1],
    ['step_id' => 'step4', 'trait_id' => 'J', 'points' => 1],
];

$result = evaluateAssessment($pdo, 'mbti_16_types', $test_raw);
echo "\n3. Evaluation Result for raw scores (I, N, T, J):\n";
echo "  - Evaluated Code: " . ($result['code'] ?? 'N/A') . "\n";
echo "  - Archetype ID: " . ($result['archetype_id'] ?? 'N/A') . "\n";
echo "  - Archetype Title: " . ($result['archetype']['title'] ?? 'N/A') . "\n";
echo "  - Strengths Count: " . count($result['archetype']['strengths'] ?? []) . "\n";
echo "  - Goals Count: " . count($result['archetype']['smart_goals'] ?? []) . "\n";

if (($result['code'] ?? '') === 'INTJ') {
    echo "\n✅ SUCCESS: Decoupled assessment pipeline evaluated correctly!\n";
} else {
    echo "\n❌ FAILED: Unexpected evaluation result.\n";
}
