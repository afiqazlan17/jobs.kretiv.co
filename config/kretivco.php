<?php

// Mirrors lib/constants.js from the old Next.js app (DEPT, ROLE) — the
// canonical lookup data referenced from Blade views and controllers.
return [

    'departments' => [
        'print' => ['label' => 'KretivPrint', 'color' => '#E85D04'],
        'work' => ['label' => 'KretivWork', 'color' => '#7209B7'],
        'tech' => ['label' => 'KretivTech', 'color' => '#3A86FF'],
        'machine' => ['label' => 'KretivMachine', 'color' => '#6B7280'],
        'event' => ['label' => 'KretivEvent', 'color' => '#E91E63'],
        'wisb' => ['label' => 'Waffiy Industries', 'color' => '#9B93A8'],
    ],

    'roles' => [
        'bod' => ['label' => 'BOD', 'color' => '#E91E63', 'desc' => 'Full access — all departments, reports, settings'],
        'dept_head' => ['label' => 'Dept Head', 'color' => '#3A86FF', 'desc' => 'Own department(s) — jobs, reports'],
        'staff' => ['label' => 'Staff', 'color' => '#6B7280', 'desc' => 'Own department(s) — jobs, no reports/finance/settings'],
        'intern' => ['label' => 'Intern', 'color' => '#10B981', 'desc' => 'Own department(s) — same access as Staff'],
    ],

];
