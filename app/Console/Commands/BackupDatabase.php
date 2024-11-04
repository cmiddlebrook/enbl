<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class BackupDatabase extends Command
{
    protected $signature = 'backup-database';
    protected $description = 'Creates a backup of the database and sends it to Google Drive';
    private $folderID = '1Q_XCmlQemTzvMLfyWKEdw474nk1Db_fh';

    public function handle()
    {
        try
        {
            $dbHost = env('DB_HOST');
            $dbName = env('DB_DATABASE');
            $dbUser = escapeshellarg(env('DB_USERNAME'));
            $dbPass = escapeshellarg(env('DB_PASSWORD')); // Ensure this is properly escaped

            // Create a backup filename
            $filename = "backup-" . Carbon::now()->format('Y-m-d_H-i-s') . ".sql";
            $backupPath = storage_path("backups/{$filename}");

            // Run the mysqldump command
            $command = "mysqldump --user={$dbUser} --password={$dbPass} --host={$dbHost} {$dbName} > {$backupPath}";

            $this->info("Executing command: $command");
            exec($command);

            $client = new \Google\Client();
            $client->setAuthConfig('config/rheybrook-marketing-1fb09c3c23a5.json');
            $client->setScopes(\Google\Service\Drive::DRIVE);

            $service = new \Google\Service\Drive($client);

            $fileMetadata = new \Google\Service\Drive\DriveFile();
            $fileMetadata->setName($filename);
            $fileMetadata->setParents([$this->folderID]);
            
            $content = file_get_contents($backupPath);
            
            $file = $service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => mime_content_type($backupPath),
                'uploadType' => 'multipart',
                'fields' => 'id'
            ]);
            
            $fileId = $file->id;
            $this->info("Database backup completed successfully! {$fileId}");

        }
        catch (\Exception $e)
        {
            // Catch any exceptions and output to console
            $this->error("An error occurred: " . $e->getMessage());
        }       
    }
}
