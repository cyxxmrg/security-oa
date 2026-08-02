<template>
  <div>
    <div class="section-title">
      联系人
      <span class="section-hint">客户的所有联系人 (姓名/职务/电话), 第一个为主联系人</span>
    </div>

    <el-form-item
      v-for="(c, idx) in contactList"
      :key="c.__key"
      :label="idx === 0 ? '主联系人' : '联系人 ' + idx"
      :prop="`contacts.${idx}.phone`"
      class="contact-row"
    >
      <div class="contact-row__fields">
        <el-input
          v-model="c.name"
          placeholder="姓名"
          maxlength="64"
          style="width: 130px"
        />
        <el-input
          v-model="c.position"
          placeholder="职务 (例: 经理/总监)"
          maxlength="100"
          style="width: 180px"
        />
        <el-input
          v-model="c.phone"
          placeholder="电话 (必填)"
          maxlength="32"
          style="flex: 1"
        />
        <el-button
          v-if="contactList.length > 1"
          :icon="Delete"
          type="danger"
          plain
          size="small"
          @click="$emit('remove', idx)"
        >删除</el-button>
      </div>
    </el-form-item>

    <el-form-item>
      <el-button :icon="Plus" plain type="primary" @click="$emit('add')">添加联系人</el-button>
    </el-form-item>
  </div>
</template>

<script setup lang="ts">
import { Plus, Delete } from '@element-plus/icons-vue'

defineProps<{
  contactList: Record<string, unknown>[]
}>()

defineEmits<{
  'add': []
  'remove': [idx: number]
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
.contact-row {
  margin-bottom: 8px;
  align-items: flex-start;
}
.contact-row__fields {
  display: flex;
  gap: 8px;
  align-items: center;
  width: 100%;
  flex-wrap: wrap;
}
</style>
