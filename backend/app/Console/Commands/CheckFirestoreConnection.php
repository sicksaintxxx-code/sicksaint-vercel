<?php

namespace App\Console\Commands;

use App\Services\FirestoreStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckFirestoreConnection extends Command
{
    protected $signature = 'sicksaint:firestore-check';
    protected $description = 'Verify Laravel can authenticate to the configured Firestore project.';

    public function handle(): int
    {
        try {
            app(FirestoreStore::class)->get('system', 'healthcheck');
            $this->info('Firestore API connection succeeded for the configured project.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Firestore connectivity check failed.', [
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->error('Firestore connection failed. Check project ID, IAM credentials, API enablement, and network access; details are in the Laravel log.');
            return self::FAILURE;
        }
    }
}
