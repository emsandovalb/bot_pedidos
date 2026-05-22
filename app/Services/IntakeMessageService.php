<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Draw;
use App\Models\IncomingMessage;
use App\Models\IntakeRequest;
use App\Models\IntakeRequestEvent;
use App\Models\MessageResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IntakeMessageService
{
    public function __construct(
        private readonly MessageParserService $messageParserService,
        private readonly CustomerConfirmationMessageService $customerConfirmationMessageService,
        private readonly NumberLimitService $numberLimitService,
    ) {
    }

    public function create(
        User $user,
        Branch $branch,
        string $customerPhone,
        ?string $customerName,
        string $rawText,
        ?array $payload = null,
        ?string $externalMessageId = null,
        string $channelType = Branch::CHANNEL_TYPE_SIMULATED,
        ?string $fromIdentifier = null,
        ?string $toIdentifier = null,
    ): array {
        return DB::transaction(function () use ($user, $branch, $customerPhone, $customerName, $rawText, $payload, $externalMessageId, $channelType, $fromIdentifier, $toIdentifier): array {
            $customer = Customer::query()
                ->where('organization_id', $user->organization_id)
                ->where('phone', $customerPhone)
                ->first();

            if (! $customer) {
                $customer = Customer::create([
                    'organization_id' => $user->organization_id,
                    'branch_id' => $branch->id,
                    'name' => $customerName,
                    'phone' => $customerPhone,
                    'external_id' => null,
                ]);
            } elseif ($customerName && ! $customer->name) {
                $customer->update(['name' => $customerName]);
            }

            $incomingMessage = IncomingMessage::create([
                'organization_id' => $user->organization_id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'channel_type' => $channelType,
                'from_identifier' => $fromIdentifier ?? $customerPhone,
                'to_identifier' => $toIdentifier ?? $branch->channel_identifier,
                'raw_text' => $rawText,
                'payload_json' => array_filter([
                    'source' => $channelType,
                    'payload' => $payload,
                ], fn ($value) => $value !== null),
                'external_message_id' => $externalMessageId,
                'status' => IncomingMessage::STATUS_RECEIVED,
                'received_at' => now(),
            ]);

            $parserResult = $this->messageParserService->parse($rawText);
            $drawReference = $parserResult['draw_reference'] ?? null;
            $resolvedDraw = $this->resolveDraw($user, $drawReference);
            $parserResult['resolved_draw'] = $resolvedDraw ? [
                'id' => $resolvedDraw->id,
                'name' => $resolvedDraw->name,
                'draw_time' => $resolvedDraw->draw_time,
                'label' => $this->drawLabel($resolvedDraw),
            ] : null;
            $parserResult['draw_reference_label'] = $drawReference;
            $parserResult['available_draws'] = $this->availableDraws($user);

            $responseText = $this->customerConfirmationMessageService->generate($rawText, $parserResult);
            $responseType = $this->customerConfirmationMessageService->typeFor($parserResult);

            $messageResponse = MessageResponse::create([
                'incoming_message_id' => $incomingMessage->id,
                'response_type' => $responseType,
                'response_text' => $responseText,
                'parser_result_json' => $parserResult,
            ]);

            $requestItems = $parserResult['items'] ?? [];
            $requestReason = $parserResult['reason'] ?? null;
            $requests = [];

            if ($requestItems === []) {
                $requestItems = [[
                    'detected_amount' => null,
                    'detected_number' => null,
                ]];
            }

            foreach ($requestItems as $item) {
                $draw = $resolvedDraw;
                $detectedNumber = $item['detected_number'] ?? null;
                $detectedAmount = $item['detected_amount'] ?? null;
                $notes = $requestReason;
                $limitWarning = null;
                $itemNeedsReview = (bool) ($parserResult['needs_review'] ?? true) || count($requestItems) > 1;

                if ($drawReference === null && $detectedNumber !== null && $detectedAmount !== null) {
                    $itemNeedsReview = true;
                    $notes = 'Draw schedule is required. Manual review required.';
                }

                if ($drawReference !== null && $draw === null && $detectedNumber !== null && $detectedAmount !== null) {
                    $itemNeedsReview = true;
                    $notes = 'Draw schedule could not be matched. Manual review required.';
                }

                if ($draw !== null && $detectedNumber !== null && $detectedAmount !== null) {
                    $limitWarning = $this->numberLimitService->warningForAmount(
                        $user->organization,
                        $branch,
                        $draw,
                        $detectedNumber,
                        (float) $detectedAmount,
                    );

                    if ($limitWarning !== null) {
                        $itemNeedsReview = true;
                        $notes = trim(implode(' ', array_filter([$notes, $limitWarning])));
                    }
                }

                $requestStatus = $itemNeedsReview
                    ? IntakeRequest::STATUS_NEEDS_REVIEW
                    : IntakeRequest::STATUS_PENDING;

                $request = IntakeRequest::create([
                    'organization_id' => $user->organization_id,
                    'branch_id' => $branch->id,
                    'draw_id' => $draw?->id,
                    'customer_id' => $customer->id,
                    'incoming_message_id' => $incomingMessage->id,
                    'detected_number' => $detectedNumber,
                    'detected_amount' => $detectedAmount,
                    'raw_text' => $rawText,
                    'status' => $requestStatus,
                    'confirmed_by' => null,
                    'confirmed_at' => null,
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'notes' => $notes,
                ]);

                $request->events()->create([
                    'user_id' => $user->id,
                    'event_type' => IntakeRequestEvent::EVENT_CREATED,
                    'old_values' => null,
                    'new_values' => [
                        'status' => $request->status,
                        'detected_number' => $request->detected_number,
                        'detected_amount' => $request->detected_amount,
                        'draw_id' => $request->draw_id,
                        'parser_type' => $parserResult['parser_type'] ?? null,
                    ],
                    'notes' => $channelType === Branch::CHANNEL_TYPE_TELEGRAM
                        ? 'Created from Telegram intake.'
                        : 'Created from intake simulation.',
                    'created_at' => now(),
                ]);

                $requests[] = $request;
            }

            return [
                'customer' => $customer->fresh(),
                'incoming_message' => $incomingMessage->fresh(),
                'message_response' => $messageResponse->fresh(),
                'requests' => array_map(static fn (IntakeRequest $request) => $request->fresh(), $requests),
                'request' => $requests[0]->fresh(),
                'parser_result' => $parserResult,
                'customer_confirmation_text' => $responseText,
            ];
        });
    }

    private function resolveDraw(User $user, ?string $drawReference): ?Draw
    {
        if ($drawReference === null || $user->organization_id === null) {
            return null;
        }

        $draws = Draw::query()
            ->where('organization_id', $user->organization_id)
            ->where('status', Draw::STATUS_ACTIVE)
            ->get();

        foreach ($draws as $draw) {
            if ($this->drawMatchesReference($draw, $drawReference)) {
                return $draw;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{id:int,name:string,draw_time:string}>
     */
    private function availableDraws(User $user): array
    {
        if ($user->organization_id === null) {
            return [];
        }

        return Draw::query()
            ->where('organization_id', $user->organization_id)
            ->where('status', Draw::STATUS_ACTIVE)
            ->orderBy('draw_time')
            ->get(['id', 'name', 'draw_time'])
            ->map(static fn (Draw $draw): array => [
                'id' => $draw->id,
                'name' => $draw->name,
                'draw_time' => $draw->draw_time,
                'label' => $draw->name,
            ])
            ->all();
    }

    private function drawLabel(Draw $draw): string
    {
        return $draw->name;
    }

    private function drawMatchesReference(Draw $draw, string $drawReference): bool
    {
        $reference = $this->normalizeDrawToken($drawReference);
        $name = $this->normalizeDrawToken($draw->name);
        $time = $this->normalizeDrawToken($draw->draw_time);

        if ($reference === $name || $reference === $time) {
            return true;
        }

        if ($reference === '12:00 md') {
            return str_contains($name, '12') && str_contains($name, 'md');
        }

        if ($reference === '2:00 pm') {
            return str_contains($name, '2') && str_contains($name, 'pm');
        }

        if ($reference === '5:00 pm') {
            return str_contains($name, '5') && str_contains($name, 'pm');
        }

        if ($reference === '7:00 pm') {
            return str_contains($name, '7') && str_contains($name, 'pm');
        }

        return false;
    }

    private function normalizeDrawToken(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(['medio dia', 'mediodia'], '12:00 md', $normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        if (preg_match('/\b12\s*(?:md|m\s*d)?\b/u', $normalized) === 1 || preg_match('/\b12\b/u', $normalized) === 1) {
            return '12:00 md';
        }

        if (preg_match('/\b2\s*pm\b/u', $normalized) === 1 || preg_match('/\b2pm\b/u', $normalized) === 1) {
            return '2:00 pm';
        }

        if (preg_match('/\b5\s*pm\b/u', $normalized) === 1 || preg_match('/\b5pm\b/u', $normalized) === 1) {
            return '5:00 pm';
        }

        if (preg_match('/\b7\s*pm\b/u', $normalized) === 1 || preg_match('/\b7pm\b/u', $normalized) === 1) {
            return '7:00 pm';
        }

        return $normalized;
    }
}
