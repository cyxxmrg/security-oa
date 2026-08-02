<template>
  <div class="page-container">
    <div class="page-header">
      <el-button :icon="ArrowLeft" plain @click="goBack">返回</el-button>
      <span class="page-title" style="margin-left: 16px">{{ isEdit ? '编辑质保期' : '新建质保期' }}</span>
    </div>

    <el-card shadow="hover" style="max-width: 800px">
      <el-form :model="form" :rules="rules" ref="formRef" label-width="120px" v-loading="loading">
        <el-form-item label="质保编号" v-if="isEdit">
          <el-input v-model="form.warranty_no" disabled />
        </el-form-item>
        <el-form-item label="项目" prop="project_id">
          <el-input-number v-model="form.project_id" :min="1" style="width: 100%" />
        </el-form-item>
        <el-form-item label="客户" prop="customer_id">
          <el-input-number v-model="form.customer_id" :min="1" style="width: 100%" />
        </el-form-item>
        <el-form-item label="设备" prop="device_id">
          <el-input-number v-model="form.device_id" :min="1" style="width: 100%" />
        </el-form-item>
        <el-form-item label="质保名称" prop="warranty_name">
          <el-input v-model="form.warranty_name" placeholder="如: 综合布线系统质保" />
        </el-form-item>
        <el-form-item label="质保类型" prop="warranty_type">
          <el-select v-model="form.warranty_type" placeholder="请选择" style="width: 100%">
            <el-option label="施工质保" value="construction" />
            <el-option label="设备质保" value="equipment" />
            <el-option label="产品质保" value="product" />
            <el-option label="服务质保" value="service" />
          </el-select>
        </el-form-item>
        <el-form-item label="开始日期" prop="start_date">
          <el-date-picker v-model="form.start_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
        </el-form-item>
        <el-form-item label="到期日期" prop="end_date">
          <el-date-picker v-model="form.end_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
        </el-form-item>
        <el-form-item label="质保月数" prop="warranty_period_months">
          <el-input-number v-model="form.warranty_period_months" :min="1" :max="600" />
        </el-form-item>
        <el-form-item label="覆盖范围">
          <el-input v-model="form.coverage_scope" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="条款说明">
          <el-input v-model="form.terms" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSave">保存</el-button>
          <el-button @click="goBack">取消</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { ArrowLeft } from '@element-plus/icons-vue'
import { warrantyApi } from '@/api/warranty'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const formRef = ref<FormInstance | null>(null)

const isEdit = computed(() => !!route.query.id)
const warrantyId = computed(() => Number(route.query.id))

const form = reactive({
  warranty_no: '',
  project_id: 1,
  customer_id: 1,
  device_id: undefined as number | undefined,
  warranty_name: '',
  warranty_type: 'construction',
  start_date: new Date().toISOString().slice(0, 10),
  end_date: '',
  warranty_period_months: 12,
  coverage_scope: '',
  terms: '',
  remark: '',
})

const rules: FormRules = {
  project_id: [{ required: true, message: '请填写项目ID', trigger: 'blur' }],
  customer_id: [{ required: true, message: '请填写客户ID', trigger: 'blur' }],
  warranty_name: [{ required: true, message: '请填写质保名称', trigger: 'blur' }],
  warranty_type: [{ required: true, message: '请选择类型', trigger: 'change' }],
  start_date: [{ required: true, message: '请选择开始日期', trigger: 'change' }],
  end_date: [{ required: true, message: '请选择到期日期', trigger: 'change' }],
  warranty_period_months: [{ required: true, message: '请填写月数', trigger: 'blur' }],
}

async function loadDetail() {
  if (!isEdit.value) return
  loading.value = true
  try {
    const res = await warrantyApi.show(warrantyId.value)
    const d = res.data || res
    Object.assign(form, {
      warranty_no: d.warranty_no,
      project_id: d.project_id,
      customer_id: d.customer_id,
      device_id: d.device_id,
      warranty_name: d.warranty_name || d.terms,
      warranty_type: d.warranty_type,
      start_date: d.start_date?.slice(0, 10),
      end_date: d.end_date?.slice(0, 10),
      warranty_period_months: d.warranty_period_months || 12,
      coverage_scope: d.coverage_scope,
      terms: d.terms,
      remark: d.remark,
    })
  } catch (e: unknown) {
    ElMessage.error('加载失败: ' + ((e as { message?: string })?.message || 'unknown'))
  } finally {
    loading.value = false
  }
}

async function handleSave() {
  try {
    await formRef.value?.validate()
  } catch {
    ElMessage.warning('请完整填写表单')
    return
  }
  try {
    if (isEdit.value) {
      await warrantyApi.update(warrantyId.value, form)
      ElMessage.success('已更新')
    } else {
      await warrantyApi.create(form)
      ElMessage.success('已创建')
    }
    router.push('/project/warranty/list')
  } catch (e: unknown) {
    ElMessage.error('保存失败: ' + ((e as { message?: string })?.message || 'unknown'))
  }
}

function goBack() { router.back() }

onMounted(loadDetail)
</script>
