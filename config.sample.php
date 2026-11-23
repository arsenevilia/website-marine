<?php
/**
 * Copy this file to config.php on the server and fill in the mailbox password.
 * config.php must never be committed or shared.
 */
return [
    // Where form submissions are delivered
    'to'        => 'operations@maritorshipbrokers.com',

    // Sender mailbox (must exist in cPanel → Email Accounts)
    'from'      => 'operations@maritorshipbrokers.com',
    'from_name' => 'Maritor Website',

    // Namecheap shared hosting SMTP
    'smtp_host' => 'mail.maritorshipbrokers.com',
    'smtp_port' => 465,
    'smtp_secure' => 'ssl',      // 'ssl' for port 465, 'tls' for port 587 (STARTTLS)
    'smtp_user' => 'operations@maritorshipbrokers.com',
    'smtp_pass' => 'CHANGE_ME',

    // Anti-spam
    'min_seconds'      => 3,     // minimum time a human needs to fill the form
    'max_per_hour_ip'  => 5,     // submissions per IP per hour
];
