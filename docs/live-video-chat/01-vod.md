# Phase 1：视频点播 VOD

本阶段只实现“上传视频 + 保存原文件 + 数据库记录 + 页面播放 MP4/MOV/WebM”。不做 FFmpeg、不做 HLS、不做直播房间、不做 Reverb 聊天。

## 1. 本阶段实现了什么

- 使用 Docker Compose 准备本地 MySQL 8.0 和 Redis 7 中间件。
- Laravel 应用仍在本机运行，不容器化 PHP 应用。
- 新增 `videos` 表保存视频元数据。
- 新增 `Video` 模型。
- 新增 `StoreVideoRequest` 负责上传校验。
- 新增 `VideoController` 负责列表、上传页、保存和详情页。
- 新增 Blade 页面：
  - `/videos`
  - `/videos/create`
  - `/videos/{video}`
- 使用 Laravel public disk 保存原始视频文件。
- 使用 HTML5 `<video controls preload="metadata">` 直接播放原文件。
- 新增 Feature Test 覆盖页面访问、上传、入库、文件保存和详情页。

## 2. Docker 中间件说明

`docker-compose.yml` 提供两个服务：

- `mysql`：MySQL 8.0，本阶段主数据库。
- `redis`：Redis 7 Alpine，Phase 1 不强依赖，先为后续队列、缓存和实时聊天阶段预留。

MySQL 宿主机端口使用 `33061`，是为了避免和本机可能已有的 MySQL `3306` 冲突。

Redis 宿主机端口使用 `63791`，是为了避免和本机可能已有的 Redis `6379` 冲突。

启动中间件：

```bash
docker compose up -d
docker compose ps
```

停止中间件：

```bash
docker compose down
```

如果要删除本地数据卷，重新初始化数据库：

```bash
docker compose down -v
```

查看 MySQL 日志：

```bash
docker compose logs -f mysql
```

## 3. `.env` 配置

`.env.example` 已写入本地 Docker MySQL / Redis 推荐配置：

```dotenv
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=33061
DB_DATABASE=laravel_live
DB_USERNAME=laravel
DB_PASSWORD=laravel

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=63791

FILESYSTEM_DISK=public
```

如果本地已经有 `.env`，不要直接覆盖，手动对照 `.env.example` 修改即可。

Phase 1 使用 `database` queue，不使用 Redis queue。Redis 只是预留。

## 4. 涉及的 Laravel 知识点

- Migration：创建 `videos` 表，并预留后续视频处理字段。
- Model：`Video` 模型负责可批量写入字段、类型转换和辅助展示方法。
- Controller：`VideoController` 负责列表、表单、保存和详情页。
- FormRequest：`StoreVideoRequest` 负责上传参数校验。
- Storage public disk：原文件保存到 `storage/app/public`，通过 `public/storage` 访问。
- Blade：用简单 Blade 页面完成 VOD 的基本交互。
- Feature Test：用 `Storage::fake('public')` 测试上传和文件保存，不污染真实磁盘。

## 5. 路由列表

| Method | URI | Name | 说明 |
| --- | --- | --- | --- |
| GET | `/videos` | `videos.index` | 视频列表 |
| GET | `/videos/create` | `videos.create` | 上传表单 |
| POST | `/videos` | `videos.store` | 保存视频 |
| GET | `/videos/{video}` | `videos.show` | 视频详情与播放 |

查看路由：

```bash
php artisan route:list --name=videos
```

## 6. `videos` 表字段说明

| 字段 | 说明 |
| --- | --- |
| `id` | 主键 |
| `user_id` | 上传用户，当前阶段可为空 |
| `title` | 视频标题 |
| `description` | 视频简介 |
| `original_disk` | 原文件磁盘，默认 `public` |
| `original_path` | 原文件在 public disk 中的相对路径 |
| `original_filename` | 用户上传时的原始文件名 |
| `mime_type` | 上传文件 MIME 类型 |
| `size_bytes` | 文件大小，单位 byte |
| `duration_seconds` | 预留给 Phase 2 的视频时长 |
| `width` | 预留给 Phase 2 的视频宽度 |
| `height` | 预留给 Phase 2 的视频高度 |
| `video_codec` | 预留给 Phase 2 的视频编码 |
| `audio_codec` | 预留给 Phase 2 的音频编码 |
| `cover_path` | 预留给 Phase 2 的封面路径 |
| `hls_path` | 预留给 Phase 3 的 HLS 目录 |
| `hls_playlist_path` | 预留给 Phase 3 的 HLS 播放列表 |
| `status` | 处理状态，本阶段默认 `pending` |
| `failure_reason` | 预留给后台处理失败原因 |
| `processed_at` | 预留给后台处理完成时间 |
| `created_at` / `updated_at` | 创建和更新时间 |

索引：

- `status`
- `user_id`
- `created_at`

## 7. 文件保存路径

上传文件保存到 public disk：

```text
storage/app/public/videos/originals
```

浏览器访问需要先创建 public storage 软链接：

```bash
php artisan storage:link
```

软链接目标：

```text
public/storage -> storage/app/public
```

数据库中保存的是 public disk 相对路径，例如：

```text
videos/originals/abc123.mp4
```

## 8. 为什么本阶段只做直接播放

Phase 1 的学习重点是 Laravel 的上传链路：

- 表单上传
- 参数校验
- Storage 保存文件
- 数据库存元数据
- Blade 展示
- HTML5 video 播放

FFmpeg、HLS、转码队列会引入更多概念。先把最小 VOD 链路跑通，再进入 Phase 2 做 `ffprobe` / `ffmpeg`，学习曲线会平很多。

## 9. 如何启动

如果 `.env` 不存在：

```bash
cp .env.example .env
php artisan key:generate
```

如果 `.env` 已存在，请手动对照 `.env.example` 调整，不要覆盖已有文件。

启动 Docker 中间件：

```bash
docker compose up -d
docker compose ps
```

执行迁移和 storage link：

```bash
php artisan migrate
php artisan storage:link
```

启动 Laravel：

```bash
composer run dev
```

访问：

```text
http://127.0.0.1:8000/videos
```

## 10. 如何测试

运行自动化测试：

```bash
php artisan test
```

测试环境使用 `phpunit.xml` 中的 SQLite in-memory 配置，不依赖 Docker MySQL。这样可以避免测试污染本地开发数据库。

手动验收：

1. 打开 `/videos`。
2. 点击上传入口进入 `/videos/create`。
3. 上传一个 `mp4`、`mov` 或 `webm` 文件。
4. 上传成功后跳转到 `/videos/{video}`。
5. 确认页面显示标题、简介、状态、原始文件名、MIME 类型、大小、创建时间。
6. 确认播放器可以播放原始文件。
7. 确认文件存在于 `storage/app/public/videos/originals`。

## 11. 常见问题

### Docker MySQL 连接失败

先看容器是否运行：

```bash
docker compose ps
```

再看日志：

```bash
docker compose logs -f mysql
```

确认 `.env`：

```dotenv
DB_HOST=127.0.0.1
DB_PORT=33061
DB_DATABASE=laravel_live
DB_USERNAME=laravel
DB_PASSWORD=laravel
```

### `33061` 端口被占用

修改 `docker-compose.yml` 的宿主机端口，例如：

```yaml
ports:
  - "33062:3306"
```

同时修改 `.env`：

```dotenv
DB_PORT=33062
```

### 上传文件大小限制

Laravel 校验限制是 `max:512000`，单位是 KB，也就是 500 MB。

如果 PHP 自身限制更小，需要调整 `php.ini`：

```ini
upload_max_filesize = 500M
post_max_size = 520M
memory_limit = 1024M
max_input_time = 300
max_execution_time = 300
```

本地临时验证时，也可以直接用 CLI 参数启动开发服务器：

```bash
php -d upload_max_filesize=500M \
    -d post_max_size=520M \
    -d memory_limit=1024M \
    -d max_input_time=300 \
    -d max_execution_time=300 \
    artisan serve
```

### 视频 404

通常是没执行：

```bash
php artisan storage:link
```

或者文件不在 `storage/app/public/videos/originals`。

### MIME 类型校验失败

当前允许：

- `mp4`
- `mov`
- `webm`

如果文件扩展名正确但仍失败，可能是测试文件不是真实视频，或浏览器/系统识别出的上传类型异常。学习阶段建议先用小体积真实视频验证。

### SQLite / MySQL / Docker MySQL 切换

- 本地开发：推荐 Docker MySQL。
- 自动化测试：推荐 SQLite in-memory。
- 临时排查：可以用 SQLite 快速验证路由和页面，但不要把它作为 Phase 1 主路径。

切换数据库后记得清理配置缓存：

```bash
php artisan config:clear
```

## 12. 下一阶段如何接入 FFmpeg 队列处理

Phase 2 建议新增：

- `app/Jobs/ProcessUploadedVideo.php`
- `app/Support/Video/VideoProbe.php`
- `app/Support/Video/VideoThumbnailer.php`
- `config/video.php`

上传成功后不在 HTTP 请求里处理视频，而是 dispatch 一个 Job：

```php
ProcessUploadedVideo::dispatch($video);
```

Job 中再调用 `ffprobe` 获取时长、分辨率、编码信息，并调用 `ffmpeg` 生成封面，把 `videos.status` 从 `pending` 更新到 `processing`、`ready` 或 `failed`。
