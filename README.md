# Модуль контроля уникальности загружаемых файлов (unique-file-storage)

Модуль для Laravel, который реализует загрузку файлов без дублирования

Пакет использует переменные окружения для настройки параметров. Все значения по умолчанию можно изменить, добавив соответствующие строки в файл `.env` вашего проекта.

## 📌 Доступные переменные окружения

| Переменная                          | Значение по умолчанию | Описание                                                                                   |
|-------------------------------------|-----------------------|--------------------------------------------------------------------------------------------|
| `UNIQUE_FILE_STORAGE`               | `true`                | Включение/отключение функционала пакета (значение `false` отключает проверку уникальности) |
| `UNIQUE_FILE_STORAGE_DB_HOST`       | `127.0.0.1`           | Хост базы данных для хранения информации о файлах                                          |
| `UNIQUE_FILE_STORAGE_DB_PORT`       | `3306`                | Порт базы данных                                                                           |
| `UNIQUE_FILE_STORAGE_DB_DATABASE`   | `unique_files`        | Имя базы данных                                                                            |
| `UNIQUE_FILE_STORAGE_DB_USERNAME`   | `unique_files_dbo`    | Имя пользователя БД                                                                        |
| `UNIQUE_FILE_STORAGE_DB_PASSWORD`   | `(пустая строка)`     | Пароль пользователя БД                                                                     |
| `UNIQUE_FILE_STORAGE_DB_CONNECTION` | `mariadb`             | Драйвер БД (например, `mysql`, `pgsql`, `sqlite`, `sqlsrv`)                                |

### 🧪 Пример .env

```env
UNIQUE_FILE_STORAGE=true
UNIQUE_FILE_STORAGE_DB_HOST=localhost
UNIQUE_FILE_STORAGE_DB_PORT=3306
UNIQUE_FILE_STORAGE_DB_DATABASE=my_unique_files_db
UNIQUE_FILE_STORAGE_DB_USERNAME=db_user
UNIQUE_FILE_STORAGE_DB_PASSWORD=your_password
UNIQUE_FILE_STORAGE_DB_CONNECTION=mysql
```

```
php artisan config:clear
```