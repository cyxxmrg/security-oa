<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">班组管理</span>
      <div class="header-actions">
        <el-button type="primary" :icon="Plus" @click="openCreate">+ 新建班组</el-button>
      </div>
    </div>

    <el-alert
      title="班组说明"
      type="info"
      :closable="false"
      show-icon
      style="margin-bottom: 16px"
    >
      <template #default>
        班组是排班的载体, 给一组员工批量设置排班。先创建班组 + 添加成员, 然后在「排班计划」里给整个组排班。
      </template>
    </el-alert>

    <div class="content-grid">
      <div
        v-for="g in list"
        :key="g.id"
        class="group-card"
        :style="{ borderTopColor: g.color || '#0C447C' }"
      >
        <div class="group-card__head">
          <div class="group-card__title">
            <span class="dot" :style="{ background: g.color }"></span>
            <span class="name">{{ g.name }}</span>
            <el-tag v-if="!g.is_active" type="info" size="small">已停用</el-tag>
          </div>
          <div class="group-card__actions">
            <el-button link size="small" @click="openEdit(g)">编辑</el-button>
            <el-button link size="small" @click="openMembers(g)">成员 ({{ g.members_count }})</el-button>
            <el-button link size="small" type="danger" @click="handleDelete(g)">删除</el-button>
          </div>
        </div>
        <div class="group-card__meta">
          <span>编码: <b>{{ g.code }}</b></span>
          <span>组长:
            <template v-if="g.leader">
              <el-tag size="small">{{ g.leader.name }}</el-tag>
            </template>
            <template v-else>
              <span style="color:#c0c4cc">未指定</span>
            </template>
          </span>
        </div>
        <div class="group-card__desc" v-if="g.description">{{ g.description }}</div>
        <div class="group-card__members" v-if="g.members?.length">
          <el-tag
            v-for="m in g.members?.slice(0, 8)"
            :key="m.id"
            size="small"
            effect="plain"
            class="member-tag"
          >{{ m.user?.name || m.user?.username }}</el-tag>
          <el-tag v-if="(g.members?.length ?? 0) > 8" size="small" type="info">+{{ (g.members?.length ?? 0) - 8 }}</el-tag>
        </div>
      </div>
      <div v-if="!loading && list.length === 0" class="empty-state">
        <el-empty description="暂无班组, 点击右上角新建" />
      </div>
    </div>

    <!-- 编辑对话框 -->
    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑班组' : '新建班组'" width="1500px" destroy-on-close>
      <el-form :model="form" :rules="rules" ref="formRef" label-width="90px">
        <el-form-item label="班组名" prop="name">
          <el-input v-model="form.name" maxlength="50" />
        </el-form-item>
        <el-form-item label="编码" prop="code">
          <el-input v-model="form.code" maxlength="20" />
        </el-form-item>
        <el-form-item label="班组长">
          <el-select v-model="form.leader_id" placeholder="选择班组长" clearable filterable style="width: 100%">
            <el-option
              v-for="u in userOptions"
              :key="u.id"
              :label="u.name || u.username"
              :value="u.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="显示色">
          <el-color-picker v-model="form.color" />
        </el-form-item>
        <el-form-item label="说明">
          <el-input v-model="form.description" type="textarea" :rows="2" maxlength="500" show-word-limit />
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="form.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="confirmSave">保存</el-button>
      </template>
    </el-dialog>

    <!-- 成员管理对话框 -->
    <el-dialog v-model="membersDialogVisible" :title="`班组成员 - ${currentGroup?.name || ''}`" width="1500px" destroy-on-close>
      <div class="members-toolbar">
        <el-button type="primary" :icon="Plus" size="small" @click="openAddMember">+ 添加成员</el-button>
        <el-button size="small" @click="syncAllMembers">同步全部 (覆盖)</el-button>
        <span class="members-toolbar__count">已选 {{ selectedUserIds.length }} 人</span>
      </div>
      <el-checkbox-group v-model="selectedUserIds" class="members-grid">
        <el-checkbox
          v-for="u in userOptions"
          :key="u.id"
          :value="u.id"
          :label="u.id"
          border
          class="member-checkbox"
        >
          {{ u.name || u.username }}
        </el-checkbox>
      </el-checkbox-group>
      <template #footer>
        <el-button @click="membersDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="membersSubmitting" @click="confirmSyncMembers">保存成员</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, nextTick } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { schedule } from '@/api/modules'
import { get } from '@/utils/request'

interface GroupMember {
  id?: number
  user_id?: number
  user?: { id?: number; name?: string; username?: string } | null
  [key: string]: unknown
}

interface GroupLeader {
  id?: number
  name?: string
  username?: string
  [key: string]: unknown
}

interface WorkGroup {
  id?: number
  name?: string
  code?: string
  color?: string
  description?: string
  is_active?: boolean
  members_count?: number
  leader?: GroupLeader | null
  members?: GroupMember[]
  [key: string]: unknown
}

interface UserOption {
  id: number
  name?: string
  username?: string
  [key: string]: unknown
}

interface GroupForm {
  id: number | null
  name: string
  code: string
  leader_id: number | null
  color: string
  description: string
  is_active: boolean
  [key: string]: unknown
}

const list = ref<WorkGroup[]>([])
const userOptions = ref<UserOption[]>([])
const loading = ref(false)
const submitting = ref(false)

const dialogVisible = ref(false)
const formRef = ref()
const form = reactive<GroupForm>({
  id: null, name: '', code: '', leader_id: null, color: '#1D9E75',
  description: '', is_active: true,
})
const rules = {
  name: [{ required: true, message: '请输入班组名', trigger: 'blur' }],
  code: [{ required: true, message: '请输入编码', trigger: 'blur' }],
}

const membersDialogVisible = ref(false)
const membersSubmitting = ref(false)
const currentGroup = ref<WorkGroup | null>(null)
const selectedUserIds = ref<number[]>([])

const load = async () => {
  loading.value = true
  try {
    const r = await schedule.listGroups()
    // V0.6.3: res = {code, data: <groups>} 可能是 array
    const d = r?.data ?? r
    list.value = Array.isArray(d) ? d : (Array.isArray(d?.data) ? d.data : [])
  } catch (e: unknown) { ElMessage.error((e as { message?: string })?.message || '加载失败') }
  finally { loading.value = false }
}

const loadUsers = async () => {
  try {
    const r = await get('/employees', { per_page: 200 })
    // V0.6.3: res = {code, data: paginator}
    const d = r?.data ?? {}
    userOptions.value = Array.isArray(d?.data) ? d.data : (Array.isArray(d) ? d : [])
  } catch { /* 静默 */ }
}

const openCreate = () => {
  Object.assign(form, {
    id: null, name: '', code: '', leader_id: null, color: '#1D9E75',
    description: '', is_active: true,
  })
  dialogVisible.value = true
  nextTick(() => formRef.value?.clearValidate())
}

const openEdit = (row: WorkGroup) => {
  Object.assign(form, row)
  dialogVisible.value = true
  nextTick(() => formRef.value?.clearValidate())
}

const confirmSave = async () => {
  if (!formRef.value) return
  try { await formRef.value.validate() } catch { return }
  submitting.value = true
  try {
    const r = form.id
      ? await schedule.updateGroup(form.id, form)
      : await schedule.createGroup(form)
    ElMessage.success(r?.message || '保存成功')
    dialogVisible.value = false
    load()
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message || (e as { message?: string })?.message || '保存失败')
  } finally { submitting.value = false }
}

const handleDelete = async (row: WorkGroup) => {
  try {
    await ElMessageBox.confirm(`确认删除班组「${row.name}」?`, '提示', { type: 'warning' })
    const r = await schedule.deleteGroup(row.id as number)
    ElMessage.success(r?.message || '已删除')
    load()
  } catch (e: unknown) {
    if (e !== 'cancel' && (e as { message?: string })?.message) ElMessage.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message || (e as { message?: string })?.message)
  }
}

const openMembers = (row: WorkGroup) => {
  currentGroup.value = row
  selectedUserIds.value = (row.members || []).map((m: GroupMember) => m.user_id ?? 0)
  membersDialogVisible.value = true
}

const openAddMember = async () => {
  if (!currentGroup.value) return
  try {
    const { value } = await ElMessageBox.prompt('输入要添加的员工 ID', '添加成员', { inputPattern: /^\d+$/, inputErrorMessage: '请输入数字 ID' })
    const r = await schedule.addMember(currentGroup.value.id as number, Number(value))
    ElMessage.success(r?.message || '已添加')
    load()
  } catch (e: unknown) {
    if (e !== 'cancel' && (e as { message?: string })?.message) ElMessage.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message || (e as { message?: string })?.message)
  }
}

const syncAllMembers = () => {
  // 直接触发保存按钮
  confirmSyncMembers()
}

const confirmSyncMembers = async () => {
  if (!currentGroup.value) return
  membersSubmitting.value = true
  try {
    const r = await schedule.syncMembers(currentGroup.value.id as number, selectedUserIds.value)
    ElMessage.success(r?.message || '成员已更新')
    membersDialogVisible.value = false
    load()
  } catch (e: unknown) {
    ElMessage.error((e as { response?: { data?: { message?: string } } })?.response?.data?.message || (e as { message?: string })?.message || '保存失败')
  } finally { membersSubmitting.value = false }
}

onMounted(() => { load(); loadUsers() })
</script>

<style scoped lang="scss">
.page-container { padding: 20px; background: #f5f7fa; min-height: 100%; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; .page-title { font-size: 20px; font-weight: 600; color: #303133; } }
.content-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 16px; }
.group-card { background: #fff; border-radius: 8px; padding: 16px 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); border-top: 4px solid #0C447C; transition: all 0.2s;
  &:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: translateY(-2px); }
  &__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
  &__title { display: flex; align-items: center; gap: 8px; .dot { width: 10px; height: 10px; border-radius: 50%; } .name { font-size: 16px; font-weight: 600; color: #303133; } }
  &__actions { display: flex; gap: 4px; }
  &__meta { display: flex; gap: 16px; font-size: 13px; color: #606266; margin-bottom: 8px; }
  &__desc { font-size: 13px; color: #909399; margin-bottom: 10px; line-height: 1.5; }
  &__members { display: flex; flex-wrap: wrap; gap: 4px; }
}
.member-tag { font-size: 12px; }
.empty-state { grid-column: 1 / -1; }
.members-toolbar { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; &__count { margin-left: auto; color: #909399; font-size: 13px; } }
.members-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; max-height: 400px; overflow-y: auto; }
.member-checkbox { margin-right: 0; }
:deep(.el-checkbox.is-bordered) { margin-right: 0; }
</style>
