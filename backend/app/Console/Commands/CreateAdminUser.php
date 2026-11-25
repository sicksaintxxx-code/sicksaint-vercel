<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirestoreStore;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sicksaint:make-admin {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promote an existing verified Firebase-linked SICKSAINT profile to administrator.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->argument('email') ?: $this->ask('Administrator email'))));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->error('A valid email address is required.'); return self::FAILURE; }
        $store = app(FirestoreStore::class);
        $id = hash('sha256', $email);
        $existing = $store->get('users', $id);
        if (!$existing) { $this->error('No SICKSAINT profile exists for that email. Create and verify the account through Firebase first.'); return self::FAILURE; }
        if (($existing['authProvider'] ?? null) !== 'firebase' || empty($existing['firebaseUid']) || empty($existing['emailVerifiedAt'])) {
            $this->error('This profile is not linked to a verified Firebase account. No data was changed.');
            return self::FAILURE;
        }
        if (($existing['role'] ?? null) === 'ADMIN') { $this->info('This verified Firebase profile is already an administrator.'); return self::SUCCESS; }
        if (!$this->confirm('Promote this existing verified Firebase profile to ADMIN?', false)) return self::FAILURE;
        $store->put('users', $id, ['role' => 'ADMIN', 'updatedAt' => now()->toIso8601String()]);
        $this->info('Existing Firebase-linked profile promoted. No password or identity fields were changed.');
        return self::SUCCESS;
    }
}
