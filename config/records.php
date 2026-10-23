<?php

return [
    // Only configure IDs after the HR records owner approves signing authority.
    'certificate_signer_ids' => array_values(array_filter(array_map('intval', explode(',', (string) env('CERTIFICATE_SIGNER_IDS', ''))))),
    'certificate_public_verification' => (bool) env('CERTIFICATE_PUBLIC_VERIFICATION', false),
    // External notification links are disabled until approved origins/base paths are configured.
    'notification_destinations' => [],
];
