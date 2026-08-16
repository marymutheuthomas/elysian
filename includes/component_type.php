<?php
// includes/component_type.php — infers a component's `type` column from its
// `content_schema` elements, used by both the mentor CMS save handler and the
// one-time backfill script so the two stay in sync.

/**
 * Determine the component `type` that best represents a content_schema stack.
 *
 * Priority: the first answer-bearing ("interactive") element wins, since that's
 * what students actually respond to. If none of the elements collect an answer
 * but a structurally distinct block (video, result reveal) is present, that's
 * used instead. A schema of pure content (headings/paragraphs/callouts) maps to
 * 'content_only'. A missing/empty schema maps to 'composite' — signalling an
 * incomplete block rather than silently mislabeling it as any specific type.
 */
function inferComponentType(?array $schema): string {
    if (empty($schema)) {
        return 'composite';
    }

    $interactive_map = [
        'input_short_answer' => 'short_answer',
        'input_free_text'    => 'free_text',
        'goal_statement'     => 'goal',
        'input_dropdown'     => 'dropdown',
        'rating_scale'       => 'rating_scale',
        'file_upload'        => 'file_upload',
        'trait_matrix'       => 'scoring_block',
    ];
    foreach ($schema as $elem) {
        $etype = $elem['type'] ?? '';
        if (isset($interactive_map[$etype])) {
            return $interactive_map[$etype];
        }
    }

    $structural_map = [
        'video_embed'   => 'video_embed',
        'result_reveal' => 'result_reveal',
    ];
    foreach ($schema as $elem) {
        $etype = $elem['type'] ?? '';
        if (isset($structural_map[$etype])) {
            return $structural_map[$etype];
        }
    }

    return 'content_only';
}
