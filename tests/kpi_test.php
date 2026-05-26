<?php

declare(strict_types=1);

require __DIR__ . '/../app/kpi.php';

$targets = [
    'barber_rtb_target' => 500,
    'barber_rtb_amber' => 400,
    'barber_days_target' => 5,
    'barber_days_amber' => 4,
    'training_attendance_target' => 0.9,
    'training_attendance_amber' => 0.8,
    'social_posts_target' => 5,
    'social_posts_amber' => 3,
    'social_reels_target' => 3,
    'social_reels_amber' => 2,
    'social_followup_target' => 0.9,
    'social_followup_amber' => 0.75,
];

function assert_same_value(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}. Expected {$expected}, got {$actual}\n");
        exit(1);
    }
}

assert_same_value(RAG_GREEN, barber_rag(500, 5, $targets)['overall_rag'], 'barber green boundary');
assert_same_value(RAG_AMBER, barber_rag(400, 4, $targets)['overall_rag'], 'barber amber boundary');
assert_same_value(RAG_RED, barber_rag(399, 5, $targets)['overall_rag'], 'barber red boundary');
assert_same_value(RAG_GREEN, training_rag(0.9, 0, $targets)['overall_rag'], 'training green');
assert_same_value(RAG_AMBER, training_rag(0.85, 1, $targets)['overall_rag'], 'training amber');
assert_same_value(RAG_RED, training_rag(0.95, 2, $targets)['overall_rag'], 'training safeguarding red');
assert_same_value(RAG_GREEN, brand_rag(5, 3, 10, 9, $targets)['overall_rag'], 'brand green');
assert_same_value(RAG_AMBER, brand_rag(3, 2, 10, 8, $targets)['overall_rag'], 'brand amber');
assert_same_value(RAG_RED, recruitment_rag(3, 0)['pipeline_rag'], 'recruitment red');
assert_same_value(3, priority_status_score('High', 'Closed'), 'closed score wins');

echo "KPI tests passed\n";

