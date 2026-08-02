<template>
  <div>
    <div class="section-title">
      开票信息
      <span class="section-hint">客户的开票抬头/税号/银行账户, 可添加多条, 标记其中一条为默认</span>
    </div>

    <el-form-item
      v-for="(info, idx) in invoiceList"
      :key="info.__key"
      :label="idx === 0 ? '开票信息 1' : '开票信息 ' + (idx + 1)"
      class="invoice-row"
    >
      <div class="invoice-row__fields">
        <el-select v-model="info.invoice_type" placeholder="发票类型" style="width: 150px" size="default">
          <el-option label="增值税普通发票" value="general" />
          <el-option label="增值税专用发票" value="special" />
          <el-option label="电子发票" value="electronic" />
        </el-select>
        <el-input
          v-model="info.company_name"
          placeholder="单位名称 (抬头) *"
          maxlength="200"
          style="width: 280px"
        />
        <el-input
          v-model="info.tax_no"
          placeholder="纳税人识别号 (税号) *"
          maxlength="50"
          style="width: 220px"
        />
        <el-checkbox v-model="info.is_default" @change="$emit('default-change', idx)">默认</el-checkbox>
        <el-button
          :icon="Delete"
          type="danger"
          plain
          size="small"
          @click="$emit('remove', idx)"
        >删除</el-button>
      </div>
      <div class="invoice-row__extra">
        <el-input
          v-model="info.register_address"
          placeholder="注册地址 (选填)"
          maxlength="200"
          class="extra-cell"
        />
        <el-input
          v-model="info.register_phone"
          placeholder="注册电话 (选填)"
          maxlength="32"
          class="extra-cell"
        />
        <el-input
          v-model="info.bank_name"
          placeholder="开户银行 (选填)"
          maxlength="100"
          class="extra-cell"
        />
        <el-input
          v-model="info.bank_account"
          placeholder="银行账号 (选填)"
          maxlength="50"
          class="extra-cell"
        />
      </div>
      <el-input
        v-model="info.remark"
        type="textarea"
        :rows="1"
        placeholder="备注 (选填)"
        maxlength="500"
        style="margin-top: 6px"
      />
    </el-form-item>

    <el-form-item>
      <el-button :icon="Plus" plain type="primary" @click="$emit('add')">添加开票信息</el-button>
    </el-form-item>
  </div>
</template>

<script setup lang="ts">
import { Plus, Delete } from '@element-plus/icons-vue'

defineProps<{
  invoiceList: Record<string, unknown>[]
}>()

defineEmits<{
  'add': []
  'remove': [idx: number]
  'default-change': [idx: number]
}>()
</script>

<style lang="scss" scoped>
.section-title {
  font-size: 14px;
  font-weight: 600;
  color: #0c447c;
  margin: 4px 0 16px;
  padding-left: 10px;
  border-left: 3px solid #0c447c;
  display: flex;
  align-items: baseline;
  gap: 12px;
}
.section-hint {
  font-size: 12px;
  color: #909399;
  font-weight: normal;
}
.invoice-row {
  margin-bottom: 8px;
  align-items: flex-start;
}
.invoice-row__fields {
  display: flex;
  gap: 8px;
  align-items: center;
  width: 100%;
  flex-wrap: wrap;
}
.invoice-row__extra {
  display: flex;
  gap: 8px;
  align-items: center;
  width: 100%;
  margin-top: 6px;
  flex-wrap: wrap;

  .extra-cell {
    flex: 1;
    min-width: 180px;
  }
}
</style>
