<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

/**
 * V1.2.6 P2-2: API 文档自动生成配置 (Scramble 0.13 升级版)
 *
 * 访问: /docs/api      (UI, Stoplight Elements)
 * JSON: /docs/api.json (OpenAPI 3.1 spec)
 *
 * 文档自动从 Controller 方法签名 + FormRequest + 路由注释生成
 * 不需要写额外的 yaml/json, 跟代码同步
 */
return [
    // ===== 基础 =====
    'info' => [
        'version'     => '1.2.6',
        'description' => '安防运维 OA 系统后端 API 文档 (自动生成, 部署时随代码同步)',
    ],

    // ===== 路由范围 =====
    'api_path' => [
        'include' => 'api',
        'exclude' => [
            'api/horizon',
            'api/telescope',
            'api/_debugbar',
            'api/up',
        ],
    ],

    'api_domain' => null,

    // ===== 文档路径 =====
    'export_path' => 'docs/api.json', // JSON spec 路径
    // 0.13 中 UI 路径由 Scramble 内置决定: /docs/api

    // ===== 缓存 =====
    'cache' => [
        'key'   => 'scramble.openapi',
        'store' => 'file',
    ],

    // ===== 鉴权 =====
    'security_strategy' => \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,

    // ===== 中间件 =====
    // V1.2.6: 顺序调整为 auth:sanctum 在前, 让 Gate 检查时 user 已就绪
    // RestrictedDocsAccess 调 Gate::allows('viewApiDocs'), 需要 user 已解析
    'middleware' => [
        'auth:sanctum',
        \Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess::class,
    ],

    // ===== UI =====
    'ui' => [
        'title' => 'Security OA API',
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view'      => 'scramble::docs',
            'theme'     => 'light',
            'hideTryIt' => false,
        ],
    ],

    // ===== 服务器 =====
    'servers' => [
        'Local'    => env('APP_URL', 'http://localhost'),
        'Staging'  => 'http://192.168.1.117:8081',
        'Prod'     => env('PROD_URL', 'https://oa.example.com'),
    ],

    // ===== 扩展 =====
    'extensions' => [],

    // ===== Enum 描述 =====
    'enum_cases_description_strategy' => 'description',
    'enum_cases_names_strategy'       => false,

    // ===== 深 query 参数 =====
    'flatten_deep_query_parameters' => true,
];
