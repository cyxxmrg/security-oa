<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    public function show(Request $request, KnowledgeArticle $article): JsonResponse
    {
        if ($article->status !== 'published' && !$request->user()?->hasActivePermissionTo('knowledge.edit')) {
            return response()->json(['code' => 1003, 'message' => '无权访问该文章'], 403);
        }

        $article->increment('view_count');
        return response()->json(['code' => 0, 'data' => $article->load('category', 'author')]);
    }

    /** 发布/编辑文章 — POST /api/knowledge/articles */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'         => 'required|string|max:200',
            'category_id'   => 'required|integer|exists:knowledge_categories,id',
            'content'       => 'nullable|string',          // 文件型可空
            'content_type'  => 'nullable|in:text,file',
            'file_path'     => 'nullable|string|max:500',
            'file_name'     => 'nullable|string|max:255',
            'file_size'     => 'nullable|integer|min:0',
            'summary'       => 'nullable|string|max:500',
            'status'        => 'nullable|in:draft,published,archived',
            'tags'          => 'nullable|array',
            'cover'         => 'nullable|string|max:500',
            'cover_image'   => 'nullable|string|max:500',
        ]);
        $data['status']      = $data['status'] ?? 'published';
        $data['author_id']   = $request->user()->id;
        $data['published_at']= $data['status'] === 'published' ? now() : null;
        $data['view_count']  = 0;
        if (isset($data['cover']) && !isset($data['cover_image'])) {
            $data['cover_image'] = $data['cover'];
        }
        unset($data['cover']);
        $data = $this->normalizeArticleData($data);

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
            'cover_image'   => 'sometimes|nullable|string|max:500',
        ]);
        if (($data['status'] ?? null) === 'published' && !$article->published_at) {
            $data['published_at'] = now();
        }
        if (isset($data['cover']) && !isset($data['cover_image'])) {
            $data['cover_image'] = $data['cover'];
        }
        unset($data['cover']);
        $oldPath = $article->file_path;
        $data = $this->normalizeArticleData($data, $article);
        $article->update($data);
        if ($oldPath && $oldPath !== $article->file_path) {
            $this->deleteAttachmentIfUnused($oldPath, $article->id);
        }
        return response()->json(['code' => 0, 'message' => '已更新', 'data' => $article]);
    }

    /** 删除文章 — DELETE /api/knowledge/articles/{article} */
    public function destroy(KnowledgeArticle $article): JsonResponse
    {
        $path = $article->file_path;
        $articleId = $article->id;
        $article->delete();
        if ($path) {
            $this->deleteAttachmentIfUnused($path, $articleId);
        }
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
    public function uploadAttachment(Request $request, FileUploadService $uploader): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,md,jpg,jpeg,png,gif|max:51200',
        ]);
        $result = $uploader->store($request, 'file', [
            'disk' => 'attachments',
            'subdir' => 'knowledge/' . date('Y/m'),
            'allowed_ext' => ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','md','jpg','jpeg','png','gif'],
            'allowed_mime' => [
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain', 'text/markdown', 'image/jpeg', 'image/png', 'image/gif',
            ],
            'max_size' => 51200,
        ]);

        return response()->json([
            'code' => 0,
            'message' => '上传成功',
            'data' => [
                'path' => $result['path'],
                'name' => $result['original_name'],
                'size' => $result['size'],
                'mime' => $result['mime'],
            ],
        ]);
    }

    public function downloadAttachment(Request $request, KnowledgeArticle $article): StreamedResponse|JsonResponse
    {
        if (!$article->file_path || ($article->status !== 'published' && !$request->user()?->hasActivePermissionTo('knowledge.edit'))) {
            return response()->json(['code' => 1003, 'message' => '无权访问该附件'], 403);
        }

        $disk = Storage::disk('attachments');
        if (!$disk->exists($article->file_path)) {
            return response()->json(['code' => 1004, 'message' => '文件已丢失'], 404);
        }
        return $disk->download($article->file_path, $article->file_name ?: basename($article->file_path));
    }

    private function normalizeArticleData(array $data, ?KnowledgeArticle $article = null): array
    {
        $contentType = $data['content_type'] ?? $article?->content_type ?? 'text';

        if ($contentType === 'file') {
            $path = $data['file_path'] ?? $article?->file_path;
            if (!$path || !str_starts_with($path, 'knowledge/') || !Storage::disk('attachments')->exists($path)) {
                throw ValidationException::withMessages(['file_path' => '附件不存在或路径无效']);
            }
            $data['file_path'] = $path;
            if (empty($data['content']) && empty($article?->content)) {
                $data['content'] = '📎 ' . ($data['file_name'] ?? $article?->file_name ?? '附件');
            }
            return $data;
        }

        $content = $data['content'] ?? $article?->content;
        if (!is_string($content) || trim($content) === '') {
            throw ValidationException::withMessages(['content' => '请输入文章内容']);
        }

        $data['content_type'] = 'text';
        $data['file_path'] = null;
        $data['file_name'] = null;
        $data['file_size'] = null;
        return $data;
    }

    private function deleteAttachmentIfUnused(string $path, int $excludingArticleId): void
    {
        $isUsed = KnowledgeArticle::where('file_path', $path)
            ->where('id', '<>', $excludingArticleId)
            ->exists();
        if (!$isUsed) {
            Storage::disk('attachments')->delete($path);
        }
    }
}
