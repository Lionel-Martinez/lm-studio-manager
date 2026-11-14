<?php

namespace App\Support;

use App\Enums\PaymentMode;
use App\Enums\TrialUnit;
use App\Models\Project;
use Illuminate\Support\Carbon;

/**
 * Arma el calendario de cobros de un proyecto a partir de su precio y su plan.
 * Todo se calcula en centavos para que las cuotas sumen exactamente el total.
 */
class PaymentPlan
{
    /** @return list<array{item: string, concept: string, due_date: string, amount: float}> */
    public static function schedule(Project $project): array
    {
        if ($project->plan_start_date === null) {
            return [];
        }

        $start = $project->plan_start_date->copy()->startOfDay();
        $rows = [];

        $total = (int) round(((float) $project->total_price) * 100);

        if ($total > 0 && $project->payment_mode !== null) {
            $rows = array_merge($rows, match ($project->payment_mode) {
                PaymentMode::Single => [self::row('single', 'Pago único', $start, $total)],
                PaymentMode::Split => self::split($project, $start, $total),
                PaymentMode::Installments => self::installments($project, $start, $total),
            });
        }

        $fee = (int) round(((float) $project->monthly_fee) * 100);

        if ($fee > 0 && ($project->monthly_months ?? 0) > 0) {
            $first = self::afterTrial($project, $start);
            $count = (int) $project->monthly_months;

            for ($i = 0; $i < $count; $i++) {
                $rows[] = self::row('monthly-'.($i + 1), 'Cuota mensual '.($i + 1).'/'.$count, $first->copy()->addMonthsNoOverflow($i), $fee);
            }
        }

        return $rows;
    }

    /** Fecha del primer cobro mensual: inicio + período de prueba + meses gratis. */
    public static function afterTrial(Project $project, Carbon $start): Carbon
    {
        $date = $start->copy();
        $value = (int) $project->trial_value;

        if ($value > 0 && $project->trial_unit !== null) {
            $date = match ($project->trial_unit) {
                TrialUnit::Days => $date->addDays($value),
                TrialUnit::Weeks => $date->addWeeks($value),
                TrialUnit::Months => $date->addMonthsNoOverflow($value),
            };
        }

        return $date->addMonthsNoOverflow((int) $project->free_months);
    }

    private static function split(Project $project, Carbon $start, int $total): array
    {
        $percent = (int) $project->deposit_percent;
        $deposit = (int) round($total * $percent / 100);
        $balanceDate = $project->due_date && $project->due_date->greaterThan($start) ? $project->due_date->copy() : $start->copy();

        return [
            self::row('deposit', "Anticipo {$percent}%", $start, $deposit),
            self::row('balance', 'Saldo '.(100 - $percent).'%', $balanceDate, $total - $deposit),
        ];
    }

    private static function installments(Project $project, Carbon $start, int $total): array
    {
        $count = max(1, (int) $project->installments_count);
        $base = intdiv($total, $count);
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            // La última cuota absorbe los centavos que sobran.
            $amount = $i === $count - 1 ? $total - $base * ($count - 1) : $base;
            $rows[] = self::row('installment-'.($i + 1), 'Cuota '.($i + 1)."/{$count}", $start->copy()->addMonthsNoOverflow($i), $amount);
        }

        return $rows;
    }

    private static function row(string $item, string $concept, Carbon $date, int $cents): array
    {
        return ['item' => $item, 'concept' => $concept, 'due_date' => $date->toDateString(), 'amount' => $cents / 100];
    }
}
