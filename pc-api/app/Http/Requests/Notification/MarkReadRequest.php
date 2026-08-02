<?php

namespace App\Http\Requests\Notification;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-14: 标记通知已读
 *
 * 用于 POST /api/notifications/mark-read
 */
class MarkReadRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'notification_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'notification_id.required' => '通知 ID 不能为空',
            'notification_id.integer'  => '通知 ID 必须为整数',
        ];
    }
}
