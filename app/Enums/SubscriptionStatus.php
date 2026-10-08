<?php


// SubscriptionStatus.php — SubscriptionStatus module.
//
// exports: SubscriptionStatus | SubscriptionStatus::Active | SubscriptionStatus::Suspended
// used_by: app/Models/Subscription.php
//         database/factories/SubscriptionFactory.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
