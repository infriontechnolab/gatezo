<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Production self-check:
 *
 *   docker compose -f compose.prod.yml exec app php artisan gatezo:doctor
 *
 * Prints PASS/WARN/FAIL per check with a fix for anything wrong. Deliberately prints no
 * secrets, hostnames, keys or addresses, so the output is safe to share when asking for help.
 */
class Doctor extends Command
{
    protected $signature = 'gatezo:doctor';

    protected $description = 'Check this deployment is production-ready (prints no secrets)';

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->line('  Gatezo deployment check');
        $this->newLine();

        $this->section('App');
        $this->check('APP_KEY is set', filled(config('app.key')), 'php artisan key:generate');
        $this->check('APP_ENV is production', config('app.env') === 'production', 'Set APP_ENV=production in .env', soft: true);
        $this->check('APP_DEBUG is off', ! config('app.debug'), 'Set APP_DEBUG=false in .env — debug pages leak configuration');
        $this->check('APP_URL is https', str_starts_with((string) config('app.url'), 'https://'), 'Set APP_URL=https://your-domain in .env', soft: true);
        $this->check('Config is cached', file_exists(base_path('bootstrap/cache/config.php')), 'php artisan optimize', soft: true);

        $this->section('Database');
        $dbUp = $this->check('Connects', $this->dbConnects(), 'Check DB_* in .env and that the db container is healthy');
        if ($dbUp) {
            $pending = $this->pendingMigrations();
            $this->check('Migrations are up to date', $pending === 0, "php artisan migrate --force  ({$pending} pending)");
            // On a budget VPS the disk is the weak link, so the whole database should fit in RAM.
            $pool = (int) round(((int) $this->dbVar('innodb_buffer_pool_size')) / 1048576);
            $dbMb = max(1, (int) round($this->databaseBytes() / 1048576));
            $this->check(
                "Database ({$dbMb} MB) fits in the buffer pool ({$pool} MB)",
                $pool >= $dbMb,
                'Raise DB_BUFFER_POOL in .env to ~25-30% of the box RAM, then recreate the db container',
                soft: true,
            );
        }

        $this->section('Storage');
        $this->check('storage/app is writable', is_writable(storage_path('app')), 'chown -R www-data storage');
        $this->check('public/storage symlink exists', is_link(public_path('storage')) || is_dir(public_path('storage')), 'php artisan storage:link — without it, uploaded logos 404');
        $this->check('Uploads are reachable', $this->uploadsWork(), 'Check the public disk and the storage symlink');
        if (($free = @disk_free_space(base_path())) !== false) {
            $freeGb = round($free / 1073741824, 1);
            $this->check("Disk has room ({$freeGb} GB free)", $freeGb > 2, 'Prune docker images and old backups', soft: $freeGb > 1);
        }

        $this->section('Mail');
        $mailer = config('mail.default');
        $this->check(
            "Mailer is not 'log' (currently: {$mailer})",
            $mailer !== 'log',
            'Set MAIL_MAILER=smtp and MAIL_* in .env — otherwise nobody can set a password or reset one',
        );
        $this->check('A from-address is set', filled(config('mail.from.address')), 'Set MAIL_FROM_ADDRESS in .env', soft: true);

        $this->section('Queue & schedule');
        if ($dbUp && Schema::hasTable('jobs')) {
            $stuck = DB::table('jobs')->where('created_at', '<', now()->subMinutes(10)->timestamp)->count();
            $this->check('No jobs stuck in the queue', $stuck === 0, "{$stuck} job(s) older than 10 min — is the queue worker running?", soft: true);
        }
        if ($dbUp && Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->count();
            $this->check('No failed jobs', $failed === 0, "php artisan queue:failed  ({$failed} failed)", soft: true);
        }

        $this->section('Accounts');
        if ($dbUp) {
            $this->check('A staff account exists for /ops', User::where('is_admin', true)->exists(), 'php artisan gatezo:admin you@example.com --name="Your Name"');
            $organizers = User::organizers()->count();
            $this->check("Organizer accounts: {$organizers}", true, '');
            $this->check('Events: '.Event::count(), true, '');
        }

        $this->section('Demo');
        $demo = config('gatezo.demo.enabled');
        $this->check(
            'Demo event '.($demo ? 'is on' : 'is off'),
            true,
            $demo ? 'On: /demo signs visitors into the shared demo event, reset nightly.' : 'Off: /demo returns 404. Set GATEZO_DEMO=true to show prospects around.',
            soft: true,
        );

        $this->newLine();
        if ($this->failures > 0) {
            $this->error("  {$this->failures} problem(s) to fix".($this->warnings ? ", {$this->warnings} warning(s)" : '').'.');

            return self::FAILURE;
        }
        $this->info('  Ready'.($this->warnings ? ", with {$this->warnings} warning(s) worth a look." : '.'));

        return self::SUCCESS;
    }

    // ---- helpers ------------------------------------------------------------------

    private function section(string $name): void
    {
        $this->line("  <options=bold>{$name}</>");
    }

    /** @return bool the result, so callers can skip dependent checks */
    private function check(bool|string $label, bool $ok = true, string $fix = '', bool $soft = false): bool
    {
        [$label, $ok] = is_string($label) ? [$label, $ok] : [$ok, $label];

        if ($ok) {
            $this->line("    <fg=green>✓</> {$label}");

            return true;
        }

        if ($soft) {
            $this->warnings++;
            $this->line("    <fg=yellow>!</> {$label}");
        } else {
            $this->failures++;
            $this->line("    <fg=red>✗</> {$label}");
        }
        if ($fix !== '') {
            $this->line("        <fg=gray>{$fix}</>");
        }

        return false;
    }

    private function dbConnects(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function pendingMigrations(): int
    {
        try {
            $ran = DB::table('migrations')->pluck('migration')->all();
            $files = collect(glob(database_path('migrations/*.php')))->map(fn ($f) => basename($f, '.php'));

            return $files->diff($ran)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function dbVar(string $name): string
    {
        try {
            return (string) (DB::selectOne("SHOW VARIABLES LIKE '{$name}'")->Value ?? 0);
        } catch (Throwable) {
            return '0';
        }
    }

    private function databaseBytes(): float
    {
        try {
            return (float) (DB::selectOne('SELECT SUM(data_length + index_length) AS n FROM information_schema.tables WHERE table_schema = DATABASE()')->n ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function uploadsWork(): bool
    {
        try {
            $probe = 'doctor-'.uniqid().'.txt';
            Storage::disk('public')->put($probe, 'ok');
            $ok = Storage::disk('public')->get($probe) === 'ok';
            Storage::disk('public')->delete($probe);

            return $ok;
        } catch (Throwable) {
            return false;
        }
    }
}
