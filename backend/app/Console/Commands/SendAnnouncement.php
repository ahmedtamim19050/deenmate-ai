<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Services\Notifications\FcmService;
use Illuminate\Console\Command;

class SendAnnouncement extends Command
{
    protected $signature = 'push:announce {title} {body} {--topic=announcements}';

    protected $description = 'Send a content announcement through FCM. Never used for worship records.';

    public function handle(FcmService $fcm): int
    {
        if (! AppSetting::value('notifications_broadcast_enabled')) {
            $this->error('Broadcasts are disabled in Notification Configuration.');

            return self::FAILURE;
        }
        if (! $fcm->isConfigured()) {
            $this->error('FCM is not configured. Set FCM_PROJECT_ID and FCM_CREDENTIALS_PATH.');

            return self::FAILURE;
        }
        $fcm->sendToTopic($this->option('topic'), $this->argument('title'), $this->argument('body'));
        AuditLog::record('push.announcement', null, ['topic' => $this->option('topic')]);
        $this->info('Sent.');

        return self::SUCCESS;
    }
}
