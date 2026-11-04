<?php

namespace App\Console\Commands;

use App\Models\DataExportRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExports extends Command
{
    protected $signature = 'deenmate:prune-exports';

    protected $description = 'Delete expired personal data export files';

    public function handle(): int
    {
        DataExportRequest::query()->whereNotNull('file_path')->where('expires_at', '<', now())->each(function (DataExportRequest $e) {
            Storage::disk('local')->delete($e->file_path);
            $e->update(['file_path' => null, 'status' => 'expired']);
        });

        return self::SUCCESS;
    }
}
