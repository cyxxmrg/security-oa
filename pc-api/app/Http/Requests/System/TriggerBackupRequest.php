<?php

namespace App\Http\Requests\System;

use App\Http\Requests\BaseFormRequest;

/**
 * V1.2.7 P1-16: 触发数据库备份
 *
 * 用于 POST /api/settings/backup (system 角色专属)
 * label 用于备份文件名前缀, 限制字符防止文件名注入
 */
class TriggerBackupRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_\-]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.regex' => '标签只能包含字母、数字、下划线、横线',
            'label.max'   => '标签最长 32 字符',
        ];
    }
}
