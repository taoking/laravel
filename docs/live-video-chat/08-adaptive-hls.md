# Phase 8: Adaptive Bitrate HLS

本阶段把原来的单码率 HLS 扩展为多码率 HLS。当前只生成 `360p` 和 `720p`，不做 `1080p`，保持学习项目的处理时间和复杂度可控。

## 1. 本阶段实现了什么

- 新增 `video_renditions` 表，记录每个清晰度的处理状态。
- 新增 `VideoRendition` 模型。
- 新增 `GenerateAdaptiveHlsForVideo` 队列 Job。
- 新增 `HlsMasterPlaylist` 支持类，由 Laravel 生成 `master.m3u8`。
- 上传视频处理完成后，自动派发 adaptive HLS Job。
- 视频详情页和房间页优先播放 `master.m3u8`。
- HLS 路由支持 master playlist、rendition playlist 和 segment。
- 页面展示 `360p`、`720p` 的处理状态。

## 2. 单码率 HLS 和多码率 HLS 的区别

单码率 HLS：

```text
index.m3u8
segment_000.ts
segment_001.ts
```

多码率 HLS：

```text
master.m3u8
360p/index.m3u8
360p/segment_000.ts
720p/index.m3u8
720p/segment_000.ts
```

单码率 HLS 只有一套分片。多码率 HLS 有一个 master playlist，下面挂多套不同清晰度的 media playlist，播放器可以根据网络和设备能力选择合适清晰度。

## 3. master.m3u8 的作用

`master.m3u8` 不直接列出视频分片，而是列出每个清晰度的 playlist：

```m3u8
#EXTM3U
#EXT-X-VERSION:3
#EXT-X-STREAM-INF:BANDWIDTH=896000,RESOLUTION=640x360
360p/index.m3u8
#EXT-X-STREAM-INF:BANDWIDTH=2928000,RESOLUTION=1280x720
720p/index.m3u8
```

当前 `BANDWIDTH` 使用视频码率 + 音频码率做粗略换算。这对学习和本地播放足够，生产环境可以根据实际编码结果进一步校准。

## 4. video_renditions 表设计

核心字段：

- `video_id`：所属视频。
- `label`：清晰度标签，例如 `360p`、`720p`。
- `width` / `height`：目标分辨率。
- `video_bitrate` / `audio_bitrate`：目标码率。
- `disk`：HLS 文件所在 disk，默认 `public`。
- `directory_path`：该清晰度输出目录。
- `playlist_path`：该清晰度 `index.m3u8` 路径。
- `status`：`pending`、`processing`、`ready`、`failed`。
- `failure_reason`：失败原因，已截断，避免 FFmpeg 输出过长。
- `processed_at`：处理完成时间。

索引：

- `video_id`
- `status`
- `video_id + label` 唯一索引

## 5. 转码目录结构

当前配置：

```php
'hls_disk' => 'public',
'hls_directory' => 'videos/hls',
'hls_master_playlist_name' => 'master.m3u8',
```

输出目录：

```text
storage/app/public/videos/hls/{video_id}/
master.m3u8
360p/
  index.m3u8
  segment_000.ts
  segment_001.ts
720p/
  index.m3u8
  segment_000.ts
  segment_001.ts
```

数据库保存的是相对路径，例如：

```text
videos/hls/15/master.m3u8
videos/hls/15/360p/index.m3u8
```

## 6. FFmpeg 如何生成 360p / 720p

当前采用每个清晰度单独执行一次 FFmpeg 的简单方案：

```bash
ffmpeg \
  -y \
  -i /absolute/path/input.mp4 \
  -vf scale=-2:360 \
  -c:v libx264 \
  -b:v 800k \
  -c:a aac \
  -b:a 96k \
  -preset veryfast \
  -f hls \
  -hls_time 6 \
  -hls_playlist_type vod \
  -hls_segment_filename /absolute/path/360p/segment_%03d.ts \
  /absolute/path/360p/index.m3u8
```

`720p` 只把 `scale=-2:720`、视频码率和音频码率换成配置值。

代码使用 Symfony Process 的数组参数形式，不拼接 shell 字符串，降低命令注入风险。

## 7. 为什么学习阶段不做 1080p

`1080p` 转码时间更长，CPU 消耗更高，对本地开发体验不友好。学习阶段先理解 adaptive HLS 的结构、状态表、master playlist 和播放器加载方式即可。后续可以在 `config/video.php` 中增加 `1080p` 配置。

## 8. 为什么默认不向上放大

配置：

```php
'hls_allow_upscale' => false,
```

如果源视频只有 `480p`，默认只生成 `360p`，不生成 `720p`。向上放大不会增加真实画质，只会增加文件大小和转码时间。

如果确实想测试，可改为：

```dotenv
VIDEO_HLS_ALLOW_UPSCALE=true
```

## 9. 播放器如何加载 master.m3u8

视频详情页仍然只使用一个 `<video>` 和原来的 `hls.js` 初始化逻辑。

后端判断：

1. 如果 `videos.hls_playlist_path` 指向 `master.m3u8`，播放 adaptive HLS。
2. 如果 master 不存在但旧 `index.m3u8` 存在，fallback 到单码率 HLS。
3. 如果 HLS 不存在，fallback 到原 MP4。

前端不需要知道内部有几个清晰度，`hls.js` 会读取 master playlist。

## 10. 如何启动队列

```bash
php artisan queue:work --queue=default --tries=1 --timeout=0
```

本地大视频转码时间可能很长，`--timeout=0` 可以避免 worker 因超时杀掉 Job。学习阶段推荐一次只处理一个视频。

## 11. 如何手动验收

1. 启动 Docker 中间件：

   ```bash
   docker compose up -d mysql redis
   ```

2. 启动 Laravel、Vite、队列：

   ```bash
   php artisan serve
   npm run dev
   php artisan queue:work --queue=default --tries=1 --timeout=0
   ```

3. 上传一个测试视频。
4. 等待队列处理完成。
5. 查看文件：

   ```bash
   ls -R storage/app/public/videos/hls/{video_id}
   ```

6. 确认存在：

   ```text
   master.m3u8
   360p/index.m3u8
   720p/index.m3u8
   ```

7. 打开 `/videos/{video}`。
8. 确认页面显示 playback source 为 adaptive HLS。
9. 确认页面显示 `360p`、`720p` 的状态。
10. 创建直播房间并绑定该视频，确认房间页也可以播放。

## 12. 常见问题

### 转码慢

多码率 HLS 会对每个清晰度分别转码，时间一定比单码率更长。学习阶段建议用 1 到 3 分钟的小视频验证。

### 720p 没生成

检查源视频高度。如果源视频低于 720 且 `VIDEO_HLS_ALLOW_UPSCALE=false`，系统会跳过 `720p`。

### master.m3u8 404

检查：

```bash
php artisan queue:work --queue=default --tries=1 --timeout=0
ls storage/app/public/videos/hls/{video_id}/master.m3u8
```

也要确认 `videos.hls_status = ready`。

### storage:link 没执行

当前 HLS 由 Laravel 路由读取，即使没有 storage link 也能通过路由播放。但封面和原始 public 文件需要：

```bash
php artisan storage:link
```

### 队列没启动

视频会停留在 `pending` 或 `processing`。启动：

```bash
php artisan queue:work --queue=default --tries=1 --timeout=0
```

### ffmpeg command not found

确认：

```bash
ffmpeg -version
ffprobe -version
```

如果命令不在 PATH，配置绝对路径：

```dotenv
FFMPEG_PATH=/opt/homebrew/bin/ffmpeg
FFPROBE_PATH=/opt/homebrew/bin/ffprobe
```

