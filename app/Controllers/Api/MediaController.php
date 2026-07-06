<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\MediaFileModel;

class MediaController extends BaseController
{
    private const MAX_SIZE    = 16 * 1024 * 1024; // 16MB
    private const ALLOWED_MIME = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/quicktime', 'video/avi',
        'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/aac', 'audio/webm',
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function upload()
    {
        $file = $this->request->getFile('media');

        if (!$file || !$file->isValid()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'No file uploaded']);
        }

        if ($file->getSizeByUnit('b') > self::MAX_SIZE) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'File exceeds 16MB limit']);
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'File type not allowed']);
        }

        $uploadDir  = WRITEPATH . 'uploads/chat-media/' . date('Y/m') . '/';
        $filename   = generate_uuid() . '.' . $file->getExtension();

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file->move($uploadDir, $filename);

        $mediaType = $this->detectMediaType($mimeType);
        $filePath  = date('Y/m') . '/' . $filename;

        $mediaId = (new MediaFileModel())->insert([
            'account_id'        => session('account_id'),
            'file_path'         => $filePath,
            'mime_type'         => $mimeType,
            'file_size'         => $file->getSizeByUnit('b'),
            'original_filename' => $file->getClientName(),
            'media_type'        => $mediaType,
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON([
            'success'  => true,
            'media_id' => $mediaId,
            'url'      => base_url('api/media/download/' . $mediaId),
        ]);
    }

    public function download(string $mediaId)
    {
        $mediaFileModel = new MediaFileModel();
        $media          = $mediaFileModel->where('account_id', session('account_id'))->find($mediaId);

        if (!$media) {
            return $this->response->setStatusCode(404)->setBody('File not found');
        }

        $filePath = WRITEPATH . 'uploads/chat-media/' . $media['file_path'];

        if (!file_exists($filePath)) {
            return $this->response->setStatusCode(404)->setBody('File not found on disk');
        }

        $mediaFileModel->update($mediaId, ['last_accessed_at' => date('Y-m-d H:i:s')]);

        $safeFilename = str_replace(['"', "\r", "\n"], '', $media['original_filename'] ?? 'file');

        return $this->response
            ->setHeader('Content-Type', $media['mime_type'])
            ->setHeader('Content-Disposition', 'inline; filename="' . $safeFilename . '"')
            ->setHeader('Content-Length', filesize($filePath))
            ->setBody(file_get_contents($filePath));
    }

    private function detectMediaType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) return 'image';
        if (str_starts_with($mimeType, 'video/')) return 'video';
        if (str_starts_with($mimeType, 'audio/')) return 'audio';
        return 'document';
    }
}
