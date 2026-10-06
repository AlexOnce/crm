<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CRM Bot Inactivity Timeout
    |--------------------------------------------------------------------------
    |
    | Number of minutes of inactivity before a conversation in 'human_live'
    | or 'waiting_agent' status is automatically returned to 'bot_active'.
    | Default is 30 minutes.
    |
    */
    'bot_inactivity_minutes' => (int) env('CRM_BOT_INACTIVITY_MINUTES', 30),
];
