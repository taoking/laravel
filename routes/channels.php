<?php

use Illuminate\Support\Facades\Broadcast;

// 学习要点：channels.php 定义广播频道授权逻辑。
// 当客户端订阅私有频道或 presence 频道时，Laravel 会调用这里的回调判断是否允许订阅。
/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    // 只有当前认证用户 ID 与频道参数 ID 一致时，才允许监听该用户私有频道。
    return (int) $user->id === (int) $id;
});
