<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeCategory;
use App\Models\KnowledgeArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KnowledgeController extends Controller
{
    public function categories(): JsonResponse { return response()->json(['code' => 0, 'data' => KnowledgeCategory::withCount('articles')->whereNull('parent_id')->with('children')->orderBy('sort_order')->get()]); }
    public function articles(Request $request): JsonResponse
    {
        $query = KnowledgeArticle::with(['category', 'author'])->where('status', 'published');
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('keyword')) $query->where('title', 'like', "%{$request->keyword}%");
        return response()->json(['code' => 0, 'data' => $query->orderBy('published_at', 'desc')->paginate()]);
    }
    public function show(KnowledgeArticle $article): JsonResponse
    {
        $article->increment('view_count');
        $data = $article->load('category', 'author')->toArray();
        if (!empty($data['file_path'])) {
            $data['file_url'] = '/api/knowledge/attachment/download?path=' . urlencode($data['file_path']);
        }
        return response()->json(['code' => 0, 'data' => $data]);
    }

    /** 发布/编辑文章 — POST /api/knowledge/articles */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'         => 'required|string|max:200',
            'category_id'   => 'required|integer|exists:knowledge_categories,id',
            'content'       => 'nullable|string',          // 文件型可空
            'content_type'  => 'nullable|in:text,file',
            'file_path'     => 'nullable|string|max:500',  // 文件型时存路径
            'file_name'     => 'nullable|string|max:255',
            'file_size'     => 'nullable|integer|min:0',
            'summary'       => 'nullable|string|max:500',
            'status'        => 'nullable|in:draft,published,archived',
            'tags'          => 'nullable|array',
            'cover'         => 'nullable|string|max:500',
        ]);
        $data['status']      = $data['status'] ?? 'published';
        $data['author_id']   = $request->user()->id;
        $data['published_at']= $data['status'] === 'published' ? now() : null;
        $data['tags']        = isset($data['tags']) ? json_encode($data['tags'], JSON_UNESCAPED_UNICODE) : null;
        $data['view_count']  = 0;

        // 文件型：content 必填校验已通过（有 file_path），置为占位
        if (($data['content_type'] ?? 'text') === 'file' && empty($data['content'])) {
            $data['content'] = '📎 ' . ($data['file_name'] ?? '附件');
        }
        // 文本型：content 必填
        if (($data['content_type'] ?? 'text') === 'text' && empty($data['content'])) {
            return response()->json(['code' => 1001, 'message' => '请输入文章内容'], 422);
        }

        $article = KnowledgeArticle::create($data);
        return response()->json(['code' => 0, 'message' => '发布成功', 'data' => $article]);
    }

    /** 更新文章 — PUT /api/knowledge/articles/{article} */
    public function update(Request $request, KnowledgeArticle $article): JsonResponse
    {
        $data = $request->validate([
            'title'         => 'sometimes|string|max:200',
            'category_id'   => 'sometimes|integer|exists:knowledge_categories,id',
            'content'       => 'sometimes|nullable|string',
            'content_type'  => 'sometimes|nullable|in:text,file',
            'file_path'     => 'sometimes|nullable|string|max:500',
            'file_name'     => 'sometimes|nullable|string|max:255',
            'file_size'     => 'sometimes|nullable|integer|min:0',
            'summary'       => 'sometimes|nullable|string|max:500',
            'status'        => 'sometimes|in:draft,published,archived',
            'tags'          => 'sometimes|nullable|array',
            'cover'         => 'sometimes|nullable|string|max:500',
        ]);
        if (isset($data['tags'])) $data['tags'] = json_encode($data['tags'], JSON_UNESCAPED_UNICODE);
        if (($data['status'] ?? null) === 'published' && !$article->published_at) {
            $data['published_at'] = now();
        }
        $article->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $article]);
    }

    /** 删除文章 — DELETE /api/knowledge/articles/{article} */
    public function destroy(KnowledgeArticle $article): JsonResponse
    {
        $article->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    /** 新增分类 — POST /api/knowledge/categories */
    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'parent_id'   => 'nullable|integer|exists:knowledge_categories,id',
            'icon'        => 'nullable|string|max:100',
            'sort_order'  => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:500',
        ]);
        $cat = KnowledgeCategory::create($data);
        return response()->json(['code' => 0, 'message' => '分类已创建', 'data' => $cat], 201);
    }

    /** 更新分类 — PUT /api/knowledge/categories/{category} */
    public function updateCategory(Request $request, KnowledgeCategory $category): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:100',
            'parent_id'   => 'sometimes|nullable|integer|exists:knowledge_categories,id',
            'icon'        => 'sometimes|nullable|string|max:100',
            'sort_order'  => 'sometimes|nullable|integer|min:0',
            'description' => 'sometimes|nullable|string|max:500',
        ]);
        $category->update($data);
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $category]);
    }

    /** 删除分类 — DELETE /api/knowledge/categories/{category} */
    public function destroyCategory(KnowledgeCategory $category): JsonResponse
    {
        // 有关联文章则禁止删除
        if ($category->articles()->exists()) {
            return response()->json(['code' => 1001, 'message' => '该分类下存在文章，请先移除'], 422);
        }
        // 有子分类则禁止删除
        if ($category->children()->exists()) {
            return response()->json(['code' => 1001, 'message' => '该分类下存在子分类，请先移除'], 422);
        }
        $category->delete();
        return response()->json(['code' => 0, 'message' => '已删除']);
    }

    /**
     * 文章附件上传 — POST /api/knowledge/upload
     * body: file=...  → {code, data:{path,name,size,mime,url}}
     * 不依赖 disk_folders，专供文章附件用，存到 attachments/knowledge/...
     */
    public function uploadAttachment(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,md,jpg,jpeg,png,gif|max:51200',
        ]);
        $file = $request->file('file');
        $path = $file->store('knowledge/' . date('Y/m'), 'attachments');

        return response()->json([
            'code' => 0,
            'message' => '上传成功',
            'data' => [
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'url'  => '/api/knowledge/attachment/download?path=' . urlencode($path),
            ],
        ]);
    }

    /**
     * 文章附件下载 — GET /api/knowledge/attachment/download?path=...
     */
    public function downloadAttachment(Request $request)
    {
        $path = $request->query('path');
        if (!$path || !str_starts_with($path, 'knowledge/')) {
            return response()->json(['code' => 1001, 'message' => '参数错误'], 400);
        }
        $disk = Storage::disk('attachments');
        if (!$disk->exists($path)) {
            return response()->json(['code' => 1004, 'message' => '文件已丢失'], 404);
        }
        return response()->download($disk->path($path));
    }
}
