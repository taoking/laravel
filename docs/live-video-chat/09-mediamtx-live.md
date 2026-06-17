# Phase 9: MediaMTX Real Live Streaming

本阶段把“伪直播房间”扩展为可以接收 OBS 推流的真实直播学习版本。第一版只使用 MediaMTX，不引入 SRS，不做 WebRTC 连麦，不做录制、回放、CDN 或复杂鉴权。

## 1. 本阶段实现了什么

- `docker-compose.yml` 新增 `mediamtx` 服务。
- `.env.example` 新增 MediaMTX 相关配置。
- 新增 `config/live.php`。
- `live_rooms` 表新增真实直播相关字段：
  - `playback_type`
  - `stream_key`
  - `push_url`
  - `playback_url`
  - `playback_protocol`
  - `media_server`
- 房间创建 / 编辑支持三种播放来源：
  - `video`：本地视频伪直播。
  - `hls_url`：外部 HLS URL。
  - `mediamtx`：OBS 推流到 MediaMTX，Laravel 播放 MediaMTX 输出的 HLS。
- 房间详情页显示 OBS Server、OBS Stream Key、推流地址和播放地址。
- Reverb 聊天继续复用原来的房间聊天实现。

## 2. 伪直播和真实直播的区别

伪直播：

```text
浏览器 -> Laravel 房间页 -> 已上传视频的 HLS -> Reverb 聊天
```

真实直播：

```text
OBS -> RTMP -> MediaMTX -> HLS -> Laravel 房间页 -> 浏览器播放器
                                -> Reverb 聊天
```

伪直播播放的是已经存在的视频文件。真实直播播放的是主播通过 OBS 正在推送的实时流。

## 3. MediaMTX 在项目中的职责

MediaMTX 负责：

- 接收 OBS 的 RTMP 推流。
- 维护直播媒体连接。
- 输出 HLS 播放地址。
- 处理音视频流生命周期。

本阶段使用 MediaMTX 默认配置，尽量减少本地学习复杂度。

## 4. Laravel 在真实直播中的职责

Laravel 负责：

- 创建和管理直播房间。
- 保存 `stream_key`、`push_url`、`playback_url`。
- 控制房间状态：`scheduled`、`live`、`ended`。
- 提供房间页面和聊天。
- 把播放地址交给浏览器播放器。

Laravel 不接收 OBS 推流，不转发大规模音视频流，也不做实时转码。

## 5. Docker Compose 如何启动 MediaMTX

启动全部中间件：

```bash
docker compose up -d
docker compose ps
```

只启动 MediaMTX：

```bash
docker compose up -d mediamtx
```

端口：

- `1935`：RTMP 推流。
- `8888`：HLS 播放。
- `8889`：WebRTC 预留，本阶段不实现。
- `8554`：RTSP 预留，本阶段不实现。

## 6. OBS 配置方式

OBS 服务地址：

```text
rtmp://127.0.0.1:1935/live
```

OBS 串流密钥：

```text
test
```

完整推流地址等价于：

```text
rtmp://127.0.0.1:1935/live/test
```

浏览器 HLS 播放地址：

```text
http://127.0.0.1:8888/live/test/index.m3u8
```

注意：HLS 通常要 OBS 推流几秒后才会生成 `index.m3u8`。

## 7. 房间如何配置 playback_type = mediamtx

进入 `/rooms/create`：

1. `Playback type` 选择 `mediamtx`。
2. `MediaMTX stream key` 填 `test`。
3. 状态可以先选 `scheduled`。
4. 保存后 Laravel 会生成：
   - `push_url = rtmp://127.0.0.1:1935/live/test`
   - `playback_url = http://127.0.0.1:8888/live/test/index.m3u8`
   - `playback_protocol = hls`
   - `media_server = mediamtx`

OBS 通常分开填写 server 和 stream key：

- Server：`rtmp://127.0.0.1:1935/live`
- Stream Key：`test`

## 8. 房间页如何播放 MediaMTX HLS

房间详情页会根据 `playback_type` 选择播放源：

1. `video`：播放绑定视频的 adaptive HLS，fallback 到单码率 HLS，再 fallback 到 MP4。
2. `hls_url`：播放 `live_rooms.playback_url`。
3. `mediamtx`：播放自动生成的 `live_rooms.playback_url`。

前端仍复用现有 `hls.js` 播放逻辑。

## 9. 如何手动验收

1. 启动 MediaMTX：

   ```bash
   docker compose up -d mediamtx
   docker compose ps
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
6. 浏览器或命令行测试 HLS：

   ```bash
   curl -I http://127.0.0.1:8888/live/test/index.m3u8
   ```

7. 在 Laravel 创建直播房间。
8. `playback_type` 选择 `mediamtx`。
9. `stream_key` 填 `test`。
10. 保存房间。
11. 打开房间详情页。
12. 确认播放器可以播放直播画面。
13. 打开两个浏览器窗口进入同一房间。
14. 发送聊天消息，确认 Reverb 聊天仍然可用。

## 10. 常见问题

### OBS 推流失败

检查 MediaMTX 容器：

```bash
docker compose ps
docker compose logs -f mediamtx
```

确认 OBS Server 是：

```text
rtmp://127.0.0.1:1935/live
```

不要把 stream key 拼到 Server 里又重复填写。

### m3u8 404

常见原因：

- OBS 还没有开始推流。
- 推流刚开始，HLS 还没切出第一个分片。
- stream key 不一致。
- MediaMTX 容器没启动。

先访问：

```text
http://127.0.0.1:8888/live/test/index.m3u8
```

### HLS 有延迟

HLS 本身是分片协议，通常有几秒到十几秒延迟。本阶段不追求低延迟。低延迟互动直播后续可以研究 WebRTC，但本项目暂不实现连麦。

### MediaMTX 容器没启动

```bash
docker compose up -d mediamtx
docker compose logs -f mediamtx
```

### 端口被占用

检查：

```bash
lsof -i :1935
lsof -i :8888
```

如果冲突，可以修改 `docker-compose.yml` 的端口映射，并同步更新 `.env` 中的 MediaMTX URL。

### playback_url 和 stream_key 不一致

编辑房间，重新保存 `playback_type=mediamtx` 和正确的 `stream_key`。Laravel 会重新生成 `push_url` 和 `playback_url`。

### 浏览器能打开页面但视频不播放

检查浏览器 Network：

- `index.m3u8` 是否 200。
- `.ts` 分片是否 200。
- Console 是否有 HLS 或 CORS 错误。

本地默认同机访问通常不会遇到复杂 CORS 问题。

## 11. 生产环境为什么不能这样直接暴露 stream_key

本阶段是本地学习项目，所以页面直接展示 stream key，方便你理解 OBS 配置。

生产环境不能这样做，原因：

- stream key 相当于推流凭证。
- 被泄露后别人可以冒充主播推流。
- 需要推流鉴权、过期签名、IP 限制、HTTPS、访问控制和审计。

## 12. 后续如何扩展

- 推流鉴权。
- 防盗链。
- 直播录制。
- 回放生成。
- 多码率直播。
- SRS 对比。
- WebRTC 低延迟播放或连麦。
- CDN 分发。
- 直播状态自动同步。

