<?php

namespace App\Filament\Support;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;

/**
 * Public uploads from the admin panel.
 *
 * The public site renders stored paths with asset($path) (paths are relative to /public, e.g. seeded
 * "images/partners/x.webp"). To stay compatible, admin uploads use a disk rooted at public_path() and
 * write into "storage/<dir>", which is the `php artisan storage:link` symlink to storage/app/public.
 * The files therefore physically live on the "public" disk under <dir>/, while the stored path
 * ("storage/<dir>/file.webp") works with asset() everywhere.
 */
class Uploads
{
    public const DISK = 'admin_public';

    public static function registerDisk(): void
    {
        config(['filesystems.disks.'.self::DISK => [
            'driver' => 'local',
            'root' => public_path(),
            'url' => '/',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ]]);
    }

    /** A FileUpload stored on the public disk under $directory, with an asset()-compatible path. */
    public static function public(string $name, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->disk(self::DISK)
            ->directory('storage/'.trim($directory, '/'))
            ->visibility('public');
    }

    public static function image(string $name, string $directory, int $maxKb = 2048): FileUpload
    {
        return self::public($name, $directory)
            ->image()
            ->acceptedFileTypes(['image/webp', 'image/jpeg', 'image/png'])
            ->maxSize($maxKb);
    }

    public static function richEditor(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->fileAttachmentsDisk(self::DISK)
            ->fileAttachmentsDirectory('storage/editor')
            ->fileAttachmentsVisibility('public')
            ->columnSpanFull();
    }
}
