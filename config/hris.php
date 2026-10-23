<?php

return [
    'timezone' => 'Asia/Manila',
    'roles' => [
        'applicant' => [],
        'staff' => [],
        'division_chief' => ['attendance.division', 'requests.division', 'lnd.division', 'idp.review', 'ipcr.review'],
        'hr' => ['staff.manage', 'organization.manage', 'pds.read', 'pds.write', 'attendance.read', 'attendance.correct', 'attendance.export', 'delegations.manage', 'integration.sync', 'requests.certify', 'requests.override', 'coc.manage', 'leave.ledger', 'payroll.manage', 'lnd.manage', 'saln.custodian', 'ipcr.report', 'concerns.manage', 'references.manage', 'documents.manage', 'recruitment.manage', 'certificates.manage', 'recognition.manage'],
        'chief_admin_officer' => ['leave.witness'],
        'executive_director' => ['requests.decide'],
        'system_admin' => ['accounts.manage', 'organization.manage', 'portal.manage', 'calendar.manage', 'delegations.manage', 'integration.sync', 'references.manage', 'notifications.publish', 'attendance.office-punch.manage', 'attendance.raw.manage'],
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
