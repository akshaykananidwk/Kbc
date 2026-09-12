<?php
declare(strict_types=1);

/**
 * Server side game constants. Anything an operator or admin may want to
 * change lives in the settings table instead - these are structural.
 */
return [
    'states' => [
        'GAME_NOT_STARTED',
        'PARTICIPANT_INTRO',
        'QUESTION_DISPLAYED',
        'TIMER_RUNNING',
        'TIMER_PAUSED',
        'ANSWER_SELECTED',
        'ANSWER_LOCKED',
        'RESULT_REVEALED',
        'CORRECT',
        'WRONG',
        'TIME_UP',
        'GAME_COMPLETED',
    ],
    'game_statuses' => [
        'pending', 'running', 'paused', 'completed', 'wrong_answer', 'time_up', 'quit', 'abandoned',
    ],
    'options' => ['A', 'B', 'C', 'D'],
    'difficulties' => ['easy', 'medium', 'hard', 'expert'],
    'lifelines' => [
        'fifty_fifty'  => 'Fifty Fifty (50:50)',
        'audience_poll'=> 'Audience Poll',
        'expert_advice'=> 'Expert Advice',
        'skip_question'=> 'Skip Question',
    ],
    'sound_events' => [
        'question_start', 'timer_start', 'timer_tick', 'answer_lock',
        'correct_answer', 'wrong_answer', 'prize_won', 'lifeline_used',
        'final_win', 'game_over',
    ],
];
