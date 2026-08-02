// 知识库页 composable — 数据加载 / 文章发布 / 分类管理
// 从 knowledge/index.vue <script setup> 抽出, 主组件 + 子组件共享
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { get, post, put, del } from '@/utils/request'
import { unwrapList } from '@/utils/response'

interface Article {
  id: number
  title: string
  summary?: string
  content: string
  status: 'published' | 'draft' | 'archived'
  category_id: number
  category?: { id: number; name: string }
  author?: { id: number; name: string }
  published_at?: string
  created_at?: string
  view_count?: number
}

interface Category {
  id: number
  name: string
  parent_id?: number | null
  icon?: string
  children?: Category[]
  articles_count?: number
}

export function useKnowledge() {
  const keyword = ref('')
  const currentCategoryId = ref<number | null>(null)
  const page = ref(1)
  const pageSize = ref(15)
  const total = ref(0)
  const loading = ref(false)
  const catLoading = ref(false)
  const saving = ref(false)
  const articles = ref<Article[]>([])
  const categories = ref<Category[]>([])

  const totalCount = computed(() => total.value)
  const currentCategory = computed(() => {
    function find(nodes: Category[]): Category | null {
      for (const n of nodes) {
        if (n.id === currentCategoryId.value) return n
        if (n.children) { const r = find(n.children); if (r) return r }
      }
      return null
    }
    return find(categories.value)
  })

  async function loadCategories() {
    catLoading.value = true
    try {
      const res = await get('/knowledge/categories')
      categories.value = unwrapList(res)
    } catch (e) {
      console.error('加载分类失败', e)
      categories.value = []
    } finally {
      catLoading.value = false
    }
  }

  async function loadArticles() {
    loading.value = true
    try {
      const params: Record<string, unknown> = { page: page.value, per_page: pageSize.value }
      if (currentCategoryId.value) params.category_id = currentCategoryId.value
      if (keyword.value) params.keyword = keyword.value
      const res = await get('/knowledge/articles', params)
      const payload = res?.data
      const list = Array.isArray(payload?.data) ? payload.data
                : Array.isArray(payload)        ? payload
                : []
      articles.value = list
      total.value = Number(payload?.total ?? list.length)
    } catch (e) {
      ElMessage.error('加载文章失败')
    } finally {
      loading.value = false
    }
  }

  function filterByCategory(id: number | null) {
    currentCategoryId.value = id
    page.value = 1
    loadArticles()
  }

  function openArticle(item: Record<string, unknown>) {
    if (item.content_type === 'file') {
      const fileName = item.file_name || item.title || '(未知文件)'
      const fileUrl = item.file_url || '#'
      ElMessageBox.alert(
        `该文章为附件类型，发布后用户可下载查看。\n\n📎 ${fileName}\n（点击下方"下载文件"按钮获取）`,
        item.title,
        { dangerouslyUseHTMLString: false, confirmButtonText: '下载文件', cancelButtonText: '关闭', showCancelButton: true }
      ).then(() => {
        if (fileUrl !== '#') window.open(fileUrl, '_blank')
      }).catch(() => { /* 关闭 */ })
      return
    }
    ElMessageBox.alert(item.content || '(无内容)', item.title, { dangerouslyUseHTMLString: false, confirmButtonText: '关闭' })
  }

  // 发布/编辑对话框
  const dialogVisible = ref(false)
  const editingId = ref<number | null>(null)
  const uploadRef = ref()
  const publishFormRef = ref()
  const form = reactive({
    title: '',
    content: '',
    summary: '',
    categoryPath: null as number | null,
    status: 'published' as 'published' | 'draft',
    content_type: 'text' as 'text' | 'file',
    fileList: [] as Record<string, unknown>[],
  })

  const publishRules = computed(() => ({
    title:         [{ required: true, message: '请输入文章标题', trigger: 'blur' }],
    categoryPath:  [{ required: true, message: '请选择分类', trigger: 'change' }],
    content:       [{ required: form.content_type === 'text', message: '请输入文章内容', trigger: 'blur' }],
    fileList:      [{
      required: form.content_type === 'file',
      validator: (_rule: Record<string, unknown>, value: Record<string, unknown>[], cb: (err?: Error) => void) => {
        if (form.content_type === 'file' && (!Array.isArray(value) || value.length === 0)) {
          return cb(new Error('请上传文件'))
        }
        cb()
      },
      trigger: 'change',
    }],
  }))

  const ALLOWED_EXTS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'md', 'jpg', 'jpeg', 'png', 'gif']
  const acceptTypes = ALLOWED_EXTS.map(e => `.${e}`).join(',')
  const MAX_SIZE = 50 * 1024 * 1024

  function formatSize(bytes: number) {
    if (!bytes && bytes !== 0) return '-'
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB'
    return (bytes/1024/1024).toFixed(1) + ' MB'
  }

  function handleFileChange(file: Record<string, unknown>) {
    const ext = (file.name.split('.').pop() || '').toLowerCase()
    if (!ALLOWED_EXTS.includes(ext)) {
      ElMessage.error(`不支持的文件格式：.${ext}，仅支持 PDF / Word / Excel / PPT / 图片 / TXT / MD`)
      file.status = 'fail'
      return false
    }
    if (file.size > MAX_SIZE) {
      ElMessage.error(`文件「${file.name}」超过 50MB 限制`)
      file.status = 'fail'
      return false
    }
    form.fileList = [file]
    if (!form.title.trim() && form.content_type === 'file') {
      form.title = file.name.replace(/\.[^.]+$/, '')
    }
    return true
  }

  function handleExceed() {
    ElMessage.warning('只能上传 1 个文件，请先移除已选文件')
  }

  function handleFileRemove() {
    form.fileList = []
  }

  function openPublishDialog(item?: Article) {
    if (item) {
      editingId.value = item.id
      Object.assign(form, {
        title: item.title, content: item.content, summary: item.summary || '',
        categoryPath: item.category_id,
        status: item.status === 'draft' ? 'draft' : 'published',
        content_type: 'text',
        fileList: [],
      })
    } else {
      editingId.value = null
      Object.assign(form, {
        title: '', content: '', summary: '',
        categoryPath: currentCategoryId.value || categories.value[0]?.id || null,
        status: 'published',
        content_type: 'text',
        fileList: [],
      })
    }
    publishFormRef.value?.clearValidate()
    dialogVisible.value = true
  }

  async function handlePublish() {
    try {
      await publishFormRef.value.validate()
    } catch {
      return
    }

    saving.value = true
    try {
      if (form.content_type === 'file') {
        if (!form.fileList || form.fileList.length === 0) {
          ElMessage.error('请先上传文件')
          saving.value = false
          return
        }
        const fileObj = form.fileList[0].raw || form.fileList[0]
        const fd = new FormData()
        fd.append('file', fileObj)
        const upRes = await post('/knowledge/upload', fd)
        const att = upRes?.data ?? upRes
        if (!att?.path) throw new Error('附件上传失败：未返回 path')

        const payload = {
          title:        form.title.trim(),
          content_type: 'file',
          file_path:    att.path,
          file_name:    att.name,
          file_size:    att.size,
          summary:      form.summary?.trim() || null,
          category_id:  form.categoryPath,
          status:       form.status,
        }
        if (editingId.value) {
          await put(`/knowledge/articles/${editingId.value}`, payload)
        } else {
          await post('/knowledge/articles', payload)
        }
        ElMessage.success(editingId.value ? '已更新' : '发布成功')
      } else {
        const payload = {
          title: form.title.trim(),
          content: form.content,
          content_type: 'text',
          summary: form.summary?.trim() || null,
          category_id: form.categoryPath,
          status: form.status,
        }
        if (editingId.value) {
          await put(`/knowledge/articles/${editingId.value}`, payload)
        } else {
          await post('/knowledge/articles', payload)
        }
        ElMessage.success(editingId.value ? '已更新' : '发布成功')
      }

      dialogVisible.value = false
      form.fileList = []
      loadArticles()
    } catch (e: unknown) {
      ElMessage.error(e?.response?.data?.message || e?.message || '发布失败')
    } finally {
      saving.value = false
    }
  }

  async function handleDelete(item: Article) {
    try {
      await del(`/knowledge/articles/${item.id}`)
      ElMessage.success('已删除')
      loadArticles()
    } catch (e: unknown) {
      ElMessage.error(e?.response?.data?.message || '删除失败')
    }
  }

  // ========== 分类管理 ==========
  const categoryDialogVisible = ref(false)
  const categoryDialogMode = ref<'addRoot' | 'addSub' | 'rename'>('addRoot')
  const categoryDialogParent = ref<Category | null>(null)
  const categoryDialogTarget = ref<Category | null>(null)
  const categoryDialogName = ref('')
  const categoryDialogIcon = ref('folder')
  const categoryDialogSaving = ref(false)

  function onCategoryAction(cmd: string) {
    if (cmd === 'root') openAddRoot()
    if (cmd === 'sub' && currentCategory.value) openAddSub(currentCategory.value)
  }

  function onNodeAction(cmd: string, data: Category) {
    if (cmd === 'addSub') openAddSub(data)
    if (cmd === 'rename') openRename(data)
    if (cmd === 'delete') deleteCategory(data)
  }

  function openAddRoot() {
    categoryDialogMode.value = 'addRoot'
    categoryDialogParent.value = null
    categoryDialogName.value = ''
    categoryDialogIcon.value = 'folder'
    categoryDialogVisible.value = true
  }

  function openAddSub(parent: Category) {
    categoryDialogMode.value = 'addSub'
    categoryDialogParent.value = parent
    categoryDialogName.value = ''
    categoryDialogIcon.value = 'folder'
    categoryDialogVisible.value = true
  }

  function openRename(target: Category) {
    categoryDialogMode.value = 'rename'
    categoryDialogTarget.value = target
    categoryDialogName.value = target.name
    categoryDialogVisible.value = true
  }

  async function confirmCategoryDialog() {
    const name = categoryDialogName.value.trim()
    if (!name) { ElMessage.warning('请输入分类名称'); return }
    categoryDialogSaving.value = true
    try {
      if (categoryDialogMode.value === 'addRoot') {
        await post('/knowledge/categories', { name, parent_id: null, icon: categoryDialogIcon.value })
        ElMessage.success('顶级分类已创建')
      } else if (categoryDialogMode.value === 'addSub') {
        await post('/knowledge/categories', {
          name, parent_id: categoryDialogParent.value!.id, icon: categoryDialogIcon.value
        })
        ElMessage.success('子分类已创建')
      } else if (categoryDialogMode.value === 'rename') {
        await put(`/knowledge/categories/${categoryDialogTarget.value!.id}`, { name })
        ElMessage.success('已重命名')
      }
      categoryDialogVisible.value = false
      await loadCategories()
    } catch (e: unknown) {
      ElMessage.error(e?.response?.data?.message || e?.message || '操作失败')
    } finally {
      categoryDialogSaving.value = false
    }
  }

  async function deleteCategory(c: Category) {
    if (c.articles_count) {
      ElMessage.warning(`「${c.name}」下还有 ${c.articles_count} 篇文章，请先移动或删除`)
      return
    }
    try {
      await ElMessageBox.confirm(
        `确定要删除分类「${c.name}」？${c.children?.length ? '其下所有子分类也会被删除' : ''}`,
        '删除分类', { type: 'warning' }
      )
    } catch { return }
    try {
      await del(`/knowledge/categories/${c.id}`)
      ElMessage.success('已删除')
      if (currentCategoryId.value === c.id) currentCategoryId.value = null
      await loadCategories()
    } catch (e: unknown) {
      ElMessage.error(e?.response?.data?.message || e?.message || '删除失败')
    }
  }

  function openCategoryDialogFromArticle() {
    categoryDialogMode.value = currentCategory.value ? 'addSub' : 'addRoot'
    categoryDialogParent.value = currentCategory.value
    categoryDialogName.value = ''
    categoryDialogIcon.value = 'folder'
    categoryDialogVisible.value = true
  }

  onMounted(() => {
    loadCategories()
    loadArticles()
  })

  return {
    keyword, currentCategoryId, page, pageSize, total, loading, catLoading, saving,
    articles, categories,
    totalCount, currentCategory,
    loadCategories, loadArticles, filterByCategory, openArticle,
    dialogVisible, editingId, uploadRef, publishFormRef, form, publishRules,
    acceptTypes, formatSize, handleFileChange, handleExceed, handleFileRemove,
    openPublishDialog, handlePublish, handleDelete,
    categoryDialogVisible, categoryDialogMode, categoryDialogParent,
    categoryDialogName, categoryDialogIcon, categoryDialogSaving,
    onCategoryAction, onNodeAction, confirmCategoryDialog, openCategoryDialogFromArticle,
  }
}
