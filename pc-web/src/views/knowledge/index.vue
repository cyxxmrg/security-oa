<template>
  <div class="page-container">
    <div class="page-header">
      <div class="page-title-wrap">
        <span class="page-title">知识库</span>
        <span class="page-desc">安防行业经验沉淀，让知识成为企业资产</span>
      </div>
      <el-button type="primary" @click="openPublishDialog()"><el-icon><Edit /></el-icon>发布文章</el-button>
    </div>
    <el-row :gutter="16">
      <el-col :span="5">
        <CategoryTreePanel
          :cat-loading="catLoading"
          :categories="categories"
          :current-category-id="currentCategoryId"
          :current-category="currentCategory"
          :total-count="totalCount"
          @category-action="onCategoryAction"
          @filter-category="filterByCategory"
          @node-action="onNodeAction"
        />
      </el-col>
      <el-col :span="19">
        <ArticleList
          v-model:keyword="keyword"
          :articles="articles"
          :loading="loading"
          :total="total"
          :page="page"
          :page-size="pageSize"
          :current-category="currentCategory"
          @search="() => { page = 1; loadArticles() }"
          @clear-category="filterByCategory(null)"
          @open="openArticle"
          @edit="openPublishDialog"
          @delete="handleDelete"
          @page-change="(p: number) => { page = p; loadArticles() }"
          @size-change="(s: number) => { pageSize = s; page = 1; loadArticles() }"
        />
      </el-col>
    </el-row>

    <!-- 发布/编辑 对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="editingId ? '编辑文章' : '发布文章'"
      width="720px"
      destroy-on-close
    >
      <el-form ref="publishFormRef" :model="form" :rules="publishRules" label-width="100px" v-loading="saving">
        <el-form-item label="文章标题" prop="title">
          <el-input v-model="form.title" placeholder="请输入文章标题" maxlength="200" show-word-limit />
        </el-form-item>
        <el-form-item label="所属分类" prop="categoryPath">
          <el-cascader
            v-model="form.categoryPath"
            :options="categories"
            :props="{ checkStrictly: true, value: 'id', label: 'name', children: 'children', emitPath: false }"
            placeholder="请选择分类"
            style="width: 100%"
          />
          <div style="margin-top: 6px; font-size: 12px; color: #909399">
            没有合适的分类？<el-button type="primary" link size="small" @click="openCategoryDialogFromArticle">点此创建</el-button>
          </div>
        </el-form-item>
        <el-form-item label="文章摘要">
          <el-input v-model="form.summary" type="textarea" :rows="2" maxlength="500" show-word-limit placeholder="可选，留空则自动取正文前 120 字" />
        </el-form-item>
        <el-form-item label="文章类型">
          <el-radio-group v-model="form.content_type">
            <el-radio label="text">纯文本</el-radio>
            <el-radio label="file">上传文件（PDF/Word/Excel/PPT/图片）</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="form.content_type === 'text'" label="正文内容" prop="content">
          <el-input
            v-model="form.content"
            type="textarea"
            :rows="10"
            placeholder="请输入文章正文（支持换行）"
            maxlength="20000"
            show-word-limit
          />
        </el-form-item>
        <el-form-item v-else label="文件上传" prop="fileList">
          <el-upload
            ref="uploadRef"
            :auto-upload="false"
            :limit="1"
            :on-change="handleFileChange"
            :on-exceed="handleExceed"
            :on-remove="handleFileRemove"
            :file-list="form.fileList"
            v-bind:accept="acceptTypes"
            drag
          >
            <div v-if="!form.fileList.length" class="upload-trigger">
              <el-icon class="upload-trigger__icon"><UploadFilled /></el-icon>
              <div class="upload-trigger__text">点击或拖拽文件到此处上传</div>
              <div class="upload-trigger__hint">支持 PDF / Word / Excel / PPT / 图片 / TXT，单文件 ≤ 50MB</div>
            </div>
            <template #tip>
              <div class="upload-tip">
                <el-icon><InfoFilled /></el-icon>
                <span>已选文件：<b>{{ form.fileList[0]?.name || '无' }}</b>
                  <span v-if="form.fileList[0]">（{{ formatSize(form.fileList[0].size) }}）</span>
                </span>
                <el-tag v-if="form.fileList[0]" size="small" type="info" effect="plain">发布后用户可下载查看</el-tag>
              </div>
            </template>
          </el-upload>
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio label="published">立即发布</el-radio>
            <el-radio label="draft">存为草稿</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="handlePublish">{{ editingId ? '保存' : '发布' }}</el-button>
      </template>
    </el-dialog>

    <!-- 分类管理对话框（新增/重命名） -->
    <CategoryFormDialog
      v-model:visible="categoryDialogVisible"
      :title="categoryDialogMode === 'addSub' ? `在「${categoryDialogParent?.name}」下新建子分类` :
               categoryDialogMode === 'addRoot' ? '新建顶级分类' : '重命名分类'"
      v-model:name="categoryDialogName"
      v-model:icon="categoryDialogIcon"
      :show-icon-field="categoryDialogMode !== 'rename'"
      :saving="categoryDialogSaving"
      @confirm="confirmCategoryDialog"
    />
  </div>
</template>

<script setup lang="ts">
import { Edit, UploadFilled, InfoFilled } from '@element-plus/icons-vue'
import CategoryFormDialog from './components/CategoryFormDialog.vue'
import ArticleList from './components/ArticleList.vue'
import { useKnowledge } from './composables/useKnowledge'
import CategoryTreePanel from './components/CategoryTreePanel.vue'

const {
  keyword, currentCategoryId, page, pageSize, total, loading, catLoading, saving,
  articles, categories,
  totalCount, currentCategory,
  loadArticles, filterByCategory, openArticle,
  dialogVisible, editingId, uploadRef, publishFormRef, form, publishRules,
  acceptTypes, formatSize, handleFileChange, handleExceed, handleFileRemove,
  openPublishDialog, handlePublish, handleDelete,
  categoryDialogVisible, categoryDialogMode, categoryDialogParent,
  categoryDialogName, categoryDialogIcon, categoryDialogSaving,
  onCategoryAction, onNodeAction, confirmCategoryDialog, openCategoryDialogFromArticle,
} = useKnowledge()
</script>

<style lang="scss" scoped>
$primary: #0C447C;

.page-container { padding: 20px; background: linear-gradient(180deg, #f0f4fa 0%, #f5f7fa 100%); min-height: 100vh; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding:18px 20px; background:#fff; border-radius:12px; box-shadow:0 1px 4px rgba(0,0,0,0.04);
  .page-title-wrap { display: flex; align-items: baseline; gap: 12px; }
  .page-title { font-size: 20px; font-weight: 600; color: $primary; }
  .page-desc { font-size: 13px; color: #909399; }
}

/* ============ 上传 ============ */
.upload-trigger { padding: 24px 0; text-align: center; color: #6b7280;
  &__icon { font-size: 48px; color: $primary; margin-bottom: 8px; }
  &__text { font-size: 14px; color: #374151; margin-bottom: 4px; }
  &__hint { font-size: 12px; color: #9ca3af; }
}
.upload-tip { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #6b7280; margin-top: 6px;
  .el-icon { color: $primary; }
}
:deep(.el-upload-dragger) { padding: 20px; border: 2px dashed #c0c4cc; border-radius: 8px; transition: all .2s;
  &:hover { border-color: $primary; background: #f0f4fa; }
}
</style>
