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
        'post_like' => 3,
        'search' => 4,
        'post_save' => 5,
        'publisher_follow' => 7,
        'contact_action' => 8,
        'help_offer' => 10,
        'volunteer_application' => 10,
        'campaign_donation' => 15,
        'interested' => 6,
        'not_interested' => -15,
    ],

    'interests' => [
        'explicit_weight' => 10,
        'behavioral_min' => -50,
        'behavioral_max' => 100,
        'decay_factor' => 0.80,
    ],

    'candidate_limit' => 200,
    'popularity_cap' => 10,
    'exploration_ratio' => 0.20,
    'minimum_view_seconds' => 2,
    'view_dedupe_minutes' => 30,
];
