<script setup lang="ts">
/**
 * FollowDialog — 添加跟进记录 (v0.3.14 C2)
 */
import { reactive, ref, watch } from 'vue'
import type { FormInstance, FormRules } from 'element-plus'

const props = withDefaults(
  defineProps<{
    visible: boolean
    targetName?: string
    submitting: boolean
  }>(),
  { visible: false, submitting: false },
)

const emit = defineEmits<{
  (e: 'update:visible', v: boolean): void
  (e: 'submit', payload: typeof form): void
}>()

const dialogVisible = ref(props.visible)
watch(() => props.visible, (v) => (dialogVisible.value = v))
const close = () => emit('update:visible', false)

const form = reactive({
  type: 'phone',
  content: '',
  next_follow_up_date: null as string | null,
  next_follow_up_note: '',
})

const formRef = ref<FormInstance>()
const rules: FormRules = {
  type:    [{ required: true, message: '请选择跟进方式', trigger: 'change' }],
  content: [{ required: true, message: '请输入跟进内容', trigger: 'blur' }],
}

const handleSubmit = async () => {
  if (!formRef.value) return
  try { await formRef.value.validate() } catch { return }
  emit('submit', { ...form })
}

const FOLLOW_TYPE_OPTIONS = [
  { value: 'phone',  label: '📞 电话沟通' },
  { value: 'visit',  label: '🏢 上门拜访' },
  { value: 'wechat', label: '💬 微信沟通' },
  { value: 'email',  label: '📧 邮件沟通' },
  { value: 'meeting',label: '🤝 会议洽谈' },
  { value: 'other',  label: '📝 其他方式' },
]
</script>

<template>
  <el-dialog
    :model-value="dialogVisible"
    @update:model-value="(v: boolean) => emit('update:visible', v)"
    title="添加跟进记录"
    width="560px"
    destroy-on-close
    :close-on-click-modal="false"
    @close="close"
  >
    <div v-if="targetName" class="target-hint">客户：<b>{{ targetName }}</b></div>
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
      <el-form-item label="跟进方式" prop="type">
        <el-select v-model="form.type" placeholder="选择跟进方式" style="width: 100%">
          <el-option v-for="o in FOLLOW_TYPE_OPTIONS" :key="o.value" :label="o.label" :value="o.value" />
        </el-select>
      </el-form-item>
      <el-form-item label="跟进内容" prop="content">
        <el-input
          v-model="form.content"
          type="textarea"
          :rows="4"
          placeholder="本次沟通要点、客户反馈、下一步计划..."
          maxlength="500"
          show-word-limit
        />
      </el-form-item>
      <el-form-item label="下次跟进">
        <el-date-picker
          v-model="form.next_follow_up_date"
          type="datetime"
          placeholder="选择日期时间"
          style="width: 100%"
        />
      </el-form-item>
      <el-form-item label="下次备注">
        <el-input v-model="form.next_follow_up_note" placeholder="可选：下次跟进的目标或要点" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="close">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">提交</el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
.target-hint {
  background: #E6F1FB;
  border-left: 3px solid #185FA5;
  padding: 8px 12px;
  margin-bottom: 16px;
  font-size: 13px;
  color: #303133;
  border-radius: 4px;
}
.target-hint b { color: #0C447C; }
</style>
