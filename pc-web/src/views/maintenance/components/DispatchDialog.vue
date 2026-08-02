<template>
  <el-dialog v-model="visible" title="派单" width="600px" destroy-on-close>
    <el-form :model="form" ref="formRef" label-width="100px">
      <el-form-item label="工单编号">
        <code>{{ wo?.code }}</code>
      </el-form-item>
      <el-form-item label="工程师" prop="engineer_id" :rules="[{ required: true, message: '请选择工程师', trigger: 'change' }]">
        <el-select v-model="form.engineer_id" filterable placeholder="选择工程师" style="width: 100%">
          <el-option v-for="e in engineers" :key="e.id" :label="e.name" :value="e.id" />
        </el-select>
      </el-form-item>
      <el-form-item label="备注">
        <el-input v-model="form.note" type="textarea" :rows="3" maxlength="200" show-word-limit placeholder="派单备注（选填）" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" @click="onSubmit" :loading="submitting">确认派单</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { get, post } from '@/utils/request'
import { unwrapList, unwrapPaginate } from '@/utils/response'

const props = defineProps<{ modelValue: boolean; wo: Record<string, unknown> | null }>()
const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'done'): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const formRef = ref()
const submitting = ref(false)
const engineers = ref<{ id: number; name: string }[]>([])

const form = ref({ engineer_id: null as number | null, note: '' })

const loadEngineers = async () => {
  try {
    // V1.2.13: 先试 /employees, 返回 {code, data: [...]} 或 {code, data: {data: [...], total}}
    let list: Record<string, unknown>[]
    try {
      const res = await get('/employees', { per_page: 200 })
      // 兼容两种格式: unwrapList (data是数组) / unwrapPaginate (data是 paginator)
      const { list: l, total: _t } = unwrapPaginate(res)
      list = l
      if (!list.length) list = unwrapList(res)
    } catch {
      const res = await get('/users', { per_page: 200 })
      list = unwrapList(res)
    }
    engineers.value = list.map((u: Record<string, unknown>) => ({
      id: Number(u.id || u.user_id),
      name: String(u.name || u.realname || ''),
    })).filter(e => e.name)
  } catch {
    engineers.value = [{ id: 0, name: '暂无可选工程师' }]
  }
}

// 弹窗打开时才加载, 避免页面加载时 /users 接口权限不足报错
watch(() => props.modelValue, (v) => { if (v) { form.value.engineer_id = null; loadEngineers() } })

const onSubmit = async () => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid || !props.wo) return
  submitting.value = true
  try {
    await post(`/work-orders/${props.wo.id}/assign`, form.value)
    ElMessage.success('已派单')
    visible.value = false
    emit('done')
  } catch (e: unknown) {
    ElMessage.error((e as { message?: string })?.message || '派单失败')
  } finally {
    submitting.value = false
  }
}
</script>
