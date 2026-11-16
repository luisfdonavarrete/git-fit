<?php

namespace App\Actions\MealPlans;

use RuntimeException;

/**
 * A planner couldn't produce a usable plan; the message is safe to show the user.
 */
class MealPlanFailed extends RuntimeException {}
