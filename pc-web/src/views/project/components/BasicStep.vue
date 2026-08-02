<template>
  <el-card class="form-card">
    <template #header>
      <div class="card-header">
        <el-icon color="#0C447C"><Document /></el-icon>
        <span>项目基础信息</span>
      </div>
    </template>
    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="项目名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入项目名称" maxlength="60" show-word-limit />
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="项目编号" prop="code">
          <el-input v-model="form.code" placeholder="保存后自动生成" disabled>
            <template #append>
              <el-button @click="autoGenerate">自动生成</el-button>
            </template>
          </el-input>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="所属客户" prop="customer">
          <el-select v-model="form.customer" placeholder="请选择客户" filterable style="width: 100%">
            <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.name" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="项目类型" prop="type">
          <el-radio-group v-model="form.type">
            <el-radio-button
              v-for="t in PROJECT_TYPE_OPTIONS"
              :key="t.value"
              :label="t.value"
            >
              {{ t.label }}
            </el-radio-button>
          </el-radio-group>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="项目地点" prop="location">
          <el-input v-model="form.location" placeholder="请输入项目实施地点" />
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="合同金额" prop="amount">
          <el-input-number
            v-model="form.amount"
            :min="0"
            :precision="2"
            :step="1"
            style="width: 100%"
          />
          <span class="form-unit">万元</span>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="计划开始" prop="startDate">
          <el-date-picker
            v-model="form.startDate"
            type="date"
            style="width: 100%"
            value-format="YYYY-MM-DD"
          />
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="计划完成" prop="endDate">
          <el-date-picker
            v-model="form.endDate"
            type="date"
            style="width: 100%"
            value-format="YYYY-MM-DD"
          />
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="优先级" prop="priority">
          <el-rate v-model="form.priority" />
          <span class="form-unit">优先级越高越紧急</span>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="合同编号" prop="contractNo">
          <el-input v-model="form.contractNo" placeholder="如有请填写" />
        </el-form-item>
      </el-col>
      <el-col :span="24">
        <el-form-item label="项目描述" prop="description">
          <el-input
            v-model="form.description"
            type="textarea"
            :rows="4"
            placeholder="请详细描述项目背景、目标、范围、技术要求等"
            maxlength="500"
            show-word-limit
          />
        </el-form-item>
      </el-col>
    </el-row>
  </el-card>
</template>

<script setup lang="ts">
import { Document } from '@element-plus/icons-vue'
import type { ProjectForm } from '../createTypes'
import { PROJECT_TYPE_OPTIONS } from '../createTypes'

defineProps<{
  form: ProjectForm
  customers: { id: number; name: string }[]
}>()

const emit = defineEmits<{ (e: 'auto-generate-code'): void }>()

const autoGenerate = () => {
  emit('auto-generate-code')
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
.form-unit {
  margin-left: 8px;
  font-size: 12px;
  color: #909399;
}
</style>
