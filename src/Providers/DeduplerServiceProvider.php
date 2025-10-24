<?php

namespace Maxkhim\Dedupler\Providers;

use Illuminate\Filesystem\Filesystem;
use Maxkhim\Dedupler\Commands\CheckDeduplerStorageCommand;
use Maxkhim\Dedupler\Commands\CleanupFilesCommand;
use Maxkhim\Dedupler\Commands\CreateDummyFilesCommand;
use Maxkhim\Dedupler\Commands\DeduplerInstallCommand;
use Maxkhim\Dedupler\Commands\FileStorageStatsCommand;
use Maxkhim\Dedupler\Commands\MigrateLegacyFilesCommand;
use Maxkhim\Dedupler\Commands\RollbackLegacyFilesCommand;
use Maxkhim\Dedupler\Contracts\FileStorageInterface;
use Maxkhim\Dedupler\Services\DeduplerService;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * ServiceProvider для пакета Dedupler.
 *
 * Наследуется от PackageServiceProvider из Laravel Package Tools,
 * обеспечивает настройку, регистрацию ресурсов, миграций, команд и т.д.
 */
class DeduplerServiceProvider extends PackageServiceProvider
{
    /**
     * Имя пакета.
     */
    public static string $name = 'laravel-storage-dedupler';
    public static string $vendor = 'maxkhim';

    /**
     * Пространство имен для шаблонов.
     */
    public static string $viewNamespace = 'dedupler';


    public static function getMigrationPath(): string
    {
        return __DIR__ . '/../../database/migrations';
    }

    /**
     * Настраивает подключение к базе данных, если оно не определено.
     *
     * Использует конфигурацию из `dedupler.db`.
     */
    protected function configureDBConnection(): void
    {

        if (is_null(config('database.connections.dedupler'))) {
            config(
                [
                    'database.connections.dedupler' => [
                        'driver' => config("dedupler.db.driver"),
                        'host' => config("dedupler.db.host"),
                        'port' => config("dedupler.db.port"),
                        'database' => config("dedupler.db.database"),
                        'username' => config("dedupler.db.username"),
                        'password' => config("dedupler.db.password"),
                        'charset' => 'utf8',
                    ]
                ]
            );
        }
    }

    protected function configureDeduplerDisk(): void
    {
        if (is_null(config('filesystems.disks.deduplicated'))) {
            config(
                [
                    'filesystems.disks.deduplicated' => config("dedupler.disks.deduplicated"),
                ]
            );
        }
    }

    /**
     * Настраивает пакет с помощью Laravel Package Tools.
     *
     * Регистрирует команды, маршруты, миграции, конфигурации, языковые файлы, представления и т.д.
     */
    public function configurePackage(Package $package): void
    {
        // Более подробная информация: https://github.com/spatie/laravel-package-tools

        $this->mergeConfigFrom(__DIR__ . '/../../config/dedupler.php', "dedupler");

        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasRoutes($this->getRoutes());

        $configFileName = "dedupler";

        if (file_exists($package->basePath("/../config/dedupler.php"))) {
            $package->hasConfigFile($configFileName);
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
     */
    public function packageRegistered(): void
    {
        $this->loadMigrationsFrom(self::getMigrationPath());
        $this->app->singleton(FileStorageInterface::class, DeduplerService::class);
        $this->app->bind('dedupler', FileStorageInterface::class);
        $this->configureDBConnection();
        $this->configureDeduplerDisk();
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
                        $file->getRealPath() => base_path("stubs/dedupler/{$file->getFilename()}"),
                    ], 'dedupler-stubs');
                }
            }
        }
        // Тестирование
        //Testable::mixin(new Testsdedupler);
    }

    /**
     * Возвращает массив команд, связанных с этим пакетом.
     *
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            CheckDeduplerStorageCommand::class,
            CleanupFilesCommand::class,
            FileStorageStatsCommand::class,
            DeduplerInstallCommand::class,
            //CreateDummyFilesCommand::class,
            MigrateLegacyFilesCommand::class,
            RollbackLegacyFilesCommand::class,
        ];
    }

    /**
     * Возвращает массив маршрутов пакета
     *
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        $routes = [];
        if (config("dedupler.api.enabled")) {
            $routes[] = __DIR__ . '/../../routes/api.php';
        }
        return $routes;
    }
}
