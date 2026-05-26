<?php

declare(strict_types=1);

const RAG_GREEN = 'GREEN';
const RAG_AMBER = 'AMBER';
const RAG_RED = 'RED';

function rag_score(string $rag): int
{
    return match (strtoupper($rag)) {
        RAG_GREEN => 3,
        RAG_AMBER => 2,
        default => 1,
    };
}

function rag_from_score(float $score): string
{
    if ($score >= 2.6) {
        return RAG_GREEN;
    }

    if ($score >= 1.8) {
        return RAG_AMBER;
    }

    return RAG_RED;
}

function rag_threshold(float $actual, float $target, float $amber): string
{
    if ($actual >= $target) {
        return RAG_GREEN;
    }

    if ($actual >= $amber) {
        return RAG_AMBER;
    }

    return RAG_RED;
}

function rag_worst(array $rags): string
{
    $scores = array_map('rag_score', $rags);
    $min = min($scores ?: [1]);

    return match ($min) {
        3 => RAG_GREEN,
        2 => RAG_AMBER,
        default => RAG_RED,
    };
}

function barber_rag(float $rtb, float $daysWorked, array $targets): array
{
    $rtbRag = rag_threshold($rtb, (float) $targets['barber_rtb_target'], (float) $targets['barber_rtb_amber']);
    $daysRag = rag_threshold($daysWorked, (float) $targets['barber_days_target'], (float) $targets['barber_days_amber']);

    if (min($rtb, $daysWorked * 100) >= 500) {
        $overall = RAG_GREEN;
    } elseif ($rtbRag !== RAG_RED && $daysRag !== RAG_RED) {
        $overall = RAG_AMBER;
    } else {
        $overall = RAG_RED;
    }

    return [
        'rtb_rag' => $rtbRag,
        'days_rag' => $daysRag,
        'overall_rag' => $overall,
        'score' => rag_score($overall),
    ];
}

function training_rag(float $attendance, int $safeguardingFlags, array $targets): array
{
    $attendanceRag = rag_threshold(
        $attendance,
        (float) $targets['training_attendance_target'],
        (float) $targets['training_attendance_amber']
    );

    if ($safeguardingFlags === 0) {
        $safeguardingRag = RAG_GREEN;
    } elseif ($safeguardingFlags === 1) {
        $safeguardingRag = RAG_AMBER;
    } else {
        $safeguardingRag = RAG_RED;
    }

    $overall = rag_worst([$attendanceRag, $safeguardingRag]);

    return [
        'attendance_rag' => $attendanceRag,
        'safeguarding_rag' => $safeguardingRag,
        'overall_rag' => $overall,
        'score' => rag_score($overall),
    ];
}

function brand_rag(int $posts, int $reels, int $leads, int $followUps, array $targets): array
{
    $postsRag = rag_threshold($posts, (float) $targets['social_posts_target'], (float) $targets['social_posts_amber']);
    $reelsRag = rag_threshold($reels, (float) $targets['social_reels_target'], (float) $targets['social_reels_amber']);

    $targetFollowUps = $leads * (float) $targets['social_followup_target'];
    $amberFollowUps = $leads * (float) $targets['social_followup_amber'];
    $followUpRag = rag_threshold($followUps, $targetFollowUps, $amberFollowUps);

    $overall = rag_worst([$postsRag, $reelsRag, $followUpRag]);

    return [
        'posts_rag' => $postsRag,
        'reels_rag' => $reelsRag,
        'followup_rag' => $followUpRag,
        'overall_rag' => $overall,
        'score' => rag_score($overall),
    ];
}

function recruitment_rag(int $required, int $activePipeline): array
{
    if ($activePipeline >= $required) {
        $rag = RAG_GREEN;
    } elseif ($activePipeline > 0) {
        $rag = RAG_AMBER;
    } else {
        $rag = RAG_RED;
    }

    return [
        'pipeline_rag' => $rag,
        'score' => rag_score($rag),
    ];
}

function priority_status_score(string $priority, string $status): int
{
    if (strtolower($status) === 'closed') {
        return 3;
    }

    return match (strtolower($priority)) {
        'low' => 3,
        'medium' => 2,
        default => 1,
    };
}

