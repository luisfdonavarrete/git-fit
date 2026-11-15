<?php

namespace App\Enums;

/**
 * Who set a nutrition goal: the user by hand, or the coach's calculation.
 */
enum GoalSource: string
{
    case Manual = 'manual';
    case Coach = 'coach';
}
