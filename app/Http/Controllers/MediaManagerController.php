<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class MediaManagerController extends Controller
{
    public function connector(Request $request)
    {
        abort_unless($request->user() && $request->user()->can('view settings'), Response::HTTP_FORBIDDEN);

        $canManageSettings = (bool) ($request->user() && $request->user()->can('manage settings'));
        $uploadsPath = public_path('uploads');

        if (!File::isDirectory($uploadsPath)) {
            File::makeDirectory($uploadsPath, 0775, true);
        }

        $baseUrl = trim((string) $request->getBaseUrl());
        $uploadsUrl = rtrim(
            $request->getSchemeAndHttpHost() . ($baseUrl !== '' ? $baseUrl : '') . '/uploads',
            '/'
        ) . '/';

        $options = [
            'bind' => [
                'upload.presave' => [
                    'Plugin.AutoResize.onUpLoadPreSave',
                ],
            ],
            'plugin' => [
                'AutoResize' => [
                    'enable' => true,
                    'maxWidth' => 1920,
                    'maxHeight' => 1920,
                    'quality' => 84,
                    'forceEffect' => true,
                ],
            ],
            'roots' => [
                [
                    'driver' => 'LocalFileSystem',
                    'path' => $uploadsPath . DIRECTORY_SEPARATOR,
                    'URL' => $uploadsUrl,
                    'alias' => 'Uploads',
                    'winHashFix' => DIRECTORY_SEPARATOR !== '/',
                    'uploadDeny' => ['all'],
                    'uploadAllow' => [
                        'image',
                        'audio',
                        'video',
                        'text/plain',
                        'text/csv',
                        'application/pdf',
                        'application/json',
                        'application/zip',
                        'application/x-zip-compressed',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ],
                    'uploadOrder' => ['deny', 'allow'],
                    'accessControl' => [self::class, 'accessControl'],
                    'accessControlData' => ['can_manage' => $canManageSettings],
                    'attributes' => [
                        [
                            'pattern' => '/^\./',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => true,
                        ],
                    ],
                    'copyFrom' => $canManageSettings,
                    'copyTo' => $canManageSettings,
                    'upload' => $canManageSettings,
                    'mkdir' => $canManageSettings,
                    'mkfile' => false,
                    'rename' => $canManageSettings,
                    'archive' => $canManageSettings,
                    'extract' => $canManageSettings,
                    'rm' => $canManageSettings,
                    'paste' => $canManageSettings,
                    'duplicate' => $canManageSettings,
                    'imgLib' => 'gd',
                    'tmbPath' => '.thumbs',
                    'tmbURL' => $uploadsUrl . '.thumbs/',
                    'utf8fix' => true,
                ],
            ],
        ];

        $connector = new \elFinderConnector(new \elFinder($options));
        $connector->run();
        exit;
    }

    public static function accessControl($attr, $path, $data, $volume, $isDir, $relpath): ?bool
    {
        $basename = basename((string) $path);
        if ($basename !== '' && str_starts_with($basename, '.') && strlen((string) $relpath) !== 1) {
            return !($attr === 'read' || $attr === 'write');
        }

        $canManage = is_array($data) ? (bool) ($data['can_manage'] ?? false) : false;
        if ($canManage) {
            return null;
        }

        return match ($attr) {
            'write' => false,
            'locked' => true,
            default => null,
        };
    }
}
