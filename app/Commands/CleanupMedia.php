<?php

namespace App\Commands;

use App\Models\BaseModel;
use App\Models\MediaFileModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CleanupMedia extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'media:cleanup';
    protected $description = 'Delete media files older than 90 days';

    public function run(array $params)
    {
        BaseModel::setBypassAccountScope(true);

        $cutoff    = date('Y-m-d H:i:s', strtotime('-90 days'));
        $model     = new MediaFileModel();
        $files     = $model->where('created_at <', $cutoff)->findAll();

        if (empty($files)) {
            CLI::write('No old media files to clean up.', 'yellow');
            return;
        }

        $count     = 0;
        $freedBytes = 0;

        foreach ($files as $file) {
            $fullPath = WRITEPATH . 'uploads/chat-media/' . $file['file_path'];

            if (file_exists($fullPath)) {
                $freedBytes += filesize($fullPath);
                unlink($fullPath);
            }

            $model->delete($file['id']);
            $count++;
        }

        $freedMb = round($freedBytes / 1024 / 1024, 2);
        CLI::write("Cleaned up {$count} files, freed {$freedMb} MB", 'green');
    }
}
