<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Office;
use App\Models\Payout;

class PayoutsJob implements ShouldQueue
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
        unset($this->data->uid);
        $payouts = [];
        foreach ($this->data as $key => $payout) {
            $payouts[] = [
                'office_id' => $office->id,
                'date' => $payout->date,
                'amount' => $payout->exchange_sum,
                'currency' => $payout->currency,
                'exchange_rate' => $payout->exchange_rate,
                'percent' => $payout->percent
            ];
        }
        Payout::insert($payouts);
    }
}
