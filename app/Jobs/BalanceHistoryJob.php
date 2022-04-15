<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Office;
use App\Models\BalanceHistory;

class BalanceHistoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data;
    /**
     * Create a new job instance.
     *
     * @return void
     */
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
        BalanceHistory::create([
            'office_id' => $office->id,
            'date' => $this->data->date,
            'amount' => $this->data->amount,
            'currency' => $this->data->currency,
            'exchange_rate' => $this->data->exchange_rate
        ]);
    }
}
