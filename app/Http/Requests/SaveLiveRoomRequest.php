<?php

namespace App\Http\Requests;

use App\Models\LiveRoom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLiveRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(LiveRoom::statuses())],
            'video_id' => ['nullable', 'integer', 'exists:videos,id'],
            'stream_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
