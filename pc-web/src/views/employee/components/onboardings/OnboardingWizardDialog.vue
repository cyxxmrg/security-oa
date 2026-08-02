<template>
  <el-dialog
    v-model="visible"
    title="办理入职"
    width="1440px"
    destroy-on-close
    :close-on-click-modal="false"
  >
    <div class="wizard-steps">
      <el-steps :active="step" finish-status="success" align-center>
        <el-step title="账号信息" description="登录账号 / 联系方式" />
        <el-step title="岗位信息" description="部门 / 岗位 / 入职日期" />
        <el-step title="证件信息" description="身份证 / 学历 / 合同" />
      </el-steps>
    </div>

    <div class="wizard-body">
      <!-- Step 1: 账号信息 -->
      <div v-show="step === 0">
        <el-form :model="form1" :rules="rules1" ref="elForm1Ref" label-width="100px">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="登录账号" prop="username">
                <el-input v-model="form1.username" placeholder="字母数字组合" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="姓名" prop="name">
                <el-input v-model="form1.name" placeholder="请输入姓名" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="手机号" prop="phone">
                <el-input v-model="form1.phone" placeholder="请输入手机号" />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="邮箱" prop="email">
                <el-input v-model="form1.email" placeholder="请输入邮箱" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-form-item label="默认密码">
            <el-input value="123456" disabled />
            <div style="font-size: 12px; color: #909399; margin-top: 4px;">
              初始密码为 123456，员工首次登录后请及时修改
            </div>
          </el-form-item>
        </el-form>
      </div>

      <!-- Step 2: 岗位信息 -->
      <div v-show="step === 1">
        <el-form :model="form2" :rules="rules2" ref="elForm2Ref" label-width="100px">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="入职日期" prop="hire_date">
                <el-date-picker
                  v-model="form2.hire_date"
                  type="date"
                  placeholder="选择日期"
                  style="width: 100%"
                  value-format="YYYY-MM-DD"
                />
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="试用期(月)" prop="probation_months">
                <el-input-number v-model="form2.probation_months" :min="0" :max="12" />
              </el-form-item>
            </el-col>
          </el-row>
          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="部门" prop="department_id">
                <el-select v-model="form2.department_id" placeholder="请选择部门" style="width: 100%">
                  <el-option
                    v-for="d in departmentList"
                    :key="d.id"
                    :label="d.name"
                    :value="d.id"
                  />
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="岗位" prop="position_id">
                <el-select v-model="form2.position_id" placeholder="请选择岗位" style="width: 100%" filterable>
                  <el-option
                    v-for="p in positionList"
                    :key="p.id"
                    :label="`${p.name} (${deptNameOf(p.department_id)})`"
                    :value="p.id"
                  />
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-form-item label="入职导师" prop="mentor_id">
            <el-select
              v-model="form2.mentor_id"
              placeholder="选择老员工作为导师"
              clearable
              filterable
              style="width: 100%"
            >
              <el-option
                v-for="u in activeUserList"
                :key="u.id"
                :label="`${u.name} (${u.username})`"
                :value="u.id"
              />
            </el-select>
          </el-form-item>
        </el-form>
      </div>

      <!-- Step 3: 证件信息 -->
      <div v-show="step === 2">
        <el-form :model="form3" label-width="100px">
          <div class="doc-block">
            <div class="doc-block__title">身份证</div>
            <el-row :gutter="16">
              <el-col :span="12">
                <el-form-item label="身份证号">
                  <el-input v-model="form3.id_card_no" placeholder="请输入身份证号" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="扫描件">
                  <el-upload
                    :http-request="(opt: Record<string, unknown>) => handleFileUpload(opt, 'id_card_file_id')"
                    :show-file-list="false"
                    accept="image/*,.pdf"
                  >
                    <el-button :icon="UploadFilled">{{ form3.id_card_file_id ? '已上传 · 重新上传' : '点击上传' }}</el-button>
                  </el-upload>
                  <div v-if="form3.id_card_file_name" class="uploaded-tip">已上传: {{ form3.id_card_file_name }}</div>
                </el-form-item>
              </el-col>
            </el-row>
          </div>

          <div class="doc-block">
            <div class="doc-block__title">驾驶证</div>
            <el-row :gutter="16">
              <el-col :span="12">
                <el-form-item label="驾驶证号">
                  <el-input v-model="form3.driver_license_no" placeholder="请输入驾驶证号" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="有效期至">
                  <el-date-picker
                    v-model="form3.driver_license_expire"
                    type="date"
                    placeholder="选择日期"
                    style="width: 100%"
                    value-format="YYYY-MM-DD"
                  />
                </el-form-item>
              </el-col>
            </el-row>
            <el-form-item label="扫描件">
              <el-upload
                :http-request="(opt: Record<string, unknown>) => handleFileUpload(opt, 'driver_license_file_id')"
                :show-file-list="false"
                accept="image/*,.pdf"
              >
                <el-button :icon="UploadFilled">{{ form3.driver_license_file_id ? '已上传 · 重新上传' : '点击上传' }}</el-button>
              </el-upload>
              <div v-if="form3.driver_license_file_name" class="uploaded-tip">已上传: {{ form3.driver_license_file_name }}</div>
            </el-form-item>
          </div>

          <div class="doc-block">
            <div class="doc-block__title">学历</div>
            <el-row :gutter="16">
              <el-col :span="8">
                <el-form-item label="学历">
                  <el-select v-model="form3.education_level" placeholder="请选择" style="width: 100%">
                    <el-option
                      v-for="o in EDUCATION_OPTIONS"
                      :key="o.value"
                      :label="o.label"
                      :value="o.value"
                    />
                  </el-select>
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="毕业院校">
                  <el-input v-model="form3.education_school" placeholder="如：清华大学" />
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="所学专业">
                  <el-input v-model="form3.education_major" placeholder="如：计算机科学" />
                </el-form-item>
              </el-col>
            </el-row>
            <el-form-item label="学历证明">
              <el-upload
                :http-request="(opt: Record<string, unknown>) => handleFileUpload(opt, 'education_file_id')"
                :show-file-list="false"
                accept="image/*,.pdf"
              >
                <el-button :icon="UploadFilled">{{ form3.education_file_id ? '已上传 · 重新上传' : '点击上传' }}</el-button>
              </el-upload>
              <div v-if="form3.education_file_name" class="uploaded-tip">已上传: {{ form3.education_file_name }}</div>
            </el-form-item>
          </div>

          <div class="doc-block">
            <div class="doc-block__title">劳动合同 <span class="optional-tip">(选填)</span></div>
            <el-form-item label="合同文件">
              <el-upload
                :http-request="(opt: Record<string, unknown>) => handleFileUpload(opt, 'contract_file_id')"
                :show-file-list="false"
                accept="image/*,.pdf,.doc,.docx"
              >
                <el-button :icon="UploadFilled">{{ form3.contract_file_id ? '已上传 · 重新上传' : '点击上传' }}</el-button>
              </el-upload>
              <div v-if="form3.contract_file_name" class="uploaded-tip">已上传: {{ form3.contract_file_name }}</div>
            </el-form-item>
          </div>
        </el-form>
      </div>
    </div>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button v-if="step > 0" @click="emit('prev')">上一步</el-button>
      <el-button v-if="step < 2" type="primary" @click="onNextClick">下一步</el-button>
      <el-button v-else type="primary" :loading="submitting" @click="emit('submit')">提交</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref, nextTick } from 'vue'
import { UploadFilled } from '@element-plus/icons-vue'
import type { Department, Position, UserOption } from './types'
import { EDUCATION_OPTIONS } from './types'

// v0.3.22 抽自 employee/Onboardings.vue:153-378
// v0.5.8.7 BUG 修复：模板 el-form ref 与父级传入的 form1Ref/form2Ref prop 同名冲突
//                导致 formStep1Ref.value 永远是空，validate 静默失败
//                修法：模板 ref 改名 elFormNRef，validate 移到子组件本地
const props = defineProps<{
  modelValue: boolean
  step: number
  submitting: boolean
  form1: Record<string, unknown>
  form2: Record<string, unknown>
  form3: Record<string, unknown>
  rules1: Record<string, unknown>
  rules2: Record<string, unknown>
  departmentList: Department[]
  positionList: Position[]
  activeUserList: UserOption[]
}>()

const elForm1Ref = ref()
const elForm2Ref = ref()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'next'): void
  (e: 'prev'): void
  (e: 'submit'): void
  (e: 'upload', opt: Record<string, unknown>, field: 'id_card_file_id' | 'driver_license_file_id' | 'education_file_id' | 'contract_file_id'): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

function deptNameOf(id?: number | null) {
  if (!id) return ''
  return props.departmentList.find(d => d.id === id)?.name || ''
}

function handleFileUpload(opt: Record<string, unknown>, field: 'id_card_file_id' | 'driver_license_file_id' | 'education_file_id' | 'contract_file_id') {
  emit('upload', opt, field)
}

async function onNextClick() {
  // 必填项校验在子组件本地完成（拿得到 el-form 实例）
  if (props.step === 0) {
    const valid = await elForm1Ref.value?.validate().catch(() => false)
    if (!valid) return
  } else if (props.step === 1) {
    const valid = await elForm2Ref.value?.validate().catch(() => false)
    if (!valid) return
  }
  await nextTick()
  emit('next')
}
</script>

<style lang="scss" scoped>
.wizard-steps {
  background: #fafbfc;
  margin: -20px -20px 20px;
  padding: 16px 20px 20px;
  border-radius: 4px 4px 0 0;
}
.wizard-body {
  min-height: 360px;
}
.uploaded-tip {
  font-size: 12px;
  color: #1D9E75;
  margin-top: 6px;
}
.optional-tip {
  font-size: 12px;
  color: #909399;
  font-weight: normal;
}
.doc-block {
  margin-bottom: 20px;
  padding: 12px 14px;
  background: #fafbfc;
  border-radius: 6px;
  border-left: 3px solid #0C447C;
  &__title {
    font-size: 14px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 8px;
  }
}
</style>
