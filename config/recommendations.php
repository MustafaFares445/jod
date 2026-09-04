<?php

declare(strict_types=1);

return [
    'weights' => [
        'followed_publisher' => 50,
        'explicit_interest' => 30,
        'behavioral_interest' => 20,
        'same_city' => 25,
        'same_governorate' => 15,
        'intent_match' => 20,
        'capability_match' => 15,
        'freshness' => 10,
        'urgency' => 10,
        'group_affinity' => 8,
        'availability_match' => 8,
        'repeated_unengaged_view' => -20,
        'not_interested' => -100,
    ],

    'interaction_weights' => [
        'post_view' => 1,
        'post_open' => 2,
        'campaign_open' => 2,
        'post_like' => 3,
        'search' => 4,
        'post_save' => 5,
        'publisher_follow' => 7,
        'contact_action' => 8,
        'help_offer' => 10,
        'volunteer_application' => 10,
        'campaign_donation' => 15,
        'not_interested' => -15,
        'exploration_interested' => 20,
        'exploration_not_interested' => -20,
    ],

    'interests' => [
        'explicit_weight' => 10,
        'behavioral_min' => -50,
        'behavioral_max' => 100,
        'decay_factor' => 0.80,
        'decay_cleanup_threshold' => 0.5,
    ],

    'candidate_limit' => 200,
    'popularity_cap' => 10,

    'exploration' => [
        'ratio' => 0.15,
        'minimum_per_page' => 1,
        'maximum_per_page' => 3,
        'interest_threshold' => 2,
        'negative_threshold' => -20,
        'prompt_cooldown_days' => 30,
        'max_prompts_per_page' => 2,
        'minimum_normal_spacing' => 4,
    ],

    'diversity' => [
        'window_size' => 5,
        'max_same_publisher' => 2,
        'max_same_category' => 3,
    ],

    'minimum_view_seconds' => 2,
    'minimum_visible_percent' => 60,
    'view_dedupe_minutes' => 30,
    'open_dedupe_minutes' => 30,
    'search_dedupe_minutes' => 30,
];
