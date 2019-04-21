<?php

namespace App\Jobs;

use App\Jobs\TranscationTimeoutJob;
use App\Transcation;
use App\User;
use App\Utility\FCMHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TranscationTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $transcation;

    public function __construct(Transcation $transcation)
    {
        $this->transcation = $transcation;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->transcation->status == 100) {
            $this->transcation->status = 400;
            $this->transcation->cancelled = 1;
            $this->transcation->save();

            $user = User::find($this->transcation->user_id);
            if ($user->fcm_token != null) {
                FCMHelper::pushMessageToUser($user->fcm_token,
                    "Taxi Call Timeout",
                    ['passenger' => 'transcation']);
            }
        } else if ($this->transcation->status == 101) {
            TranscationTimeoutJob::dispatch($this->transcation)->delay(now()->addMinutes(3));
        }
    }
}
