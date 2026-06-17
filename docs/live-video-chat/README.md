# Live Video Chat Learning Project

这是一个基于 Laravel 13 的学习型视频直播项目，用来串联视频上传、异步处理、Adaptive HLS 播放、直播房间、MediaMTX 真实推流接入、Laravel Reverb 在线聊天，以及真实直播架构设计。

项目目标不是一次性做成生产级系统，而是保留清晰、可运行、可阅读、可逐步扩展的代码结构。

## 1. 项目简介

当前已经实现：

- 视频上传和 MP4 播放。
- FFmpeg / ffprobe 队列异步处理。
- 视频封面和 metadata 提取。
- 单码率 HLS fallback。
- 360p / 720p 多码率 Adaptive HLS。
- 直播房间创建、编辑、状态切换。
- 绑定视频模拟直播。
- 外部 HLS URL 播放。
- MediaMTX + OBS 真实直播学习链路。
- Laravel Reverb + Echo 实时聊天。
- 真实直播架构预留说明。

核心页面：

- `/videos`：视频列表。
- `/videos/create`：上传视频。
- `/videos/{video}`：视频详情、播放、rendition 状态。
- `/rooms`：直播房间列表。
- `/rooms/create`：创建房间。
- `/rooms/{room}`：房间观看和聊天。

## 2. 最新启动顺序

推荐按这个顺序启动：

1. 启动 Docker 中间件。
2. 准备 Laravel `.env`、APP_KEY、数据库迁移和 storage link。
3. 启动 queue worker。
4. 启动 Reverb。
5. 启动 Vite。
6. 启动 Laravel HTTP 服务。
7. 浏览器手动验收上传、转码、HLS、房间、MediaMTX 和聊天。

完整命令：

```bash
docker compose up -d
docker compose ps

php artisan migrate
php artisan storage:link

php artisan queue:work --queue=default --tries=1 --timeout=0
php artisan reverb:start
npm run dev
php artisan serve
```

`queue:work`、`reverb:start`、`npm run dev`、`php artisan serve` 建议分别放在不同终端中运行。

## 3. Docker 中间件

Laravel 应用本身仍在本机运行，中间件优先使用 Docker Compose。

```bash
docker compose up -d
docker compose ps
```

当前服务：

- MySQL：`127.0.0.1:33061`
- Redis：`127.0.0.1:63791`
- MediaMTX RTMP：`127.0.0.1:1935`
- MediaMTX HLS：`http://127.0.0.1:8888`
- MediaMTX WebRTC 预留：`127.0.0.1:8889`
- MediaMTX RTSP 预留：`127.0.0.1:8554`

Phase 1 只需要 MySQL；Redis 和 MediaMTX 是后续阶段使用或预留。

## 4. Laravel 准备

首次运行：

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm install
```

关键配置：

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=33061
DB_DATABASE=laravel_live
DB_USERNAME=laravel
DB_PASSWORD=laravel

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public

FFMPEG_PATH=ffmpeg
FFPROBE_PATH=ffprobe
VIDEO_PROCESS_TIMEOUT=300
VIDEO_HLS_DISK=public
VIDEO_HLS_DIRECTORY=videos/hls
VIDEO_HLS_SEGMENT_TIME=6
VIDEO_HLS_ALLOW_UPSCALE=false
VIDEO_HLS_MASTER_PLAYLIST_NAME=master.m3u8

LIVE_MEDIA_SERVER=mediamtx
MEDIAMTX_RTMP_BASE_URL=rtmp://127.0.0.1:1935/live
MEDIAMTX_HLS_BASE_URL=http://127.0.0.1:8888/live
MEDIAMTX_DEFAULT_STREAM_KEY=test
```

## 5. Laravel HTTP 服务

小文件测试可以直接使用：

```bash
php artisan serve
```

如果要上传接近 500MB 的视频，推荐使用下面的方式启动 PHP 内置服务器，确保上传限制真正生效：

```bash
cd public
php -d upload_max_filesize=500M \
    -d post_max_size=520M \
    -d memory_limit=1024M \
    -d max_input_time=300 \
    -d max_execution_time=300 \
    -S 127.0.0.1:8000 \
    ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

说明：`php -d ... artisan serve` 在部分环境中只会影响父进程，不一定影响实际处理请求的 PHP 内置服务器子进程。大文件上传时，上面这种 `public` 目录启动方式更稳定。

## 6. 队列启动

视频处理、封面生成和 Adaptive HLS 转码都依赖 Laravel Queue。

```bash
php artisan queue:work --queue=default --tries=1 --timeout=0
```

确认 Job 执行成功的方法：

- `/videos/{video}` 页面显示视频状态为 `ready`。
- HLS 状态显示为 `ready`。
- 页面显示 `360p`、`720p` rendition 状态。
- `storage/app/public/videos/thumbnails/{video_id}/thumbnail.jpg` 存在。
- `storage/app/public/videos/hls/{video_id}/master.m3u8` 存在。

## 7. Reverb 启动

聊天广播依赖 Laravel Reverb。

```bash
php artisan reverb:start
```

如果修改了 `.env` 中的 Reverb 配置，需要重启 Reverb 和 Vite。

## 8. Vite 启动

前端的 `hls.js`、Echo 初始化和聊天脚本通过 Vite 打包。

```bash
npm run dev
```

如果修改了 `.env` 中的 `VITE_REVERB_*` 配置，需要重启 `npm run dev`。

## 9. 完整手动验收流程

### A. 多码率 HLS

1. 启动 Docker 中间件：

   ```bash
   docker compose up -d
   ```

2. 启动 Laravel、队列、Vite：

   ```bash
   php artisan serve
   php artisan queue:work --queue=default --tries=1 --timeout=0
   npm run dev
   ```

3. 打开 `http://127.0.0.1:8000/videos/create`。
4. 上传一个测试视频。
5. 等待视频处理和 HLS 转码完成。
6. 查看：

   ```bash
   ls -R storage/app/public/videos/hls/{video_id}
   ```

7. 确认存在：

   ```text
   master.m3u8
   360p/index.m3u8
   720p/index.m3u8
   ```

8. 打开 `/videos/{video}`。
9. 确认播放器加载 master playlist。
10. 确认页面显示 `360p`、`720p` 状态。

### B. 直播房间和伪直播

1. 打开 `/rooms/create`。
2. `Playback type` 选择 `video`。
3. 绑定刚上传并完成 HLS 的视频。
4. 将房间状态设为 `live`。
5. 打开 `/rooms/{room}`。
6. 确认房间页播放绑定视频的 Adaptive HLS。
7. 手动切换状态为 `ended`，确认状态能更新。

### C. MediaMTX 真实直播

1. 启动 MediaMTX：

   ```bash
   docker compose up -d mediamtx
   ```

2. 打开 OBS。
3. 设置服务地址：

   ```text
   rtmp://127.0.0.1:1935/live
   ```

4. 设置串流密钥：

   ```text
   test
   ```

5. 开始推流。
6. 浏览器测试：

   ```text
   http://127.0.0.1:8888/live/test/index.m3u8
   ```

7. 打开 `/rooms/create`。
8. `Playback type` 选择 `mediamtx`。
9. `MediaMTX stream key` 填 `test`。
10. 保存房间。
11. 打开房间详情页。
12. 确认能播放直播画面。

### D. 在线聊天

1. 保持 `php artisan reverb:start`、`php artisan queue:work`、`npm run dev` 运行。
2. 用两个浏览器窗口打开同一个房间。
3. 在一个窗口发送消息。
4. 确认另一个窗口可以实时收到消息。
5. 刷新页面后，确认历史消息仍在。

## 10. 常见问题

### 上传 100MB 以上视频失败

优先检查 PHP 上传限制：

```bash
php -i | grep -E "upload_max_filesize|post_max_size|memory_limit"
```

大文件上传请使用 README 第 5 节的 `public` 目录启动命令。

### 视频一直是 pending 或 processing

通常是队列没有启动：

```bash
php artisan queue:work --queue=default --tries=1 --timeout=0
```

也可以查看数据库 `jobs` 表是否堆积。

### 720p 没生成

如果源视频高度低于 720，且 `VIDEO_HLS_ALLOW_UPSCALE=false`，系统会跳过 `720p`。这是为了避免无意义放大。

### master.m3u8 404

检查：

- `videos.hls_status` 是否为 `ready`。
- `storage/app/public/videos/hls/{video_id}/master.m3u8` 是否存在。
- 队列 worker 是否处理过 `GenerateAdaptiveHlsForVideo`。

### 视频处理失败

检查：

```bash
ffmpeg -version
ffprobe -version
```

如果命令不在 PATH 中，请在 `.env` 中配置绝对路径：

```dotenv
FFMPEG_PATH=/opt/homebrew/bin/ffmpeg
FFPROBE_PATH=/opt/homebrew/bin/ffprobe
```

### MediaMTX m3u8 404

检查：

- `docker compose ps mediamtx`
- OBS 是否正在推流。
- OBS stream key 是否和房间 `stream_key` 一致。
- 是否已经等待几秒让 MediaMTX 生成 HLS 分片。

### 聊天没有实时刷新

检查：

- `php artisan reverb:start` 是否运行。
- `npm run dev` 是否运行。
- `.env` 和前端 `VITE_REVERB_*` 是否一致。
- 浏览器控制台是否有 WebSocket 连接错误。

## 11. 各阶段文档导航

- [00-project-plan.md](./00-project-plan.md)：最初项目计划和阶段拆分。
- [01-vod.md](./01-vod.md)：视频上传和 MP4 播放。
- [02-ffmpeg-queue.md](./02-ffmpeg-queue.md)：FFmpeg / ffprobe 队列异步处理。
- [03-hls-playback.md](./03-hls-playback.md)：HLS 转码和播放。
- [04-reverb-chat.md](./04-reverb-chat.md)：直播房间和 Reverb 聊天。
- [05-live-architecture.md](./05-live-architecture.md)：伪直播和真实直播架构。
- [07-project-review.md](./07-project-review.md)：项目整体复盘、代码体检和学习整理。
- [08-adaptive-hls.md](./08-adaptive-hls.md)：多码率 HLS / Adaptive Bitrate HLS。
- [09-mediamtx-live.md](./09-mediamtx-live.md)：MediaMTX 真实直播接入。
- [10-mainstream-roadmap.md](./10-mainstream-roadmap.md)：对照主流视频/直播平台的后续功能和技术路线图。
- [11-senior-backend-interview-system-design.md](./11-senior-backend-interview-system-design.md)：面向大视频/直播网站资深后端面试的覆盖度检查和总架构设计。
