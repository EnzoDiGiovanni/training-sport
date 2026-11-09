<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/** Restaure une sauvegarde spatie/laravel-backup : base de données + images importées (storage/app/public). */
class RestoreBackup extends Command
{
    protected $signature = 'app:backup-restore {file? : Chemin de l’archive sur le disque de sauvegarde (défaut : la plus récente)} {--disk=backups} {--force}';

    protected $description = 'Restaure la base et les images depuis une sauvegarde (écrase les données actuelles)';

    public function handle(): int
    {
        $disk = Storage::disk($this->option('disk'));
        $file = $this->argument('file') ?? collect($disk->allFiles())->filter(fn ($f) => str_ends_with($f, '.zip'))->sortBy(fn ($f) => $disk->lastModified($f))->last();
        if (! $file || ! $disk->exists($file)) {
            $this->error('Aucune sauvegarde trouvée.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm("Restaurer « {$file} » ? La base et les images actuelles seront remplacées.")) {
            return self::FAILURE;
        }

        $tmp = storage_path('app/restore-'.uniqid());
        File::ensureDirectoryExists($tmp);
        try {
            File::put("$tmp/backup.zip", $disk->readStream($file));
            $zip = new ZipArchive;
            if ($zip->open("$tmp/backup.zip") !== true || ! $zip->extractTo("$tmp/x")) {
                throw new RuntimeException('Archive illisible (mot de passe BACKUP_ARCHIVE_PASSWORD ?).');
            }
            $zip->close();

            $dump = collect(File::allFiles("$tmp/x/db-dumps"))->first()?->getPathname() ?? throw new RuntimeException('Pas de dump de base dans l’archive.');
            $this->restoreDatabase($dump);

            File::deleteDirectory(storage_path('app/public'));
            File::ensureDirectoryExists(storage_path('app/public'));
            foreach (File::directories("$tmp/x") as $dir) {
                if (basename($dir) !== 'db-dumps') {
                    File::copyDirectory($dir, storage_path('app/public/'.basename($dir)));
                }
            }
            foreach (File::files("$tmp/x") as $f) {
                File::copy($f->getPathname(), storage_path('app/public/'.$f->getFilename()));
            }
        } finally {
            File::deleteDirectory($tmp);
        }
        $this->info("Sauvegarde « {$file} » restaurée.");

        return self::SUCCESS;
    }

    private function restoreDatabase(string $dump): void
    {
        $c = config('database.connections.'.config('database.default'));
        DB::disconnect();
        $result = match ($c['driver']) {
            'mysql', 'mariadb' => Process::env(['MYSQL_PWD' => $c['password']])->input(File::get($dump))
                ->run(['mysql', '-h', $c['host'], '-P', (string) $c['port'], '-u', $c['username'], $c['database']]),
            'sqlite' => (function () use ($c, $dump) {
                File::delete($c['database']);

                return Process::input(File::get($dump))->run(['sqlite3', $c['database']]);
            })(),
            default => throw new RuntimeException("Pilote {$c['driver']} non pris en charge."),
        };
        if ($result->failed()) {
            throw new RuntimeException('Échec de la restauration de la base : '.$result->errorOutput());
        }
    }
}
