<template>
  <el-card class="form-card">
    <template #header>
      <div class="card-header">
        <el-icon color="#0C447C"><User /></el-icon>
        <span>项目团队配置</span>
      </div>
    </template>
    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="项目经理" prop="manager">
          <el-select v-model="form.manager" placeholder="请选择项目经理" filterable style="width: 100%">
            <el-option v-for="m in managers" :key="m.id" :label="m.name" :value="m.name" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="项目助理">
          <el-select v-model="form.assistant" placeholder="请选择项目助理" filterable style="width: 100%">
            <el-option v-for="m in managers" :key="m.id" :label="m.name" :value="m.name" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="24">
        <el-form-item label="团队成员" prop="team">
          <el-select
            v-model="form.team"
            multiple
            filterable
            placeholder="请选择团队成员（可多选）"
            style="width: 100%"
          >
            <el-option v-for="m in allMembers" :key="m.id" :label="m.name" :value="m.name" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="安全员">
          <el-select v-model="form.safetyOfficer" placeholder="请选择安全员" filterable style="width: 100%">
            <el-option v-for="m in allMembers" :key="m.id" :label="m.name" :value="m.name" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="质检员">
          <el-select v-model="form.qcOfficer" placeholder="请选择质检员" filterable style="width: 100%">
            <el-option v-for="m in allMembers" :key="m.id" :label="m.name" :value="m.name" />
          </el-select>
        </el-form-item>
      </el-col>
    </el-row>

    <el-divider />

    <h4 class="section-sub-title">已选团队成员（{{ form.team.length }}人）</h4>
    <div class="team-grid">
      <div v-for="m in form.team" :key="m" class="team-card">
        <el-avatar :size="40" style="background: #0C447C">{{ m.charAt(0) }}</el-avatar>
        <div class="team-info">
          <div class="team-name">{{ m }}</div>
          <div class="team-role">{{ getRole(m) }}</div>
        </div>
        <el-button type="danger" link size="small" @click="removeMember(m)">移除</el-button>
      </div>
      <el-empty v-if="form.team.length === 0" description="请选择团队成员" :image-size="80" />
    </div>
  </el-card>
</template>

<script setup lang="ts">
import { User } from '@element-plus/icons-vue'
import type { ProjectForm } from '../createTypes'

const props = defineProps<{
  form: ProjectForm
  managers: { id: number; name: string }[]
  allMembers: { id: number; name: string }[]
}>()

const removeMember = (m: string) => {
  props.form.team = props.form.team.filter((x) => x !== m)
}

const getRole = (m: string) => {
  if (m === props.form.manager) return '项目经理'
  if (m === props.form.safetyOfficer) return '安全员'
  if (m === props.form.qcOfficer) return '质检员'
  if (m === props.form.assistant) return '项目助理'
  return '工程师'
}
</script>

<style scoped>
.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 16px;
  font-weight: 600;
  color: #303133;
}
.section-sub-title {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin: 0 0 12px 0;
}
.team-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px;
}
.team-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: #f5f7fa;
  border-radius: 8px;
  transition: all 0.2s;
}
.team-card:hover {
  background: #e6f0fa;
  transform: translateY(-2px);
}
.team-info {
  flex: 1;
  min-width: 0;
}
.team-name {
  font-weight: 600;
  color: #303133;
}
.team-role {
  font-size: 12px;
  color: #909399;
  margin-top: 2px;
}
</style>
