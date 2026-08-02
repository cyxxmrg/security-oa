<template>
  <div class="page-container">
    <!-- 未初始化：引导界面 -->
    <div v-if="!initialized && !checkingInit" class="init-wizard">
      <div class="init-wizard__card">
        <div class="init-wizard__icon">📁</div>
        <h1 class="init-wizard__title">初始化公司网盘</h1>
        <p class="init-wizard__desc">首次使用前需要设置存储目录，系统将自动创建三个根文件夹：<b>项目</b>、<b>工作</b>、<b>公共</b></p>

        <div class="init-wizard__options">
          <div class="init-option" :class="{ active: initMode === 'auto' }" @click="initMode = 'auto'">
            <el-radio v-model="initMode" value="auto" size="large">自动选择</el-radio>
            <div class="init-option__desc">自动检测服务器磁盘，选择剩余空间最大的分区存储文件</div>
            <div v-if="initMode === 'auto' && diskList.length > 0" class="init-option__disks">
              <div class="disk-item" v-for="d in diskList" :key="d.mount">
                <div class="disk-item__info">
                  <span class="disk-item__mount">{{ d.mount }}</span>
                  <span class="disk-item__device">{{ d.device }}</span>
                </div>
                <el-progress :percentage="diskPercent(d)" :color="diskColor(d)" :stroke-width="8" />
                <div class="disk-item__size">
                  可用 <b>{{ d.avail_fmt }}</b> / 共 {{ d.total_fmt }}
                </div>
              </div>
              <div v-if="diskList.length > 0" class="disk-item recommended">
                <el-tag type="success" effect="dark" size="small">推荐</el-tag>
                <span>将安装在 <b>{{ diskList[0].mount }}/oa_disk</b>（剩余 {{ diskList[0].avail_fmt }}）</span>
              </div>
            </div>
            <div v-else class="init-option__disks loading">
              <el-icon class="is-loading"><Loading /></el-icon> 正在检测磁盘...
            </div>
          </div>

          <div class="init-option" :class="{ active: initMode === 'manual' }" @click="initMode = 'manual'">
            <el-radio v-model="initMode" value="manual" size="large">手动指定</el-radio>
            <div class="init-option__desc">手动设置文件存储路径（目录需存在且可写）</div>
            <div v-if="initMode === 'manual'" class="init-option__manual">
              <el-input v-model="manualPath" placeholder="例如: /data/disk 或 /mnt/storage/disk" style="width:100%">
                <template #prepend>存储路径</template>
              </el-input>
              <div v-if="manualPath" class="path-hint">
                <el-tag v-if="pathValid === true" type="success" size="small" effect="plain">✓ 目录可写</el-tag>
                <el-tag v-else-if="pathValid === false" type="danger" size="small" effect="plain">✗ 目录不可写</el-tag>
                <el-tag v-else type="info" size="small" effect="plain">输入后自动校验</el-tag>
              </div>
            </div>
          </div>
        </div>

        <div class="init-wizard__actions">
          <el-button type="primary" size="large" :loading="initLoading" @click="handleInit" :disabled="initMode === 'manual' && !manualPath">
            <el-icon><FolderAdd /></el-icon> 开始初始化
          </el-button>
        </div>
      </div>
    </div>

    <!-- 已初始化：正常网盘界面 -->
    <div v-else class="disk-main-wrap">
    <div class="page-header">
      <div class="page-title-wrap">
        <span class="page-title">公司网盘</span>
        <span class="page-desc">项目文档、日常资料、技术资料统一管理</span>
      </div>
      <div>
        <el-button @click="refreshAll"><el-icon><Refresh /></el-icon>刷新</el-button>
        <el-button type="primary" :disabled="!currentFolder || currentFolder.id === 0" @click="triggerUpload">
          <el-icon><Upload /></el-icon>上传文件
        </el-button>
        <el-button type="success" @click="showFolderDialog = true">
          <el-icon><FolderAdd /></el-icon>新建文件夹
        </el-button>
        <input ref="fileInput" type="file" multiple style="display:none" @change="handleFileSelected" />
      </div>
    </div>

    <div class="disk-body">
    <!-- 左侧文件夹树 -->
    <div class="disk-side">
      <div class="disk-side__head">
        <span>文件夹</span>
        <el-tooltip content="新建文件夹" placement="top">
          <el-icon class="disk-side__add" @click="showFolderDialog = true"><Plus /></el-icon>
        </el-tooltip>
      </div>
      <!-- 3 根快速切换 -->
      <div class="disk-side__chips">
        <el-tag class="chip" :type="currentFolder?.scope==='project_root' ? 'primary' : 'info'" effect="plain" round @click="quickGoRoot('project_root')">
          <el-icon><Folder /></el-icon>项目
        </el-tag>
        <el-tag class="chip" :type="currentFolder?.scope==='work_root' ? 'success' : 'info'" effect="plain" round @click="quickGoRoot('work_root')">
          <el-icon><User /></el-icon>我的工作
        </el-tag>
        <el-tag class="chip" :type="currentFolder?.scope==='share_root' ? 'warning' : 'info'" effect="plain" round @click="quickGoRoot('share_root')">
          <el-icon><Share /></el-icon>公共
        </el-tag>
      </div>
      <div class="disk-side__tree">
          <div class="tree-node tree-node--root" :class="{ active: !currentFolder }" @click="goHome">
            <el-icon><HomeFilled /></el-icon>
            <span>全部文件</span>
          </div>
          <el-tree
            ref="treeRef"
            :data="folderTree"
            :props="{ label: 'name', children: 'children' }"
            node-key="id"
            highlight-current
            :expand-on-click-node="false"
            :default-expand-all="false"
            @node-click="handleTreeClick"
            empty-text="暂无文件夹"
          >
            <template #default="{ node, data }">
              <span class="tree-node tree-node--custom" :class="{ active: currentFolder?.id === data.id }">
                <el-icon><Folder /></el-icon>
                <span class="tree-node__name">{{ data.name }}</span>
                <el-tag v-if="data.system_type" size="small" :type="systemTag(data.system_type)" effect="plain" class="tree-node__tag">
                  {{ systemLabel(data.system_type) }}
                </el-tag>
                <el-dropdown trigger="click" @click.stop @command="(c) => onTreeAction(c, data)">
                  <el-icon class="tree-node__more" @click.stop><MoreFilled /></el-icon>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item command="rename">重命名</el-dropdown-item>
                      <el-dropdown-item command="delete" :disabled="!!data.system_type" divided>删除</el-dropdown-item>
                    </el-dropdown-menu>
                  </template>
                </el-dropdown>
              </span>
            </template>
          </el-tree>
        </div>
        <!-- 系统文件夹说明 -->
        <div class="disk-side__hint">
          <el-icon><InfoFilled /></el-icon>
          <div>
            <div>📁 <b>project</b> - 每个新建项目自动生成子文件夹</div>
            <div>📁 <b>work</b> - 按员工姓名建子文件夹，存放个人资料</div>
            <div>📁 <b>share</b> - 公共资料，全员可访问</div>
          </div>
        </div>
      </div>

      <!-- 右侧文件区 -->
      <div class="disk-main">
        <div class="disk-toolbar">
          <el-breadcrumb separator="/">
            <el-breadcrumb-item><a @click="goHome" style="cursor:pointer;color:#0C447C">全部文件</a></el-breadcrumb-item>
            <el-breadcrumb-item v-for="f in breadcrumb" :key="f.id"><a @click="goToFolder(f)" style="cursor:pointer">{{ f.name }}</a></el-breadcrumb-item>
          </el-breadcrumb>
          <el-input v-model="keyword" placeholder="搜索文件..." clearable style="width:240px" @clear="loadData" @keyup.enter="loadData">
            <template #prefix><el-icon><Search /></el-icon></template>
          </el-input>
        </div>
        <div class="content-card">
          <el-table :data="tableData" v-loading="loading" stripe style="width:100%">
            <el-table-column type="selection" width="40" />
            <el-table-column label="文件名" min-width="320" sortable>
              <template #default="{ row }">
                <div class="file-name" :class="{ 'is-folder': row.type==='folder' }" @click="row.type==='folder' && goToFolder(row)">
                  <el-icon :size="22" :color="row.type==='folder' ? '#BA7517' : fileColor(row.extension)">
                    <Folder v-if="row.type==='folder'" />
                    <Document v-else-if="['doc','docx'].includes(row.extension)" />
                    <Document v-else-if="['xls','xlsx'].includes(row.extension)" />
                    <Picture v-else-if="['jpg','png','gif','bmp','webp'].includes(row.extension)" />
                    <VideoCamera v-else-if="['mp4','avi','mov','mkv'].includes(row.extension)" />
                    <Headset v-else-if="['mp3','wav','flac'].includes(row.extension)" />
                    <Files v-else-if="['zip','rar','7z','tar','gz'].includes(row.extension)" />
                    <Document v-else />
                  </el-icon>
                  <span class="file-name__text">{{ row.original_name || row.name }}</span>
                  <el-tag v-if="row.type==='folder' && row.system_type" size="small" :type="systemTag(row.system_type)" effect="plain" round>
                    {{ systemLabel(row.system_type) }}
                  </el-tag>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="大小" width="120" align="right">
              <template #default="{ row }">
                <span v-if="row.type==='folder'">{{ row.files_count ?? '-' }} 项</span>
                <span v-else>{{ formatSize(row.size) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="上传者" width="100">
              <template #default="{ row }">{{ row.uploader_name || row.creator_name || '-' }}</template>
            </el-table-column>
            <el-table-column label="修改时间" width="160">
              <template #default="{ row }">{{ formatTime(row.updated_at || row.created_at) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="180" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.type!=='folder'" text type="primary" size="small" @click="handleDownload(row)">下载</el-button>
                <el-button text type="primary" size="small" @click="handleRename(row)">重命名</el-button>
                <el-button text type="danger" size="small" :disabled="row.type==='folder' && !!row.system_type" @click="handleDelete(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
          <el-empty v-if="!loading && tableData.length===0" :description="currentFolder ? '该文件夹为空，点击右上角上传文件' : '请在左侧选择文件夹'" />
        </div>
      </div>
    </div>

    <!-- 新建文件夹对话框 -->
    <FolderDialog
      v-model:visible="showFolderDialog"
      :title="`新建文件夹${currentFolder ? '（在 ' + currentFolder.name + ' 下）' : ''}`"
      v-model:name="newFolderName"
      :creating="creating"
      @confirm="handleCreateFolder"
    />

    <!-- 重命名对话框 -->
    <RenameDialog
      v-model:visible="showRenameDialog"
      v-model:value="renameValue"
      @confirm="confirmRename"
    />

    <!-- 上传进度 -->
    <UploadProgressDialog
      v-model:visible="showUploading"
      :queue="uploadQueue"
      @close="showUploading=false;uploadQueue.length=0"
      @cancel="cancelUpload"
      @cancel-all="cancelAllUploads"
      @retry="retryUpload"
    />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Upload, FolderAdd, Folder, Document, Picture, VideoCamera, Headset, Files,
  Search, Refresh, HomeFilled, Plus, InfoFilled, MoreFilled,
  User, Share, Loading
} from '@element-plus/icons-vue'
import { get, post, put, del } from '@/utils/request'
import { unwrapList } from '@/utils/response'
import axios from 'axios'
import FolderDialog from './components/FolderDialog.vue'
import RenameDialog from './components/RenameDialog.vue'
import UploadProgressDialog from './components/UploadProgressDialog.vue'

// ===== 初始化相关 =====
const initialized = ref(false)
const checkingInit = ref(true)
const initMode = ref<'auto' | 'manual'>('auto')
const manualPath = ref('')
const pathValid = ref<boolean | null>(null)
const initLoading = ref(false)
const diskList = ref<DiskItemInfo[]>([])

interface DiskItemInfo {
  device: string
  mount: string
  total: number
  used: number
  avail: number
  pcent: string
  total_fmt: string
  avail_fmt: string
}

// 磁盘进度百分比
function diskPercent(d: DiskItemInfo) {
  if (d.total === 0) return 0
  return Math.round((d.used / d.total) * 100)
}
function diskColor(d: DiskItemInfo) {
  const p = diskPercent(d)
  if (p > 90) return '#A32D2D'
  if (p > 70) return '#BA7517'
  return '#1D9E75'
}

// 手动路径校验（输入后延迟检查）
watch(manualPath, (val: string) => {
  if (!val) { pathValid.value = null; return }
  pathValid.value = null
  // 简单校验存不存在
})

async function checkInit() {
  checkingInit.value = true
  try {
    const res = await get('/disk/stats')
    const d = (res as { data?: { initialized?: boolean } })?.data ?? res
    initialized.value = !!(d as { initialized?: boolean })?.initialized
    if (!initialized.value) {
      // 预加载磁盘列表
      try {
        const dres = await get('/disk/disk-list')
        const dd = (dres as { data?: DiskItemInfo[] })?.data ?? []
        diskList.value = Array.isArray(dd) ? dd : []
      } catch { /* */ }
    }
  } catch {
    initialized.value = false
  } finally {
    checkingInit.value = false
  }
}

async function handleInit() {
  initLoading.value = true
  try {
    const payload: Record<string, unknown> = {}
    if (initMode.value === 'auto') {
      payload.auto_detect = true
    } else {
      payload.storage_path = manualPath.value.trim()
      payload.auto_detect = false
    }
    const res = await post('/disk/init', payload)
    if ((res as { code?: number })?.code === 0) {
      ElMessage.success('网盘初始化成功！三个根文件夹（项目/工作/公共）已创建')
      initialized.value = true
      loadTree()
      loadData()
    } else {
      ElMessage.error((res as { message?: string })?.message || '初始化失败')
    }
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '初始化失败，请检查目录权限')
  } finally {
    initLoading.value = false
  }
}

// ===== 原有代码 =====
interface DiskItem {
  id: number
  name: string
  original_name?: string
  type?: 'folder' | 'file'
  extension?: string
  size?: number
  files_count?: number
  uploader_name?: string
  creator_name?: string
  updated_at?: string
  created_at?: string
  system_type?: string
  is_protected?: boolean
  is_system?: boolean
  scope?: string
  parent_id?: number | null
  children?: DiskItem[]
  [key: string]: unknown
}

// 上传队列项
interface UploadItem {
  id: string
  name: string
  size: number
  progress: number
  status: 'uploading' | 'done' | 'error' | 'canceled'
  error: string
  speed: string
  eta: string
  _file: File
}

// 上传接口响应
interface UploadResponse {
  id?: number
  file_id?: number
  success?: boolean
  code?: number
  data?: { id?: number }
  message?: string
}

// API 错误（catch 块统一类型）
interface ApiError {
  message?: string
  name?: string
  code?: string
  response?: { data?: { message?: string } }
}

// 上传进度事件（axios onUploadProgress 回调）
interface UploadProgressEvent {
  loaded: number
  total?: number
}

const fileInput = ref<HTMLInputElement>()
const treeRef = ref()
const loading = ref(false)
const keyword = ref('')

// 当前选中文件夹
const currentFolder = ref<DiskItem | null>(null)
const breadcrumb = ref<DiskItem[]>([])
const tableData = ref<DiskItem[]>([])

// 整棵文件夹树
const folderTree = ref<DiskItem[]>([])

// 新建文件夹
const showFolderDialog = ref(false)
const newFolderName = ref('')
const creating = ref(false)

// 重命名
const showRenameDialog = ref(false)
const renameValue = ref('')
const renameTarget = ref<DiskItem | null>(null)

// 上传
const showUploading = ref(false)
// item 结构: { id, name, size, progress, status, error, speed, eta, _abort }
// 用 reactive() 包装确保 progress 赋值触发响应式
const uploadQueue = reactive<UploadItem[]>([])
// file.id -> AbortController 的 Map (按文件独立中断)
const uploadCtrls = new Map<string, AbortController>()
// 每个 item 独立递增的序列号
let uploadSeq = 0

function fileColor(ext: string) {
  const m: Record<string, string> = { pdf: '#A32D2D', doc: '#185FA5', docx: '#185FA5', xls: '#1D9E75', xlsx: '#1D9E75', jpg: '#D85A30', png: '#D85A30', gif: '#D85A30', mp4: '#534AB7', zip: '#909399' }
  return m[ext?.toLowerCase()] || '#909399'
}

function formatSize(bytes: number): string {
  if (!bytes && bytes !== 0) return '-'
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB'
  if (bytes < 1024*1024*1024) return (bytes/1024/1024).toFixed(1) + ' MB'
  return (bytes/1024/1024/1024).toFixed(2) + ' GB'
}

function formatTime(s?: string) {
  if (!s) return '-'
  return s.slice(0, 16).replace('T', ' ')
}

// 系统文件夹类型
const SYSTEM_LABELS: Record<string, { label: string; type: string }> = {
  project_root: { label: '项目根', type: 'primary' },
  work:         { label: '工作',   type: 'success' },
  share:        { label: '公共',   type: 'info' },
  project_doc:  { label: '项目文档', type: 'warning' },
}
function systemLabel(t: string) { return SYSTEM_LABELS[t]?.label || t }
function systemTag(t: string): string { return SYSTEM_LABELS[t]?.type || 'info' }

// 是否受保护（根目录不可改/删）
function isProtected(f: DiskItem | null | undefined) { return !!f?.is_protected }
function isSystemRoot(f: DiskItem | null | undefined) { return ['project_root','work_root','share_root'].includes(f?.scope || '') }

async function loadTree() {
  try {
    // V1.0: 用 /disk/tree 返回 3 个根（已按权限过滤）
    const res = await get('/disk/tree')
    folderTree.value = unwrapList(res) as DiskItem[]
  } catch (e) {
    folderTree.value = []
  }
}

async function loadData() {
  loading.value = true
  try {
    const [folRes, fileRes] = await Promise.all([
      get('/disk/folders', { parent_id: currentFolder.value?.id ?? null }),
      get('/disk/files', {
        folder_id: currentFolder.value?.id ?? null,
        ...(keyword.value ? { keyword: keyword.value } : {})
      })
    ])
    const folders = ((folRes.data || folRes || []) as DiskItem[]).map((f: DiskItem) => ({ ...f, type: 'folder' as const }))
    const filesRaw = (fileRes as { data?: { data?: { items?: DiskItem[] } | DiskItem[] } | DiskItem[] })?.data?.data?.items || (fileRes as { data?: { data?: DiskItem[] } })?.data?.data || (fileRes as { data?: DiskItem[] })?.data || []
    const files = (Array.isArray(filesRaw) ? filesRaw : []).map((f: DiskItem) => ({ ...f, type: 'file' as const }))
    tableData.value = [...folders, ...files]
  } catch (e) {
    tableData.value = []
  } finally {
    loading.value = false
  }
}

function handleTreeClick(data: DiskItem) {
  if (data.is_system) {
    // 系统根文件夹，可点击进入
  }
  currentFolder.value = data
  breadcrumb.value = buildBreadcrumb(data)
  loadData()
}

function buildBreadcrumb(target: DiskItem): DiskItem[] {
  const map = new Map<number, DiskItem>()
  function collect(nodes: DiskItem[]) {
    for (const n of nodes) {
      map.set(n.id, n)
      if (n.children?.length) collect(n.children)
    }
  }
  collect(folderTree.value)
  const path: DiskItem[] = []
  let cur = map.get(target.id)
  while (cur && cur.parent_id) {
    const p = map.get(cur.parent_id)
    if (p) { path.unshift(p); cur = p } else break
  }
  return path
}

function goToFolder(row: DiskItem) {
  if (row.type !== 'folder') return
  currentFolder.value = row
  breadcrumb.value = buildBreadcrumb(row)
  loadData()
  // 同步展开树
  treeRef.value?.setCurrentKey(row.id)
}

function goHome() {
  currentFolder.value = null
  breadcrumb.value = []
  loadData()
  treeRef.value?.setCurrentKey(null)
}

function refreshAll() {
  loadTree()
  loadData()
}

async function handleCreateFolder() {
  if (!newFolderName.value.trim()) { ElMessage.warning('请输入文件夹名称'); return }
  creating.value = true
  try {
    await post('/disk/folders', {
      name: newFolderName.value.trim(),
      parent_id: currentFolder.value?.id ?? null
    })
    ElMessage.success('文件夹创建成功')
    showFolderDialog.value = false
    newFolderName.value = ''
    await loadTree()
    await loadData()
  } catch (e) {
    ElMessage.error((e as ApiError)?.message || '创建失败')
  } finally {
    creating.value = false
  }
}

function handleRename(row: DiskItem) {
  renameTarget.value = row
  renameValue.value = row.name
  showRenameDialog.value = true
}

async function confirmRename() {
  if (!renameValue.value.trim()) { ElMessage.warning('名称不能为空'); return }
  try {
    if (renameTarget.value.type === 'folder') {
      await put(`/disk/folders/${renameTarget.value.id}`, { name: renameValue.value.trim() })
    } else {
      await put(`/disk/files/${renameTarget.value.id}`, { name: renameValue.value.trim() })
    }
    ElMessage.success('已重命名')
    showRenameDialog.value = false
    refreshAll()
  } catch (e) {
    ElMessage.error((e as ApiError)?.message || '重命名失败')
  }
}

function triggerUpload() { fileInput.value?.click() }

async function uploadOne(file: File, item: UploadItem) {
  const ctrl = new AbortController()
  uploadCtrls.set(item.id, ctrl)
  // 速度/剩余时间估算
  let lastLoaded = 0
  let lastTime = Date.now()
  const onProgress = (ev: UploadProgressEvent) => {
    if (ev.total) {
      item.progress = Math.round((ev.loaded / ev.total) * 100)
      const now = Date.now()
      const dt = (now - lastTime) / 1000
      if (dt > 0.3) {
        const speed = (ev.loaded - lastLoaded) / dt // bytes/sec
        if (speed > 0) {
          const remain = (ev.total - ev.loaded) / speed
          item.speed = speed > 1024 * 1024 ? `${(speed / 1024 / 1024).toFixed(1)} MB/s` : `${(speed / 1024).toFixed(0)} KB/s`
          item.eta = remain > 60 ? `${Math.ceil(remain / 60)} 分钟` : `${Math.ceil(remain)} 秒`
        }
        lastLoaded = ev.loaded
        lastTime = now
      }
    }
  }
  try {
    const fd = new FormData()
    fd.append('file', file)
    fd.append('folder_id', String(currentFolder.value.id))
    const res = await post<UploadResponse>('/disk/upload', fd, {
      signal: ctrl.signal,
      onUploadProgress: onProgress,
    })
    if (res && (res.id || res.file_id || res.success || (res.code === 0 && res.data?.id))) {
      item.status = 'done'; item.progress = 100
    } else {
      item.status = 'error'; item.error = res?.message || '上传失败'
    }
  } catch (e) {
    if (axios.isCancel?.(e) || (e as ApiError)?.name === 'CanceledError' || (e as ApiError)?.code === 'ERR_CANCELED') {
      item.status = 'canceled'
    } else {
      item.status = 'error'
      item.error = (e as ApiError)?.response?.data?.message || (e as ApiError)?.message || '上传失败'
    }
  } finally {
    uploadCtrls.delete(item.id)
  }
}

function cancelUpload(id: string) {
  const ctrl = uploadCtrls.get(id)
  if (ctrl) ctrl.abort()
}
function cancelAllUploads() {
  uploadCtrls.forEach(c => c.abort())
}

async function handleFileSelected(e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (!files || files.length === 0) return
  if (!currentFolder.value) {
    ElMessage.warning('请先在左侧选择目标文件夹')
    return
  }
  showUploading.value = true
  // 准备 item (reactive 数组, 单独 reactive 对象)
  const items = Array.from(files).map(file => {
    const it: UploadItem = reactive({
      id: `up-${++uploadSeq}-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
      name: file.name,
      size: file.size,
      progress: 0,
      status: 'uploading',
      error: '',
      speed: '',
      eta: '',
      _file: file, // 重试用
    })
    uploadQueue.push(it)
    return it
  })
  // 并行上传 (每个文件独立, 互不阻塞)
  await Promise.all(items.map(it => uploadOne(it._file, it)))
  await loadData()
  // 清空 input 允许同名文件再选
  if (fileInput.value) fileInput.value.value = ''
}

function retryUpload(id: string) {
  const it = uploadQueue.find((x: UploadItem) => x.id === id)
  if (it?._file) {
    it.progress = 0; it.error = ''; it.status = 'uploading'; it.speed = ''; it.eta = ''
    uploadOne(it._file, it)
  }
}

async function handleDownload(row: DiskItem) {
  try {
    // V1.0: 用后端 /disk/files/{id}/download 流式下载
    const token = localStorage.getItem('token') || ''
    const res = await fetch(`/api/disk/files/${row.id}/download`, {
      headers: { Authorization: 'Bearer ' + token },
    })
    if (!res.ok) {
      const j = await res.json().catch(() => ({}))
      ElMessage.error((j as { message?: string })?.message || '下载失败')
      return
    }
    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = row.original_name || row.name
    document.body.appendChild(a); a.click(); a.remove()
    URL.revokeObjectURL(url)
    ElMessage.success('已下载：' + (row.original_name || row.name))
  } catch (e) {
    ElMessage.error((e as ApiError)?.message || '下载失败')
  }
}

async function handleDelete(row: DiskItem) {
  if (row.type === 'folder' && (row.is_protected || isSystemRoot(row))) {
    ElMessage.warning('系统根目录不可删除')
    return
  }
  try {
    await ElMessageBox.confirm(`确认删除「${row.name}」？${row.type === 'folder' ? '文件夹内所有内容也会被删除' : ''}`, '删除确认', { type: 'warning' })
  } catch { return }
  try {
    if (row.type === 'folder') {
      await del(`/disk/folders/${row.id}`)
    } else {
      await del(`/disk/files/${row.id}`)
    }
    ElMessage.success('已删除')
    refreshAll()
  } catch (e) {
    ElMessage.error((e as ApiError)?.message || '删除失败')
  }
}

async function onTreeAction(cmd: string, data: DiskItem) {
  if (cmd === 'rename') {
    if (data.is_protected) { ElMessage.warning('受保护文件夹不可重命名'); return }
    handleRename(data); return
  }
  if (cmd === 'delete') {
    if (data.is_protected || isSystemRoot(data)) { ElMessage.warning('系统根目录不可删除'); return }
    handleDelete(data); return
  }
}

// 快速跳到根（project / work / share）
function quickGoRoot(scope: string) {
  const root = folderTree.value.find((r: DiskItem) => r.scope === scope)
  if (root) {
    currentFolder.value = root
    breadcrumb.value = []
    loadData()
    treeRef.value?.setCurrentKey(root.id)
  } else {
    ElMessage.warning('该根目录尚未初始化')
  }
}

onMounted(async () => {
  await checkInit()
  if (initialized.value) {
    loadTree()
    loadData()
  }
})
</script>

<style lang="scss" scoped>
$primary: #0C447C;
$success: #1D9E75;
$warning: #BA7517;
$danger: #A32D2D;

/* ===== 初始化向导 ===== */
.init-wizard {
  display: flex; align-items: center; justify-content: center; min-height: 80vh;
  &__card { max-width: 680px; width: 100%; background: #fff; border-radius: 20px; padding: 48px 40px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); text-align: center; }
  &__icon { font-size: 56px; margin-bottom: 8px; }
  &__title { font-size: 26px; color: #1f2937; margin-bottom: 8px; }
  &__desc { font-size: 14px; color: #6b7280; line-height: 1.6; margin-bottom: 32px; }
  &__options { text-align: left; display: flex; flex-direction: column; gap: 16px; }
  &__actions { margin-top: 32px; }
}

.init-option {
  border: 2px solid #e5e7eb; border-radius: 12px; padding: 20px; cursor: pointer; transition: all .2s;
  &:hover { border-color: #93c5fd; }
  &.active { border-color: $primary; background: #f0f7ff; }
  :deep(.el-radio) { margin-bottom: 8px; }
  :deep(.el-radio__label) { font-size: 16px; font-weight: 600; color: #1f2937; }
  &__desc { font-size: 13px; color: #6b7280; margin: 4px 0 12px 0; }
  &__disks { display: flex; flex-direction: column; gap: 8px;
    &.loading { display: flex; align-items: center; gap: 8px; color: #6b7280; font-size: 13px; padding: 12px; }
  }
  &__manual { margin-top: 12px; }
}

.disk-item {
  background: #f9fafb; border-radius: 8px; padding: 10px 14px; font-size: 13px;
  &__info { display: flex; justify-content: space-between; margin-bottom: 4px; }
  &__mount { font-weight: 600; color: #1f2937; }
  &__device { color: #9ca3af; font-size: 12px; }
  &__size { font-size: 12px; color: #6b7280; margin-top: 4px; }
  &.recommended { display: flex; align-items: center; gap: 8px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; font-size: 13px; }
}

.path-hint { margin-top: 8px; }

.page-container { padding: 20px; background: linear-gradient(180deg, #f0f4fa 0%, #f5f7fa 100%); min-height: 100vh; }

.page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; padding:18px 20px; background:#fff; border-radius:12px; box-shadow:0 1px 4px rgba(0,0,0,0.04);
  .page-title-wrap { display: flex; align-items: baseline; gap: 12px; }
  .page-title { font-size:20px; color:$primary; font-weight:600; }
  .page-desc { font-size:13px; color:#6b7280; }
}

.disk-body { display:grid; grid-template-columns: 260px 1fr; gap:16px; min-height: 600px; }

.disk-side { background:#fff; border-radius:12px; padding:16px; box-shadow:0 1px 4px rgba(0,0,0,0.04); display:flex; flex-direction:column;
  &__head { display:flex; justify-content:space-between; align-items:center; font-size:14px; font-weight:600; color:#374151; padding-bottom:12px; border-bottom:1px solid #f0f0f0; }
  &__add { cursor:pointer; padding:4px; border-radius:4px; transition:background .2s; &:hover { background:#f0f4fa; color:$primary; } }
  &__chips { display:flex; gap:6px; padding:10px 0; flex-wrap:wrap; .chip { cursor:pointer; font-size:12px; padding: 2px 10px; transition: all .2s; &:hover { transform: translateY(-1px); } } }
  &__tree { flex:1; margin-top:8px; overflow:auto; }
  &__hint { padding:10px 12px; background:#f9fafb; border-radius:6px; font-size:12px; color:#6b7280; line-height:1.8; display:flex; gap:6px;
    :deep(.el-icon) { margin-top:2px; color:$warning; }
  }
}

.tree-node { display:flex; align-items:center; gap:6px; padding:4px 6px; border-radius:4px; cursor:pointer; font-size:13px; transition: background .2s; width:100%;
  &--root { font-weight:500; color:#374151; padding:8px 10px; }
  &--custom { color:#374151; }
  &.active { background:#e6f0fa !important; color:$primary; font-weight:600; }
  &:hover { background:#f5f7fa; }
  &__name { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  &__tag { flex-shrink:0; }
  &__more { padding:2px; border-radius:3px; color:#9ca3af; opacity:0; transition: opacity .2s; .tree-node:hover & { opacity:1; } &:hover { background:#e5e7eb; color:$primary; } }
}

.disk-main { display:flex; flex-direction:column; gap:12px; }
.disk-toolbar { display:flex; justify-content:space-between; align-items:center; padding:12px 20px; background:#fff; border-radius:12px; box-shadow:0 1px 4px rgba(0,0,0,0.04); }
.content-card { background:#fff; border-radius:12px; padding:20px; box-shadow:0 1px 4px rgba(0,0,0,0.04); flex:1; }

.file-name { display:flex; align-items:center; gap:8px;
  &__text { color:#1f2937; }
  &.is-folder { cursor:pointer; .file-name__text { color:$primary; font-weight:500; } }
  &.is-folder:hover .file-name__text { text-decoration:underline; }
}

:deep(.el-tree-node__content) { height: 32px; }
:deep(.el-tree-node__content:hover) { background: transparent; }
:deep(.el-tree-node.is-current > .el-tree-node__content) { background: transparent; }
</style>
