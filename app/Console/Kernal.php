<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\ImportUsersFromCsv::class,

    ];

    protected function schedule($schedule)
    {
        //
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
    }
}