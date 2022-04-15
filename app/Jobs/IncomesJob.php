<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Office;
use App\Models\Income;

class IncomesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $table = Office::select('id', 'uid')->where('uid', $this->data->uid);
        if (!$table->exists()) {
            Office::create(['uid' => $this->data->uid]);
        }
        $office = $table->first();
        unset($this->data->uid);
        $incomes = [];
        foreach ($this->data as $key => $income) {
            $incomes[] = [
                'office_id' => $office->id,
                'date' => $income->date,
                'manager' => $income->manager_bio,
                'manager_id' => $income->manager_id,
                'total_amount' => $income->total_amount,
                'payout_sum' => $income->payout,
                'payout_currency' => $income->currency,
                'percent' => $income->percent
            ];
        }
        Income::insert($incomes);
    }
}
