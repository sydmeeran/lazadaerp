<?php

namespace App\Console\Commands\shop;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Shop;

class syncOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shop:syncOrders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Orders';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $shops = Shop::all();
        foreach($shops as $shop){
            $shop->syncOrders(Carbon::now()->subDays(7)->format('Y-m-d'), '+2 day');
            $shop->touch();
            echo 'Synced Orders Successfully';
        }
    }
}
