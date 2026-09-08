# 开发日志与代码同步到 GitHub 操作说明

> 适用仓库: `github.com/guo383975-design/security-oa`(Security OA,安防运维 OA)
> 适用对象:本机开发/交付人员;本文档描述「本地改代码 → 写开发日志 → 提交 → 推送到 GitHub」的完整规范与操作,以及遇到历史分叉/认证/网络问题时的处理办法。
> 最后更新:2026-09-08

---

## 1. 目的

让 GitHub 上的代码与本地开发状态保持一致,同时把**开发日志**(版本变更记录、提交记录)同步到 GitHub,形成可追溯的产品历史:

- 代码变更以 `git commit` 记录,随 `git push` 同步;
- 面向使用者的变更以 `CHANGELOG.md` + README 版本徽标记录;
- 面向开发者的工程说明、验证报告沉淀在 `docs/`、`output/`;
- 每个功能/修复提交后,开发日志随代码一起上 GitHub。

## 2. 仓库与分支约定

```text
仓库:  https://github.com/guo383975-design/security-oa.git
默认分支: main(受保护程度低,单人/小团队直接推送)
```

- **main = 可发布主干**:改动应保持可构建、可测试。
- 多人/并行功能开发时建议:**特性分支 + Pull Request**,不在 main 上直接堆大改。
- 与远程相关的**三种引用**要分清:
  - `HEAD` / `main`:本地当前分支;
  - `origin/main`:上次 fetch 时看到的远端状态(**可能过期**,push 前先 fetch);
  - GitHub 网页上的 main:真正远端状态。

## 3. 本机环境准备

### 3.1 git

本开发机没有把 git 装入系统 PATH,使用的是 WorkBuddy 自带的 PortableGit:

```powershell
$git = 'C:\Users\MRG\.workbuddy\binaries\PortableGit\versions\1.2.0\cmd\git.exe'
& $git --version
```

若希望任意终端直接使用,可把 PortableGit 加入 PATH,或安装官方 Git for Windows 后重开终端。

### 3.2 身份

```powershell
& $git config --global user.name  "WorkBuddy"
& $git config --global user.email "workbuddy@local"
# 推送提交将以此身份显示;如需显示真实姓名,改用 GitHub 账号名与邮箱
```

### 3.3 认证(推送必需)

三种方式任选:

1. **Personal Access Token(PAT)** — 推荐
   - GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
   - 勾选 `repo` 全选(写权限);生成后仅本次使用,用后可在 GitHub 撤销。
   - 使用时只注入**当前命令的环境变量**,不要写进任何文件、脚本或提交:
     ```powershell
     $env:GITHUB_TOKEN = "ghp_xxxxxxxxxxxxxxxx"
     & $git -C D:\work\website\OA push https://oauth2:$env:GITHUB_TOKEN@github.com/guo383975-design/security-oa.git main
     ```
2. **git-credential-manager 保存**(本机 PortableGit 自带 `git-credential-manager.exe`)
   - 第一次 push 时按提示完成浏览器登录,凭据会存入 Windows 凭据管理器,之后免密。
3. **SSH 方式**
   - 在 GitHub 添加本机公钥后,把 remote 改为 `git@github.com:guo383975-design/security-oa.git`。

### 3.4 首次克隆

```powershell
& $git clone https://github.com/guo383975-design/security-oa.git D:\work\website\OA
```

## 4. 日常同步流程(每次开发的固定动作)

```text
① 拉取远端最新   git fetch origin
② 查看差异       git status / git diff
③ 写/更新开发日志 CHANGELOG.md、docs 等
④ 暂存与检查     git add <files>  →  git status 复核
⑤ 提交           git commit -m "规范信息"
⑥ 再拉一次       git pull --rebase origin main(避免分叉)
⑦ 推送           git push origin main
```

### 4.1 提交信息规范(参考本仓库既有历史)

```text
格式: <type>(<scope>): <概述> V<版本号>

示例:
feat(inventory): 工具使用单明细页(领用/退还) V1.4.2
fix(frontend): 版本号显示错乱 V1.4.1
fix: 完成 V1.4.2 专项修复与验证
docs: 重塑 Security OA 项目门面与工程导览
test: repair and isolate legacy suites
chore: 同步本地状态与部署目录
```

type 常用:feat / fix / refactor / docs / test / chore / perf / build / ci。多行提交可追加详细说明(根因、修复点、验证结果),参考仓库 2026-08-02 的长提交。

### 4.2 开发日志与版本号联动

| 内容 | 位置 | 何时更新 |
| --- | --- | --- |
| 面向使用者的变更 | `CHANGELOG.md`(按版本小节) | 每个发布版本收敛时 |
| 版本徽标 | `README.md` 顶部 badge 指向 CHANGELOG | 随版本号更新 |
| 版本单一真相源 | `pc-api/config/oa.php` 的 `app_version` | 每次版本升级 |
| 前端版本兜底 | `pc-web/src/stores/systemConfig.ts` 的 `version` | 与后端保持一致 |
| 前端 package 版本 | `pc-web/package.json` | 发布时同步 |
| 工程说明/验证报告 | `docs/`、`output/` | 有重大设计或验证结论时 |

> 注意 2026-09 的教训:代码已到 v1.4.4,而 CHANGELOG/README 还停在 v1.4.2——改版本号时务必同步这 5 处,再一起提交推送。

### 4.3 开发日志写法建议

- **CHANGELOG 每个版本**:产品能力 / 工程质量 / 验证 三小节(参考 v1.4.2 条目)。
- **提交信息正文**建议含:根因、修复方案、影响面、验证方式(构建/测试/实机复测)。
- 每个里程碑在 GitHub 上打 Tag + Release,便于交付追溯:
  ```powershell
  & $git tag v1.4.4 && & $git push origin v1.4.4
  ```

## 5. 提交前检查(不要急着 push)

1. **不要提交这些内容**(.gitignore 已覆盖,`git status` 复核):
   - 依赖与构建:`node_modules/`、`dist/`、`.deploy_pkg/`、根目录 `*.tar.gz` 部署包;
   - 运行时与日志:`/pc-api/storage/`、`*.log`、`logs/`、`framework/`;
   - 环境与凭据:`.env`、`.env.*`、`deploy/`(含真实服务器口令,整目录不入库)、密钥文件;
   - AI 工作区与报告快照:`.workbuddy/`、`output/`(仅正式定稿报告按需纳入)。
2. **敏感信息自查**:提交前执行
   ```powershell
   python D:\work\website\OA\hooks\check_secrets.py  # 若本机配好 quality-gate 环境
   ```
   或至少全文搜索 `ghp_|password =|api[_-]?key|secret|BEGIN .*PRIVATE KEY`。
3. **质量门禁**:仓库根有 `.pre-commit-config.yaml`(black/ruff/文件卫生/密钥扫描),安装后 `pre-commit install`,每次 commit 自动检查;若解释器路径是本机绝对路径,换机器需调整。
4. **改动面复核**:`git diff --stat` 看是否夹带无关文件;大文件(>500KB)会触发 added-large-files 钩子。

## 6. 冲突与历史分叉处理

### 6.1 判断是否分叉

```powershell
& $git fetch origin
# 落后(远端有新提交):
& $git log --oneline HEAD..origin/main
# 领先(本地有新提交):
& $git log --oneline origin/main..HEAD
```

两边都有内容 = **分叉**。

### 6.2 本地领先、远端无新提交(最常见)

```powershell
& $git push origin main          # 直接快进推送
```

### 6.3 分叉处理(远端有新提交,本地也改过)

先提交本地工作区,再合并:

```powershell
& $git add -A
& $git commit -m "chore: 同步本地状态"
& $git merge origin/main         # 或 git pull --no-rebase origin main
# 有冲突时逐个解决 → git add <file> → 全部解决后:
& $git commit                    # 完成 merge commit
& $git push origin main
```

- 冲突解决原则:两侧都改了的文件,**逐段看内容取合并语义**,不要整文件覆盖;
- 不建议 `force push` 覆盖远端(会永久丢弃远端独有提交——2026-09-08 处理分叉时已核实远端含本地没有的合规加固内容,最终选择合并保留)。

### 6.4 撤销/回退

```powershell
& $git reset --soft HEAD~1       # 撤销最近一次提交(保留改动)
& $git push origin main --force-with-lease   # 仅在上一步确认要覆盖远端时使用
```

## 7. 推送后验证

- GitHub 网页 → `guo383975-design/security-oa` → Commits 看最新提交;
- Actions → CI 绿勾(`.github/workflows/ci.yml`:PHPUnit 全量测试 + PG15);
- 确认 `origin/main` 与本地一致:
  ```powershell
  & $git fetch origin && & $git rev-parse HEAD origin/main
  ```

## 8. 本机常见问题速查

| 现象 | 原因 | 处理 |
| --- | --- | --- |
| `schannel: SEC_E_NO_CREDENTIALS` / TLS EOF | 本机 TLS 后端/代理不稳 | 加 `-c http.sslBackend=openssl -c http.version=HTTP/1.1`,必要时重试 |
| push 提示认证失败 403/401 | 无凭据或 token 失效 | 按 §3.3 提供 PAT / credential-manager / SSH;token 用后即撤销 |
| 警告 `LF will be replaced by CRLF` | Windows 行尾转换提示 | 无害;仓库建议统一 LF(`git config core.autocrlf false`) |
| `Your local changes would be overwritten by merge` | 未提交就 merge | 先 commit/stash 再 merge |
| 提交了不该提交的大文件 | 未走 §5 检查 | `git rm --cached <file>` + 加入 .gitignore + amend 提交 |

## 9. 命令速查卡

```powershell
$git = 'C:\Users\MRG\.workbuddy\binaries\PortableGit\versions\1.2.0\cmd\git.exe'
$oa  = 'D:\work\website\OA'

# 状态与差异
& $git -C $oa status
& $git -C $oa diff --stat
# 拉取与推送(openssl 后端)
& $git -C $oa -c http.sslBackend=openssl -c http.version=HTTP/1.1 fetch origin
& $git -C $oa -c http.sslBackend=openssl push origin main
# 提交
& $git -C $oa add -A
& $git -C $oa commit -m "feat(scope): 概述 V1.4.4"
# 历史
& $git -C $oa log --oneline -20
```
