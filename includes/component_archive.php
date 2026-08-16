<?php
// includes/component_archive.php — lookup helper for components that have
// been deleted from the CMS but are still referenced by a student's saved
// answers. See includes/component_type.php for the sibling type-inference
// helper this file is modeled after.

/**
 * Fetch archived (deleted) component snapshots for a set of component IDs.
 * Returns a [id => row] map, same shape callers already use for live
 * component lookups (id/pillar_title/type/question/options).
 */
function getArchivedComponents(PDO $pdo, array $ids): array {
    $ids = array_values(array_unique(array_filter($ids, fn($id) => $id !== '' && $id !== null)));
    if (empty($ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $pdo->prepare("SELECT * FROM `component_archive` WHERE `id` IN ($placeholders)");
        $stmt->execute($ids);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['id']] = $row;
        }
        return $map;
    } catch (Throwable $t) {
        return [];
    }
}
