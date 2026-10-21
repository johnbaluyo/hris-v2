<?php

return [
    'timezone' => 'Asia/Manila',
    'roles' => [
        'applicant' => [],
        'staff' => [],
        'division_chief' => ['attendance.division'],
        'hr' => ['staff.manage', 'organization.manage', 'pds.read', 'pds.write', 'attendance.read', 'attendance.correct', 'attendance.export', 'delegations.manage', 'integration.sync'],
        'executive_director' => [],
        'system_admin' => ['accounts.manage', 'organization.manage', 'portal.manage', 'calendar.manage', 'delegations.manage', 'integration.sync'],
    ],
    'employee_types' => ['Permanent', 'CSW', 'Contractual Regular', 'Probationary', 'Intern', 'Applicant'],
    // Explicit operator allowlist. URLs and secrets never come from a submitted portal URL.
    'partners' => [
        'ssi' => [
            'url' => env('HRIS_SSI_URL'),
            'token' => env('HRIS_SSI_TOKEN'),
            'allow_get_fallback' => env('HRIS_SSI_GET_FALLBACK', false),
        ],
    ],
];
