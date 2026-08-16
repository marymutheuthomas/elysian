<?php
// save_profile.php — Action handler for saving mentor-authored archetype outcome profiles
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/profiles.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mentor auth guard
if (!isset($_SESSION['mentor_logged_in']) || $_SESSION['mentor_logged_in'] !== true) {
    header("Location: /mentor/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = isset($_POST['code']) ? strtoupper(trim($_POST['code'])) : '';
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $tagline = isset($_POST['tagline']) ? trim($_POST['tagline']) : '';
    $strengths = isset($_POST['strengths']) ? $_POST['strengths'] : '';
    $growth_areas = isset($_POST['growth_areas']) ? $_POST['growth_areas'] : (isset($_POST['weaknesses']) ? $_POST['weaknesses'] : '');

    // Handle 4 individual SMART goals or array/text input
    $goals = [];
    if (isset($_POST['goal_1']) || isset($_POST['goal_2']) || isset($_POST['goal_3']) || isset($_POST['goal_4'])) {
        for ($i = 1; $i <= 4; $i++) {
            $g_val = isset($_POST["goal_$i"]) ? trim($_POST["goal_$i"]) : '';
            if (!empty($g_val)) {
                $goals[] = $g_val;
            }
        }
    } elseif (isset($_POST['smart_goals'])) {
        $goals = $_POST['smart_goals'];
    }

    if (!empty($code) && !empty($title)) {
        saveProfileArchetype($pdo, $code, $title, $tagline, $strengths, $growth_areas, $goals);
    }

    header("Location: /mentor/index.php?tab=archetypes&selected_code=" . urlencode($code) . "&saved=1");
    exit;
}

header("Location: /mentor/index.php?tab=archetypes");
exit;
