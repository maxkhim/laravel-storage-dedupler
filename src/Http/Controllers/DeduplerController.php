<?php

namespace Maxkhim\Dedupler\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maxkhim\Dedupler\Facades\Dedupler;
use Maxkhim\Dedupler\Helpers\FormatingHelper;
use Maxkhim\Dedupler\Models\UniqueFile;
use Maxkhim\Dedupler\Models\UniqueFileToModel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeduplerController extends Controller
{
    /**
     * Сохранить один файл
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:' . (config('dedupler.max_file_size') ?? 102400),
            'model_type' => 'sometimes|string',
            'model_id' => 'sometimes|string',
            'disk' => 'sometimes|string',
        ]);

        try {
            $file = $request->file('file');

            // Если указана модель для привязки
            $model = null;
            if ($request->has(['model_type', 'model_id'])) {
                $modelClass = $request->input('model_type');
                $modelId = $request->input('model_id');

                if (class_exists($modelClass)) {
                    $model = $modelClass::find($modelId);
                    if (!$model) {
                        return response()->json([
                            'error' => 'Model not found'
                        ], 404);
                    }
                }
            }

            // Сохраняем файл
            if ($model) {
                $fileRelation = Dedupler::storeFromUploadedFile(
                    $file,
                    $model,
                    [
                        'disk' => $request->input('disk'),
                        'pivot' => [
                            'status' => 'completed',
                            'original_name' => $file->getClientOriginalName()
                        ]
                    ]
                );
            } else {
                // Создаем временную модель для хранения файла
                $temporaryModel = new class extends \Illuminate\Database\Eloquent\Model {
                    protected $table = 'temporary_file_holder';
                };
                $temporaryModel->id = uniqid('temp_', true);

                $fileRelation = Dedupler::storeFromUploadedFile(
                    $file,
                    $temporaryModel,
                    [
                        'disk' => $request->input('disk'),
                        'pivot' => [
                            'status' => 'completed',
                            'original_name' => $file->getClientOriginalName()
                        ]
                    ]
                );
            }

            if (!$fileRelation) {
                return response()->json([
                    'error' => 'Failed to store file'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatFileResponse($fileRelation->file, $fileRelation)
            ], 201);
        } catch (\Exception $e) {
            Log::error('File storage error: ' . $e->getMessage());

            return response()->json([
                'error' => 'File storage failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Сохранить несколько файлов
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeMultiple(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|max:' . (config('dedupler.max_file_size') ?? 102400),
            'model_type' => 'sometimes|string',
            'model_id' => 'sometimes|string',
            'disk' => 'sometimes|string',
        ]);

        try {
            $files = $request->file('files');
            $results = [];

            // Если указана модель для привязки
            $model = null;
            if ($request->has(['model_type', 'model_id'])) {
                $modelClass = $request->input('model_type');
                $modelId = $request->input('model_id');

                if (class_exists($modelClass)) {
                    $model = $modelClass::find($modelId);
                    if (!$model) {
                        return response()->json([
                            'error' => 'Model not found'
                        ], 404);
                    }
                }
            }

            foreach ($files as $key => $file) {
                try {
                    if ($model) {
                        $fileRelation = Dedupler::storeFromUploadedFile(
                            $file,
                            $model,
                            [
                                'disk' => $request->input('disk'),
                                'pivot' => [
                                    'status' => 'completed',
                                    'original_name' => $file->getClientOriginalName()
                                ]
                            ]
                        );
                    } else {
                        $temporaryModel = new class extends \Illuminate\Database\Eloquent\Model {
                            protected $table = 'temporary_file_holder';
                        };
                        $temporaryModel->id = uniqid('temp_' . $key . '_', true);

                        $fileRelation = Dedupler::storeFromUploadedFile(
                            $file,
                            $temporaryModel,
                            [
                                'disk' => $request->input('disk'),
                                'pivot' => [
                                    'status' => 'completed',
                                    'original_name' => $file->getClientOriginalName()
                                ]
                            ]
                        );
                    }

                    if ($fileRelation) {
                        $results[] = $this->formatFileResponse($fileRelation->file, $fileRelation);
                    } else {
                        $results[] = [
                            'error' => 'Failed to store file',
                            'original_name' => $file->getClientOriginalName()
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error("File storage error for {$file->getClientOriginalName()}: " . $e->getMessage());
                    $results[] = [
                        'error' => 'Storage failed',
                        'original_name' => $file->getClientOriginalName(),
                        'message' => config('app.debug') ? $e->getMessage() : null
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $results
            ], 201);
        } catch (\Exception $e) {
            Log::error('Multiple file storage error: ' . $e->getMessage());

            return response()->json([
                'error' => 'Multiple file storage failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Получить информацию о файле по хэшу
     *
     * @param string $hash
     * @return JsonResponse
     */
    public function show(string $hash): JsonResponse
    {
        try {
            $file = UniqueFile::query()->find($hash);

            if (!$file) {
                return response()->json([
                    'error' => 'File not found'
                ], 404);
            }

            // Получаем информацию о связанных моделях
            $relations = UniqueFileToModel::query()->where('sha1_hash', $hash)->get();
            $modelCounts = $relations->groupBy('deduplable_type')->map->count();

            $fileInfo = [
                'hash' => $file->id,
                'sha1_hash' => $file->sha1_hash,
                'md5_hash' => $file->md5_hash,
                'exists' => Storage::disk($file->disk)->exists($file->path),
                'filename' => $file->filename,
                'original_name' => $file->original_name,
                'path' => $file->path,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'size_human' => $this->formatBytes($file->size),
                'disk' => $file->disk,
                'status' => $file->status,
                'url' => Dedupler::getUrl($file->id),
                'created_at' => $file->created_at?->toISOString(),
                'updated_at' => $file->updated_at?->toISOString(),
                'relations_count' => $relations->count(),
                'relations_by_type' => $modelCounts,
                'relations' => $relations->map(function ($relation) {
                    return [
                        'id' => $relation->id,
                        'deduplable_type' => $relation->deduplable_type,
                        'deduplable_id' => $relation->deduplable_id,
                        'status' => $relation->status,
                        'original_name' => $relation->original_name,
                        'created_at' => $relation->created_at?->toISOString(),
                    ];
                })->take(10) // Ограничиваем вывод для избежания перегрузки
            ];

            return response()->json([
                'success' => true,
                'data' => $fileInfo
            ]);
        } catch (\Exception $e) {
            Log::error('File info error: ' . $e->getMessage());

            return response()->json([
                'error' => 'Failed to get file info',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Скачать файл по хэшу
     *
     * @param string $hash
     * @return StreamedResponse|JsonResponse
     */
    public function download(string $hash): StreamedResponse|JsonResponse
    {
        try {
            $file = UniqueFile::query()->find($hash);

            if (!$file) {
                return response()->json([
                    'error' => 'File not found'
                ], 404);
            }

            // Проверяем существование файла в хранилище
            if (!Storage::disk($file->disk)->exists($file->path)) {
                return response()->json([
                    'error' => 'File not found in storage'
                ], 404);
            }

            // Для больших файлов используем потоковую передачу
            $storageDisk = Storage::disk($file->disk);
            $fileSize = $file->size;

            // Определяем имя файла для скачивания
            $downloadName = $file->original_name ?: $file->filename;

            // Потоковая отдача для больших файлов
            return $storageDisk->download(
                $file->path,
                $downloadName,
                [
                    'Content-Type' => $file->mime_type,
                    'Content-Length' => $fileSize,
                    'Content-Disposition' => "attachment; filename=\"{$downloadName}\"",
                ]
            );
        } catch (\Exception $e) {
            Log::error('File download error: ' . $e->getMessage());

            return response()->json([
                'error' => 'Failed to download file',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Прямая отдача файла (для изображений и т.д.)
     *
     * @param string $hash
     * @return Response|JsonResponse|StreamedResponse
     */
    public function stream(string $hash): Response|JsonResponse|StreamedResponse
    {
        try {
            $file = UniqueFile::query()->find($hash);

            if (!$file) {
                return response()->json([
                    'error' => 'File not found'
                ], 404);
            }

            if (!Storage::disk($file->disk)->exists($file->path)) {
                return response()->json([
                    'error' => 'File not found in storage'
                ], 404);
            }

            $storageDisk = Storage::disk($file->disk);
            $fileSize = $file->size;
            $mimeType = $file->mime_type;

            // Получаем поток файла
            $stream = $storageDisk->readStream($file->path);

            // Устанавливаем заголовки для кэширования
            $headers = [
                'Content-Type' => $mimeType,
                'Content-Length' => $fileSize,
                'Cache-Control' => 'public, max-age=31536000', // Кэшируем на год
                'ETag' => $hash,
            ];

            return response()->stream(
                function () use ($stream) {
                    fpassthru($stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                },
                200,
                $headers
            );
        } catch (\Exception $e) {
            Log::error('File stream error: ' . $e->getMessage());

            return response()->json([
                'error' => 'Failed to stream file',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Форматирование ответа с информацией о файле
     */
    private function formatFileResponse(UniqueFile $file, $relation = null): array
    {
        return [
            'hash' => $file->id,
            'sha1_hash' => $file->sha1_hash,
            'md5_hash' => $file->md5_hash,
            'filename' => $file->filename,
            'original_name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'size_human' => $this->formatBytes($file->size),
            'disk' => $file->disk,
            'status' => $file->status,
            'url' => Dedupler::getUrl($file->id),
            'download_url' => route('unique-files.download', ['hash' => $file->id]),
            'stream_url' => route('unique-files.stream', ['hash' => $file->id]),
            'created_at' => $file->created_at?->toISOString(),
            'relation_id' => $relation?->id,
            'relation_status' => $relation?->status,
        ];
    }

    /**
     * Форматирование размера файла в читаемый вид
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        return FormatingHelper::formatBytes($bytes, $precision);
    }
}
