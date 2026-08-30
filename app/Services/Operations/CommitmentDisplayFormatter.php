<?php

namespace App\Services\Operations;

use Carbon\CarbonImmutable;

class CommitmentDisplayFormatter
{
    /**
     * @return array{
     *     label:string,
     *     date_label:string,
     *     time_label:string,
     *     time_window_label:?string,
     *     date_value:?string,
     *     time_value:?string
     * }
     */
    public function format(
        ?string $requestedDate,
        ?string $commitmentDate,
        ?string $requestedTimeWindow,
        ?string $commitmentTime,
        ?string $timezone = null,
    ): array {
        $timezone = $this->timezone($timezone);
        $referenceDate = CarbonImmutable::now($timezone)->startOfDay();
        $resolvedDate = $this->resolveDate($requestedDate, $commitmentDate, $timezone);
        $timeValue = $this->normalizeTime($commitmentTime);
        $timeWindowLabel = $this->timeWindowLabel($requestedTimeWindow);

        $dateLabel = $resolvedDate !== null
            ? $this->dateLabel($resolvedDate, $referenceDate)
            : null;

        $label = $this->buildLabel($dateLabel, $timeValue, $timeWindowLabel);

        return [
            'label' => $label,
            'date_label' => $dateLabel ?? 'Sin fecha confirmada',
            'time_label' => $timeValue ?? ($timeWindowLabel ?? 'Sin hora definida'),
            'time_window_label' => $timeWindowLabel,
            'date_value' => $resolvedDate?->toDateString(),
            'time_value' => $timeValue,
        ];
    }

    private function timezone(?string $timezone): string
    {
        $timezone = is_string($timezone) ? trim($timezone) : '';

        return $timezone !== '' ? $timezone : (string) config('app.timezone', 'UTC');
    }

    private function resolveDate(?string $requestedDate, ?string $commitmentDate, string $timezone): ?CarbonImmutable
    {
        foreach ([$commitmentDate, $requestedDate] as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            try {
                return CarbonImmutable::parse($candidate, $timezone)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function dateLabel(CarbonImmutable $date, CarbonImmutable $referenceDate): string
    {
        if ($date->equalTo($referenceDate)) {
            return 'Hoy';
        }

        if ($date->equalTo($referenceDate->addDay())) {
            return 'Mañana';
        }

        return $date->locale('es')->isoFormat('dddd D [de] MMMM');
    }

    private function normalizeTime(?string $time): ?string
    {
        if (! is_string($time)) {
            return null;
        }

        $time = trim($time);

        if ($time === '') {
            return null;
        }

        if (preg_match('/^(?<hour>\d{2}):(?<minute>\d{2})/', $time, $matches) !== 1) {
            return $time;
        }

        $hour = (int) $matches['hour'];
        $minute = (int) $matches['minute'];
        $suffix = $hour < 12 ? 'a. m.' : 'p. m.';
        $displayHour = $hour % 12;

        if ($displayHour === 0) {
            $displayHour = 12;
        }

        return sprintf('%d:%02d %s', $displayHour, $minute, $suffix);
    }

    private function timeWindowLabel(?string $requestedTimeWindow): ?string
    {
        return match ($requestedTimeWindow) {
            'morning' => 'por la mañana',
            'afternoon' => 'por la tarde',
            'evening' => 'por la noche',
            'after_work' => 'después del trabajo',
            default => null,
        };
    }

    private function buildLabel(?string $dateLabel, ?string $timeValue, ?string $timeWindowLabel): string
    {
        if ($dateLabel !== null) {
            if ($timeValue !== null) {
                return $dateLabel.' a las '.$timeValue;
            }

            if ($timeWindowLabel !== null) {
                return $dateLabel.' '.$timeWindowLabel;
            }

            return $dateLabel;
        }

        if ($timeWindowLabel !== null) {
            return 'Hoy '.$timeWindowLabel;
        }

        if ($timeValue !== null) {
            return 'Hoy a las '.$timeValue;
        }

        return 'Sin fecha confirmada';
    }
}
