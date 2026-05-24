<?php
declare(strict_types=1);

return [
    'superadmin' => [
        'id' => 'superadmin',
        'name' => 'Super Administrator',
        'role' => 'superadmin',
        'active' => true,
        'password_hash' => '$2y$10$Tj6fEiSj40X0XK01VHodSuHdVonZTEUl0b8jISpZ1LiBbhCffJDYm',
    ],
    'admin' => [
        'id' => 'admin',
        'name' => 'Administrator',
        'role' => 'admin',
        'active' => true,
        'password_hash' => '$2y$10$LR8GJSnC0WPhk8q9O/rsReHWklA4RMgfCG3zOTQRIVxyZ.g.PujY6',
    ],
    'user' => [
        'id' => 'user',
        'name' => 'User Operasional',
        'role' => 'user',
        'active' => true,
        'password_hash' => '$2y$10$A6bTQKzv6WPvXozvxWWIF.bfvcRYvDQtkNFpLb0QoTTLju/Bphjfq',
    ],
    'guest' => [
        'id' => 'guest',
        'name' => 'Guest Viewer',
        'role' => 'guest',
        'active' => true,
        'password_hash' => '$2y$10$XSkfn7wKA.119jWsBWXIUO.TRVMwsfQu9bH6mYOVtHBAisX3ViChq',
    ],
];
