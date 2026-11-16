<?php

namespace App\Actions\MealPlans;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;

/**
 * Thin wrapper around the Anthropic SDK so the meal planner can be faked in tests.
 */
class ClaudeMealPlanClient
{
    /**
     * Send one structured-output request and return the JSON text Claude produced.
     *
     * @param  array<string, mixed>  $schema  JSON schema the reply must match.
     *
     * @throws MealPlanFailed When the request fails, is declined or is cut off.
     */
    public function complete(string $system, string $prompt, array $schema): string
    {
        try {
            $message = $this->client()->beta->messages->create(
                maxTokens: 16000,
                messages: [['role' => 'user', 'content' => $prompt]],
                model: config()->string('services.anthropic.meal_plan_model'),
                system: $system,
                outputConfig: [
                    'effort' => 'medium',
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AnthropicException $exception) {
            report($exception);

            throw new MealPlanFailed(__('Claude couldn\'t be reached. Try the recipe library planner instead.'), previous: $exception);
        }

        return $this->text($message);
    }

    /**
     * The reply's text, after checking it finished normally.
     *
     * @throws MealPlanFailed
     */
    private function text(BetaMessage $message): string
    {
        if ($message->stopReason === 'refusal') {
            throw new MealPlanFailed(__('Claude declined to plan this day. Try the recipe library planner instead.'));
        }

        if ($message->stopReason === 'max_tokens') {
            throw new MealPlanFailed(__('Claude\'s plan was cut off. Try again.'));
        }

        foreach ($message->content as $block) {
            if ($block instanceof BetaTextBlock) {
                return $block->text;
            }
        }

        throw new MealPlanFailed(__('Claude didn\'t return a plan. Try again.'));
    }

    private function client(): Client
    {
        return new Client(apiKey: config()->string('services.anthropic.key'));
    }
}
