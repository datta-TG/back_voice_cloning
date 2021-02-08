<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpParser\Node\Expr\Array_;

class ApiRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $url;
    protected $params;

    /**
     * Make an api call.
     *
     * @param String $url
     * @param array $params
     */
    public function __construct(String $url, Array $params)
    {
        $this->url = $url;
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $response = Http::timeout(900)->post(\Config::get('values.ai_url') . $this->url . '/', $this->params);
        Storage::disk('local')->makeDirectory('info');
        Storage::disk('local')->put('info.json', $response);
    }
}
