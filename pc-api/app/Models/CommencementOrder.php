<?php

namespace App\Models;

/**
 * V0.4.3 兼容 alias — V0.4.3 Controller 用 CommencementOrder 但实际 Model 是 ProjectCommencementOrder
 * 这个 alias 类只为 PSR-4 解析，不建表
 */
class CommencementOrder extends ProjectCommencementOrder
{
}
