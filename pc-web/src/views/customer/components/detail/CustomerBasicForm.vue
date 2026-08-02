<template>
  <div>
    <div class="section-title">基本信息</div>

    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="客户名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入客户名称" maxlength="128" show-word-limit />
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="行业" prop="industry">
          <el-input v-model="form.industry" placeholder="例: 智能制造/金融/政府" maxlength="64" />
        </el-form-item>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="客户分类" prop="category">
          <el-select v-model="form.category" placeholder="请选择" clearable style="width: 100%">
            <el-option label="VIP 客户" value="vip" />
            <el-option label="普通客户" value="normal" />
            <el-option label="潜在客户" value="potential" />
          </el-select>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item label="客户来源" prop="source">
          <el-select v-model="form.source" placeholder="请选择" clearable allow-create filterable style="width: 100%">
            <el-option label="转介绍" value="referral" />
            <el-option label="陌拜" value="cold_call" />
            <el-option label="展会" value="exhibition" />
            <el-option label="网络营销" value="online" />
            <el-option label="老客户" value="existing" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
      </el-col>
    </el-row>

    <el-row :gutter="20">
      <el-col :span="8">
        <el-form-item label="省" prop="province">
          <el-input v-model="form.province" placeholder="例: 北京市 (可选)" maxlength="32" />
        </el-form-item>
      </el-col>
      <el-col :span="8">
        <el-form-item label="市" prop="city">
          <el-input v-model="form.city" placeholder="例: 北京市 (可选)" maxlength="32" />
        </el-form-item>
      </el-col>
      <el-col :span="8">
        <el-form-item label="区/县" prop="district">
          <el-input v-model="form.district" placeholder="例: 朝阳区 (可选)" maxlength="32" />
        </el-form-item>
      </el-col>
    </el-row>

    <el-form-item label="详细地址" prop="address">
      <el-input v-model="form.address" placeholder="街道、楼栋、门牌号 (可选)" maxlength="255" />
    </el-form-item>

    <el-row :gutter="20">
      <el-col :span="12">
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="form.status">
            <el-radio value="active">启用</el-radio>
            <el-radio value="inactive">停用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-col>
    </el-row>

    <el-form-item label="客户标签" prop="tags">
      <el-select
        v-model="form.tags"
        multiple
        filterable
        allow-create
        default-first-option
        placeholder="可输入后回车创建标签"
        style="width: 100%"
      >
        <el-option v-for="t in commonTags" :key="t" :label="t" :value="t" />
      </el-select>
    </el-form-item>

    <el-form-item label="备注" prop="description">
      <el-input
        v-model="form.description"
        type="textarea"
        :rows="3"
        placeholder="客户背景、需求特点、注意事项等"
      />
    </el-form-item>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  form: Record<string, unknown>
  commonTags: string[]
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
</style>
