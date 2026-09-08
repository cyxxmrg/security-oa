<?php

return [
    'former_employee_retention_days' => (int) env('FORMER_EMPLOYEE_RETENTION_DAYS', 1095),
    'audit_log_retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 365),
    'system_log_retention_days' => (int) env('SYSTEM_LOG_RETENTION_DAYS', 365),
];
