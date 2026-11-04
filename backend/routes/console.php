<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('deenmate:prune-exports')->hourly();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
