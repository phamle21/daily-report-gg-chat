<?php

return [
    'reporter' => '',
    'default_project' => 'JRR',
    'projects' => [
        'JRR' => [
            'webhook' => '',
            'slack_webhook' => '',
            'avatar' => 'https://www.jrr.jp/wp-content/uploads/2026/04/favicon.png',
        ],
        'Primass' => [
            'webhook' => '',
            'slack_webhook' => '',
            'avatar' => 'https://www.jumvea.or.jp/logo_thumb_img/logo_thumb_576.jpeg',
        ],
    ],
    'wepro' => [
        'enabled' => false,
        'base_url' => 'https://wepro.rcvn.work',
        'basic_user' => '',
        'basic_pass' => '',
        'remember_cookie' => '',
        'include_subtasks' => true,
    ],
    'google_form' => [
        'enabled' => false,
        'url' => '',
        'email' => '',
        'department' => '',
        'start_hour' => '08',
        'start_minute' => '00',
        'end_hour' => '17',
        'end_minute' => '00',
        'report_note' => '',
    ],
];
