<?php

namespace Maxkhim\UniqueFileStorage\Providers;


use Illuminate\Filesystem\Filesystem;
use Maxkhim\UniqueFileStorage\Commands\CheckUniqueFileStorageCommand;
use Maxkhim\UniqueFileStorage\Contracts\FileStorageInterface;
use Maxkhim\UniqueFileStorage\Services\FileStorageService;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * ServiceProvider для пакета unique-file-storage.
 *
 * Наследуется от PackageServiceProvider из Laravel Package Tools,
 * обеспечивает настройку, регистрацию ресурсов, миграций, команд и т.д.
 */
class UniqueFileStorageServiceProvider extends PackageServiceProvider
{
    /**
     * Имя пакета.
     */
    public static string $name = 'unique-file-storage';

    /**
     * Пространство имен для шаблонов.
     */
    public static string $viewNamespace = 'unique-file-storage';

    /**
     * Настраивает подключение к базе данных, если оно не определено.
     *
     * Использует конфигурацию из `unique-file-storage.db`.
     */
    protected function configureDBConnection(): void
    {

        if (is_null(config('database.connections.unique_file_storage'))) {
            config(
                [
                    'database.connections.unique_file_storage' => [
                        'driver' => config("unique-file-storage.db.driver"),
                        'host' => config("unique-file-storage.db.host"),
                        'port' => config("unique-file-storage.db.port"),
                        'database' => config("unique-file-storage.db.database"),
                        'username' => config("unique-file-storage.db.username"),
                        'password' => config("unique-file-storage.db.password"),
                        'charset' => 'utf8',
                    ]
                ]
            );
        }
    }
/*
    public function register()
    {
        $this->app->singleton(FileStorageInterface::class, FileStorageService::class);
        $this->app->bind('unique-file-storage', FileStorageInterface::class);
    }
*/
    /**
     * Настраивает пакет с помощью Laravel Package Tools.
     *
     * Регистрирует команды, маршруты, миграции, конфигурации, языковые файлы, представления и т.д.
     */
    public function configurePackage(Package $package): void
    {
        // Более подробная информация: https://github.com/spatie/laravel-package-tools

        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasRoutes([
                'web',
                'api'
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    //->askToStarRepoOnGitHub(':vendor_slug/:package_slug')
                ;
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile($configFileName);
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    /**
     * Выполняется после регистрации пакета.
     * Пока не содержит реализации.
     */
    public function packageRegistered(): void {
        $this->app->singleton(FileStorageInterface::class, FileStorageService::class);
        $this->app->bind('unique-file-storage', FileStorageInterface::class);
        $this->configureDBConnection();
    }

    /**
     * Выполняется после загрузки пакета.
     *
     * Регистрирует активы, иконки, публикует stubs и обрабатывает тестирование.
     */
    public function packageBooted(): void
    {
        // Обработка stubs
        if (app()->runningInConsole()) {
            if (is_dir(__DIR__ . '/../../stubs')) {
                foreach (app(Filesystem::class)->files(__DIR__ . '/../../stubs/') as $file) {
                    $this->publishes([
                        $file->getRealPath() => base_path("stubs/unique-file-storage/{$file->getFilename()}"),
                    ], 'unique-file-storage-stubs');
                }
            }
        }

        // Тестирование
        //Testable::mixin(new Testsunique-file-storage);
    }

    /**
     * Возвращает имя пакета npm или пакета, отвечающего за активы.
     *
     * @return string|null
     */
    protected function getAssetPackageName(): ?string
    {
        return 'maxkhim/unique-file-storage';
    }

    /**
     * Возвращает массив команд, связанных с этим пакетом.
     *
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            CheckUniqueFileStorageCommand::class,
        ];
    }

    /**
     * Возвращает массив маршрутов пакета (не используется в данном случае).
     *
     * @return array<string>
     */
    /*protected function getRoutes(): array
    {
        return [
        ];
    }*/


    /**
     * Возвращает массив миграций пакета.
     *
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_unique_file_storage_table',
        ];
    }
}