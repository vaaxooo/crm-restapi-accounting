<?php

namespace App\Services;

use App\Jobs\IncomesJob;
use App\Jobs\ExpensesJob;
use App\Jobs\PayoutsJob;
use App\Jobs\BalanceHistoryJob;
use App\Models\BalanceHistory;
use App\Models\Income;
use App\Models\Payout;
use App\Models\Expense;
use App\Models\Office;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Request;

class Accounting
{

    /**
     * @var object
     */
    private object $exchange_rates;

    public function __construct()
    {
        if (!Redis::get('exchange_rates')) {
            $kurs = json_decode(@file_get_contents('http://api.currencylayer.com/live?access_key=56cbeab727c4d31acbb87b32604ee8c5&format=1'));
            $rates = json_decode(@file_get_contents('https://apirone.com/api/v2/ticker?currency=btc'));
            $kurs->quotes->usd = $rates->usd;
            Redis::set('exchange_rates', json_encode($kurs->quotes), 'EX', 43200);
        }
        $this->exchange_rates = (object) json_decode(Redis::get('exchange_rates'));
        $this->amqp();
    }

    /**
     * @param $request
     * @return array
     */
    public function offices($request)
    {
        $request = (object) Request::all();
        if (!isset($request->start_date) && !isset($request->end_date)) {
            return [
                'status' => FALSE,
                'message' => 'The [start_date, end_date] fields required'
            ];
        }
        $offices = Office::paginate(15);
        foreach ($offices as $key => $office) {
            $incomes = Income::where('office_id', $office->id)->whereDate('date', '>=', $request->start_date)->whereDate('date', '<=', $request->end_date)->get();
            $expenses = Expense::where('office_id', $office->id)->whereDate('date', '>=', $request->start_date)->whereDate('date', '<=', $request->end_date)->get();
            $payouts = Payout::where('office_id', $office->id)->whereDate('date', '>=', $request->start_date)->whereDate('date', '<=', $request->end_date)->get();
            $total_payouts = 0;

            if (!empty($payouts)) {
                foreach ($payouts as $key => $payout) {

                    if (strtoupper($payout->currency) == "BTC") {
                        $total_payouts += $payout->amount * $payout->exchange_rate;
                    } else {
                        $total_payouts += $payout->amount;
                    }
                }
            }
            $total_salaries = 0;
            if (!empty($incomes)) {
                foreach ($incomes as $key => $manager) {
                    if ($manager->total_amount > 0) {
                        $total_salaries += $manager->total_amount * $manager->percent / 100;
                    }
                }
            }
            $total_expenses = 0;
            if (!empty($expenses)) {
                foreach ($expenses as $key => $expense) {
                    if (strtoupper($expense->currency) == 'BTC') {
                        $total_expenses += $expense->amount * $this->exchange_rates->USDUAH;
                    } else {
                        $total_expenses += $expense->amount;
                    }
                }
            }

            $balanceHistory = BalanceHistory::select('date', 'office_id', 'amount', 'exchange_rate', 'currency')->where('office_id', $office->id)->whereDate('created_at', '>=', $request->start_date)->whereDate('created_at', '<=', $request->end_date)->first();

            $balance = 0;
            if ($balanceHistory) {
                $balance = $balanceHistory->amount * $balanceHistory->exchange_rate;
            }

            $statistics = [
                'total_incomes' => $incomes->sum('payout_sum'),
                'total_payouts' => $total_payouts,
                'total_salaries' => $total_salaries,
                'total_expenses' => $total_expenses,
                'total_expenses_plus_salaries' =>  $total_expenses + $total_salaries,
                'accounting_income' => $total_payouts - ($total_expenses + $total_salaries),
                'balance_history' => $balance
            ];
            $office->statistics = $statistics;
        }

        return [
            'status' => TRUE,
            'data' => $offices
        ];
    }

    /**
     * @param         $request
     * @param  int    $office
     * @return array
     */
    public function incomes($request, int $office): array
    {
        $request = (object) Request::all();
        if (!(isset($request->start_date) && isset($request->end_date))) {
            return [
                'status' => FALSE,
                'message' => 'The [start_date, end_date] fields required'
            ];
        }
        $data = Income::select(DB::raw('id, office_id, date, manager, manager_id, COUNT(total_amount) as total_amount, payout_sum, payout_currency, percent'))
            ->where('office_id', $office)
            ->whereDate('date', '>=', $request->start_date)
            ->whereDate('date', '<=', $request->end_date)
            ->groupBy('id')
            ->paginate(15);

        $total_salaries = 0;
        $manager_salaries = 0;

        foreach ($data as $key => $manager) {
            $manager->salary = 0;
            if ($manager->total_amount > 0) {
                $manager->salary = $manager->total_amount * $manager->percent / 100;
            }
            $total_salaries += $manager->salary;
            if ($manager->id != 1) {
                $manager_salaries += $manager->salary;
            }
        }


        $clouser_salary = $total_salaries * 10 / 100;
        $total_btc = Income::where('office_id', $office)
            ->where('payout_currency', 'BTC')
            ->whereDate('date', '>=', $request->start_date)
            ->whereDate('date', '<=', $request->end_date)
            ->sum('payout_sum');
        $total_usdt = Income::where('office_id', $office)
            ->where('payout_currency', 'USDT')
            ->whereDate('date', '>=', $request->start_date)
            ->whereDate('date', '<=', $request->end_date)
            ->sum('payout_sum');

        $payments_for_the_week = ($total_btc * $this->exchange_rates->USDUAH) + $total_usdt;


        return [
            'status' => TRUE,
            'data' => $data,
            'statistics' => [
                'payments_for_the_week' => (int) $payments_for_the_week,
                'total_btc' => (int) $total_btc,
                'total_usdt' => (int) $total_usdt,
                'total_salaries' => (int) $total_salaries,
                'clouser_salary' => (int) $clouser_salary,
                'manager_salaries' => (int) $manager_salaries
            ]
        ];
    }

    /**
     * @param       $request
     * @param  int  $office
     * @return array
     */
    public function expenses($request, int $office): array
    {
        $request = (object) Request::all();
        if (!(isset($request->start_date) && isset($request->end_date))) {
            return [
                'status' => FALSE,
                'message' => 'The [start_date, end_date] fields required'
            ];
        }
        $data = Expense::where('office_id', $office)
            ->whereDate('date', '>=', $request->start_date)
            ->whereDate('date', '<=', $request->end_date)
            ->paginate(15);
        foreach ($data as $key => $expense) {
            if (strtoupper($expense->currency) == 'BTC') {
                $data->amount_in_usd = $expense->amount * $this->exchange_rates->usd;
            }
            $data[$key] = $expense;
        }

        $usd = Expense::where('currency', 'USD')->sum('amount');
        $btc = Expense::where('currency', 'BTC')->sum('amount');
        $uah = Expense::where('currency', 'UAH')->sum('amount');
        $total = $usd + ($btc * $this->exchange_rates->usd) + ($uah / $this->exchange_rates->USDUAH);

        return [
            'status' => TRUE,
            'data' => $data,
            'statistics' => [
                'total' => $total,
                'usd' => $usd,
                'btc' => $btc,
                'uah' => $uah
            ]
        ];
    }

    /**
     * @param       $request
     * @param  int  $office
     * @return array
     */
    public function payouts($request, int $office): array
    {
        $request = (object) Request::all();
        if (!(isset($request->start_date) && isset($request->end_date))) {
            return [
                'status' => FALSE,
                'message' => 'The [start_date, end_date] fields required'
            ];
        }
        $data = Payout::where('office_id', $office)
            ->whereDate('date', '>=', $request->start_date)
            ->whereDate('date', '<=', $request->end_date)
            ->paginate(15);

        $total_usd = 0;
        foreach ($data as $key => $payout) {
            if (strtoupper($payout->currency) == 'USD') {
                $payout->payout_sum = [
                    'btc' => round($payout->amount / $this->exchange_rates->usd, 4),
                    'usd' => $payout->amount
                ];
            } else {
                $payout->payout_sum = [
                    'btc' => $payout->amount,
                    'usd' => (int) $payout->amount * $payout->exchange_rate
                ];
            }

            $total_usd += $payout->payout_sum['usd'];
        }

        $usd =  Payout::where('currency', 'USD')->sum('amount');
        $btc = Payout::where('currency', 'BTC')->sum('amount');
        return [
            'status' => TRUE,
            'data' => $data,
            'statistics' => [
                'total' => $total_usd,
                'btc' => $btc,
                'usd' => $usd
            ]
        ];
    }

    /**
     * @param $request
     * @param $office
     * @return array
     */
    public function weekly_report($request, $office): array
    {
        $request = (object) Request::all();
        if (!(isset($request->start_date) && isset($request->end_date))) {
            return [
                'status' => FALSE,
                'message' => 'The [start_date, end_date] fields required'
            ];
        }
        $incomes = Income::where('office_id', $office)->whereDate('date', '>=', $request->start_date)->whereDate('date', '<=', $request->end_date)->get();
        $expenses = Expense::where('office_id', $office)->whereDate('date', '>=', $request->start_date)->whereDate('date', '<=', $request->end_date)->get();
        $payouts = Payout::where('office_id', $office)->whereDate('date', '>=', $request->start_date)->whereDate('date', '<=', $request->end_date)->get();

        $total_payouts = 0;
        foreach ($payouts as $key => $payout) {
            if (strtoupper($payout->currency) == "BTC") {
                $total_payouts += $payout->amount * $payout->exchange_rate;
            } else {
                $total_payouts += $payout->amount;
            }
        }

        $total_salaries = 0;
        foreach ($incomes as $key => $manager) {
            if ($manager->total_amount > 0) {
                $total_salaries += $manager->total_amount * $manager->percent / 100;
            }
        }

        $total_expenses = 0;
        foreach ($expenses as $key => $expense) {
            if (strtoupper($expense->currency) == 'BTC') {
                $total_expenses += $expense->amount * $this->exchange_rates->USDUAH;
            } else {
                $total_expenses += $expense->amount;
            }
        }
        $balanceHistory = BalanceHistory::select('date', 'office_id', 'amount', 'exchange_rate')->where('office_id', $office)->whereDate('date', '>=', $request->start_date)->whereDate('date', '<=', $request->end_date)->first();
        return [
            'status' => TRUE,
            'data' => [
                'total_incomes' => $incomes->sum('payout_sum'),
                'total_payouts' => $total_payouts,
                'total_salaries' => $total_salaries,
                'total_expenses' => $total_expenses,
                'total_expenses_plus_salaries' =>  $total_expenses + $total_salaries,
                'accounting_income' => $total_payouts - ($total_expenses + $total_salaries),
                'balance_history' => $balanceHistory->amount * $balanceHistory->exchange_rate
            ]
        ];
    }

    /**
     * @param $request
     * @param $office
     * @return array
     */
    public function setOfficeName($request, $office): array
    {
        $request = (object) Request::all();
        if (!isset($request->name)) {
            return [
                'status' => FALSE,
                'message' => 'The [name] field required'
            ];
        }
        $office = Office::where('id', $office);
        if (!$office->exists()) {
            return [
                'status' => FALSE,
                'message' => 'Office not found'
            ];
        }
        $office->update(['name' => $request->name]);
        return [
            'status' => TRUE,
            'message' => 'Data has been successfully updated'
        ];
    }

    /**
     * @param $office
     * @return array
     */
    public function getOfficeData($office): array
    {
        $office = Office::where('id', $office);
        if (!$office->exists()) {
            return [
                'status' => FALSE,
                'message' => 'Office not found'
            ];
        }
        return [
            'status' => TRUE,
            'data' => $office->first()
        ];
    }


    public function amqp()
    {
        \Amqp::consume('incomes', function ($message, $resolver) {
            $data = (object) json_decode($message->body);
            IncomesJob::dispatch($data);
            $resolver->acknowledge($message);
            $resolver->stopWhenProcessed();
        });

        \Amqp::consume('expenses', function ($message, $resolver) {
            $data = (object) json_decode($message->body);
            ExpensesJob::dispatch($data);
            $resolver->acknowledge($message);
            $resolver->stopWhenProcessed();
        });

        \Amqp::consume('payouts', function ($message, $resolver) {
            $data = (object) json_decode($message->body);
            PayoutsJob::dispatch($data);
            $resolver->acknowledge($message);
            $resolver->stopWhenProcessed();
        });

        \Amqp::consume('balance_history', function ($message, $resolver) {
            $data = (object) json_decode($message->body);
            BalanceHistoryJob::dispatch($data);
            $resolver->acknowledge($message);
            $resolver->stopWhenProcessed();
        });
    }
}
