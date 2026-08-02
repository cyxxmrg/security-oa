<template>
  <div class="page-container">
    <div class="page-header">
      <span class="page-title">班次配置</span>
      <div class="header-actions">
        <el-button type="primary" :icon="Plus" @click="openCreate">+ 新建班次</el-button>
      </div>
    </div>

    <el-alert
      title="班次说明"
      type="info"
      :closable="false"
      show-icon
      style="margin-bottom: 16px"
    >
      <template #default>
        配置员工排班使用的班次模板。下班时间早于上班时间自动识别为跨夜班（如 22:00-06:00）。<br>
        <b>迟到阈值</b>：超过 start_time 多少分钟算迟到；<b>早退阈值</b>：早于 end_time 多少分钟算早退。默认值是 5 分钟。<br>
        <span style="color:#A32D2D; font-weight:600">标记"系统默认"的班次</span>是入职自动分配的班次（早 8 点 - 下午 5 点），<b>不可删除</b>，但可以手动修改时间/阈值。
      </template>
    </el-alert>

    <div class="content-card">
      <el-table :data="list" stripe style="width: 100%" v-loading="loading" empty-text="暂无班次">
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column label="班次名" min-width="140">
          <template #default="{ row }">
            <el-tag :color="row.color" effect="dark" style="color: #fff; border: none">
              {{ row.name }}
            </el-tag>
            <el-tag v-if="row.is_overnight" type="warning" size="small" style="margin-left: 6px">跨夜</el-tag>
            <el-tag v-if="row.is_default" type="danger" size="small" style="margin-left: 6px">系统默认</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="code" label="编码" width="120" />
        <el-table-column label="时间范围" min-width="160">
          <template #default="{ row }">
            <span style="font-family: monospace; font-size: 15px">
              {{ row.start_time?.slice(0,5) }} ~ {{ row.end_time?.slice(0,5) }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="工时" width="80" align="center">
          <template #default="{ row }">{{ row.work_hours }}h</template>
        </el-table-column>
        <el-table-column label="迟到/早退" width="120" align="center">
          <template #default="{ row }">
            <span style="color:#BA7517">迟 {{ row.late_threshold_minutes }}m</span>
            <el-divider direction="vertical" />
            <span style="color:#A32D2D">早 {{ row.early_leave_threshold_minutes }}m</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
              {{ row.is_active ? '启用' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="openEdit(row)">编辑</el-button>
            <el-button type="danger" link size="small" :disabled="row.is_default" @click="handleDelete(row)">
              {{ row.is_default ? '不可删' : '删除' }}
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- 编辑对话框 -->
    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑班次' : '新建班次'" width="1500px" destroy-on-close>
      <el-form :model="form" :rules="rules" ref="formRef" label-width="100px">
        <el-form-item label="班次名" prop="name">
          <el-input v-model="form.name" placeholder="如：早班" maxlength="50" />
        </el-form-item>
        <el-form-item label="编码" prop="code">
          <el-input v-model="form.code" placeholder="如：day/middle/night" maxlength="20" />
        </el-form-item>
        <el-form-item label="上班时间" prop="start_time">
          <el-time-picker v-model="form.start_time" value-format="HH:mm:ss" format="HH:mm:ss" placeholder="选择时间" style="width: 100%" />
        </el-form-item>
        <el-form-item label="下班时间" prop="end_time">
          <el-time-picker v-model="form.end_time" value-format="HH:mm:ss" format="HH:mm:ss" placeholder="选择时间" style="width: 100%" />
          <span v-if="isOvernight" style="margin-left: 8px; color: #BA7517; font-size: 12px">⚠ 跨夜班</span>
        </el-form-item>
        <el-form-item label="标准工时">
          <el-input-number v-model="form.work_hours" :min="0" :max="24" :step="0.5" :precision="1" />
          <span style="margin-left: 8px; color: #909399; font-size: 12px">小时</span>
        </el-form-item>
        <el-form-item label="迟到阈值">
          <el-input-number v-model="form.late_threshold_minutes" :min="0" :max="120" />
          <span style="margin-left: 8px; color: #909399; font-size: 12px">分钟</span>
        </el-form-item>
        <el-form-item label="早退阈值">
          <el-input-number v-model="form.early_leave_threshold_minutes" :min="0" :max="120" />
          <span style="margin-left: 8px; color: #909399; font-size: 12px">分钟</span>
        </el-form-item>
        <el-form-item label="显示色">
          <el-color-picker v-model="form.color" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" :max="999" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" type="textarea" :rows="2" maxlength="500" show-word-limit />
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
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { schedule } from '@/api/modules'
import type { Shift, ErrorWithMessage, ErrorWithResponse } from './types'

const list = ref<Shift[]>([])
const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const formRef = ref<FormInstance | null>(null)
const form = reactive<Shift>({
  id: null, name: '', code: '', start_time: '09:00:00', end_time: '18:00:00',
  work_hours: 8.0, late_threshold_minutes: 5, early_leave_threshold_minutes: 5,
  color: '#0C447C', sort_order: 0, remark: '', is_active: true,
})
const rules: FormRules = {
  name: [{ required: true, message: '请输入班次名', trigger: 'blur' }],
  code: [{ required: true, message: '请输入编码', trigger: 'blur' }],
  start_time: [{ required: true, message: '请选择上班时间', trigger: 'change' }],
  end_time: [{ required: true, message: '请选择下班时间', trigger: 'change' }],
}

const isOvernight = computed(() => form.end_time && form.start_time && form.end_time < form.start_time)

const load = async () => {
  loading.value = true
  try {
    const r = await schedule.listShifts()
    // V0.6.3: res = {code, data: <shifts>} 可能是 array
    const d = r?.data ?? r
    list.value = Array.isArray(d) ? d : (Array.isArray(d?.data) ? d.data : [])
  } catch (e: unknown) { ElMessage.error((e as ErrorWithMessage)?.message || '加载失败') }
  finally { loading.value = false }
}

const openCreate = () => {
  Object.assign(form, {
    id: null, name: '', code: '', start_time: '09:00:00', end_time: '18:00:00',
    work_hours: 8.0, late_threshold_minutes: 5, early_leave_threshold_minutes: 5,
    color: '#0C447C', sort_order: 0, remark: '', is_active: true,
  })
  dialogVisible.value = true
  nextTick(() => formRef.value?.clearValidate())
}

const openEdit = (row: Shift) => {
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
      ? await schedule.updateShift(form.id, form)
      : await schedule.createShift(form)
    ElMessage.success(r?.message || '保存成功')
    dialogVisible.value = false
    load()
  } catch (e: unknown) {
    ElMessage.error((e as ErrorWithResponse)?.response?.data?.message || (e as ErrorWithMessage)?.message || '保存失败')
  } finally { submitting.value = false }
}

const handleDelete = async (row: Shift) => {
  try {
    await ElMessageBox.confirm(`确认删除班次「${row.name}」?`, '提示', { type: 'warning' })
    const r = await schedule.deleteShift(row.id)
    ElMessage.success(r?.message || '已删除')
    load()
  } catch (e: unknown) {
    if (e !== 'cancel' && (e as ErrorWithMessage).message) ElMessage.error((e as ErrorWithResponse)?.response?.data?.message || (e as ErrorWithMessage).message)
  }
}

onMounted(load)
</script>

<style scoped lang="scss">
.page-container { padding: 20px; background: #f5f7fa; min-height: 100%; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; .page-title { font-size: 20px; font-weight: 600; color: #303133; } }
.content-card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06); }
</style>
