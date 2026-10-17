<?php

return [
    // Enable only after verifying the transport, supervised workers and relay.
    'activation_mail_enabled' => (bool) env('FLEET_ACTIVATION_MAIL_ENABLED', false),
    'verification_mail_enabled' => (bool) env('FLEET_VERIFICATION_MAIL_ENABLED', false),
    'security_mail_enabled' => (bool) env('FLEET_SECURITY_MAIL_ENABLED', false),
    'temporary_password_hours' => (int) env('FLEET_TEMPORARY_PASSWORD_HOURS', 24),
    'activation_hours' => (int) env('FLEET_ACTIVATION_HOURS', 24),
    'verification_minutes' => (int) env('FLEET_VERIFICATION_MINUTES', 60),
    'recovery_minutes' => 30,
    'mail_max_attempts' => 3,
    'mail_lease_seconds' => 120,
    'health_pending_max' => (int) env('FLEET_DELIVERY_PENDING_MAX', 100),
    'health_oldest_seconds' => (int) env('FLEET_DELIVERY_OLDEST_SECONDS', 600),
    'health_expired_leases_max' => (int) env('FLEET_DELIVERY_EXPIRED_LEASES_MAX', 0),
    'health_final_failures_max' => (int) env('FLEET_DELIVERY_FINAL_FAILURES_MAX', 0),
    'health_redis_memory_percent' => (int) env('FLEET_REDIS_MEMORY_ALERT_PERCENT', 85),
    'health_heartbeat_seconds' => (int) env('FLEET_DELIVERY_HEARTBEAT_SECONDS', 90),
    'health_alert_ttl_seconds' => (int) env('FLEET_DELIVERY_ALERT_TTL_SECONDS', 900),
];
