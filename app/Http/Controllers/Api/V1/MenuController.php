<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Access\Models\Menu;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function update(Request $request, Menu $menu): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:80'],
            'path' => ['sometimes', 'required', 'string', 'max:160'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_visible' => ['sometimes', 'boolean'],
        ]);

        $menu->fill($validated)->save();

        return ApiResponse::success([
            'menu' => [
                'id' => $menu->id,
                'parent_id' => $menu->parent_id,
                'title' => $menu->title,
                'route_name' => $menu->route_name,
                'path' => $menu->path,
                'icon' => $menu->icon,
                'permission' => $menu->permission?->code,
                'sort_order' => $menu->sort_order,
                'is_visible' => $menu->is_visible,
            ],
        ]);
    }
}
