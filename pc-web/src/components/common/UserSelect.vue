<template>
  <el-select
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    :placeholder="placeholder"
    :multiple="multiple"
    :clearable="clearable"
    :filterable="true"
    :remote="true"
    :remote-method="loadUsers"
    :loading="loading"
    :collapse-tags="multiple"
    style="width: 100%"
    popper-class="user-select-dropdown"
  >
    <el-option
      v-for="u in options"
      :key="u.id"
      :value="u.id"
      :label="u.label"
    >
      <div class="user-option">
        <el-avatar :size="24" :src="u.avatar_url">
          {{ (u.name || '?').slice(0, 1) }}
        </el-avatar>
        <div class="user-info">
          <div class="user-name">{{ u.name }}</div>
          <div class="user-meta">
            <span v-if="u.position">{{ u.position }}</span>
            <span v-if="u.department"> · {{ u.department }}</span>
          </div>
        </div>
      </div>
    </el-option>
    <template v-if="options.length === 0 && !loading" #empty>
      <div class="empty-tip">{{ remoteKeyword ? '无匹配员工' : '输入姓名/部门搜索' }}</div>
    </template>
  </el-select>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { unwrapList } from '@/utils/response'
import { getEmployeeList } from '@/api/employee'

interface UserOption {
  id: number
  name: string
  position?: string
  department?: string
  avatar_url?: string
  label?: string
  [k: string]: unknown
}

const props = defineProps<{
  modelValue?: number | number[] | null
  placeholder?: string
  multiple?: boolean
  clearable?: boolean
}>()

defineEmits<{
  'update:modelValue': [v: number | number[] | null]
}>()

const options = ref<UserOption[]>([])
const loading = ref(false)
const remoteKeyword = ref('')

const formatLabel = (u: UserOption) => {
  const parts = [u.name]
  if (u.position) parts.push(u.position)
  if (u.department) parts.push(u.department)
  return parts.join(' · ')
}

const loadUsers = async (kw?: string) => {
  loading.value = true
  remoteKeyword.value = kw || ''
  try {
    const params: Record<string, unknown> = { per_page: 100 }
    if (kw) params.keyword = kw
    const r = await getEmployeeList(params)
    const list = unwrapList(r as unknown as Record<string, unknown>) as UserOption[]
    options.value = list.map((u) => ({ ...u, label: formatLabel(u) }))
  } catch {
    options.value = []
  } finally {
    loading.value = false
  }
}

// 已选中的 user, 也加载到 options 里以便显示
const loadSelected = async () => {
  const ids = props.modelValue
  if (!ids) return
  const idArr = Array.isArray(ids) ? ids : [ids]
  const missing = idArr.filter((id) => !options.value.some((o) => o.id === id))
  if (missing.length === 0) return
  // 局部加载 — 不通过 remote-method 触发, 直接调一次取这些 id
  try {
    loading.value = true
    const r = await getEmployeeList({ per_page: 100 })
    const list = unwrapList(r as unknown as Record<string, unknown>) as UserOption[]
    const matched = list.filter((u) => idArr.includes(u.id))
    matched.forEach((u) => {
      if (!options.value.some((o) => o.id === u.id)) {
        options.value.push({ ...u, label: formatLabel(u) })
      }
    })
  } finally {
    loading.value = false
  }
}

watch(() => props.modelValue, () => { loadSelected() }, { immediate: false })
watch(() => props.multiple, () => { loadSelected() })

// 首次打开 / 切换时初始化默认 50 条
let initialized = false
watch(() => options.value.length, (len) => {
  if (len === 0 && !initialized) {
    initialized = true
    loadUsers('')
  }
})
</script>

<style lang="scss" scoped>
.user-option {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 2px 0;
}
.user-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.user-name {
  font-size: 13px;
  font-weight: 500;
  color: #303133;
  line-height: 1.2;
}
.user-meta {
  font-size: 11px;
  color: #909399;
  line-height: 1.2;
}
.empty-tip {
  padding: 12px;
  text-align: center;
  color: #909399;
  font-size: 12px;
}
</style>